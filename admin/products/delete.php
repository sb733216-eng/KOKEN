<?php
require_once __DIR__ . '/../product_helpers.php'; require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); exit('Geçersiz ürün.'); }
$stmt = $pdo->prepare('SELECT image,image2,image3 FROM products WHERE id = :id'); $stmt->execute(['id' => $id]); $images = $stmt->fetch() ?: [];
$delete = $pdo->prepare('DELETE FROM products WHERE id = :id'); $delete->execute(['id' => $id]);
if ($delete->rowCount()) { foreach ($images as $image) remove_product_image(is_string($image) ? $image : null); flash('Ürün silindi.'); }
else flash('Ürün bulunamadı.', 'warning');
header('Location: ' . app_url('admin/products/index.php')); exit;
