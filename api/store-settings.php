<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../config/db.php';

try {
    $values = $pdo->query("SELECT setting_key, setting_value FROM store_settings WHERE setting_key IN ('free_shipping_limit', 'shipping_fee', 'low_stock_threshold')")->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode([
        'freeShippingLimit' => isset($values['free_shipping_limit']) ? (float)$values['free_shipping_limit'] : 750,
        'shippingFee' => isset($values['shipping_fee']) ? (float)$values['shipping_fee'] : 60,
        'lowStockThreshold' => isset($values['low_stock_threshold']) ? (int)$values['low_stock_threshold'] : 5,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('Store settings API failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Mağaza ayarları şu anda yüklenemiyor.'], JSON_UNESCAPED_UNICODE);
}
