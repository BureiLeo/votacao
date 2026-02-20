<?php
/**
 * admin/index.php — Login + Dashboard + Configurações
 */
$_umaHora = 60 * 60;
ini_set('session.gc_maxlifetime', $_umaHora);
session_set_cookie_params([
    'lifetime' => $_umaHora,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
require_once '../config/database.php';

// ------- MIGRATION AUTOMÁTICA — tabelas de histórico -------
(function () {
    $pdo = getDB();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS historico_votacoes (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            nome             VARCHAR(180) NOT NULL,
            total_votos      INT          NOT NULL DEFAULT 0,
            votos_nulos      INT          NOT NULL DEFAULT 0,
            duracao_segundos INT              NULL DEFAULT NULL,
            criado_em        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS historico_votos_cand (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            votacao_id     INT          NOT NULL,
            cargo_nome     VARCHAR(180) NOT NULL,
            candidato_nome VARCHAR(180) NOT NULL,
            votos          INT          NOT NULL DEFAULT 0,
            FOREIGN KEY (votacao_id) REFERENCES historico_votacoes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    // Adiciona coluna duracao_segundos se ainda não existir (upgrade seguro)
    try {
        $pdo->exec("ALTER TABLE historico_votacoes ADD COLUMN duracao_segundos INT NULL DEFAULT NULL");
    } catch (PDOException $e) { /* coluna já existe */ }
    // Garante chave votacao_inicio em configuracoes
    $pdo->exec("INSERT IGNORE INTO configuracoes (chave, valor) VALUES ('votacao_inicio', '')");
    // Garante chave urna_liberada em configuracoes
    $pdo->exec("INSERT IGNORE INTO configuracoes (chave, valor) VALUES ('urna_liberada', 'false')");
    // Garante chave encerrada em configuracoes
    $pdo->exec("INSERT IGNORE INTO configuracoes (chave, valor) VALUES ('encerrada', 'false')");
})();

$erro    = '';
$sucesso = '';

// ------- LOGOUT -------
if (isset($_POST['sair'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// ------- LOGIN -------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha']) && empty($_SESSION['admin_logado'])) {
    $hash  = getConfig('admin_hash', '');
    if ($hash && password_verify($_POST['senha'], $hash)) {
        $_SESSION['admin_logado']           = true;
        $_SESSION['admin_ultima_atividade'] = time();
    } else {
        $erro = 'Senha incorreta.';
        // Pequeno delay para dificultar brute-force
        sleep(1);
    }
}

// ------- AÇÕES (autenticado) -------
if (!empty($_SESSION['admin_logado']) && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $pdo = getDB();

    // ── Liberar urna para próxima pessoa
    if (isset($_POST['liberar_urna'])) {
        $pdo->prepare(
            "INSERT INTO configuracoes (chave, valor) VALUES ('urna_liberada', 'true')
             ON DUPLICATE KEY UPDATE valor = 'true'"
        )->execute();
        $sucesso = '🟢 Urna liberada! A próxima pessoa pode votar.';
    }

    // ── Iniciar votação
    if (isset($_POST['iniciar_votacao'])) {
        $agora = date('Y-m-d H:i:s');
        $pdo->exec("UPDATE configuracoes SET valor = 'true'  WHERE chave = 'votacao_ativa'");
        $pdo->exec("UPDATE configuracoes SET valor = 'false' WHERE chave = 'encerrada'");
        $pdo->exec("UPDATE configuracoes SET valor = 'false' WHERE chave = 'revelado'");
        $pdo->exec("UPDATE configuracoes SET valor = 'false' WHERE chave = 'urna_liberada'");
        $pdo->prepare(
            "INSERT INTO configuracoes (chave, valor) VALUES ('votacao_inicio', ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
        )->execute([$agora]);
        $sucesso = '✅ Votação iniciada!';
    }

    // ── Encerrar votação (sem revelar)
    if (isset($_POST['encerrar_votacao'])) {
        $pdo->exec("UPDATE configuracoes SET valor = 'false' WHERE chave = 'votacao_ativa'");
        $pdo->exec("UPDATE configuracoes SET valor = 'true'  WHERE chave = 'encerrada'");
        $sucesso = '🔒 Votação encerrada. Resultados ainda ocultos.';
    }

    // ── Revelar resultados no painel
    if (isset($_POST['revelar_resultados'])) {
        $pdo->prepare(
            "INSERT INTO configuracoes (chave, valor) VALUES ('revelado', 'true')
             ON DUPLICATE KEY UPDATE valor = 'true'"
        )->execute();
        $sucesso = '🎉 Resultados REVELADOS no painel!';
    }

    // ── Ocultar resultados (volta para estado encerrada)
    if (isset($_POST['ocultar_resultados'])) {
        $pdo->prepare(
            "INSERT INTO configuracoes (chave, valor) VALUES ('revelado', 'false')
             ON DUPLICATE KEY UPDATE valor = 'false'"
        )->execute();
        $pdo->exec("UPDATE configuracoes SET valor = 'true' WHERE chave = 'encerrada'");
        $sucesso = 'Resultados ocultados. Painel voltou ao modo suspense.';
    }

    // Zerar todos os votos (sem arquivar)
    if (isset($_POST['zerar_votos'])) {
        $pdo->exec("DELETE FROM votos");
        $pdo->exec("UPDATE codigos SET usado = 0, usado_em = NULL");
        $pdo->exec("UPDATE configuracoes SET valor = 'false' WHERE chave = 'revelado'");
        $pdo->exec("UPDATE configuracoes SET valor = 'false' WHERE chave = 'votacao_ativa'");
        $pdo->exec("UPDATE configuracoes SET valor = 'false' WHERE chave = 'encerrada'");
        $pdo->exec("UPDATE configuracoes SET valor = 'false' WHERE chave = 'urna_liberada'");
        $sucesso = 'Todos os votos foram zerados e os códigos foram liberados para nova votação.';
    }

    // ── NOVA VOTAÇÃO (wizard)
    if (isset($_POST['nova_votacao'])) {
        $nomeVotacao = trim($_POST['nome_votacao'] ?? '');
        if ($nomeVotacao === '') {
            $erro = 'Informe um nome para identificar esta votação antes de arquivar.';
        } else {
            $totalVotos = (int)$pdo->query("SELECT COUNT(*) FROM votos")->fetchColumn();
            $votosNulos = (int)$pdo->query("SELECT COUNT(*) FROM votos WHERE candidato_id IS NULL")->fetchColumn();

            // Calcular duração em segundos a partir de votacao_inicio
            $inicioStr = getConfig('votacao_inicio', '');
            $duracao   = ($inicioStr && $inicioStr !== '') ? max(0, time() - strtotime($inicioStr)) : null;

            $stmtHist = $pdo->prepare(
                "INSERT INTO historico_votacoes (nome, total_votos, votos_nulos, duracao_segundos) VALUES (?, ?, ?, ?)"
            );
            $stmtHist->execute([$nomeVotacao, $totalVotos, $votosNulos, $duracao]);
            $votacaoId = (int)$pdo->lastInsertId();

            $rows = $pdo->query("
                SELECT cg.nome AS cargo_nome, cd.nome AS candidato_nome, COUNT(*) AS votos
                FROM votos v
                JOIN cargos     cg ON cg.id = v.cargo_id
                JOIN candidatos cd ON cd.id = v.candidato_id
                WHERE v.candidato_id IS NOT NULL
                GROUP BY v.cargo_id, v.candidato_id
                ORDER BY cg.ordem, votos DESC
            ")->fetchAll();

            $stmtCand = $pdo->prepare(
                "INSERT INTO historico_votos_cand (votacao_id, cargo_nome, candidato_nome, votos) VALUES (?, ?, ?, ?)"
            );
            foreach ($rows as $r) {
                $stmtCand->execute([$votacaoId, $r['cargo_nome'], $r['candidato_nome'], $r['votos']]);
            }

            $pdo->exec("DELETE FROM votos");
            $pdo->exec("UPDATE codigos SET usado = 0, usado_em = NULL");
            $pdo->exec("UPDATE configuracoes SET valor = 'false' WHERE chave = 'revelado'");
            $pdo->exec("UPDATE configuracoes SET valor = 'false' WHERE chave = 'votacao_ativa'");
            $pdo->exec("UPDATE configuracoes SET valor = 'false' WHERE chave = 'encerrada'");
            $pdo->exec("UPDATE configuracoes SET valor = 'false' WHERE chave = 'urna_liberada'");
            $pdo->exec("UPDATE configuracoes SET valor = '' WHERE chave = 'votacao_inicio'");

            $sucesso = 'Votação "' . htmlspecialchars($nomeVotacao) . '" arquivada! Sistema pronto para nova rodada.';
        }
    }

    // Excluir registro do histórico
    if (isset($_POST['excluir_historico'])) {
        $hid = (int)$_POST['excluir_historico'];
        $pdo->prepare("DELETE FROM historico_votacoes WHERE id = ?")->execute([$hid]);
        $sucesso = 'Registro de histórico excluído.';
    }
}

$logado = !empty($_SESSION['admin_logado']);

// ------- ESTATÍSTICAS + HISTÓRICO -------
$stats    = [];
$historico = [];
if ($logado) {
    $pdo   = getDB();
    $stats = [
        'total_codigos'   => (int)$pdo->query("SELECT COUNT(*) FROM codigos")->fetchColumn(),
        'usados'          => (int)$pdo->query("SELECT COUNT(*) FROM codigos WHERE usado=1")->fetchColumn(),
        'total_votos'     => (int)$pdo->query("SELECT COUNT(*) FROM votos")->fetchColumn(),
        'candidatos'      => (int)$pdo->query("SELECT COUNT(*) FROM candidatos WHERE ativo=1")->fetchColumn(),
        'votos_nulos'     => (int)$pdo->query("SELECT COUNT(*) FROM votos WHERE candidato_id IS NULL")->fetchColumn(),
    ];
    $stats['disponiveis'] = $stats['total_codigos'] - $stats['usados'];

    $votacoesHist = $pdo->query("SELECT * FROM historico_votacoes ORDER BY criado_em DESC")->fetchAll();
    foreach ($votacoesHist as $v) {
        $det = $pdo->prepare(
            "SELECT cargo_nome, candidato_nome, votos FROM historico_votos_cand
             WHERE votacao_id = ? ORDER BY cargo_nome, votos DESC"
        );
        $det->execute([$v['id']]);
        $historico[] = ['meta' => $v, 'detalhes' => $det->fetchAll()];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $logado ? 'Dashboard Admin' : 'Login Admin'; ?> — Votação</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        /* ── Wizard Modal ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.65); z-index: 999;
            align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: #1e293b; border: 1px solid #334155;
            border-radius: 14px; padding: 32px;
            max-width: 480px; width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,.5);
        }
        .modal-box h3 { margin: 0 0 8px; font-size: 1.15rem; }
        .modal-box p  { color: #94a3b8; font-size: .88rem; margin-bottom: 16px; }
        .wizard-steps { display: flex; gap: 8px; margin-bottom: 24px; }
        .wizard-step  { flex: 1; height: 4px; border-radius: 4px; background: #334155; transition: background .3s; }
        .wizard-step.done { background: #4f46e5; }
        /* ── Histórico ── */
        .hist-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 10px; margin-bottom: 12px; overflow: hidden; }
        .hist-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; cursor: pointer; gap: 12px; }
        .hist-header:hover { background: #1e293b; }
        .hist-title  { font-weight: 700; font-size: .95rem; }
        .hist-meta   { font-size: .78rem; color: #64748b; margin-top: 2px; }
        .hist-badge  { background: #4f46e5; color: #fff; padding: 3px 12px; border-radius: 99px; font-size: .78rem; font-weight: 700; white-space: nowrap; }
        .hist-body   { display: none; padding: 0 16px 16px; }
        .hist-body.open { display: block; }
        .hist-cargo  { margin-top: 12px; }
        .hist-cargo-nome { font-size: .78rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
        .hist-cand-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #1e293b; font-size: .88rem; }
        .hist-cand-row:last-child { border-bottom: none; }
        .hist-votos { font-weight: 700; color: #818cf8; }
        .btn-excluir-hist { background: none; border: 1px solid #7f1d1d; color: #fca5a5; padding: 3px 10px; border-radius: 6px; font-size: .75rem; cursor: pointer; }
        .btn-excluir-hist:hover { background: #7f1d1d; }
    </style>
</head>
<body>
<?php if (!$logado): ?>
<!-- ======== TELA DE LOGIN ======== -->
<div class="page-center">
    <div class="card card-sm">
        <h2 class="text-center mb-2">&#128274; Área Administrativa</h2>
        <?php if ($erro): ?>
            <div class="alerta alerta-erro"><?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>
        <?php if (($_GET['sessao'] ?? '') === 'expirada'): ?>
            <div class="alerta alerta-aviso">&#9201; Sua sessão expirou após 1 hora de inatividade. Faça login novamente.</div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label>Senha</label>
                <input type="password" name="senha" autofocus placeholder="Senha do admin" required>
            </div>
            <button class="btn btn-primary btn-full" type="submit">Entrar</button>
        </form>
    </div>
</div>
<?php else: ?>
<!-- ======== DASHBOARD ======== -->
<div class="admin-nav-header">
    <h1>&#127891; Votação Jornada Jovem — Admin</h1>
    <form method="POST" style="display:inline">
        <button name="sair" class="btn btn-danger btn-sm">Sair</button>
    </form>
</div>
<nav class="admin-nav">
    <a href="index.php" class="ativo">&#127968; Dashboard</a>
    <a href="candidatos.php">&#128101; Candidatos</a>
    <a href="codigos.php">&#128273; Códigos</a>
    <a href="imprimir_codigos.php">&#128438; Imprimir</a>
    <a href="../painel/index.php" target="_blank">&#128200; Painel ao Vivo &#8599;</a>
    <a href="../urna/index.php" target="_blank">&#128683; Urna &#8599;</a>
</nav>

<div class="container" style="max-width:900px">
    <?php if ($sucesso): ?>
        <div class="alerta alerta-sucesso mt-2"><?php echo htmlspecialchars($sucesso); ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alerta alerta-erro mt-2"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <!-- Estatísticas -->
    <h2 class="mt-3 mb-2">Visão Geral</h2>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="num"><?php echo $stats['total_codigos']; ?></div>
            <div class="desc">Códigos gerados</div>
        </div>
        <div class="stat-card">
            <div class="num" style="color:var(--success)"><?php echo $stats['usados']; ?></div>
            <div class="desc">Já votaram</div>
        </div>
        <div class="stat-card">
            <div class="num" style="color:var(--warning)"><?php echo $stats['disponiveis']; ?></div>
            <div class="desc">Aguardando</div>
        </div>
        <div class="stat-card">
            <div class="num"><?php echo $stats['total_votos']; ?></div>
            <div class="desc">Votos registrados</div>
        </div>
        <div class="stat-card">
            <div class="num" style="color:var(--muted)"><?php echo $stats['votos_nulos']; ?></div>
            <div class="desc">Votos nulos</div>
        </div>
        <div class="stat-card">
            <div class="num"><?php echo $stats['candidatos']; ?></div>
            <div class="desc">Candidatos ativos</div>
        </div>
    </div>

    <!-- Controle de votação -->
    <?php $vStatus = votacaoStatus(); ?>
    <div class="card">
        <h2>&#9881; Controle da Votação</h2>

        <!-- Status visual por etapa -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin:14px 0 18px;align-items:center">
            <?php
            $etapas = [
                'aguardando' => ['&#9203;', 'Aguardando',  '#64748b'],
                'ativa'      => ['&#9654;', 'Em andamento','#16a34a'],
                'encerrada'  => ['&#9209;', 'Encerrada',   '#dc2626'],
                'revelada'   => ['&#127942;','Revelada',   '#d97706'],
            ];
            foreach ($etapas as $key => [$icon, $label, $cor]):
                $ativo = ($vStatus === $key);
            ?>
            <div style="display:flex;align-items:center;gap:6px;padding:6px 14px;border-radius:99px;
                        background:<?php echo $ativo ? $cor : '#0f172a'; ?>;
                        border:2px solid <?php echo $ativo ? $cor : '#334155'; ?>;
                        font-size:.83rem;font-weight:<?php echo $ativo ? '700' : '400'; ?>;
                        color:<?php echo $ativo ? '#fff' : '#64748b'; ?>">
                <?php echo $icon; ?> <?php echo $label; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="d-flex gap-2 flex-wrap" style="margin-top:4px">
            <!-- Botão Nova Votação (sempre visível) -->
            <button type="button" class="btn" style="background:#059669;color:#fff" onclick="abrirWizard()">
                &#128257; Nova Votação
            </button>

            <?php if ($vStatus === 'aguardando'): ?>
            <form method="POST">
                <button name="iniciar_votacao"
                        class="btn btn-primary"
                        onclick="return confirm('Iniciar a votação agora?')">
                    &#9654; Iniciar votação
                </button>
            </form>

            <?php elseif ($vStatus === 'ativa'): ?>
            <?php $liberada = urnaLiberada(); ?>
            <form method="POST">
                <?php if (!$liberada): ?>
                <button name="liberar_urna"
                        class="btn"
                        style="background:#4f46e5;color:#fff;font-size:1rem;padding:10px 22px"
                        onclick="return confirm('Liberar a urna agora? Todos os participantes poderão votar.')">
                    &#128994; Liberar votação para todos
                </button>
                <?php else: ?>
                <span style="color:#4ade80;font-weight:700;font-size:.9rem">&#128994; Votação liberada — participantes podem votar livremente</span>
                <?php endif; ?>
            </form>
            <form method="POST">
                <button name="encerrar_votacao"
                        class="btn btn-danger"
                        onclick="return confirm('Encerrar a votação? Ninguém mais poderá votar.')">
                    &#9209; Encerrar votação
                </button>
            </form>

            <?php elseif ($vStatus === 'encerrada'): ?>
            <form method="POST">
                <button name="revelar_resultados"
                        class="btn"
                        style="background:#d97706;color:#fff"
                        onclick="return confirm('Revelar os resultados no painel agora?')">
                    &#127942; Revelar resultados
                </button>
            </form>

            <?php elseif ($vStatus === 'revelada'): ?>
            <form method="POST">
                <button name="ocultar_resultados"
                        class="btn btn-secondary"
                        onclick="return confirm('Ocultar os resultados? O painel voltará ao modo suspense.')">
                    &#128274; Ocultar resultados
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Zona de Perigo -->
    <div class="card" style="border:2px solid var(--danger)">
        <h2 style="color:var(--danger)">&#128683; Zona de Perigo</h2>
        <p class="text-muted" style="margin:8px 0 16px">Esta ação apaga <strong>todos os votos</strong> sem arquivar, libera todos os códigos e oculta os resultados. <strong>Não pode ser desfeita.</strong> Para preservar o histórico, use o botão <strong>Nova Votação</strong>.</p>
        <form method="POST">
            <button name="zerar_votos"
                    class="btn btn-danger"
                    onclick="return confirm('⚠️ ATENÇÃO: todos os votos serão APAGADOS sem arquivar.\n\nDeseja continuar?')">
                &#128465; Zerar sem arquivar
            </button>
        </form>
    </div>

    <!-- ── Histórico de Votações ── -->
    <h2 class="mt-3 mb-2">&#128202; Histórico de Votações</h2>
    <?php if (empty($historico)): ?>
        <p class="text-muted" style="font-size:.88rem">Nenhuma votação arquivada ainda. Use o botão <strong>Nova Votação</strong> para arquivar e iniciar uma nova rodada.</p>
    <?php else: ?>
        <?php foreach ($historico as $item):
            $meta = $item['meta'];
            $det  = $item['detalhes'];
            $porCargo = [];
            foreach ($det as $d) { $porCargo[$d['cargo_nome']][] = $d; }
        ?>
        <div class="hist-card">
            <div class="hist-header" onclick="toggleHist(<?php echo $meta['id']; ?>)">
                <div>
                    <div class="hist-title">&#127942; <?php echo htmlspecialchars($meta['nome']); ?></div>
                    <div class="hist-meta">
                        <?php echo date('d/m/Y H:i', strtotime($meta['criado_em'])); ?>
                        &bull; <?php echo $meta['total_votos']; ?> votos
                        &bull; <?php echo $meta['votos_nulos']; ?> nulos
                        <?php if ($meta['duracao_segundos'] !== null): ?>
                        &bull; &#9201; <?php
                            $s = (int)$meta['duracao_segundos'];
                            $h = intdiv($s, 3600); $m = intdiv($s % 3600, 60); $ss = $s % 60;
                            echo $h > 0 ? sprintf('%dh %02dm %02ds', $h, $m, $ss) : sprintf('%02dm %02ds', $m, $ss);
                        ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px">
                    <span class="hist-badge"><?php echo $meta['total_votos']; ?> votos</span>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este registro do histórico?')">
                        <input type="hidden" name="excluir_historico" value="<?php echo $meta['id']; ?>">
                        <button class="btn-excluir-hist" type="submit" onclick="event.stopPropagation()">&#128465;</button>
                    </form>
                    <span style="color:#64748b" id="arrow-<?php echo $meta['id']; ?>">&#9660;</span>
                </div>
            </div>
            <div class="hist-body" id="hist-body-<?php echo $meta['id']; ?>">
                <?php if (empty($det)): ?>
                    <p style="color:#64748b;font-size:.84rem">Nenhum voto nominal registrado (apenas nulos).</p>
                <?php else: ?>
                    <?php foreach ($porCargo as $cargo => $cands): ?>
                        <div class="hist-cargo">
                            <div class="hist-cargo-nome"><?php echo htmlspecialchars($cargo); ?></div>
                            <?php foreach ($cands as $c): ?>
                                <div class="hist-cand-row">
                                    <span><?php echo htmlspecialchars($c['candidato_nome']); ?></span>
                                    <span class="hist-votos"><?php echo $c['votos']; ?> voto<?php echo $c['votos'] != 1 ? 's' : ''; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div><!-- /container -->

<!-- ══════════ MODAL WIZARD NOVA VOTAÇÃO ══════════ -->
<div class="modal-overlay" id="modalWizard">
    <div class="modal-box">
        <div id="step1">
            <div class="wizard-steps">
                <div class="wizard-step done"></div>
                <div class="wizard-step"></div>
            </div>
            <h3>&#128257; Iniciar Nova Votação</h3>
            <p>Esta ação irá <strong>arquivar</strong> os resultados atuais com nome e horário, depois <strong>zerar todos os votos</strong>, liberar os códigos e deixar os resultados <strong>ocultos</strong>.</p>
            <p style="color:#fbbf24;font-size:.85rem">&#9888; Os votos atuais são salvos no histórico antes de serem apagados.</p>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
                <button class="btn btn-secondary" onclick="fecharWizard()">Cancelar</button>
                <button class="btn btn-primary" onclick="irStep2()">Continuar &#8594;</button>
            </div>
        </div>
        <div id="step2" style="display:none">
            <div class="wizard-steps">
                <div class="wizard-step done"></div>
                <div class="wizard-step done"></div>
            </div>
            <h3>&#128221; Nome desta Votação</h3>
            <p>Dê um nome para identificar esta rodada no histórico.</p>
            <form method="POST">
                <div class="form-group">
                    <input type="text" name="nome_votacao" id="inputNomeVotacao"
                           placeholder="Ex: 1ª Rodada — Fevereiro/2026"
                           maxlength="180" style="width:100%;box-sizing:border-box">
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
                    <button type="button" class="btn btn-secondary" onclick="voltarStep1()">&#8592; Voltar</button>
                    <button type="submit" name="nova_votacao"
                            class="btn" style="background:#059669;color:#fff"
                            onclick="return validarNome()">
                        &#128257; Arquivar e Iniciar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirWizard() {
    document.getElementById('modalWizard').classList.add('open');
    document.getElementById('step1').style.display = '';
    document.getElementById('step2').style.display = 'none';
}
function fecharWizard() { document.getElementById('modalWizard').classList.remove('open'); }
function irStep2() {
    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = '';
    document.getElementById('inputNomeVotacao').focus();
}
function voltarStep1() {
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step1').style.display = '';
}
function validarNome() {
    const v = document.getElementById('inputNomeVotacao').value.trim();
    if (!v) { alert('Informe um nome para a votação.'); return false; }
    return confirm('Arquivar votação como "' + v + '" e iniciar nova rodada?\n\nEsta ação não pode ser desfeita.');
}
document.getElementById('modalWizard').addEventListener('click', function(e) {
    if (e.target === this) fecharWizard();
});
function toggleHist(id) {
    const body  = document.getElementById('hist-body-' + id);
    const arrow = document.getElementById('arrow-' + id);
    const open  = body.classList.toggle('open');
    arrow.innerHTML = open ? '&#9650;' : '&#9660;';
}
</script>

<?php endif; ?>
</body>
</html>
