<?php
/**
 * admin/_auth.php — Incluído em todas as páginas admin.
 * Garante que o usuário está logado ou redireciona para a tela de login.
 */

define('ADMIN_SESSION_TTL', 60 * 60); // 1 hora em segundos

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', ADMIN_SESSION_TTL);
    session_set_cookie_params([
        'lifetime' => ADMIN_SESSION_TTL,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// Verifica timeout manual (independente das configs do servidor)
if (!empty($_SESSION['admin_logado'])) {
    $agora      = time();
    $ultimaAtiv = $_SESSION['admin_ultima_atividade'] ?? $agora;

    if (($agora - $ultimaAtiv) > ADMIN_SESSION_TTL) {
        // Sessão expirou — destrói e redireciona
        session_unset();
        session_destroy();
        $url = defined('ADMIN_LOGIN_URL') ? ADMIN_LOGIN_URL : 'index.php';
        header('Location: ' . $url . '?sessao=expirada');
        exit();
    }

    // Renova timestamp a cada requisição
    $_SESSION['admin_ultima_atividade'] = $agora;
}

if (empty($_SESSION['admin_logado'])) {
    header('Location: ' . (defined('ADMIN_LOGIN_URL') ? ADMIN_LOGIN_URL : 'index.php'));
    exit();
}
