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
    <style>
        .estado-tela {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            min-height: 80vh; text-align: center; padding: 40px 20px;
        }
        .estado-tela .icone { font-size: 5rem; margin-bottom: 20px; }
        .estado-tela h2 { font-size: 2rem; margin-bottom: 12px; }
        .estado-tela p  { color: #64748b; font-size: 1rem; max-width: 380px; }
        .pontos::after {
            content: '';
            animation: pontos 1.5s steps(4,end) infinite;
        }
        @keyframes pontos {
            0%{content:'.'} 33%{content:'..'} 66%{content:'...'} 100%{content:''}
        }
    </style>
    <?php if (votacaoStatus() === 'aguardando'): ?>
    <meta http-equiv="refresh" content="10">
    <?php endif; ?>
</head>
<body>
<div class="page-header">
    <h1>&#127891; Eleição Jornada Jovem 2026</h1>
</div>

<?php $status = votacaoStatus(); ?>

<?php if ($status === 'aguardando'): ?>
<!-- ── AGUARDANDO INÍCIO ── -->
<div class="estado-tela">
    <div class="icone">&#9203;</div>
    <h2>Aguardando início da votação<span class="pontos"></span></h2>
    <p>A votação ainda não foi iniciada. Por favor, aguarde a organização abrir as urnas.</p>
</div>

<?php elseif ($status === 'encerrada' || $status === 'revelada'): ?>
<!-- ── VOTAÇÃO ENCERRADA ── -->
<div class="estado-tela">
    <div class="icone">&#128683;</div>
    <h2>Votação encerrada</h2>
    <p>O período de votação foi encerrado. Obrigado pela sua participação!</p>
    <?php if ($status === 'revelada'): ?>
    <p style="margin-top:16px">
        <a href="../painel/index.php" style="color:#4f46e5;font-weight:700">&#128200; Ver resultados &rarr;</a>
    </p>
    <?php endif; ?>
</div>

<?php elseif (!urnaLiberada()): ?>
<!-- ── URNA TRAVADA — AGUARDANDO LIBERAÇÃO DA MESA ── -->
<div class="estado-tela" id="tela-espera">
    <div class="icone">&#128274;</div>
    <h2>Votação ainda não liberada<span class="pontos"></span></h2>
    <p>Aguarde a organização liberar a votação. Assim que liberado, você poderá digitar seu código e votar normalmente.</p>
</div>
<script>
// Polling: verifica a cada 2s se a urna foi liberada
(function poll() {
    fetch('status.php?t=' + Date.now())
        .then(r => r.json())
        .then(data => {
            if (data.urna_liberada) {
                window.location.reload();
            } else {
                setTimeout(poll, 2000);
            }
        })
        .catch(() => setTimeout(poll, 3000));
})();
</script>

<?php else: ?>
<!-- ── URNA LIBERADA — FORMULÁRIO ── -->
<div class="page-center" style="margin-top:-40px">
    <div class="card card-sm">
        <h2 class="text-center mb-2">&#128273; Seu Código</h2>

        <?php if ($erro): ?>
            <div class="alerta alerta-erro"><?php echo htmlspecialchars($erro); ?></div>
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
<?php endif; ?>
</body>
</html>
