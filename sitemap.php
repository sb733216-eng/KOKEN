<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/storefront.php';
try {
    $slugs = $pdo->query('SELECT slug FROM products WHERE is_active = 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $exception) {
    error_log('Sitemap failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Site haritası şu anda oluşturulamıyor.');
}
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
$paths = ['', 'hakkimizda.html', 'uretim-tesisimiz.html', 'teslimat-ve-kargo.html', 'iptal-ve-iade.html', 'mesafeli-satis-sozlesmesi.html', 'kvkk.html', 'cerez-politikasi.html'];
foreach ($slugs as $slug) $paths[] = 'urun/' . rawurlencode((string)$slug) . '.html';
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($paths as $path) {
    echo '  <url><loc>' . htmlspecialchars(store_absolute_url($path), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>' . "\n";
}
echo '</urlset>';
