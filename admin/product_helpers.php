<?php
require_once __DIR__ . '/auth.php';

function product_categories(PDO $pdo): array
{
    return $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
}

function record_stock_movement(PDO $pdo, int $productId, string $title, ?int $before, ?int $after, string $type, ?int $referenceId = null, string $note = ''): void
{
    if ($before === $after) return;
    $delta = ($after ?? 0) - ($before ?? 0);
    $stmt = $pdo->prepare('INSERT INTO stock_movements(product_id,product_title,change_qty,stock_before,stock_after,movement_type,reference_id,note) VALUES(:id,:title,:delta,:before,:after,:type,:reference,:note)');
    $stmt->execute(['id'=>$productId,'title'=>$title,'delta'=>$delta,'before'=>$before,'after'=>$after,'type'=>$type,'reference'=>$referenceId,'note'=>$note]);
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function product_slugify(string $value): string
{
    $value = trim($value);
    $ascii = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : false;
    if (is_string($ascii)) $value = $ascii;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function unique_product_slug(PDO $pdo, string $requested, ?int $excludeId = null): string
{
    $base = product_slugify($requested);
    if ($base === '') $base = 'urun';
    $base = substr($base, 0, 200);
    $candidate = $base;
    $suffix = 2;
    while (true) {
        $sql = 'SELECT id FROM products WHERE slug = :slug' . ($excludeId ? ' AND id <> :id' : '') . ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $params = ['slug' => $candidate];
        if ($excludeId) $params['id'] = $excludeId;
        $stmt->execute($params);
        if (!$stmt->fetchColumn()) return $candidate;
        $tail = '-' . $suffix++;
        $candidate = substr($base, 0, 220 - strlen($tail)) . $tail;
    }
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
            $currentImage = $current[$slot] ?? null;
            if (!is_string($currentImage) || $currentImage === '' || basename($currentImage) !== $currentImage) $currentImage = null;
            $gallery[$slot] = $newFile ?? $currentImage;
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

function validate_product_input(array $input, PDO $pdo, ?int $excludeId = null): array
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
    $seoTitle = trim((string)($input['seo_title'] ?? ''));
    $seoDescription = trim((string)($input['seo_description'] ?? ''));
    $slug = unique_product_slug($pdo, trim((string)($input['slug'] ?? '')) !== '' ? (string)$input['slug'] : $title, $excludeId);
    $badges = trim((string)($input['badges'] ?? ''));
    if (text_length($description) > 10000) throw new RuntimeException('Açıklama 10.000 karakteri geçemez.');
    if (text_length($weight) > 80 || text_length($chip) > 120) throw new RuntimeException('Gramaj 80, rozet metni 120 karakteri geçemez.');
    if (text_length($ingredients) > 5000) throw new RuntimeException('İçindekiler 5.000 karakteri geçemez.');
    if (text_length($seoTitle) > 180 || text_length($seoDescription) > 320) throw new RuntimeException('SEO başlığı 180, açıklaması 320 karakteri geçemez.');
    if (text_length($badges) > 1000) throw new RuntimeException('Ürün özellikleri toplam 1.000 karakteri geçemez.');
    foreach (preg_split('/\R/u', $badges) ?: [] as $badge) if (text_length(trim($badge)) > 100) throw new RuntimeException('Her ürün özelliği en fazla 100 karakter olabilir.');
    $oldPriceText = str_replace(',', '.', trim((string)($input['old_price'] ?? '')));
    $oldPrice = null;
    if ($oldPriceText !== '') {
        if (!is_numeric($oldPriceText) || (float)$oldPriceText < 0 || (float)$oldPriceText > 99999999.99) throw new RuntimeException('Geçerli bir eski fiyat girin.');
        if ((float)$oldPriceText > (float)$priceText) $oldPrice = number_format((float)$oldPriceText, 2, '.', '');
    }
    return ['title' => $title, 'slug' => $slug, 'badges' => $badges, 'weight' => $weight, 'chip' => $chip, 'price' => number_format((float)$priceText, 2, '.', ''), 'old_price' => $oldPrice, 'category_id' => $categoryId, 'description' => $description, 'ingredients' => $ingredients, 'seo_title'=>$seoTitle, 'seo_description'=>$seoDescription, 'stock_qty' => $stockQty, 'is_new_harvest' => isset($input['is_new_harvest']) ? 1 : 0, 'is_active' => isset($input['is_active']) ? 1 : 0];
}

function product_image_url(?string $image): string
{
    if (!$image) return '';
    if (basename($image) !== $image) return '';
    return app_url('uploads/products/' . rawurlencode($image));
}
