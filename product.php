<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$slug = (string)($_GET['slug'] ?? '');
if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/404.html';
    exit;
}

$projectPath = realpath(__DIR__);
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$relativePath = ($projectPath && $documentRoot && stripos($projectPath, $documentRoot) === 0) ? substr($projectPath, strlen($documentRoot)) : '';
$appBase = rtrim('/' . trim(str_replace(DIRECTORY_SEPARATOR, '/', $relativePath), '/'), '/');
$appUrl = static fn(string $path): string => $appBase . '/' . ltrim($path, '/');

$statement = $pdo->prepare('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.slug=:slug AND p.is_active=1 LIMIT 1');
$statement->execute(['slug' => $slug]);
$product = $statement->fetch();
if (!$product) {
    $redirect = $pdo->prepare('SELECT p.slug FROM product_slug_redirects r JOIN products p ON p.id=r.product_id WHERE r.slug=:slug AND p.is_active=1 LIMIT 1');
    $redirect->execute(['slug' => $slug]);
    $currentSlug = $redirect->fetchColumn();
    if (is_string($currentSlug) && $currentSlug !== '') {
        header('Location: ' . $appUrl('urun/' . rawurlencode($currentSlug)), true, 301);
        exit;
    }
}
if (!$product) {
    http_response_code(404);
    require __DIR__ . '/404.html';
    exit;
}

$escape = static fn(?string $value): string => htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$canonical = 'https://kokenfindik.com/urun/' . rawurlencode($slug);
$title = trim((string)$product['seo_title']) ?: ((string)$product['title'] . ' | Köken Fındık');
$description = trim((string)$product['seo_description']) ?: trim(preg_replace('/\s+/u', ' ', strip_tags((string)$product['description'])) ?: '');
if ($description === '') $description = (string)$product['title'] . ' ürününü Köken Fındık mağazasında inceleyin.';
$description = function_exists('mb_substr') ? mb_substr($description, 0, 320, 'UTF-8') : substr($description, 0, 320);
$imageSrcs = [];
$imageUrls = [];
foreach (['image', 'image2', 'image3'] as $imageColumn) {
    $image = (string)($product[$imageColumn] ?? '');
    if ($image !== '' && basename($image) === $image) {
        $localImage = $appUrl('uploads/products/' . rawurlencode($image));
        $imageSrcs[] = $localImage;
        $imageUrls[] = 'https://kokenfindik.com' . $localImage;
    }
}
$imageUrl = $imageUrls[0] ?? 'https://kokenfindik.com/og-image.jpg';
$badges = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string)($product['badges'] ?? '')) ?: [])));
$schema = [
    '@context' => 'https://schema.org', '@type' => 'Product',
    'name' => (string)$product['title'], 'sku' => (string)$product['id'],
    'description' => $description, 'image' => $imageUrls ?: [$imageUrl],
    'category' => (string)($product['category_name'] ?: 'Fındık'),
    'brand' => ['@type' => 'Brand', 'name' => 'Köken Fındık'],
    'offers' => ['@type' => 'Offer', 'url' => $canonical, 'priceCurrency' => 'TRY', 'price' => (string)$product['price'], 'availability' => $product['stock_qty'] !== null && (int)$product['stock_qty'] === 0 ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock', 'itemCondition' => 'https://schema.org/NewCondition'],
];
if ($badges) $schema['additionalProperty'] = array_map(static fn(string $badge): array => ['@type' => 'PropertyValue', 'name' => $badge, 'value' => true], $badges);
$schemaJson = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$addUrl = $appUrl('index.html') . '?add_to_cart=' . (int)$product['id'] . '#urunler';
$lowStockThreshold = (int)($pdo->query("SELECT setting_value FROM store_settings WHERE setting_key='low_stock_threshold'")->fetchColumn() ?: 5);
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $escape($title) ?></title><meta name="description" content="<?= $escape($description) ?>">
  <link rel="canonical" href="<?= $escape($canonical) ?>">
  <meta property="og:type" content="product"><meta property="og:site_name" content="Köken Fındık">
  <meta property="og:title" content="<?= $escape($title) ?>"><meta property="og:description" content="<?= $escape($description) ?>">
  <meta property="og:url" content="<?= $escape($canonical) ?>"><meta property="og:image" content="<?= $escape($imageUrl) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="icon" href="<?= $escape($appUrl('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $escape($appUrl('style.css')) ?>">
  <script type="application/ld+json"><?= $schemaJson ?: '{}' ?></script>
</head>
<body>
  <header class="header"><div class="container header__inner"><a class="brand" href="<?= $escape($appUrl('index.html')) ?>"><img src="<?= $escape($appUrl('logo.jpg')) ?>" alt="" width="42" height="42">Köken Fındık</a><a class="btn btn--primary" href="<?= $escape($appUrl('index.html#urunler')) ?>">Mağazaya dön</a></div></header>
  <main class="product-page container">
    <nav aria-label="İçerik yolu" class="product-breadcrumb"><a href="<?= $escape($appUrl('index.html')) ?>">Ana sayfa</a><span aria-hidden="true">›</span><a href="<?= $escape($appUrl('index.html#urunler')) ?>">Ürünler</a><span aria-hidden="true">›</span><span aria-current="page"><?= $escape((string)$product['title']) ?></span></nav>
    <article class="product-page__card">
      <div class="product-page__media<?= count($imageSrcs) > 1 ? ' product-page__media--gallery' : '' ?>">
        <?php foreach ($imageSrcs as $index => $imageSrc): ?><img src="<?= $escape($imageSrc) ?>" alt="<?= $escape((string)$product['title']) ?><?= $index ? ' - görsel ' . ($index + 1) : '' ?>" width="800" height="800" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>><?php endforeach; ?>
      </div>
      <div class="product-page__content">
        <p class="product-page__category"><?= $escape((string)($product['category_name'] ?: 'Fındık')) ?><?= $product['weight'] ? ' · ' . $escape((string)$product['weight']) : '' ?></p>
        <h1><?= $escape((string)$product['title']) ?></h1>
        <?php if (!empty($product['is_new_harvest'])): ?><p><span class="tag tag--new">Yeni Mahsul</span></p><?php endif; ?>
        <?php if ($badges): ?><ul class="badges badges--earth product-page__badges" aria-label="Ürün özellikleri"><?php foreach ($badges as $badge): ?><li><?= $escape($badge) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <p class="product-page__description"><?= nl2br($escape((string)$product['description'])) ?></p>
        <?php if (!empty($product['ingredients'])): ?><p><strong>İçindekiler:</strong> <?= $escape((string)$product['ingredients']) ?></p><?php endif; ?>
        <p class="product-page__price"><?= number_format((float)$product['price'], 2, ',', '.') ?> ₺</p>
        <?php if ($product['stock_qty'] !== null && (int)$product['stock_qty'] === 0): ?><p class="stock-tag stock-tag--out product-page__stock">Stokta yok</p><a class="btn btn--primary btn--lg" href="<?= $escape($appUrl('index.html')) ?>?view_product=<?= (int)$product['id'] ?>#urunler">Stok gelince haber ver</a>
        <?php else: ?><p class="product-page__stock">Stok durumu: <?= $product['stock_qty'] === null ? 'Stok takibi yapılmıyor' : ((int)$product['stock_qty'] <= $lowStockThreshold ? 'Stok azalıyor · Son ' . (int)$product['stock_qty'] . ' adet' : 'Stokta') ?></p><a class="btn btn--primary btn--lg" href="<?= $escape($addUrl) ?>">Sepete ekle</a><?php endif; ?>
      </div>
    </article>
  </main>
  <footer class="footer"><div class="container"><p>© <?= date('Y') ?> Köken Fındık</p></div></footer>
</body>
</html>
