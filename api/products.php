<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $pdo->query('SELECT p.id, p.title, p.weight, p.chip, p.price, p.old_price, p.description, p.ingredients, p.seo_title, p.seo_description, p.image, p.image2, p.image3, p.stock_qty, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 ORDER BY p.created_at DESC, p.id DESC');
    $products = array_map(static function (array $row): array {
        return [
            'id' => (string)$row['id'],
            'title' => $row['title'],
            'category' => $row['category_name'] ?: 'Genel',
            'weight' => $row['weight'] ?? '',
            'chip' => $row['chip'] ?? '',
            'price' => (float)$row['price'],
            'old_price' => $row['old_price'] === null ? 0 : (float)$row['old_price'],
            'description' => $row['description'] ?? '',
            'ingredients' => $row['ingredients'] ?? '',
            'seo_title' => $row['seo_title'] ?? '',
            'seo_description' => $row['seo_description'] ?? '',
            'image' => product_image_path($row['image'] ?? null),
            'image2' => product_image_path($row['image2'] ?? null),
            'image3' => product_image_path($row['image3'] ?? null),
            'stockQty' => $row['stock_qty'] === null ? null : (int)$row['stock_qty'],
        ];
    }, $stmt->fetchAll());
    echo json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('Product catalog API failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ürün kataloğu şu anda yüklenemiyor.'], JSON_UNESCAPED_UNICODE);
}

function product_image_path(?string $image): string
{
    if (!$image) return '';
    if (filter_var($image, FILTER_VALIDATE_URL) && strtolower((string)parse_url($image, PHP_URL_SCHEME)) === 'https') return $image;
    if (basename($image) !== $image) return '';
    return 'uploads/products/' . rawurlencode($image);
}
