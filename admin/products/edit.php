<?php
require_once __DIR__ . '/../product_helpers.php'; require_admin();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit('Ürün bulunamadı.'); }
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id'); $stmt->execute(['id' => $id]); $product = $stmt->fetch();
if (!$product) { http_response_code(404); exit('Ürün bulunamadı.'); }
$categories = product_categories($pdo); $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $data = validate_product_input($_POST);
        if (!in_array((string)$data['category_id'], array_map('strval', array_column($categories, 'id')), true)) throw new RuntimeException('Seçilen kategori bulunamadı.');
        $gallery = prepare_product_gallery($product, $_FILES, $_POST);
        $data = array_merge($data, $gallery['columns']);
        $data['id'] = $id;
        $update = $pdo->prepare('UPDATE products SET title=:title, weight=:weight, chip=:chip, category_id=:category_id, price=:price, old_price=:old_price, description=:description, ingredients=:ingredients, image=:image, image2=:image2, image3=:image3, is_active=:is_active, stock_qty=:stock_qty WHERE id=:id');
        $update->execute($data);
        foreach ($gallery['removed'] as $oldImage) remove_product_image($oldImage);
        flash('Ürün güncellendi.'); header('Location: ' . app_url('admin/products/index.php')); exit;
    } catch (Throwable $exception) { foreach (($gallery['uploaded'] ?? []) as $uploadedImage) remove_product_image($uploadedImage); if (!($exception instanceof RuntimeException)) error_log($exception->getMessage()); $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Ürün güncellenemedi.'; $product = array_merge($product, $_POST); $product['is_active'] = isset($_POST['is_active']) ? 1 : 0; }
}
$pageTitle = 'Ürünü düzenle'; $current = 'products'; $submitLabel = 'Değişiklikleri kaydet'; require __DIR__ . '/../header.php';
?>
<div class="page-heading"><div><p class="eyebrow">KATALOG · #<?= (int)$id ?></p><h1>Ürünü düzenle</h1><p class="text-secondary">Ürün bilgilerini güncelleyin.</p></div></div>
<?php require __DIR__ . '/form.php'; require __DIR__ . '/../footer.php'; ?>
