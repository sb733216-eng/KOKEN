<?php
require_once __DIR__ . '/../auth.php'; require_admin();
$statuses = [
    'pending_confirmation' => 'WhatsApp onayı bekleniyor',
    'confirmed' => 'Onaylandı', 'preparing' => 'Hazırlanıyor', 'shipped' => 'Kargoda',
    'completed' => 'Tamamlandı', 'cancelled' => 'İptal edildi',
];
$error = '';
// Assign short tracking codes to orders created before the short-code migration.
try {
    $legacyOrders = $pdo->query('SELECT id,DATE(created_at) AS order_date FROM orders WHERE tracking_code IS NULL ORDER BY created_at,id')->fetchAll();
    if ($legacyOrders) {
        $pdo->beginTransaction();
        $lockSequence = $pdo->prepare('INSERT INTO order_code_sequences (order_date,last_value) VALUES (:date,99) ON DUPLICATE KEY UPDATE last_value=GREATEST(last_value,99)');
        $readSequence = $pdo->prepare('SELECT last_value FROM order_code_sequences WHERE order_date=:date FOR UPDATE');
        $saveSequence = $pdo->prepare('UPDATE order_code_sequences SET last_value=:value WHERE order_date=:date');
        $saveCode = $pdo->prepare('UPDATE orders SET tracking_code=:code,order_date=:date WHERE id=:id AND tracking_code IS NULL');
        foreach ($legacyOrders as $legacy) {
            $date = $legacy['order_date'];
            $lockSequence->execute(['date' => $date]);
            $readSequence->execute(['date' => $date]);
            $nextCode = (int)$readSequence->fetchColumn() + 1;
            if ($nextCode > 99999) throw new RuntimeException('Sipariş kodu sınırı aşıldı.');
            $saveSequence->execute(['value' => $nextCode, 'date' => $date]);
            $saveCode->execute(['code' => str_pad((string)$nextCode, 3, '0', STR_PAD_LEFT), 'date' => $date, 'id' => $legacy['id']]);
        }
        $pdo->commit();
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Legacy order code backfill failed: ' . $exception->getMessage());
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $newStatus = (string)($_POST['status'] ?? '');
    $action = (string)($_POST['action'] ?? 'status');
    if (!$orderId) $error = 'Sipariş bilgisi geçersiz.';
    elseif ($action === 'delete') {
        try {
            $pdo->beginTransaction();
            $get = $pdo->prepare('SELECT status FROM orders WHERE id=:id FOR UPDATE'); $get->execute(['id' => $orderId]); $oldStatus = $get->fetchColumn();
            if ($oldStatus === false) throw new RuntimeException('Sipariş bulunamadı.');
            if ($oldStatus !== 'cancelled') {
                $items = $pdo->prepare('SELECT oi.product_id,oi.quantity,p.stock_qty FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=:id FOR UPDATE');
                $items->execute(['id' => $orderId]);
                foreach ($items->fetchAll() as $item) if ($item['product_id'] !== null && $item['stock_qty'] !== null) {
                    $restore = $pdo->prepare('UPDATE products SET stock_qty=stock_qty+:qty WHERE id=:id');
                    $restore->execute(['qty' => $item['quantity'], 'id' => $item['product_id']]);
                }
            }
            $delete = $pdo->prepare('DELETE FROM orders WHERE id=:id'); $delete->execute(['id' => $orderId]);
            $pdo->commit(); flash('Sipariş silindi; takip edilen stok geri eklendi.'); header('Location: ' . app_url('admin/orders/index.php')); exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Sipariş silinemedi.';
            if (!($exception instanceof RuntimeException)) error_log('Order delete failed: ' . $exception->getMessage());
        }
    } elseif (!isset($statuses[$newStatus])) $error = 'Sipariş durumu geçersiz.';
    else {
        try {
            $pdo->beginTransaction();
            $get = $pdo->prepare('SELECT status FROM orders WHERE id = :id FOR UPDATE'); $get->execute(['id' => $orderId]); $oldStatus = $get->fetchColumn();
            if ($oldStatus === false) throw new RuntimeException('Sipariş bulunamadı.');
            if ($oldStatus !== $newStatus && $oldStatus === 'cancelled') {
                $items = $pdo->prepare('SELECT oi.product_id,oi.quantity,p.stock_qty FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=:id FOR UPDATE');
                $items->execute(['id' => $orderId]);
                foreach ($items->fetchAll() as $item) if ($item['product_id'] !== null && $item['stock_qty'] !== null) {
                    $reserve = $pdo->prepare('UPDATE products SET stock_qty=stock_qty-:qty WHERE id=:id AND stock_qty>=:qty_check'); $reserve->execute(['qty' => $item['quantity'], 'id' => $item['product_id'], 'qty_check' => $item['quantity']]);
                    if ($reserve->rowCount() !== 1) throw new RuntimeException('Sipariş yeniden açılamadı; yeterli stok yok.');
                }
            } elseif ($oldStatus !== $newStatus && $newStatus === 'cancelled') {
                $items = $pdo->prepare('SELECT oi.product_id,oi.quantity,p.stock_qty FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=:id FOR UPDATE');
                $items->execute(['id' => $orderId]);
                foreach ($items->fetchAll() as $item) if ($item['product_id'] !== null && $item['stock_qty'] !== null) {
                    $restore = $pdo->prepare('UPDATE products SET stock_qty=stock_qty+:qty WHERE id=:id'); $restore->execute(['qty' => $item['quantity'], 'id' => $item['product_id']]);
                }
            }
            $save = $pdo->prepare('UPDATE orders SET status=:status WHERE id=:id'); $save->execute(['status' => $newStatus, 'id' => $orderId]);
            $pdo->commit(); flash('Sipariş durumu güncellendi.'); header('Location: ' . app_url('admin/orders/index.php')); exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Sipariş durumu güncellenemedi.';
            if (!($exception instanceof RuntimeException)) error_log('Order status update failed: ' . $exception->getMessage());
        }
    }
}
$orders = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC,id DESC LIMIT 300')->fetchAll();
$itemQuery = $pdo->prepare('SELECT product_title,quantity,unit_price,line_total FROM order_items WHERE order_id=:id ORDER BY id');
$pageTitle = 'Siparişler'; $current = 'orders'; require __DIR__ . '/../header.php';
?>
<div class="page-heading"><div><p class="eyebrow">SATIŞ</p><h1>Siparişler</h1><p class="text-secondary">En son 300 sipariş gösterilir. Yeni siparişler WhatsApp onayı bekliyor olarak kaydedilir.</p></div></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="content-card"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>SİPARİŞ</th><th>MÜŞTERİ / TESLİMAT</th><th>ÜRÜNLER</th><th>TOPLAM</th><th>DURUM</th><th>İŞLEM</th></tr></thead><tbody>
<?php foreach ($orders as $order): $itemQuery->execute(['id' => $order['id']]); $items = $itemQuery->fetchAll(); ?>
<tr><td><strong>#<?= e($order['tracking_code'] ?? '—') ?></strong><small class="d-block text-secondary"><?= e(date('d.m.Y H:i', strtotime($order['created_at']))) ?></small></td><td><?= e($order['customer_name']) ?><small class="d-block"><a href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a></small><small class="d-block text-secondary"><?= e($order['district']) ?> / <?= e($order['city']) ?> · <?= e($order['address']) ?></small><?php if ($order['note']): ?><small class="d-block">Not: <?= e($order['note']) ?></small><?php endif; ?></td><td><?php foreach ($items as $item): ?><div><?= e($item['product_title']) ?> × <?= (int)$item['quantity'] ?> <small class="text-secondary">(₺<?= number_format((float)$item['line_total'],2,',','.') ?>)</small></div><?php endforeach; ?><small class="text-secondary"><?= $order['payment_method'] === 'iban' ? 'Havale/EFT' : 'Kapıda ödeme' ?></small></td><td>Ara toplam ₺<?= number_format((float)$order['subtotal'],2,',','.') ?><small class="d-block text-secondary">Kargo ₺<?= number_format((float)$order['shipping_fee'],2,',','.') ?></small><strong>₺<?= number_format((float)$order['total'],2,',','.') ?></strong></td><td><?= e($statuses[$order['status']] ?? $order['status']) ?></td><td><div class="d-flex gap-2"><form method="post" class="d-flex gap-2"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><select class="form-select form-select-sm" name="status"><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $order['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary" name="action" value="status">Kaydet</button></form><form method="post" onsubmit="return confirm('Bu siparişi kalıcı olarak silmek istediğinize emin misiniz?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><button class="btn btn-sm btn-danger" name="action" value="delete">Sil</button></form></div></td></tr>
<?php endforeach; ?>
<?php if (!$orders): ?><tr><td colspan="6"><div class="empty-state"><strong>Henüz sipariş yok</strong><p>Mağazadan gelen siparişler burada listelenecek.</p></div></td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../footer.php'; ?>
