<?php
$product = $product ?? [
    'title' => '', 'weight' => '', 'chip' => '', 'category_id' => '', 'price' => '',
    'old_price' => '', 'description' => '', 'ingredients' => '', 'image' => null,
    'image2' => '', 'image3' => '', 'stock_qty' => null, 'is_active' => 1,
];
?>
<div class="content-card form-card">
  <div class="card-heading"><div><h2>Ürün bilgileri</h2><p>Vitrinde gösterilecek ürün bilgilerini girin.</p></div></div>
  <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="mb-3"><label class="form-label" for="title">Ürün adı <span class="required">*</span></label><input class="form-control" id="title" name="title" maxlength="180" value="<?= e((string)$product['title']) ?>" required></div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="weight">Gramaj</label><input class="form-control" id="weight" name="weight" maxlength="80" value="<?= e((string)($product['weight'] ?? '')) ?>" placeholder="500g"></div>
          <div class="col-md-6"><label class="form-label" for="chip">Kart etiketi</label><input class="form-control" id="chip" name="chip" maxlength="120" value="<?= e((string)($product['chip'] ?? '')) ?>" placeholder="Avantaj paketi"></div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-md-6"><label class="form-label" for="category_id">Kategori <span class="required">*</span></label><select class="form-select" id="category_id" name="category_id" required><option value="">Kategori seçin</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (string)$product['category_id'] === (string)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label" for="price">Satış fiyatı (₺) <span class="required">*</span></label><input class="form-control" id="price" name="price" type="number" min="0" step="0.01" value="<?= e((string)$product['price']) ?>" required></div>
          <div class="col-md-6"><label class="form-label" for="old_price">Eski fiyat (₺)</label><input class="form-control" id="old_price" name="old_price" type="number" min="0" step="0.01" value="<?= e((string)($product['old_price'] ?? '')) ?>"><small class="form-hint">İndirim etiketi için satış fiyatından yüksek olmalı.</small></div>
          <div class="col-md-6"><label class="form-label" for="stock_qty">Stok adedi</label><input class="form-control" id="stock_qty" name="stock_qty" type="number" min="0" step="1" value="<?= e((string)($product['stock_qty'] ?? '')) ?>" placeholder="Boş bırakılırsa takip edilmez"><small class="form-hint">Az stok uyarısı için mağaza ayarlarında eşik belirleyin.</small></div>
        </div>
        <div class="mt-3"><label class="form-label" for="description">Açıklama</label><textarea class="form-control" id="description" name="description" rows="5" maxlength="10000"><?= e((string)$product['description']) ?></textarea></div>
        <div class="mt-3"><label class="form-label" for="ingredients">İçindekiler</label><textarea class="form-control" id="ingredients" name="ingredients" rows="2" maxlength="5000"><?= e((string)($product['ingredients'] ?? '')) ?></textarea></div>
      </div>
      <div class="col-lg-4">
        <label class="form-label">Ürün galerisi</label><p class="form-hint">Görselleri oklarla sıralayın; ana görsel mağaza kartında kullanılır. En fazla 3 görsel.</p>
        <input type="hidden" name="gallery_order" id="gallery_order" value="image,image2,image3">
        <div id="product-gallery">
        <?php foreach (['image' => 'Görsel 1', 'image2' => 'Görsel 2', 'image3' => 'Görsel 3'] as $slot => $slotLabel): ?>
          <div class="content-card p-3 mb-2" data-gallery-slot="<?= e($slot) ?>">
            <div class="d-flex justify-content-between align-items-center"><strong><?= e($slotLabel) ?></strong><div class="d-flex gap-1"><button class="btn btn-sm btn-outline-secondary" type="button" data-gallery-move="up" aria-label="Yukarı taşı">↑</button><button class="btn btn-sm btn-outline-secondary" type="button" data-gallery-move="down" aria-label="Aşağı taşı">↓</button></div></div>
            <?php if (!empty($product[$slot])): ?><img class="image-current" src="<?= e(product_image_url((string)$product[$slot])) ?>" alt="Mevcut <?= e($slotLabel) ?>"><?php endif; ?>
            <input class="form-control mt-2" type="file" id="<?= e($slot) ?>" name="<?= e($slot) ?>" accept="image/jpeg,image/png,image/webp,image/gif">
            <div class="form-check mt-2"><input class="form-check-input" type="radio" name="main_image" id="main_<?= e($slot) ?>" value="<?= e($slot) ?>" <?= $slot === 'image' ? 'checked' : '' ?>><label class="form-check-label" for="main_<?= e($slot) ?>">Ana görsel yap</label></div>
            <?php if (!empty($product[$slot])): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="remove_images[]" id="remove_<?= e($slot) ?>" value="<?= e($slot) ?>"><label class="form-check-label" for="remove_<?= e($slot) ?>">Bu görseli kaldır</label></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
        </div>
        <div class="form-check form-switch active-switch mt-4"><input class="form-check-input" type="checkbox" role="switch" name="is_active" id="is_active" value="1" <?= !empty($product['is_active']) ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Ürün aktif</label></div><small class="form-hint">Aktif ürünler mağazada görünür.</small>
      </div>
    </div>
    <div class="form-actions"><a class="btn btn-light" href="<?= e(app_url('admin/products/index.php')) ?>">Vazgeç</a><button class="btn btn-primary" type="submit"><?= e($submitLabel) ?></button></div>
  </form>
</div>
<script>
(() => {
  const gallery = document.getElementById('product-gallery');
  const order = document.getElementById('gallery_order');
  const syncOrder = (selectFirst = false) => {
    const cards = [...gallery.children];
    if (selectFirst && cards[0]) cards[0].querySelector('input[name="main_image"]').checked = true;
    order.value = cards.map((card) => card.dataset.gallerySlot).join(',');
  };
  gallery.addEventListener('click', (event) => {
    const button = event.target.closest('[data-gallery-move]');
    if (!button) return;
    const card = button.closest('[data-gallery-slot]');
    if (button.dataset.galleryMove === 'up' && card.previousElementSibling) gallery.insertBefore(card, card.previousElementSibling);
    if (button.dataset.galleryMove === 'down' && card.nextElementSibling) gallery.insertBefore(card.nextElementSibling, card);
    syncOrder(true);
  });
  gallery.addEventListener('change', (event) => {
    if (!event.target.matches('input[name="main_image"]')) return;
    const card = event.target.closest('[data-gallery-slot]');
    gallery.insertBefore(card, gallery.firstElementChild);
    syncOrder(true);
  });
  syncOrder();
})();
</script>
