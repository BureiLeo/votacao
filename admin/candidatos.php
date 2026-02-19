<?php
/**
 * admin/candidatos.php — Gerenciar candidatos por cargo
 */
define('ADMIN_LOGIN_URL', 'index.php');
require '_auth.php';

$pdo     = getDB();
$sucesso = '';
$erro    = '';

// ------- AÇÕES -------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Adicionar candidato
    if (isset($_POST['adicionar'])) {
        $nome     = trim($_POST['nome']     ?? '');
        $cargo_id = (int)($_POST['cargo_id'] ?? 0);

        if ($nome === '' || $cargo_id <= 0) {
            $erro = 'Preencha o nome e selecione o cargo.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO candidatos (nome, cargo_id) VALUES (?, ?)");
            $stmt->execute([$nome, $cargo_id]);
            $sucesso = "Candidato \"$nome\" adicionado.";
        }
    }

    // Desativar (soft-delete)
    if (isset($_POST['desativar'])) {
        $id   = (int)$_POST['candidato_id'];
        $stmt = $pdo->prepare("UPDATE candidatos SET ativo = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $sucesso = 'Candidato desativado.';
    }

    // Reativar
    if (isset($_POST['reativar'])) {
        $id   = (int)$_POST['candidato_id'];
        $stmt = $pdo->prepare("UPDATE candidatos SET ativo = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $sucesso = 'Candidato reativado.';
    }

    // Zerar todos os candidatos
    if (isset($_POST['zerar_candidatos'])) {
        // ON DELETE SET NULL nas votos → votos existentes viram nulos automaticamente
        $pdo->exec("DELETE FROM candidatos");
        $pdo->exec("ALTER TABLE candidatos AUTO_INCREMENT = 1");
        $sucesso = 'Todos os candidatos foram removidos. Cadastre os novos abaixo.';
    }
}

// ------- DADOS -------
$cargos = $pdo->query("SELECT id, nome FROM cargos ORDER BY ordem")->fetchAll();

$candidatos_raw = $pdo->query(
    "SELECT cd.id, cd.nome, cd.ativo, ca.nome AS cargo_nome, ca.ordem
     FROM candidatos cd
     JOIN cargos ca ON ca.id = cd.cargo_id
     ORDER BY ca.ordem, cd.nome"
)->fetchAll();

// Agrupa por cargo
$por_cargo = [];
foreach ($candidatos_raw as $c) {
    $por_cargo[$c['cargo_nome']][] = $c;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidatos — Admin</title>
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
    <a href="candidatos.php" class="ativo">&#128101; Candidatos</a>
    <a href="codigos.php">&#128273; Códigos</a>
    <a href="../painel/index.php" target="_blank">&#128200; Painel &#8599;</a>
</nav>

<div class="container" style="max-width:860px">
    <?php if ($sucesso): ?>
        <div class="alerta alerta-sucesso mt-2"><?php echo htmlspecialchars($sucesso); ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alerta alerta-erro mt-2"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <!-- Adicionar candidato -->
    <div class="card mt-3">
        <h2>&#10133; Adicionar Candidato</h2>
        <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-top:16px">
            <div class="form-group" style="flex:2;min-width:180px;margin:0">
                <label>Nome do candidato</label>
                <input type="text" name="nome" placeholder="Nome completo" required maxlength="120">
            </div>
            <div class="form-group" style="flex:2;min-width:180px;margin:0">
                <label>Cargo</label>
                <select name="cargo_id" required>
                    <option value="">— Selecione —</option>
                    <?php foreach ($cargos as $cargo): ?>
                        <option value="<?php echo $cargo['id']; ?>">
                            <?php echo htmlspecialchars($cargo['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="padding-bottom:1px">
                <button name="adicionar" class="btn btn-primary">Adicionar</button>
            </div>
        </form>
    </div>

    <!-- Zona de perigo -->
    <div class="card" style="border:2px solid #fca5a5">
        <h2 style="color:var(--danger)">&#9888; Zona de Perigo</h2>
        <p class="text-muted mt-1 mb-2">
            Remove <strong>todos</strong> os candidatos do banco para que você possa recadastrar do zero.
            Votos já registrados que apontavam para candidatos ficam marcados como <em>nulo</em>.
        </p>
        <form method="POST">
            <button name="zerar_candidatos" class="btn btn-danger"
                onclick="return confirm('Tem certeza? TODOS os candidatos serão excluídos permanentemente.')">
                &#128465; Apagar todos os candidatos
            </button>
        </form>
    </div>

    <!-- Lista por cargo -->
    <?php foreach ($por_cargo as $cargo_nome => $candidatos): ?>
        <div class="card">
            <h2 style="font-size:1.05rem;margin-bottom:12px">&#128204; <?php echo htmlspecialchars($cargo_nome); ?></h2>
            <div class="tabela-wrap">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>Status</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidatos as $c): ?>
                            <tr>
                                <td><?php echo $c['id']; ?></td>
                                <td><?php echo htmlspecialchars($c['nome']); ?></td>
                                <td>
                                    <?php if ($c['ativo']): ?>
                                        <span style="color:var(--success);font-weight:600">&#9679; Ativo</span>
                                    <?php else: ?>
                                        <span style="color:var(--muted)">&#9675; Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="candidato_id" value="<?php echo $c['id']; ?>">
                                        <?php if ($c['ativo']): ?>
                                            <button name="desativar" class="btn btn-danger btn-sm"
                                                onclick="return confirm('Desativar este candidato?')">Desativar</button>
                                        <?php else: ?>
                                            <button name="reativar" class="btn btn-secondary btn-sm">Reativar</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
