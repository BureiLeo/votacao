<?php
/**
 * urna/concluido.php — Finaliza a votação e marca o código como usado
 */
session_start();

require_once '../config/database.php';

// Garante que veio de uma sessão válida
if (empty($_SESSION['votacao_codigo_id'])) {
    header('Location: index.php');
    exit();
}

$codigo_id = (int)$_SESSION['votacao_codigo_id'];

// Marca o código como usado
try {
    $stmt = getDB()->prepare(
        "UPDATE codigos SET usado = 1, usado_em = NOW() WHERE id = ? AND usado = 0"
    );
    $stmt->execute([$codigo_id]);
} catch (PDOException $e) {
    // Silencioso — se já foi marcado por algum motivo, tudo bem
}

// Limpa a sessão de votação
unset(
    $_SESSION['votacao_codigo_id'],
    $_SESSION['votacao_cargos'],
    $_SESSION['votacao_cargo_idx'],
    $_SESSION['votacao_csrf']
);
session_destroy();

// Trava a urna para aguardar próxima liberação da mesa
try {
    getDB()->prepare(
        "INSERT INTO configuracoes (chave, valor) VALUES ('urna_liberada', 'false')
         ON DUPLICATE KEY UPDATE valor = 'false'"
    )->execute();
} catch (PDOException $e) { /* silencioso */ }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votação Concluída</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="page-center">
    <div class="card card-sm concluido-box">
        <div class="icone">&#9989;</div>
        <h1>Votação concluída!</h1>
        <p>Obrigado por participar da<br>
           <strong>Eleição Jornada Jovem 2026</strong>.</p>
        <p class="mt-2 text-muted">Seu voto foi registrado em todos os cargos.<br>
           Este código não pode mais ser utilizado.</p>
        <p class="mt-2 text-muted" style="font-size:.85rem">
            Esta tela será fechada automaticamente em <strong id="cnt">10</strong> segundos.
        </p>
    </div>
</div>
<script>
let s = 10;
const el = document.getElementById('cnt');
const t = setInterval(() => {
    s--;
    if (el) el.textContent = s;
    if (s <= 0) { clearInterval(t); window.location = 'index.php'; }
}, 1000);
</script>
</body>
</html>
