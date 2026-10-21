<?php
require_once __DIR__ . '/../auth.php'; require_admin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '' || (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name)) > 120) throw new RuntimeException('Kategori adı 1–120 karakter olmalıdır.');
            $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (:name)'); $stmt->execute(['name' => $name]);
            flash('Kategori eklendi.'); header('Location: ' . app_url('admin/categories/index.php')); exit;
        }
        if ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) throw new RuntimeException('Geçersiz kategori.');
            $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id'); $stmt->execute(['id' => $id]);
            flash($stmt->rowCount() ? 'Kategori silindi. Bu kategorideki ürünler kategorisiz bırakıldı.' : 'Kategori bulunamadı.', $stmt->rowCount() ? 'success' : 'warning');
            header('Location: ' . app_url('admin/categories/index.php')); exit;
        }
    } catch (PDOException $exception) {
        $error = $exception->getCode() === '23000' ? 'Bu kategori adı zaten kullanılıyor.' : 'İşlem sırasında bir hata oluştu.';
        error_log($exception->getMessage());
    } catch (RuntimeException $exception) { $error = $exception->getMessage(); }
}
$categories = $pdo->query('SELECT c.id, c.name, c.created_at, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.name')->fetchAll();
$pageTitle = 'Kategoriler'; $current = 'categories'; require __DIR__ . '/../header.php';
?>
<div class="page-heading"><div><p class="eyebrow">KATALOG</p><h1>Kategoriler</h1><p class="text-secondary">Ürünlerinizi kategorilere göre düzenleyin.</p></div></div>
<div class="row g-4"><div class="col-lg-4"><div class="content-card"><div class="card-heading"><div><h2>Yeni kategori</h2><p>Kataloğa kategori ekleyin.</p></div></div><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add"><label class="form-label" for="name">Kategori adı</label><input class="form-control mb-3" id="name" name="name" maxlength="120" required><button class="btn btn-primary" type="submit">＋ Kategori ekle</button></form></div></div><div class="col-lg-8"><div class="content-card"><div class="card-heading"><div><h2>Kategori listesi</h2><p><?= count($categories) ?> kategori</p></div></div><div class="table-responsive"><table class="table product-table align-middle"><thead><tr><th>KATEGORİ</th><th>ÜRÜN</th><th>OLUŞTURULMA</th><th></th></tr></thead><tbody><?php foreach ($categories as $category): ?><tr><td><strong><?= e($category['name']) ?></strong></td><td><?= (int)$category['product_count'] ?></td><td><?= e(date('d.m.Y', strtotime($category['created_at']))) ?></td><td class="text-end"><form method="post" onsubmit="return confirm('Kategoriyi silmek istiyor musunuz? Ürünler kategorisiz kalır.')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$category['id'] ?>"><button class="icon-button danger" title="Sil">⌫</button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div></div>
<?php require __DIR__ . '/../footer.php'; ?>
