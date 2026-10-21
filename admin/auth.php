<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
require_once __DIR__ . '/../config/db.php';

$projectPath = realpath(__DIR__ . '/..');
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$relativePath = ($projectPath && $documentRoot && stripos($projectPath, $documentRoot) === 0)
    ? substr($projectPath, strlen($documentRoot))
    : '';
if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', rtrim('/' . trim(str_replace(DIRECTORY_SEPARATOR, '/', $relativePath), '/'), '/'));
}

function app_url(string $path = ''): string
{
    return APP_BASE_URL . '/' . ltrim($path, '/');
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals($_SESSION['csrf_token'] ?? '', $submitted)) {
        http_response_code(419);
        exit('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
    }
}

function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) { header('Location: ' . app_url('admin/login.php')); exit; }
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}
