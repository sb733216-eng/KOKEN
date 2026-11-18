<?php
declare(strict_types=1);

function store_base_path(): string
{
    static $base;
    if (isset($base)) return $base;
    $project = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');
    $root = rtrim(str_replace('\\', '/', realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: ''), '/');
    $base = $root !== '' && strncasecmp($project, $root . '/', strlen($root) + 1) === 0
        ? '/' . trim(substr($project, strlen($root)), '/') : '';
    return $base;
}

function store_path(string $path = ''): string
{
    return store_base_path() . '/' . ltrim($path, '/');
}

function store_origin(): string
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if (preg_match('/^(localhost|127\.0\.0\.1)(:[0-9]+)?$/D', $host)
        && in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true)) {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $host;
    }
    return 'https://kokenfindik.com';
}

function store_absolute_url(string $path = ''): string
{
    return store_origin() . store_path($path);
}

function store_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function store_product_data(array $row): array
{
    $image = static fn(mixed $value): string => is_string($value) && $value !== '' && basename($value) === $value
        ? 'uploads/products/' . rawurlencode($value) : '';
    return [
        'id' => (string)$row['id'], 'slug' => (string)$row['slug'],
        'url' => store_path('urun/' . rawurlencode((string)$row['slug']) . '.html'),
        'canonical_url' => store_absolute_url('urun/' . rawurlencode((string)$row['slug']) . '.html'),
        'title' => (string)$row['title'],
        'badges' => array_values(array_filter(array_map('trim', preg_split('/\R/u', (string)($row['badges'] ?? '')) ?: []))),
        'category' => $row['category_name'] ?: 'Genel',
        'weight' => $row['weight'] ?? '', 'chip' => $row['chip'] ?? '',
        'price' => (float)$row['price'],
        'old_price' => $row['old_price'] === null ? 0 : (float)$row['old_price'],
        'description' => $row['description'] ?? '', 'ingredients' => $row['ingredients'] ?? '',
        'seo_title' => $row['seo_title'] ?? '', 'seo_description' => $row['seo_description'] ?? '',
        'image' => $image($row['image'] ?? null),
        'image2' => $image($row['image2'] ?? null), 'image3' => $image($row['image3'] ?? null),
        'stockQty' => $row['stock_qty'] === null ? null : (int)$row['stock_qty'],
        'newHarvest' => (bool)$row['is_new_harvest'],
    ];
}

function store_seo_description(array $product): string
{
    $text = trim((string)($product['seo_description'] ?: $product['description'] ?: $product['title'] . ' — Köken Fındık'));
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}
