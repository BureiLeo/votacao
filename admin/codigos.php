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
}

// ------- DADOS -------
$codigos = $pdo->query(
    "SELECT id, codigo, usado, criado_em, usado_em
     FROM codigos
     ORDER BY usado ASC, criado_em DESC"
)->fetchAll();

$total   = count($codigos);
$usados  = array_sum(array_column($codigos, 'usado'));
$livres  = $total - $usados;

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
    <div class="stats-grid mt-3" style="grid-template-columns:repeat(3,1fr)">
        <div class="stat-card">
            <div class="num"><?php echo $total; ?></div>
            <div class="desc">Total de códigos</div>
        </div>
        <div class="stat-card">
            <div class="num" style="color:var(--success)"><?php echo $usados; ?></div>
            <div class="desc">Utilizados</div>
        </div>
        <div class="stat-card">
            <div class="num" style="color:var(--warning)"><?php echo $livres; ?></div>
            <div class="desc">Disponíveis</div>
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
        <form method="POST" style="display:inline">
            <button name="limpar_nao_usados" class="btn btn-danger btn-sm"
                onclick="return confirm('Remover TODOS os códigos não utilizados?')">
                &#128465; Limpar não utilizados
            </button>
        </form>
    </div>

    <!-- Tabela de códigos -->
    <div class="d-flex" style="justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px">
        <h2 style="margin:0">&#128273; Códigos</h2>
        <span style="font-size:.8rem;color:var(--muted)" id="ultima-atualizacao">Atualizando…</span>
    </div>
    <div class="tabela-wrap" id="tabela-wrap">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Status</th>
                    <th>Criado em</th>
                    <th>Usado em</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($codigos)): ?>
                    <tr><td colspan="4" class="text-center" style="padding:20px;color:var(--muted)">
                        Nenhum código gerado ainda.
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($codigos as $c): ?>
                    <tr>
                        <td><strong style="font-family:monospace;font-size:1.05rem;letter-spacing:.1em">
                            <?php echo htmlspecialchars($c['codigo']); ?>
                        </strong></td>
                        <td>
                            <?php if ($c['usado']): ?>
                                <span style="color:var(--success);font-weight:600">&#9679; Usado</span>
                            <?php else: ?>
                                <span style="color:var(--warning);font-weight:600">&#9675; Disponível</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($c['criado_em']); ?></td>
                        <td><?php echo $c['usado_em'] ? htmlspecialchars($c['usado_em']) : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<script>
async function atualizarTabela() {
    try {
        const res  = await fetch('codigos.php?ajax_tabela=1&t=' + Date.now());
        const html = await res.text();
        const tmp  = document.createElement('div');
        tmp.innerHTML = html;
        const nova = tmp.querySelector('#tabela-wrap');
        if (nova) {
            document.getElementById('tabela-wrap').innerHTML = nova.innerHTML;
        }
        const stats = tmp.querySelectorAll('.stat-card .num');
        const locais = document.querySelectorAll('.stat-card .num');
        stats.forEach((el, i) => { if (locais[i]) locais[i].textContent = el.textContent; });
        const agora = new Date();
        document.getElementById('ultima-atualizacao').textContent =
            'Atualizado às ' + agora.toLocaleTimeString('pt-BR');
    } catch(e) {}
}
atualizarTabela();
setInterval(atualizarTabela, 8000);
</script>

</body>
</html>
