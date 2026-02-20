<?php
/**
 * admin/codigos.php — Gerar e gerenciar códigos de votação
 */
define('ADMIN_LOGIN_URL', 'index.php');
require '_auth.php';

$pdo     = getDB();
$sucesso = '';
$erro    = '';

// ------- AÇÕES -------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Gerar N códigos
    if (isset($_POST['gerar'])) {
        $quantidade = max(1, min(500, (int)($_POST['quantidade'] ?? 0)));
        $tamanho    = max(4, min(12, (int)($_POST['tamanho']    ?? 6)));
        $chars      = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem 0,O,I,1 (confusos)
        $gerados    = 0;

        $stmt = $pdo->prepare("INSERT IGNORE INTO codigos (codigo) VALUES (?)");

        for ($tentativas = 0; $tentativas < $quantidade * 5 && $gerados < $quantidade; $tentativas++) {
            $codigo = '';
            for ($i = 0; $i < $tamanho; $i++) {
                $codigo .= $chars[random_int(0, strlen($chars) - 1)];
            }
            try {
                $stmt->execute([$codigo]);
                if ($stmt->rowCount() > 0) $gerados++;
            } catch (PDOException $e) {
                // Duplicado — tenta outro
            }
        }
        $sucesso = "$gerados código(s) gerado(s) com sucesso.";
    }

    // Excluir códigos não usados
    if (isset($_POST['limpar_nao_usados'])) {
        $del = $pdo->exec("DELETE FROM codigos WHERE usado = 0");
        $sucesso = "$del código(s) não utilizados removidos.";
    }

    // Excluir códigos não impressos (e não usados)
    if (isset($_POST['limpar_nao_impressos'])) {
        $del = $pdo->exec("DELETE FROM codigos WHERE impresso = 0 AND usado = 0");
        $sucesso = "$del código(s) não impressos removidos.";
    }

    // Apagar todos os códigos não usados e reiniciar numeração do zero
    if (isset($_POST['zerar_numeracao'])) {
        $pdo->exec("DELETE FROM codigos WHERE usado = 0");
        // Se não há mais nenhum código, TRUNCATE reinicia o ID do zero
        $restantes = (int)$pdo->query("SELECT COUNT(*) FROM codigos")->fetchColumn();
        if ($restantes === 0) {
            $pdo->exec("TRUNCATE TABLE codigos");
            $sucesso = 'Tabela zerada. O próximo código gerado começará do #1.';
        } else {
            $maxId = (int)$pdo->query("SELECT MAX(id) FROM codigos")->fetchColumn();
            $pdo->exec("ALTER TABLE codigos AUTO_INCREMENT = " . ($maxId + 1));
            $sucesso = "Não usados removidos. Ainda há $restantes código(s) usados — próximo ID será #" . ($maxId + 1) . ".";
        }
    }
}

// ------- DADOS -------
$codigos = $pdo->query(
    "SELECT id, codigo, usado, impresso, criado_em, usado_em
     FROM codigos
     ORDER BY usado ASC, impresso DESC, criado_em DESC"
)->fetchAll();

$total       = count($codigos);
$usados      = array_sum(array_column($codigos, 'usado'));
$impressos   = array_sum(array_column($codigos, 'impresso'));
$naoImpress  = array_sum(array_map(fn($c) => (!$c['impresso'] && !$c['usado']) ? 1 : 0, $codigos));
$livres      = $total - $usados;

// Exportar lista de não-usados como texto plano
if (isset($_GET['exportar'])) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="codigos_votacao.txt"');
    foreach ($codigos as $c) {
        if (!$c['usado']) echo $c['codigo'] . "\n";
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Códigos — Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<script>
// Atualiza a cada 5s com timestamp na URL para evitar cache do navegador
setInterval(() => {
    if (!document.querySelector('input:focus, select:focus, textarea:focus')) {
        const url = window.location.pathname + '?_t=' + Date.now();
        window.location.replace(url);
    }
}, 5000);
</script>
<div class="admin-nav-header">
    <h1>&#127891; Votação Jornada Jovem — Admin</h1>
    <form method="POST" action="index.php" style="display:inline">
        <button name="sair" class="btn btn-danger btn-sm">Sair</button>
    </form>
</div>
<nav class="admin-nav">
    <a href="index.php">&#127968; Dashboard</a>
    <a href="candidatos.php">&#128101; Candidatos</a>
    <a href="codigos.php" class="ativo">&#128273; Códigos</a>
    <a href="../painel/index.php" target="_blank">&#128200; Painel &#8599;</a>
</nav>

<div class="container" style="max-width:860px">
    <?php if ($sucesso): ?>
        <div class="alerta alerta-sucesso mt-2"><?php echo htmlspecialchars($sucesso); ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alerta alerta-erro mt-2"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <!-- Estatísticas rápidas -->
    <div class="stats-grid mt-3" style="grid-template-columns:repeat(auto-fill,minmax(130px,1fr))">
        <div class="stat-card">
            <div class="num" id="stat-total"><?php echo $total; ?></div>
            <div class="desc">Total de códigos</div>
        </div>
        <div class="stat-card">
            <div class="num" id="stat-usados" style="color:var(--success)"><?php echo $usados; ?></div>
            <div class="desc">Utilizados</div>
        </div>
        <div class="stat-card">
            <div class="num" id="stat-livres" style="color:var(--warning)"><?php echo $livres; ?></div>
            <div class="desc">Disponíveis</div>
        </div>
        <div class="stat-card">
            <div class="num" id="stat-impressos" style="color:#818cf8"><?php echo $impressos; ?></div>
            <div class="desc">Impressos</div>
        </div>
        <div class="stat-card">
            <div class="num" id="stat-naoimpress" style="color:#f87171"><?php echo $naoImpress; ?></div>
            <div class="desc">Não impressos</div>
        </div>
    </div>

    <!-- Gerar códigos -->
    <div class="card">
        <h2>&#10133; Gerar Novos Códigos</h2>
        <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-top:16px">
            <div class="form-group" style="flex:1;min-width:120px;margin:0">
                <label>Quantidade</label>
                <input type="number" name="quantidade" value="50" min="1" max="500" required>
                <p class="hint">Máx: 500</p>
            </div>
            <div class="form-group" style="flex:1;min-width:120px;margin:0">
                <label>Tamanho do código</label>
                <input type="number" name="tamanho" value="6" min="4" max="12" required>
                <p class="hint">Ex: 6 → "A3BK9X"</p>
            </div>
            <div style="padding-bottom:20px">
                <button name="gerar" class="btn btn-primary">Gerar</button>
            </div>
        </form>
    </div>

    <!-- Ações em lote -->
    <div class="d-flex gap-2 flex-wrap mb-2">
        <a href="?exportar=1" class="btn btn-secondary btn-sm">&#8681; Exportar disponíveis (.txt)</a>
        <a href="imprimir_codigos.php" class="btn btn-sm" style="background:#7c3aed;color:#fff">&#128438; Imprimir códigos</a>
        <form method="POST" style="display:inline">
            <button name="limpar_nao_impressos" class="btn btn-sm" style="background:#92400e;color:#fff"
                onclick="return confirm('Remover todos os códigos que ainda não foram impressos?')">
                &#128465; Apagar não impressos (<?php echo $naoImpress; ?>)
            </button>
        </form>
        <form method="POST" style="display:inline">
            <button name="limpar_nao_usados" class="btn btn-danger btn-sm"
                onclick="return confirm('Remover TODOS os códigos não utilizados?')">
                &#128465; Limpar não utilizados
            </button>
        </form>
        <form method="POST" style="display:inline">
            <button name="zerar_numeracao" class="btn btn-sm" style="background:#7f1d1d;color:#fca5a5"
                onclick="return confirm('Isso vai APAGAR todos os códigos não usados e reiniciar a numeração.\n\nCódigos já utilizados serão mantidos.\n\nDeseja continuar?')">
                &#128260; Apagar não usados + reiniciar #ID
            </button>
        </form>
    </div>

    <!-- Tabela de códigos -->
    <div class="d-flex" style="justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px">
        <h2 style="margin:0">&#128273; Códigos</h2>
        <span style="font-size:.8rem;color:var(--muted)" id="ultima-atualizacao">Atualizando…</span>
    </div>
    <div class="tabela-wrap" id="tabela-wrap">
        <table class="tabela" id="tabela-codigos">
            <thead>
                <tr>
                    <th style="color:var(--muted);font-weight:500">#ID</th>
                    <th>Código</th>
                    <th>Impresso</th>
                    <th>Status</th>
                    <th>Usado em</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($codigos)): ?>
                    <tr><td colspan="5" class="text-center" style="padding:20px;color:var(--muted)">
                        Nenhum código gerado ainda.
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($codigos as $c): ?>
                    <tr>
                        <td style="color:var(--muted);font-size:.8rem;white-space:nowrap">#<?php echo $c['id']; ?></td>
                        <td><strong style="font-family:monospace;font-size:1.05rem;letter-spacing:.1em">
                            <?php echo htmlspecialchars($c['codigo']); ?>
                        </strong></td>
                        <td>
                            <?php if ($c['impresso']): ?>
                                <span style="color:#818cf8;font-weight:600">&#9679; Sim</span>
                            <?php else: ?>
                                <span style="color:#f87171;font-weight:600">&#9675; Não</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['usado']): ?>
                                <span style="color:var(--success);font-weight:600">&#9679; Usado</span>
                            <?php else: ?>
                                <span style="color:var(--warning);font-weight:600">&#9675; Disponível</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $c['usado_em'] ? htmlspecialchars($c['usado_em']) : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<script>
const STATUS_USADO     = '<span style="color:var(--success);font-weight:600">&#9679; Usado</span>';
const STATUS_LIVRE     = '<span style="color:var(--warning);font-weight:600">&#9675; Disponível</span>';
const STATUS_IMP_SIM   = '<span style="color:#818cf8;font-weight:600">&#9679; Sim</span>';
const STATUS_IMP_NAO   = '<span style="color:#f87171;font-weight:600">&#9675; Não</span>';

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function atualizarTabela() {
    try {
        const res  = await fetch('api_codigos.php?t=' + Date.now());

        // Sessão expirou → servidor redirecionou (retorna HTML, não JSON)
        if (!res.ok || res.headers.get('content-type')?.indexOf('application/json') === -1) {
            document.getElementById('ultima-atualizacao').innerHTML =
                '⚠️ Sessão expirada. <a href="index.php">Fazer login</a>';
            return;
        }

        const data = await res.json();
        if (!data.ok) return;

        const upd = (id, val) => { const el = document.getElementById(id); if (el && val != null) el.textContent = val; };
        upd('stat-total',      data.total);
        upd('stat-usados',     data.usados);
        upd('stat-livres',     data.livres);
        upd('stat-impressos',  data.impressos);
        upd('stat-naoimpress', data.naoImpress);

        // Reconstrói linhas da tabela
        const tbody = document.querySelector('#tabela-codigos tbody');
        if (!tbody) return;

        if (!data.lista.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center" style="padding:20px;color:var(--muted)">Nenhum código gerado ainda.</td></tr>';
        } else {
            tbody.innerHTML = data.lista.map(c => `
                <tr>
                    <td style="color:var(--muted);font-size:.8rem;white-space:nowrap">#${esc(c.id)}</td>
                    <td><strong style="font-family:monospace;font-size:1.05rem;letter-spacing:.1em">${esc(c.codigo)}</strong></td>
                    <td>${c.impresso ? STATUS_IMP_SIM : STATUS_IMP_NAO}</td>
                    <td>${c.usado ? STATUS_USADO : STATUS_LIVRE}</td>
                    <td>${c.usado_em ? esc(c.usado_em) : '—'}</td>
                </tr>`).join('');
        }

        document.getElementById('ultima-atualizacao').textContent =
            'Atualizado às ' + new Date().toLocaleTimeString('pt-BR');

    } catch(e) {
        document.getElementById('ultima-atualizacao').textContent = 'Erro ao atualizar.';
    }
}

atualizarTabela();
setInterval(atualizarTabela, 5000);
</script>

</body>
</html>
