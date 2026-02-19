<?php
/**
 * urna/voto.php — Votação cargo a cargo
 */
session_start();

require_once '../config/database.php';

// --- Garante sessão válida ---
if (empty($_SESSION['votacao_codigo_id']) || empty($_SESSION['votacao_cargos'])) {
    header('Location: index.php');
    exit();
}

$codigo_id = (int)$_SESSION['votacao_codigo_id'];
$cargos    = $_SESSION['votacao_cargos'];
$idx       = (int)($_SESSION['votacao_cargo_idx'] ?? 0);
$total     = count($cargos);

// --- Todos os cargos votados → conclui ---
if ($idx >= $total) {
    header('Location: concluido.php');
    exit();
}

$cargo_id_atual = (int)$cargos[$idx];
$pdo            = getDB();
$erro           = '';

// -------------------------------------------------------
// POST — registrar voto
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['votacao_csrf'] ?? '', $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die('Token inválido. Recarregue a página.');
    }

    // Sanitiza candidato_id  (null → voto nulo)
    $candidato_raw = $_POST['candidato_id'] ?? null;
    $candidato_id  = ($candidato_raw === 'nulo' || $candidato_raw === null)
        ? null
        : (int)$candidato_raw;

    // Verifica se cargo_id bate com o esperado (anti-tamper)
    $post_cargo_id = (int)($_POST['cargo_id'] ?? 0);
    if ($post_cargo_id !== $cargo_id_atual) {
        die('Cargo inválido.');
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO votos (codigo_id, cargo_id, candidato_id)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$codigo_id, $cargo_id_atual, $candidato_id]);

        // Avança para o próximo cargo
        $_SESSION['votacao_cargo_idx'] = $idx + 1;
        unset($_SESSION['votacao_csrf']);

        header('Location: voto.php');
        exit();

    } catch (PDOException $e) {
        // UNIQUE constraint → tentativa de voto duplo neste cargo
        if ($e->getCode() === '23000') {
            // Pula silenciosamente (já votou neste cargo) e avança
            $_SESSION['votacao_cargo_idx'] = $idx + 1;
            header('Location: voto.php');
            exit();
        }
        $erro = 'Erro ao registrar voto. Tente novamente.';
    }
}

// -------------------------------------------------------
// GET — exibir formulário
// -------------------------------------------------------

// Gera CSRF para este formulário
$_SESSION['votacao_csrf'] = bin2hex(random_bytes(32));

// Busca dados do cargo atual
$stmt = $pdo->prepare("SELECT nome FROM cargos WHERE id = ?");
$stmt->execute([$cargo_id_atual]);
$cargo = $stmt->fetch();
if (!$cargo) {
    die('Cargo não encontrado.');
}

// Busca candidatos do cargo (apenas ativos)
$stmt = $pdo->prepare(
    "SELECT id, nome FROM candidatos WHERE cargo_id = ? AND ativo = 1 ORDER BY nome ASC"
);
$stmt->execute([$cargo_id_atual]);
$candidatos = $stmt->fetchAll();

$numero_cargo = $idx + 1;
$pct          = round(($idx / $total) * 100);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votação — <?php echo htmlspecialchars($cargo['nome']); ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="page-header">
    <h1>&#127891; Eleição Jornada Jovem 2026</h1>
    <p>Cargo <?php echo $numero_cargo; ?> de <?php echo $total; ?></p>
</div>

<div class="container">
    <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <!-- Barra de progresso -->
    <div class="progresso">
        <span><?php echo $idx; ?>/<?php echo $total; ?> concluídos</span>
        <div class="progresso-bar-wrap">
            <div class="progresso-bar" style="width:<?php echo $pct; ?>%"></div>
        </div>
        <span><?php echo $pct; ?>%</span>
    </div>

    <div class="card">
        <div class="cargo-titulo">
            &#128204; <?php echo htmlspecialchars($cargo['nome']); ?>
        </div>

        <form method="POST" id="form-voto">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['votacao_csrf']; ?>">
            <input type="hidden" name="cargo_id"   value="<?php echo $cargo_id_atual; ?>">

            <div class="opcao-lista">
                <?php foreach ($candidatos as $c): ?>
                    <label class="opcao" id="label-<?php echo $c['id']; ?>">
                        <input type="radio"
                               name="candidato_id"
                               value="<?php echo $c['id']; ?>"
                               onchange="highlight(this)"
                               required>
                        <?php echo htmlspecialchars($c['nome']); ?>
                    </label>
                <?php endforeach; ?>

                <!-- Voto nulo sempre disponível -->
                <label class="opcao nulo" id="label-nulo">
                    <input type="radio"
                           name="candidato_id"
                           value="nulo"
                           onchange="highlight(this)">
                    &#128683; VOTO NULO
                </label>
            </div>

            <button class="btn btn-primary btn-full" type="submit" id="btn-votar" disabled>
                Confirmar voto &rarr;
            </button>
        </form>
    </div>
</div>

<script>
function highlight(radio) {
    // Remove seleção anterior
    document.querySelectorAll('.opcao').forEach(el => el.classList.remove('selecionado'));
    // Marca o pai
    radio.closest('.opcao').classList.add('selecionado');
    // Habilita botão
    document.getElementById('btn-votar').disabled = false;
}

// Confirmação antes de enviar
document.getElementById('form-voto').addEventListener('submit', function(e) {
    const selecionado = document.querySelector('input[name="candidato_id"]:checked');
    if (!selecionado) { e.preventDefault(); return; }

    const label = selecionado.closest('.opcao').textContent.trim();
    if (!confirm('Confirmar voto em:\n"' + label + '"?\n\nEsta ação não pode ser desfeita.')) {
        e.preventDefault();
    }
});
</script>
</body>
</html>
