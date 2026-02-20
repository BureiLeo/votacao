<?php
/**
 * urna/status.php — Retorna o estado de liberação da urna (usado pelo polling JS)
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

require_once '../config/database.php';

echo json_encode([
    'votacao_status' => votacaoStatus(),
    'urna_liberada'  => urnaLiberada(),
]);
