<?php
/**
 * admin/api_codigos.php — Retorna JSON com estatísticas e lista de códigos.
 * Usado pelo polling do codigos.php para atualização sem reload.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

define('ADMIN_LOGIN_URL', 'index.php');
require '_auth.php';

try {
    $codigos = getDB()->query(
        "SELECT codigo, usado, criado_em, usado_em
         FROM codigos
         ORDER BY usado ASC, criado_em DESC"
    )->fetchAll();

    $total  = count($codigos);
    $usados = array_sum(array_column($codigos, 'usado'));

    echo json_encode([
        'ok'     => true,
        'total'  => $total,
        'usados' => $usados,
        'livres' => $total - $usados,
        'lista'  => $codigos,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
