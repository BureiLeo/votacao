<?php
/**
 * urna/index.php — Entrada do código de votação
 */
session_start();

require_once '../config/database.php';

// Se já está no meio de uma votação → continua de onde parou
if (!empty($_SESSION['votacao_codigo_id'])) {
    header('Location: voto.php');
    exit();
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));

    if ($codigo === '') {
        $erro = 'Digite o código de votação.';
    } elseif (!votacaoAtiva()) {
        $erro = 'A votação não está ativa no momento. Aguarde a organização.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id, usado FROM codigos WHERE codigo = ?");
        $stmt->execute([$codigo]);
        $row  = $stmt->fetch();

        if (!$row) {
            $erro = 'Código não encontrado. Verifique e tente novamente.';
        } elseif ($row['usado']) {
            $erro = 'Este código já foi utilizado.';
        } else {
            // Carrega a lista ordenada de cargos
            $stmt2  = $pdo->query("SELECT id FROM cargos ORDER BY ordem ASC");
            $cargos = $stmt2->fetchAll(PDO::FETCH_COLUMN);

            // Inicializa sessão de votação
            $_SESSION['votacao_codigo_id']  = (int)$row['id'];
            $_SESSION['votacao_cargos']     = $cargos;
            $_SESSION['votacao_cargo_idx']  = 0;

            header('Location: voto.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eleição Jornada Jovem</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="page-header">
    <h1>&#127891; Eleição Jornada Jovem 2026</h1>
    <p>Informe seu código para iniciar a votação</p>
</div>

<div class="page-center" style="margin-top:-40px">
    <div class="card card-sm">
        <h2 class="text-center mb-2">&#128273; Seu Código</h2>

        <?php if ($erro): ?>
            <div class="alerta alerta-erro"><?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>

        <?php if (!votacaoAtiva() && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
            <div class="alerta alerta-aviso">&#9888; A votação não está ativa no momento.</div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="codigo">Código de votação</label>
                <input
                    class="codigo-input"
                    type="text"
                    id="codigo"
                    name="codigo"
                    maxlength="12"
                    placeholder="EX: AB12CD"
                    autofocus
                    required>
                <p class="hint">O código foi distribuído pela organização. Não compartilhe.</p>
            </div>
            <button class="btn btn-primary btn-full" type="submit">Começar votação &rarr;</button>
        </form>
    </div>
</div>
</body>
</html>
