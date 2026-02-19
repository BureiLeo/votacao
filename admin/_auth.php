<?php
/**
 * admin/_auth.php — Incluído em todas as páginas admin.
 * Garante que o usuário está logado ou redireciona para a tela de login.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['admin_logado'])) {
    header('Location: ' . (defined('ADMIN_LOGIN_URL') ? ADMIN_LOGIN_URL : 'index.php'));
    exit();
}
