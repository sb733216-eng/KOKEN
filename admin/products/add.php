<?php
require_once __DIR__ . '/../product_helpers.php'; require_admin();
$categories = product_categories($pdo); $error = '';
$product = ['title' => '', 'weight' => '', 'chip' => '', 'category_id' => '', 'price' => '', 'old_price' => '', 'description' => '', 'ingredients' => '', 'image' => null, 'image2' => '', 'image3' => '', 'stock_qty' => null, 'is_active' => 1];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $data = validate_product_input($_POST);
        if (!$categories || !in_array((string)$data['category_id'], array_map('strval', array_column($categories, 'id')), true)) throw new RuntimeException('Seçilen kategori bulunamadı.');
        $gallery = prepare_product_gallery([], $_FILES, $_POST);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO products (title, weight, chip, category_id, price, old_price, description, ingredients, seo_title, seo_description, image, image2, image3, is_active, stock_qty) VALUES (:title, :weight, :chip, :category_id, :price, :old_price, :description, :ingredients, :seo_title, :seo_description, :image, :image2, :image3, :is_active, :stock_qty)');
        $stmt->execute($data + $gallery['columns']);
        $newId=(int)$pdo->lastInsertId();
        if ($data['stock_qty'] !== null) record_stock_movement($pdo,$newId,$data['title'],null,(int)$data['stock_qty'],'manual_update',null,'İlk stok kaydı');
        $pdo->commit();
        flash('Ürün başarıyla eklendi.'); header('Location: ' . app_url('admin/products/index.php')); exit;
    } catch (Throwable $exception) { if($pdo->inTransaction())$pdo->rollBack(); foreach (($gallery['uploaded'] ?? []) as $uploadedImage) remove_product_image($uploadedImage); if (!($exception instanceof RuntimeException)) error_log($exception->getMessage()); $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Ürün kaydedilemedi.'; $product = array_merge($product, $_POST); $product['is_active'] = isset($_POST['is_active']) ? 1 : 0; }
}
$pageTitle = 'Ürün ekle'; $current = 'products'; $submitLabel = 'Ürünü kaydet'; require __DIR__ . '/../header.php';
?>
<div class="page-heading"><div><p class="eyebrow">KATALOG</p><h1>Yeni ürün</h1><p class="text-secondary">Kataloğunuza yeni bir ürün ekleyin.</p></div></div>
<?php require __DIR__ . '/form.php'; require __DIR__ . '/../footer.php'; ?>
