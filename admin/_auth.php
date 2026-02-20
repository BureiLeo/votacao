<?php
/**
 * admin/_auth.php — Incluído em todas as páginas admin.
 * Garante que o usuário está logado ou redireciona para a tela de login.
 */

define('ADMIN_SESSION_TTL', 60 * 60); // 1 hora em segundos

if (session_status() === PHP_SESSION_NONE) {
    // Salva sessões num diretório do próprio projeto para evitar
    // que o GC do servidor compartilhado apague antes da hora
    $sessDir = __DIR__ . '/../config/sessions';
    if (!is_dir($sessDir)) {
        mkdir($sessDir, 0700, true);
    }
    session_save_path($sessDir);

    ini_set('session.gc_maxlifetime', ADMIN_SESSION_TTL);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor',     100);
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

// Verifica timeout manual (funciona mesmo que o servidor ignore o ini_set)
if (!empty($_SESSION['admin_logado'])) {
    $agora      = time();
    $ultimaAtiv = $_SESSION['admin_ultima_atividade'] ?? $agora;

    if (($agora - $ultimaAtiv) > ADMIN_SESSION_TTL) {
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
