<?php
/**
 * admin/api_marcar_impresso.php — Marca códigos como impressos
 * POST JSON: { "ids": [1, 2, 3, ...] }
 */
define('ADMIN_LOGIN_URL', 'index.php');
require '_auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$ids  = array_filter(array_map('intval', (array)($body['ids'] ?? [])));

if (empty($ids)) {
    echo json_encode(['ok' => false, 'erro' => 'Nenhum ID recebido.']);
    exit;
}

$pdo = getDB();

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare(
    "UPDATE codigos
     SET impresso = 1, impresso_em = NOW()
     WHERE id IN ($placeholders) AND impresso = 0"
);
$stmt->execute(array_values($ids));

echo json_encode(['ok' => true, 'atualizados' => $stmt->rowCount()]);
