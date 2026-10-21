<?php
require_once __DIR__ . '/auth.php';

function product_categories(PDO $pdo): array
{
    return $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function save_product_image(?array $file): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Görsel yüklenemedi.');
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) throw new RuntimeException('Görsel boyutu en fazla 5 MB olabilir.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) throw new RuntimeException('Yalnızca JPG, PNG, WEBP veya GIF görsel yükleyebilirsiniz.');
    $directory = __DIR__ . '/../uploads/products';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('Görsel klasörü oluşturulamadı.');
    $filename = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) throw new RuntimeException('Görsel kaydedilemedi.');
    return $filename;
}

function remove_product_image(?string $filename): void
{
    if (!$filename || basename($filename) !== $filename) return;
    global $pdo;
    if ($pdo instanceof PDO) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM products WHERE image=:image OR image2=:image2 OR image3=:image3');
        $check->execute(['image' => $filename, 'image2' => $filename, 'image3' => $filename]);
        if ((int)$check->fetchColumn() > 0) return;
    }
    $path = __DIR__ . '/../uploads/products/' . $filename;
    if (is_file($path)) @unlink($path);
}

function prepare_product_gallery(array $current, array $files, array $input): array
{
    $slots = ['image', 'image2', 'image3'];
    $gallery = [];
    $uploaded = [];
    try {
        foreach ($slots as $slot) {
            $newFile = save_product_image($files[$slot] ?? null);
            if ($newFile !== null) $uploaded[] = $newFile;
            $gallery[$slot] = $newFile ?? ($current[$slot] ?? null);
        }
    } catch (Throwable $exception) {
        foreach ($uploaded as $filename) remove_product_image($filename);
        throw $exception;
    }
    $remove = $input['remove_images'] ?? [];
    if (!is_array($remove)) $remove = [];
    foreach ($remove as $slot) if (in_array($slot, $slots, true) && empty($files[$slot]['name'])) $gallery[$slot] = null;

    $order = array_values(array_intersect(explode(',', (string)($input['gallery_order'] ?? 'image,image2,image3')), $slots));
    $order = array_values(array_unique(array_merge($order, $slots)));
    $main = (string)($input['main_image'] ?? 'image');
    if (!in_array($main, $slots, true) || !$gallery[$main]) {
        $main = '';
        foreach ($order as $slot) if ($gallery[$slot]) { $main = $slot; break; }
    }
    $ordered = [];
    if ($main !== '') $ordered[] = $main;
    foreach ($order as $slot) if ($slot !== $main && $gallery[$slot]) $ordered[] = $slot;
    $values = array_values(array_map(static fn(string $slot) => $gallery[$slot], $ordered));
    while (count($values) < 3) $values[] = null;
    $columns = ['image' => $values[0] ?? null, 'image2' => $values[1] ?? null, 'image3' => $values[2] ?? null];
    $kept = array_filter($columns, static fn($value) => is_string($value));
    $removed = [];
    foreach ($slots as $slot) {
        $old = $current[$slot] ?? null;
        if ($old && !in_array($old, $kept, true)) $removed[] = $old;
    }
    return ['columns' => $columns, 'uploaded' => $uploaded, 'removed' => array_values(array_unique($removed))];
}

function validate_product_input(array $input): array
{
    $title = trim((string)($input['title'] ?? ''));
    $weight = trim((string)($input['weight'] ?? ''));
    $chip = trim((string)($input['chip'] ?? ''));
    $priceText = str_replace(',', '.', trim((string)($input['price'] ?? '')));
    if ($title === '' || text_length($title) > 180) throw new RuntimeException('Ürün adı zorunludur ve 180 karakteri geçemez.');
    if (!is_numeric($priceText) || (float)$priceText < 0 || (float)$priceText > 99999999.99) throw new RuntimeException('Geçerli ve sıfırdan küçük olmayan bir fiyat girin.');
    $categoryId = filter_var($input['category_id'] ?? '', FILTER_VALIDATE_INT);
    if (!$categoryId || $categoryId < 1) throw new RuntimeException('Bir kategori seçin.');
    $stockText = trim((string)($input['stock_qty'] ?? ''));
    $stockQty = null;
    if ($stockText !== '') {
        $stockQty = filter_var($stockText, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
        if ($stockQty === false) throw new RuntimeException('Stok adedi sıfır veya daha büyük bir tam sayı olmalıdır.');
    }
    $description = trim((string)($input['description'] ?? ''));
    $ingredients = trim((string)($input['ingredients'] ?? ''));
    if (text_length($description) > 10000) throw new RuntimeException('Açıklama 10.000 karakteri geçemez.');
    if (text_length($weight) > 80 || text_length($chip) > 120) throw new RuntimeException('Gramaj 80, rozet metni 120 karakteri geçemez.');
    if (text_length($ingredients) > 5000) throw new RuntimeException('İçindekiler 5.000 karakteri geçemez.');
    $oldPriceText = str_replace(',', '.', trim((string)($input['old_price'] ?? '')));
    $oldPrice = null;
    if ($oldPriceText !== '') {
        if (!is_numeric($oldPriceText) || (float)$oldPriceText < 0 || (float)$oldPriceText > 99999999.99) throw new RuntimeException('Geçerli bir eski fiyat girin.');
        if ((float)$oldPriceText > (float)$priceText) $oldPrice = number_format((float)$oldPriceText, 2, '.', '');
    }
    return ['title' => $title, 'weight' => $weight, 'chip' => $chip, 'price' => number_format((float)$priceText, 2, '.', ''), 'old_price' => $oldPrice, 'category_id' => $categoryId, 'description' => $description, 'ingredients' => $ingredients, 'stock_qty' => $stockQty, 'is_active' => isset($input['is_active']) ? 1 : 0];
}

function product_image_url(?string $image): string
{
    if (!$image) return '';
    // Legacy products can contain remote URLs until their images are re-uploaded locally.
    if (filter_var($image, FILTER_VALIDATE_URL) && strtolower((string)parse_url($image, PHP_URL_SCHEME)) === 'https') return $image;
    if (basename($image) !== $image) return '';
    return app_url('uploads/products/' . rawurlencode($image));
}
