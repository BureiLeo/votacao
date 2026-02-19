<?php
/**
 * config/database.php
 * Configuração PDO — edite apenas as constantes se necessário.
 */

define('DB_HOST',    'localhost');
define('DB_NAME',    'u323377136_votacao');
define('DB_USER',    'u323377136_admin_jj');
define('DB_PASS',    'votacaoJJ@2026');
define('DB_CHARSET', 'utf8mb4');

/**
 * Retorna uma instância PDO singleton.
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn     = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        die('<h2 style="font-family:sans-serif;color:#dc2626">Erro de conexão com o banco de dados.<br><small>' .
            htmlspecialchars($e->getMessage()) . '</small></h2>');
    }

    return $pdo;
}

/**
 * Lê uma configuração da tabela `configuracoes`.
 */
function getConfig(string $chave, string $padrao = ''): string
{
    static $cache = [];

    if (!isset($cache[$chave])) {
        $stmt = getDB()->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
        $stmt->execute([$chave]);
        $cache[$chave] = $stmt->fetchColumn() ?: $padrao;
    }

    return $cache[$chave];
}

/**
 * Retorna true se a votação estiver ativa.
 */
function votacaoAtiva(): bool
{
    return getConfig('votacao_ativa', 'false') === 'true';
}
