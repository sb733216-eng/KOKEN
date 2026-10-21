<?php
require_once __DIR__ . '/auth.php';
require_admin();

$settings = ['free_shipping_limit' => '750', 'shipping_fee' => '60', 'low_stock_threshold' => '5'];
$rows = $pdo->query('SELECT setting_key, setting_value FROM store_settings')->fetchAll();
foreach ($rows as $row) $settings[$row['setting_key']] = $row['setting_value'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $limit = str_replace(',', '.', trim((string)($_POST['free_shipping_limit'] ?? '')));
    $fee = str_replace(',', '.', trim((string)($_POST['shipping_fee'] ?? '')));
    $lowStock = trim((string)($_POST['low_stock_threshold'] ?? ''));
    if (!is_numeric($limit) || (float)$limit < 0 || (float)$limit > 10000000) {
        $error = 'Ücretsiz kargo eşiği 0 ile 10.000.000 arasında olmalıdır.';
    } elseif (!is_numeric($fee) || (float)$fee < 0 || (float)$fee > 10000000) {
        $error = 'Kargo ücreti 0 ile 10.000.000 arasında olmalıdır.';
    } elseif (filter_var($lowStock, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]) === false) {
        $error = 'Az stok eşiği 0 ile 1.000.000 arasında tam sayı olmalıdır.';
    } else {
        $settings['free_shipping_limit'] = number_format((float)$limit, 2, '.', '');
        $settings['shipping_fee'] = number_format((float)$fee, 2, '.', '');
        $settings['low_stock_threshold'] = $lowStock;
        try {
            $pdo->beginTransaction();
            $save = $pdo->prepare('INSERT INTO store_settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($settings as $key => $value) {
                if (!in_array($key, ['free_shipping_limit', 'shipping_fee', 'low_stock_threshold'], true)) continue;
                $save->execute(['setting_key' => $key, 'setting_value' => $value]);
            }
            $pdo->commit();
            flash('Mağaza ve kargo ayarları kaydedildi.');
            header('Location: ' . app_url('admin/settings.php'));
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Store settings update failed: ' . $exception->getMessage());
            $error = 'Ayarlar kaydedilemedi. Veritabanı ayarlarını kontrol edin.';
        }
    }
}

$pageTitle = 'Mağaza ayarları';
$current = 'settings';
require __DIR__ . '/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">MAĞAZA</p><h1>Mağaza ayarları</h1><p class="text-secondary">Kargo eşiklerini ve stok uyarısı seviyesini belirleyin.</p></div></div>
<div class="content-card form-card"><div class="card-heading"><div><h2>Sipariş, kargo ve stok</h2><p>Ayarlar sepette, sipariş özetinde ve ürün listesindeki stok uyarılarında kullanılır.</p></div></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="row g-3"><div class="col-md-4"><label class="form-label" for="free_shipping_limit">Ücretsiz kargo alt limiti (₺)</label><input class="form-control" type="number" id="free_shipping_limit" name="free_shipping_limit" min="0" max="10000000" step="0.01" value="<?= e((string)$settings['free_shipping_limit']) ?>" required><small class="form-hint">Bu tutara ulaşan sepetlerde kargo ücretsiz olur.</small></div><div class="col-md-4"><label class="form-label" for="shipping_fee">Alt limit altındaki kargo ücreti (₺)</label><input class="form-control" type="number" id="shipping_fee" name="shipping_fee" min="0" max="10000000" step="0.01" value="<?= e((string)$settings['shipping_fee']) ?>" required><small class="form-hint">Ücretsiz kargo altındaki siparişlere eklenir.</small></div><div class="col-md-4"><label class="form-label" for="low_stock_threshold">Az stok uyarısı (adet)</label><input class="form-control" type="number" id="low_stock_threshold" name="low_stock_threshold" min="0" max="1000000" step="1" value="<?= e((string)$settings['low_stock_threshold']) ?>" required><small class="form-hint">Bu sayı ve altındaki stoklar uyarı verir.</small></div></div><div class="form-actions"><button class="btn btn-primary" type="submit">Ayarları kaydet</button></div></form></div>
<?php require __DIR__ . '/footer.php'; ?>
