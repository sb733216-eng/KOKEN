<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/storefront.php';

function product_not_found(): never
{
    http_response_code(404);
    header('X-Robots-Tag: noindex');
    $html = file_get_contents(__DIR__ . '/404.html');
    echo str_replace('<head>', '<head><base href="' . store_escape(store_path()) . '">', $html ?: 'Ürün bulunamadı.');
    exit;
}

$slug = $_GET['slug'] ?? '';
if (!is_string($slug) || strlen($slug) > 220 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) product_not_found();
$initialBufferLevel = ob_get_level();
try {
    $query = $pdo->prepare('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = :slug AND p.is_active = 1 LIMIT 1');
    $query->execute(['slug' => $slug]);
    $row = $query->fetch();
    if (!$row) {
        $redirect = $pdo->prepare('SELECT p.slug FROM product_slug_redirects r JOIN products p ON p.id = r.product_id WHERE r.slug = :slug AND p.is_active = 1 LIMIT 1');
        $redirect->execute(['slug' => $slug]);
        $currentSlug = $redirect->fetchColumn();
        if ($currentSlug && $currentSlug !== $slug) {
            header('Location: ' . store_path('urun/' . rawurlencode((string)$currentSlug) . '.html'), true, 301);
            exit;
        }
        product_not_found();
    }
    $product = store_product_data($row);
    if (parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) !== $product['url']) {
        header('Location: ' . $product['url'], true, 301);
        exit;
    }
    $catalog = array_map('store_product_data', $pdo->query('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 ORDER BY p.created_at DESC, p.id DESC')->fetchAll());
    $html = file_get_contents(__DIR__ . '/index.html');
    if ($html === false) throw new RuntimeException('Storefront template unavailable.');
    $attribute = static function (string $tag, string $name): string {
        preg_match('~\b' . preg_quote($name, '~') . '\s*=\s*"([^"]*)"~i', $tag, $match);
        return html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    };
    preg_match('~<title\b[^>]*>(.*?)</title>~is', $html, $homeTitle);
    $home = ['path' => store_path(), 'title' => html_entity_decode($homeTitle[1] ?? 'Köken Fındık', ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'metadata' => []];
    preg_match_all('~<meta\b[^>]*>~i', $html, $metaTags);
    foreach ($metaTags[0] as $tag) {
        $name = $attribute($tag, 'name') ?: $attribute($tag, 'property');
        if ($name !== '') $home['metadata'][$name] = $attribute($tag, 'content');
    }
    $home['metadata']['canonical'] = store_absolute_url();
    $home['metadata']['og:url'] = store_absolute_url();
    $title = trim((string)$product['seo_title']) ?: $product['title'] . ' | Köken Fındık';
    $description = store_seo_description($product);
    $images = array_values(array_unique(array_filter([$product['image'], $product['image2'], $product['image3']])));
    $unavailable = $product['stockQty'] === 0;
    $discount = $product['old_price'] > $product['price'] ? (int)round((1 - $product['price'] / $product['old_price']) * 100) : 0;
    $price = static fn(float $value): string => rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') . ' TL';
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;
    $schema = [
        '@context' => 'https://schema.org', '@type' => 'Product', '@id' => $product['canonical_url'] . '#product',
        'name' => $product['title'], 'sku' => $product['id'], 'url' => $product['canonical_url'],
        'description' => $product['description'] ?: $description,
        'image' => array_map('store_absolute_url', $images),
        'brand' => ['@type' => 'Brand', 'name' => 'Köken Fındık'], 'category' => $product['category'],
        'offers' => ['@type' => 'Offer', 'url' => $product['canonical_url'], 'priceCurrency' => 'TRY',
            'price' => number_format($product['price'], 2, '.', ''),
            'availability' => $unavailable ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
            'itemCondition' => 'https://schema.org/NewCondition'],
    ];
    $replace = static function (string $pattern, string $replacement) use (&$html): void {
        $html = preg_replace_callback($pattern, static fn() => $replacement, $html, 1, $count);
        if ($html === null || $count !== 1) throw new RuntimeException('Storefront template section missing: ' . $pattern);
    };
    $replace('~<head>~i', '<head><base href="' . store_escape(store_path()) . '">');
    $replace('~<title\b[^>]*>.*?</title>~is', '<title>' . store_escape($title) . '</title>');
    $replace('~<link\b[^>]*rel="canonical"[^>]*>~i', '<link rel="canonical" href="' . store_escape($product['canonical_url']) . '">');
    $socialImage = store_absolute_url($images[0] ?? 'og-image.jpg');
    $metadata = ['description' => $description, 'og:type' => 'product', 'og:url' => $product['canonical_url'], 'og:title' => $title, 'og:description' => $description, 'og:image' => $socialImage, 'og:image:secure_url' => $socialImage, 'og:image:alt' => $product['title'], 'twitter:title' => $title, 'twitter:description' => $description, 'twitter:image' => $socialImage];
    foreach ($metadata as $name => $value) {
        $kind = str_starts_with($name, 'og:') ? 'property' : 'name';
        $replace('~<meta\b(?=[^>]*' . $kind . '="' . preg_quote($name, '~') . '")[^>]*>~i', '<meta ' . $kind . '="' . $name . '" content="' . store_escape($value) . '">');
    }
    $html = preg_replace('~<meta\b[^>]*property="og:image:(?:width|height|type)"[^>]*>\s*~i', '', $html);
    $html = preg_replace('~<link\b[^>]*rel="preload"[^>]*href="hero\.jpg"[^>]*>\s*~i', '', $html);
    $replace('~<script\b[^>]*id="ldProducts"[^>]*>.*?</script>~is', '<script type="application/ld+json" id="ldProducts">' . json_encode($schema, $jsonFlags) . '</script>');
    ob_start();
    require __DIR__ . '/views/product-modal.php';
    $modal = (string)ob_get_clean();
    $replace('~<!-- ÜRÜN DETAY -->.*?<!-- YASAL METİNLER -->~s', '<!-- ÜRÜN DETAY -->' . $modal . '<!-- YASAL METİNLER -->');
    $replace('~<body>~i', '<body class="overlay-open no-scroll">');
    $bootstrap = ['product' => $product, 'catalog' => $catalog, 'home' => $home];
    $replace('~</body>~i', '<script type="application/json" id="productPageData">' . json_encode($bootstrap, $jsonFlags) . '</script></body>');
} catch (Throwable $exception) {
    while (ob_get_level() > $initialBufferLevel) ob_end_clean();
    error_log('Product page failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Ürün bilgileri şu anda alınamıyor.');
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
echo $html;
