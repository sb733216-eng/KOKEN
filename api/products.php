<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/storefront.php';
try {
    $stmt = $pdo->query('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 ORDER BY p.created_at DESC, p.id DESC');
    echo json_encode(array_map('store_product_data', $stmt->fetchAll()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('Product catalog API failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ürün kataloğu şu anda yüklenemiyor.'], JSON_UNESCAPED_UNICODE);
}