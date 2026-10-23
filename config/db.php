<?php
declare(strict_types=1);

$httpHostHeader = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
$parsedHost = parse_url('http://' . $httpHostHeader, PHP_URL_HOST);
$httpHost = strtolower(is_string($parsedHost) ? $parsedHost : '');
$isLoopbackHost = in_array($httpHost, ['localhost', '127.0.0.1', '::1'], true);
$configuredEnvironment = getenv('APP_ENV');
$appEnvironment = strtolower(trim($configuredEnvironment !== false && $configuredEnvironment !== ''
    ? $configuredEnvironment
    : ($isLoopbackHost ? 'local' : 'production')));

if (!in_array($appEnvironment, ['local', 'production'], true)) {
    error_log('Database configuration rejected: APP_ENV must be local or production.');
    http_response_code(500);
    exit('Uygulama ortamı ayarları geçersiz.');
}

if ($appEnvironment === 'local' && $httpHost !== '' && !$isLoopbackHost) {
    error_log('Database configuration rejected: local environment requested by a non-local host.');
    http_response_code(500);
    exit('Uygulama ortamı ayarları geçersiz.');
}

$dbHost = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');

if ($appEnvironment === 'production') {
    $missingSettings = $dbHost === false || trim($dbHost) === ''
        || $dbName === false || trim($dbName) === ''
        || $dbUser === false || trim($dbUser) === ''
        || $dbPass === false || $dbPass === '';
    if ($missingSettings || strtolower(trim((string)$dbUser)) === 'root') {
        error_log('Production database configuration rejected: explicit DB_HOST, DB_NAME, dedicated DB_USER, and non-empty DB_PASS are required.');
        http_response_code(500);
        exit('Üretim veritabanı ayarları eksik.');
    }
} else {
    $dbHost = $dbHost !== false && trim($dbHost) !== '' ? $dbHost : '127.0.0.1';
    $dbName = $dbName !== false && trim($dbName) !== '' ? $dbName : 'koken_admin';
    $dbUser = $dbUser !== false && trim($dbUser) !== '' ? $dbUser : 'root';
    $dbPass = $dbPass !== false ? $dbPass : '';
}

try {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Veritabanı bağlantısı kurulamadı.');
}
