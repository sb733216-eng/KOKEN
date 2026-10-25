<?php
require_once __DIR__ . '/../product_helpers.php';
require_admin();

$lowStockThreshold = (int)($pdo->query("SELECT setting_value FROM store_settings WHERE setting_key = 'low_stock_threshold'")->fetchColumn() ?: 5);
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$search = trim((string)($_GET['q'] ?? ''));
$categoryFilter = filter_var($_GET['category'] ?? '', FILTER_VALIDATE_INT);
$stockFilter = (string)($_GET['stock'] ?? '');
$statusFilter = (string)($_GET['status'] ?? '');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(p.title LIKE :title_search OR CAST(p.id AS CHAR) = :id_search)';
    $params['title_search'] = '%' . (function_exists('mb_substr') ? mb_substr($search, 0, 100, 'UTF-8') : substr($search, 0, 100)) . '%';
    $params['id_search'] = preg_replace('/\D+/', '', $search);
}
if ($categoryFilter !== false && $categoryFilter !== null && $categoryFilter > 0) {
    $where[] = 'p.category_id = :category_id';
    $params['category_id'] = $categoryFilter;
}
if ($stockFilter === 'out') {
    $where[] = 'p.stock_qty = 0';
} elseif ($stockFilter === 'low') {
    $where[] = 'p.stock_qty > 0 AND p.stock_qty <= :low_threshold';
    $params['low_threshold'] = $lowStockThreshold;
} elseif ($stockFilter === 'available') {
    $where[] = 'p.stock_qty > :available_threshold';
    $params['available_threshold'] = $lowStockThreshold;
} elseif ($stockFilter === 'untracked') {
    $where[] = 'p.stock_qty IS NULL';
}
if ($statusFilter === 'active' || $statusFilter === 'inactive') {
    $where[] = 'p.is_active = :is_active';
    $params['is_active'] = $statusFilter === 'active' ? 1 : 0;
}

$sql = 'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY p.created_at DESC, p.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

$pageTitle = 'Ürünler';
$current = 'products';
require __DIR__ . '/../header.php';
?>
<div class="page-heading products-heading">
  <div><h1>Ürünler</h1><p class="text-secondary">Mağazanızdaki ürünleri görüntüleyin ve yönetin.</p></div>
  <div class="products-heading-actions">
    <a class="btn btn-outline-secondary" href="<?= e(app_url('admin/products/export.php')) ?>">CSV dışa aktar</a>
    <a class="btn btn-primary products-add-button" href="<?= e(app_url('admin/products/add.php')) ?>">＋ Ürün ekle</a>
  </div>
</div>
<div class="content-card products-card">
  <form class="products-filters" method="get" action="<?= e(app_url('admin/products/index.php')) ?>">
    <div class="products-search">
      <label for="product-search">Ürün adı veya kodu ara</label>
      <input class="form-control" id="product-search" type="search" name="q" value="<?= e($search) ?>" placeholder="Örn. Fındık ezmesi veya 125">
    </div>
    <div class="products-filter-field">
      <label for="category-filter">Kategori</label>
      <select class="form-select" id="category-filter" name="category">
        <option value="">Tüm kategoriler</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int)$category['id'] ?>" <?= (string)$categoryFilter === (string)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="products-filter-field">
      <label for="stock-filter">Stok durumu</label>
      <select class="form-select" id="stock-filter" name="stock">
        <option value="">Tüm stoklar</option>
        <option value="available" <?= $stockFilter === 'available' ? 'selected' : '' ?>>Stokta</option>
        <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>Az stok</option>
        <option value="out" <?= $stockFilter === 'out' ? 'selected' : '' ?>>Tükendi</option>
        <option value="untracked" <?= $stockFilter === 'untracked' ? 'selected' : '' ?>>Stok takibi kapalı</option>
      </select>
    </div>
    <div class="products-filter-field">
      <label for="status-filter">Ürün durumu</label>
      <select class="form-select" id="status-filter" name="status">
        <option value="">Aktif ve pasif</option>
        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktif</option>
        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Pasif</option>
      </select>
    </div>
    <div class="products-filter-actions"><button class="btn btn-primary" type="submit">Filtrele</button><a class="btn btn-light" href="<?= e(app_url('admin/products/index.php')) ?>">Temizle</a></div>
    <p class="products-result-count" aria-live="polite"><strong><?= count($products) ?></strong> ürün gösteriliyor <span>· Toplam <?= $totalProducts ?> ürün</span></p>
  </form>
  <div class="table-responsive">
    <table class="table product-table products-table align-middle">
      <thead><tr><th>ÜRÜN</th><th>KATEGORİ</th><th>FİYAT</th><th>STOK</th><th>DURUM</th><th class="text-end">İŞLEMLER</th></tr></thead>
      <tbody>
      <?php foreach ($products as $product): ?>
        <tr>
          <td><div class="product-cell">
            <?php if ($product['image']): ?><img src="<?= e(product_image_url((string)$product['image'])) ?>" alt=""><?php else: ?><span class="product-placeholder" aria-hidden="true">◇</span><?php endif; ?>
            <div class="product-meta"><strong title="<?= e($product['title']) ?>"><?= e($product['title']) ?></strong><small>#<?= (int)$product['id'] ?> <span aria-hidden="true">·</span> <?= e(date('d.m.Y', strtotime($product['created_at']))) ?></small></div>
          </div></td>
          <td class="product-category"><?= e($product['category_name'] ?? 'Kategorisiz') ?></td>
          <td class="price-cell">₺<?= number_format((float)$product['price'], 2, ',', '.') ?></td>
          <td>
            <?php if ($product['stock_qty'] === null): ?><span class="stock-badge stock-untracked">Stok takibi kapalı</span>
            <?php elseif ((int)$product['stock_qty'] === 0): ?><span class="stock-badge stock-out">Tükendi</span>
            <?php elseif ((int)$product['stock_qty'] <= $lowStockThreshold): ?><span class="stock-badge stock-low">Az stok: <?= (int)$product['stock_qty'] ?></span>
            <?php else: ?><span class="stock-badge stock-available">Stokta: <?= (int)$product['stock_qty'] ?></span><?php endif; ?>
          </td>
          <td><span class="status-badge <?= $product['is_active'] ? 'is-active' : 'is-inactive' ?>"><i></i><?= $product['is_active'] ? 'Aktif' : 'Pasif' ?></span></td>
          <td><div class="product-actions">
            <a class="btn btn-sm btn-outline-success edit-product-button" href="<?= e(app_url('admin/products/edit.php?id=' . (int)$product['id'])) ?>">Düzenle</a>
            <details class="product-action-menu">
              <summary aria-label="<?= e($product['title']) ?> için diğer işlemler" title="Diğer işlemler">⋯</summary>
              <div class="product-action-menu-panel"><form method="post" action="<?= e(app_url('admin/products/delete.php')) ?>" onsubmit="return confirm('Bu ürünü silmek istediğinize emin misiniz?')">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                <button class="product-delete-action" type="submit">Ürünü sil</button>
              </form></div>
            </details>
          </div></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?>
        <tr><td colspan="6"><div class="empty-state"><span>◇</span><strong><?= $totalProducts ? 'Aramanızla eşleşen ürün yok' : 'Henüz ürün yok' ?></strong><p><?= $totalProducts ? 'Filtreleri değiştirebilir veya temizleyebilirsiniz.' : 'İlk ürününüzü ekleyerek kataloğunuzu oluşturmaya başlayın.' ?></p><?php if (!$totalProducts): ?><a class="btn btn-primary" href="<?= e(app_url('admin/products/add.php')) ?>">Ürün ekle</a><?php else: ?><a class="btn btn-light" href="<?= e(app_url('admin/products/index.php')) ?>">Filtreleri temizle</a><?php endif; ?></div></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../footer.php'; ?>