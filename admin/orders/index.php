<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../../config/mailer.php';
require_once __DIR__ . '/../../config/order_helpers.php';

$statuses = order_status_labels();
$paymentStatuses = order_payment_status_labels();
$shortStatusLabels = $statuses;
$shortPaymentLabels = $paymentStatuses;
$shortPaymentLabels['paid'] = 'Ödendi';
$shortPaymentLabels['partially_refunded'] = 'Kısmi iade';
$shortPaymentLabels['refunded'] = 'İade kaydı var';
$mailHealth = order_mail_health();
$error = '';
$success = '';

function record_order_change(PDO $pdo, int $orderId, string $field, ?string $before, ?string $after): void
{
    if ($before === $after) return;
    $statement = $pdo->prepare('INSERT INTO order_status_history (order_id,field_name,previous_value,new_value,changed_by) VALUES (:order_id,:field,:previous,:next,:admin)');
    $statement->execute(['order_id'=>$orderId,'field'=>$field,'previous'=>$before,'next'=>$after,'admin'=>$_SESSION['admin_id'] ?? null]);
}

function order_money(float|int|string $amount): string
{
    return '₺' . number_format((float)$amount, 2, ',', '.');
}

function order_redirect_after_post(int $orderId = 0): never
{
    $raw = (string)($_POST['return_query'] ?? '');
    parse_str($raw, $parsed);
    $allowed = ['q','status','payment_status','from','to','sort','direction','per_page','page'];
    $safe = [];
    foreach ($allowed as $key) {
        if (isset($parsed[$key]) && is_scalar($parsed[$key])) $safe[$key] = (string)$parsed[$key];
    }
    if ($orderId > 0) $safe['open'] = $orderId;
    $url = app_url('admin/orders/index.php') . ($safe ? '?' . http_build_query($safe) : '');
    if ($orderId > 0) $url .= '#order-' . $orderId;
    header('Location: ' . $url);
    exit;
}

function order_safe_ids(mixed $value): array
{
    if (!is_array($value)) return [];
    $ids = [];
    foreach (array_slice($value, 0, 500) as $candidate) {
        if (!is_scalar($candidate)) continue;
        $id = filter_var($candidate, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($id !== false) $ids[] = (int)$id;
    }
    return array_values(array_unique($ids));
}

function order_filters_from_request(array $source, array $statuses, array $paymentStatuses): array
{
    $q = isset($source['q']) && is_string($source['q']) ? trim($source['q']) : '';
    if (function_exists('mb_substr')) $q = mb_substr($q, 0, 120, 'UTF-8'); else $q = substr($q, 0, 120);
    $rawStatus = order_status_key(isset($source['status']) && is_string($source['status']) ? $source['status'] : '');
    $rawPayment = isset($source['payment_status']) && is_string($source['payment_status']) ? $source['payment_status'] : '';
    $status = isset($statuses[$rawStatus]) ? $rawStatus : '';
    $payment = isset($paymentStatuses[$rawPayment]) ? $rawPayment : '';
    $date = static function (mixed $value): string {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return '';
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $parsed && $parsed->format('Y-m-d') === $value ? $value : '';
    };
    $sort = in_array(($source['sort'] ?? ''), ['created_at','total'], true) ? (string)$source['sort'] : 'created_at';
    $direction = in_array(strtolower((string)($source['direction'] ?? '')), ['asc','desc'], true) ? strtolower((string)$source['direction']) : 'desc';
    $perPage = filter_var($source['per_page'] ?? 20, FILTER_VALIDATE_INT);
    if (!in_array($perPage, [10,20,50,100], true)) $perPage = 20;
    $page = filter_var($source['page'] ?? 1, FILTER_VALIDATE_INT);
    if (!$page || $page < 1) $page = 1;
    return ['q'=>$q,'status'=>$status,'payment_status'=>$payment,'from'=>$date($source['from'] ?? ''),'to'=>$date($source['to'] ?? ''),'sort'=>$sort,'direction'=>$direction,'per_page'=>$perPage,'page'=>$page];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $action = (string)($_POST['action'] ?? 'save');
    $validActions = ['save','email_status','email_retry','add_note','soft_delete'];
    if (!$orderId || !in_array($action, $validActions, true)) {
        $error = 'Sipariş işlemi geçersiz.';
    } elseif ($action === 'soft_delete') {
        try {
            $pdo->beginTransaction();
            $orderQuery = $pdo->prepare('SELECT id,order_number,status,deleted_at FROM orders WHERE id=:id FOR UPDATE');
            $orderQuery->execute(['id'=>$orderId]);
            $order = $orderQuery->fetch(PDO::FETCH_ASSOC);
            if (!$order || $order['deleted_at'] !== null) throw new RuntimeException('Sipariş bulunamadı veya daha önce silinmiş.');

            // İptal durumuna geçiş mevcut stok geri koyma kuralını çalıştırır.
            // Sipariş zaten iptalse aynı stok ikinci kez artırılmaz.
            if ($order['status'] !== 'cancelled') {
                $items = $pdo->prepare('SELECT oi.product_id,oi.product_title,oi.quantity,p.stock_qty FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=:id FOR UPDATE');
                $items->execute(['id'=>$orderId]);
                $stockReturns = [];
                foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
                    if ($item['product_id'] === null || $item['stock_qty'] === null) continue;
                    $productId = (int)$item['product_id'];
                    if (!isset($stockReturns[$productId])) $stockReturns[$productId] = ['title'=>(string)$item['product_title'],'quantity'=>0,'before'=>(int)$item['stock_qty']];
                    $stockReturns[$productId]['quantity'] += (int)$item['quantity'];
                }
                foreach ($stockReturns as $productId=>$stockReturn) {
                    $before = $stockReturn['before'];
                    $quantity = $stockReturn['quantity'];
                    $pdo->prepare('UPDATE products SET stock_qty=stock_qty+:quantity WHERE id=:id')->execute(['quantity'=>$quantity,'id'=>$productId]);
                    $pdo->prepare("INSERT INTO stock_movements (product_id,product_title,change_qty,stock_before,stock_after,movement_type,reference_id,note) VALUES (:product_id,:title,:change,:before,:after,'order_restore',:reference,:note)")->execute(['product_id'=>$productId,'title'=>$stockReturn['title'],'change'=>$quantity,'before'=>$before,'after'=>$before+$quantity,'reference'=>$orderId,'note'=>'Sipariş silindi: ayrılmış stok bir kez geri eklendi']);
                }
            }

            $adminId = filter_var($_SESSION['admin_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if ($adminId === false) throw new RuntimeException('Yönetici oturumu doğrulanamadı. Yeniden giriş yapın.');
            $deletedAt = date('Y-m-d H:i:s');
            if ($order['status'] !== 'cancelled') record_order_change($pdo, (int)$orderId, 'status', (string)$order['status'], 'cancelled');
            record_order_change($pdo, (int)$orderId, 'deleted_at', null, $deletedAt);
            $deleteStatement = $pdo->prepare("UPDATE orders SET status='cancelled',deleted_at=:deleted_at,deleted_by=:admin WHERE id=:id AND deleted_at IS NULL");
            $deleteStatement->execute(['deleted_at'=>$deletedAt,'admin'=>$adminId,'id'=>$orderId]);
            if ($deleteStatement->rowCount() !== 1) throw new RuntimeException('Sipariş zaten silinmiş veya güncellenmiş.');
            $pdo->commit();
            flash('Sipariş listeden kaldırıldı. Müşteriye bildirim gönderilmedi ve para iadesi başlatılmadı.');
            order_redirect_after_post();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Sipariş silinemedi.';
            if (!($exception instanceof RuntimeException)) error_log('Order soft delete failed: ' . $exception->getMessage());
        }
    } elseif ($action === 'email_retry') {
        $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
        try {
            if (!$mailHealth['ready']) throw new RuntimeException('E-posta ayarları tamamlanmadan yeniden gönderim yapılamaz.');
            $check = $pdo->prepare("SELECT id,status FROM order_email_notifications WHERE id=:notification AND order_id=:order_id AND status IN ('failed','pending')");
            $check->execute(['notification'=>$notificationId,'order_id'=>$orderId]);
            if (!$check->fetch()) throw new RuntimeException('Yalnızca bekleyen veya başarısız e-posta bildirimi gönderilebilir.');
            $result = dispatch_order_email_notification($pdo, (int)$notificationId);
            flash($result['status'] === 'sent' ? 'E-posta sunucusuna iletildi. Alıcı posta kutusuna teslimat doğrulanmıyor.' : 'E-posta gönderilemedi (' . ($result['error'] ?? 'smtp_failure') . '). Ayarları kontrol edip yeniden deneyin.', $result['status'] === 'sent' ? 'success' : 'warning');
            order_redirect_after_post((int)$orderId);
        } catch (Throwable $exception) {
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'E-posta yeniden gönderilemedi.';
            if (!($exception instanceof RuntimeException)) error_log('Order email retry action failed: ' . $exception->getMessage());
        }
    } elseif ($action === 'add_note') {
        $note = trim((string)($_POST['internal_note'] ?? ''));
        $requestKey = trim((string)($_POST['note_request_key'] ?? ''));
        $length = function_exists('mb_strlen') ? mb_strlen($note, 'UTF-8') : strlen($note);
        if (!preg_match('/^[a-f0-9]{64}$/', $requestKey)) $error = 'Not isteği geçersiz; sayfayı yenileyin.';
        elseif ($length < 2 || $length > 3000) $error = 'Personel notu 2 ile 3.000 karakter arasında olmalıdır.';
        else try {
            $pdo->beginTransaction();
            $insert = $pdo->prepare('INSERT INTO order_internal_notes (order_id,admin_id,request_key,note) VALUES (:order_id,:admin,:request_key,:note)');
            $insert->execute(['order_id'=>$orderId,'admin'=>$_SESSION['admin_id'] ?? null,'request_key'=>$requestKey,'note'=>$note]);
            record_order_change($pdo, (int)$orderId, 'internal_note', null, 'Personel notu eklendi');
            $pdo->commit();
            flash('Personel notu eklendi.');
            order_redirect_after_post((int)$orderId);
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((string)$exception->getCode() === '23000') { flash('Bu not isteği daha önce kaydedilmiş.'); order_redirect_after_post((int)$orderId); }
            error_log('Order internal note failed: ' . $exception->getMessage());
            $error = 'Personel notu kaydedilemedi.';
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Order internal note audit failed: ' . $exception->getMessage());
            $error = 'Personel notu kaydedilemedi.';
        }
    } else {
        $nonce = trim((string)($_POST['email_nonce'] ?? ''));
        if ($action === 'email_status' && !$mailHealth['ready']) $error = 'E-posta ayarları tamamlanmadan bildirim gönderilemez.';
        elseif ($action === 'email_status' && !preg_match('/^[a-f0-9]{64}$/', $nonce)) $error = 'E-posta isteği geçersiz; sayfayı yenileyin.';
        elseif ($action === 'email_status' && !empty($_SESSION['used_order_email_nonces'][$nonce])) $error = 'Bu e-posta isteği daha önce işlendi. Yeni bir gönderim için sayfayı yenileyin.';
        else try {
            $pdo->beginTransaction();
            $get = $pdo->prepare('SELECT * FROM orders WHERE id=:id FOR UPDATE');
            $get->execute(['id'=>$orderId]);
            $order = $get->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new RuntimeException('Sipariş bulunamadı.');
            if ($action === 'email_status' && filter_var((string)$order['customer_email'], FILTER_VALIDATE_EMAIL) === false) throw new RuntimeException('Bu siparişte geçerli bir e-posta adresi kayıtlı değil.');
            $newStatus = (string)($_POST['status'] ?? order_status_key($order['status']));
            $newPaymentStatus = (string)($_POST['payment_status'] ?? $order['payment_status'] ?? 'unspecified');
            if (!isset($statuses[$newStatus])) throw new RuntimeException('Sipariş durumu geçersiz.');
            if (!isset($paymentStatuses[$newPaymentStatus])) throw new RuntimeException('Ödeme durumu geçersiz.');
            $carrier = trim((string)($_POST['carrier'] ?? $order['carrier'] ?? ''));
            $tracking = trim((string)($_POST['tracking_number'] ?? $order['tracking_number'] ?? ''));
            $trackingUrl = trim((string)($_POST['tracking_url'] ?? $order['tracking_url'] ?? ''));
            $length = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value,'UTF-8') : strlen($value);
            if ($length($carrier)>80 || $length($tracking)>120 || $length($trackingUrl)>500) throw new RuntimeException('Kargo firması, takip numarası veya bağlantısı çok uzun.');
            if ($trackingUrl !== '') {
                $parts = parse_url($trackingUrl);
                if (filter_var($trackingUrl, FILTER_VALIDATE_URL) === false || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) throw new RuntimeException('Takip bağlantısı https:// ile başlayan geçerli bir adres olmalıdır.');
            }
            if ($newStatus === 'shipped' && ($carrier === '' || $tracking === '')) throw new RuntimeException('Kargoda durumuna geçmek için kargo firması ve takip numarası girin.');

            if (($order['status'] !== $newStatus && ($newStatus === 'cancelled' || $order['status'] === 'cancelled'))) {
                $items = $pdo->prepare('SELECT oi.product_id,oi.product_title,oi.quantity,p.stock_qty FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=:id FOR UPDATE');
                $items->execute(['id'=>$orderId]);
                foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) if ($item['product_id'] !== null && $item['stock_qty'] !== null) {
                    $before = (int)$item['stock_qty'];
                    $qty = (int)$item['quantity'];
                    $delta = $newStatus === 'cancelled' ? $qty : -$qty;
                    if ($delta < 0 && $before < $qty) throw new RuntimeException('Sipariş yeniden açılamadı; yeterli stok yok.');
                    $pdo->prepare('UPDATE products SET stock_qty=stock_qty+:delta WHERE id=:id')->execute(['delta'=>$delta,'id'=>$item['product_id']]);
                    $pdo->prepare("INSERT INTO stock_movements (product_id,product_title,change_qty,stock_before,stock_after,movement_type,reference_id,note) VALUES (:pid,:title,:change,:before,:after,:type,:ref,:note)")->execute(['pid'=>$item['product_id'],'title'=>$item['product_title'],'change'=>$delta,'before'=>$before,'after'=>$before+$delta,'type'=>$delta>0?'order_restore':'order_reserve','ref'=>$orderId,'note'=>$delta>0?'Sipariş iptali: stok iade':'İptal edilen sipariş yeniden açıldı']);
                }
            }
            record_order_change($pdo,$orderId,'status',(string)$order['status'],$newStatus);
            record_order_change($pdo,$orderId,'payment_status',(string)($order['payment_status'] ?? 'unspecified'),$newPaymentStatus);
            record_order_change($pdo,$orderId,'carrier',(string)($order['carrier'] ?? ''),$carrier);
            record_order_change($pdo,$orderId,'tracking_number',(string)($order['tracking_number'] ?? ''),$tracking);
            record_order_change($pdo,$orderId,'tracking_url',(string)($order['tracking_url'] ?? ''),$trackingUrl);
            $pdo->prepare('UPDATE orders SET status=:status,payment_status=:payment_status,carrier=:carrier,tracking_number=:tracking,tracking_url=:tracking_url WHERE id=:id')->execute(['status'=>$newStatus,'payment_status'=>$newPaymentStatus,'carrier'=>$carrier,'tracking'=>$tracking,'tracking_url'=>$trackingUrl,'id'=>$orderId]);
            $notificationId = null;
            if ($action === 'email_status') {
                $subject = order_mail_notification_subject($newStatus, (string)$order['order_number'], 'status_update');
                $idempotencyKey = hash('sha256', 'status_update:' . $orderId . ':' . $nonce);
                $notificationId = queue_order_email_notification($pdo,$orderId,'status_update',$idempotencyKey,(string)$order['customer_email'],$subject);
            }
            $pdo->commit();
            if ($action === 'email_status') {
                $_SESSION['used_order_email_nonces'][$nonce] = true;
                if (count($_SESSION['used_order_email_nonces']) > 200) $_SESSION['used_order_email_nonces'] = array_slice($_SESSION['used_order_email_nonces'],-100,null,true);
                $result = dispatch_order_email_notification($pdo,(int)$notificationId);
                flash($result['status'] === 'sent' ? 'Sipariş kaydedildi; e-posta sunucusuna iletildi. Alıcı posta kutusuna teslimat doğrulanmıyor.' : 'Sipariş kaydedildi; e-posta gönderilemedi (' . ($result['error'] ?? 'smtp_failure') . '). Ayrıntı siparişin e-posta geçmişinde.', $result['status'] === 'sent' ? 'success' : 'warning');
            } else flash('Sipariş, ödeme ve kargo bilgileri güncellendi.');
            order_redirect_after_post((int)$orderId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Sipariş işlemi tamamlanamadı.';
            if (!($exception instanceof RuntimeException)) error_log('Order admin action failed: ' . $exception->getMessage());
        }
    }
}

$filters = order_filters_from_request($_GET, $statuses, $paymentStatuses);
$where = [];
$params = [];
if ($filters['q'] !== '') {
        $where[] = '(o.customer_name LIKE :q_name OR o.phone LIKE :q_phone OR o.customer_email LIKE :q_email OR o.order_number LIKE :q_number)';
    $searchTerm = '%' . $filters['q'] . '%';
    $params += ['q_name'=>$searchTerm,'q_phone'=>$searchTerm,'q_email'=>$searchTerm,'q_number'=>$searchTerm];
}
if ($filters['status'] === 'preparing') $where[] = "o.status IN ('preparing','pending_confirmation','confirmed')";
elseif ($filters['status'] !== '') { $where[] = 'o.status=:status'; $params['status'] = $filters['status']; }
if ($filters['payment_status'] !== '') { $where[] = 'o.payment_status=:payment_status'; $params['payment_status'] = $filters['payment_status']; }
if ($filters['from'] !== '') { $where[] = 'o.created_at>=:date_from'; $params['date_from'] = $filters['from'] . ' 00:00:00'; }
if ($filters['to'] !== '') {
    $toExclusive = DateTimeImmutable::createFromFormat('!Y-m-d', $filters['to'])->modify('+1 day')->format('Y-m-d');
    $where[] = 'o.created_at<:date_to'; $params['date_to'] = $toExclusive . ' 00:00:00';
}
$where = array_merge(['o.deleted_at IS NULL'], $where);
$whereSql = ' WHERE ' . implode(' AND ', $where);
$sortColumn = $filters['sort'] === 'total' ? 'o.total' : 'o.created_at';
$sortDirection = strtoupper($filters['direction']);
$orderBy = $sortColumn . ' ' . $sortDirection . ', o.id ' . $sortDirection;
$countStatement = $pdo->prepare('SELECT COUNT(*) FROM orders o' . $whereSql);
$countStatement->execute($params);
$totalOrders = (int)$countStatement->fetchColumn();
$totalPages = max(1, (int)ceil($totalOrders / $filters['per_page']));
$filters['page'] = min($filters['page'], $totalPages);
$offset = ($filters['page'] - 1) * $filters['per_page'];
$selectedIds = order_safe_ids($_GET['ids'] ?? []);
$selectedSql = '';
$queryParams = $params;
if ($selectedIds) {
    $bulkPlaceholders = [];
    foreach ($selectedIds as $index=>$id) { $placeholder = ':bulk_id_' . $index; $bulkPlaceholders[] = $placeholder; $queryParams[$placeholder] = $id; }
    $selectedSql = ($whereSql ? ' AND ' : ' WHERE ') . 'o.id IN (' . implode(',', $bulkPlaceholders) . ')';
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $statement = $pdo->prepare('SELECT o.* FROM orders o' . $whereSql . $selectedSql . ' ORDER BY ' . $orderBy);
    $statement->execute($queryParams);
    $exportOrders = $statement->fetchAll(PDO::FETCH_ASSOC);
    $itemsStatement = $pdo->prepare('SELECT product_title,variant_label,quantity,line_total FROM order_items WHERE order_id=:id ORDER BY id');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="koken-siparisler-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Sipariş no','Tarih','Müşteri','Telefon','E-posta','İl','İlçe','Adres','Ürünler','Ara toplam','İndirim','Kargo','Genel toplam','Ödeme yöntemi','Ödeme durumu','Sipariş durumu'], ';');
    foreach ($exportOrders as $row) {
        $itemsStatement->execute(['id'=>$row['id']]);
        $itemNames = [];
        foreach ($itemsStatement->fetchAll(PDO::FETCH_ASSOC) as $item) $itemNames[] = trim($item['product_title'] . ' ' . $item['variant_label']) . ' × ' . (int)$item['quantity'];
        $values = [$row['order_number'],$row['created_at'],$row['customer_name'],$row['phone'],$row['customer_email'],$row['city'],$row['district'],$row['address'],implode(' | ',$itemNames),$row['subtotal'],$row['discount_amount'] ?? '0.00',$row['shipping_fee'],$row['total'],order_payment_method_label((string)$row['payment_method']),order_payment_status_label((string)$row['payment_status']),order_status_label((string)$row['status'])];
        foreach ($values as &$value) {
            $value = (string)$value;
            if (preg_match('/^[=+@\-\t\r]/', $value)) $value = "'" . $value;
        }
        unset($value);
        fputcsv($out, $values, ';');
    }
    fclose($out);
    exit;
}

if (isset($_GET['print']) && $_GET['print'] === '1') {
    $statement = $pdo->prepare('SELECT o.* FROM orders o' . $whereSql . $selectedSql . ' ORDER BY ' . $orderBy);
    $statement->execute($queryParams);
    $printOrders = $statement->fetchAll(PDO::FETCH_ASSOC);
    $itemsStatement = $pdo->prepare('SELECT product_title,variant_label,quantity FROM order_items WHERE order_id=:id ORDER BY id');
    ?>
    <!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sipariş hazırlama listesi</title><style>body{font:14px Arial,sans-serif;color:#17211d;margin:24px}.toolbar{margin-bottom:18px}article{border:1px solid #9ca9a1;border-radius:8px;padding:14px;margin:0 0 12px;break-inside:avoid}h1{font-size:22px}.muted{color:#56635b}@media print{.toolbar{display:none}body{margin:10mm}}</style></head><body><div class="toolbar"><button onclick="window.print()">Yazdır</button> <button onclick="history.back()">Geri dön</button></div><h1>Sipariş hazırlama listesi</h1><p><?= count($printOrders) ?> sipariş · <?= date('d.m.Y H:i') ?></p>
    <?php foreach ($printOrders as $row): $itemsStatement->execute(['id'=>$row['id']]); $printItems = $itemsStatement->fetchAll(PDO::FETCH_ASSOC); ?>
      <article><strong>#<?= e($row['order_number']) ?> · <?= e(date('d.m.Y H:i',strtotime($row['created_at']))) ?></strong><p><?= e($row['customer_name']) ?> · <?= e($row['phone']) ?><br><?= e($row['city']) ?> / <?= e($row['district']) ?> · <?= e($row['address']) ?></p><ul><?php foreach ($printItems as $item): ?><li><?= e($item['product_title']) ?> <?= e($item['variant_label']) ?> × <?= (int)$item['quantity'] ?></li><?php endforeach; ?></ul><span class="muted">Ödeme: <?= e(order_payment_method_label((string)$row['payment_method'])) ?> · Toplam: <?= e(order_money($row['total'])) ?></span></article>
    <?php endforeach; ?><script>window.addEventListener('load',()=>window.print());</script></body></html>
    <?php
    exit;
}

$listStatement = $pdo->prepare('SELECT o.* FROM orders o' . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT :limit OFFSET :offset');
foreach ($params as $key=>$value) $listStatement->bindValue(':' . $key, $value);
$listStatement->bindValue(':limit', (int)$filters['per_page'], PDO::PARAM_INT);
$listStatement->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$listStatement->execute();
$orders = $listStatement->fetchAll(PDO::FETCH_ASSOC);
$returnQuery = http_build_query(array_filter($filters, static fn($value) => $value !== '' && $value !== null));
$listBaseQuery = $filters;
$listBaseQuery['page'] = 1;
$clearUrl = app_url('admin/orders/index.php');
$pageUrl = static function (int $page) use ($filters): string {
    $query = $filters; $query['page'] = $page;
    return app_url('admin/orders/index.php') . '?' . http_build_query($query);
};
$pageTitle = 'Siparişler';
$current = 'orders';
require __DIR__ . '/../header.php';
?>
<div class="page-heading"><div><h1>Siparişler</h1><p class="text-secondary">Siparişleri ara, filtrele ve ayrıntıları yönetin.</p></div><div class="d-flex flex-wrap gap-2"><button class="btn btn-outline-secondary" type="button" id="bulk-print">Seçilenleri hazırlama listesinde yazdır</button><button class="btn btn-outline-success" type="button" id="bulk-csv">CSV dışa aktar</button></div></div>
<?php if (!$mailHealth['ready']): ?><div class="alert alert-warning"><strong>E-posta bildirimi yapılandırılmamış.</strong> Teknik eksikler: <?= e(implode(', ', $mailHealth['missing'])) ?>. Ayrıntılı hata bilgileri sipariş detayındaki e-posta geçmişinde gösterilir.</div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form class="content-card orders-filter-form p-3 mb-3 row g-2 align-items-end" method="get">
  <div class="col-md-3"><label class="form-label" for="order-q">Müşteri / telefon / e-posta / sipariş no</label><input class="form-control" id="order-q" name="q" value="<?= e($filters['q']) ?>"></div>
  <div class="col-md-2"><label class="form-label" for="order-status">Sipariş durumu</label><select class="form-select" id="order-status" name="status"><option value="">Tüm durumlar</option><?php foreach ($statuses as $key=>$label): ?><option value="<?= e($key) ?>" <?= $filters['status']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label" for="payment-status">Ödeme durumu</label><select class="form-select" id="payment-status" name="payment_status"><option value="">Tümü</option><?php foreach ($paymentStatuses as $key=>$label): ?><option value="<?= e($key) ?>" <?= $filters['payment_status']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label" for="orders-from">Başlangıç</label><input class="form-control" id="orders-from" type="date" name="from" value="<?= e($filters['from']) ?>"></div>
  <div class="col-md-2"><label class="form-label" for="orders-to">Bitiş</label><input class="form-control" id="orders-to" type="date" name="to" value="<?= e($filters['to']) ?>"></div>
  <div class="col-md-1 d-flex gap-2"><button class="btn btn-primary">Ara</button><a class="btn btn-outline-secondary" href="<?= e($clearUrl) ?>" aria-label="Filtreleri temizle">Temizle</a></div>
  <div class="col-md-3"><label class="form-label" for="orders-sort">Sıralama</label><select class="form-select" id="orders-sort" name="sort"><option value="created_at" <?= $filters['sort']==='created_at'?'selected':'' ?>>Tarih</option><option value="total" <?= $filters['sort']==='total'?'selected':'' ?>>Tutar</option></select></div>
  <div class="col-md-2"><label class="form-label" for="orders-direction">Yön</label><select class="form-select" id="orders-direction" name="direction"><option value="desc" <?= $filters['direction']==='desc'?'selected':'' ?>>Azalan</option><option value="asc" <?= $filters['direction']==='asc'?'selected':'' ?>>Artan</option></select></div>
  <div class="col-md-2"><label class="form-label" for="orders-per-page">Sayfa başına</label><select class="form-select" id="orders-per-page" name="per_page"><?php foreach ([10,20,50,100] as $size): ?><option value="<?= $size ?>" <?= $filters['per_page']===$size?'selected':'' ?>><?= $size ?></option><?php endforeach; ?></select></div>
</form>
<div class="orders-result-bar"><strong><?= number_format($totalOrders,0,',','.') ?> sipariş bulundu</strong><span>Sayfa <?= (int)$filters['page'] ?> / <?= (int)$totalPages ?></span></div>
<form id="bulk-order-form" method="get" action="<?= e(app_url('admin/orders/index.php')) ?>"><input type="hidden" name="q" value="<?= e($filters['q']) ?>"><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><input type="hidden" name="payment_status" value="<?= e($filters['payment_status']) ?>"><input type="hidden" name="from" value="<?= e($filters['from']) ?>"><input type="hidden" name="to" value="<?= e($filters['to']) ?>"><input type="hidden" name="sort" value="<?= e($filters['sort']) ?>"><input type="hidden" name="direction" value="<?= e($filters['direction']) ?>"><input type="hidden" name="per_page" value="<?= (int)$filters['per_page'] ?>"></form>
<div class="content-card orders-card"><div class="table-responsive"><table class="table align-middle orders-table"><thead><tr><th><input type="checkbox" class="form-check-input" id="select-visible" aria-label="Sayfadaki siparişleri seç"></th><th>Sipariş no / tarih</th><th>Müşteri</th><th>Ürün / adet özeti</th><th>Toplam</th><th>Ödeme</th><th>Sipariş durumu</th><th>İşlem</th></tr></thead><tbody>
<?php foreach ($orders as $order):
    $id = (int)$order['id'];
    $itemsQuery = $pdo->prepare('SELECT id,product_id,product_title,variant_label,quantity,unit_price,line_total FROM order_items WHERE order_id=:id ORDER BY id');
    $itemsQuery->execute(['id'=>$id]);
    $items = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);
    $itemSummary = array_slice(array_map(static fn($item) => trim((string)$item['product_title'] . ' ' . (string)$item['variant_label']) . ' × ' . (int)$item['quantity'], $items), 0, 2);
    $allQty = array_sum(array_map(static fn($item)=>(int)$item['quantity'], $items));
    $rowOpen = (int)($_GET['open'] ?? 0) === $id;
    $waPhone = preg_replace('/\D+/', '', (string)$order['phone']);
    if (str_starts_with($waPhone, '0')) $waPhone = '9' . $waPhone;
    $waText = 'Merhaba ' . $order['customer_name'] . ', #' . $order['order_number'] . ' siparişinizin durumu: ' . (order_status_label((string)$order['status'])) . '.';
    if ($order['carrier'] || $order['tracking_number']) $waText .= ' Kargo: ' . $order['carrier'] . ' ' . $order['tracking_number'];
    $waUrl = 'https://wa.me/' . $waPhone . '?text=' . rawurlencode($waText);
    $emailNonce = bin2hex(random_bytes(32));
    $noteKey = bin2hex(random_bytes(32));
    $returnFields = '<input type="hidden" name="return_query" value="' . e($returnQuery) . '">';
?>
<tr class="order-summary-row" id="order-summary-<?= $id ?>">
  <td data-label="Seç"><input form="bulk-order-form" type="checkbox" class="form-check-input order-select" name="ids[]" value="<?= $id ?>" aria-label="#<?= e($order['order_number']) ?> siparişini seç"></td>
  <td data-label="Sipariş no / tarih"><button type="button" class="order-number-link" data-order-toggle="order-detail-<?= $id ?>" aria-expanded="<?= $rowOpen?'true':'false' ?>">#<?= e($order['order_number']) ?></button><small class="d-block text-secondary"><?= e(date('d.m.Y H:i',strtotime($order['created_at']))) ?></small></td>
  <td data-label="Müşteri"><strong><?= e($order['customer_name']) ?></strong><small class="d-block"><a href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a></small></td>
  <td data-label="Ürün / adet"><span><?= e(implode(' · ', $itemSummary)) ?><?= count($items)>2?' · +' . (count($items)-2) . ' ürün':'' ?></span><small class="d-block"><?= (int)$allQty ?> adet</small></td>
  <td data-label="Toplam"><strong><?= e(order_money($order['total'])) ?></strong></td>
  <td data-label="Ödeme"><span class="order-state order-state-payment"><?= e($shortPaymentLabels[$order['payment_status'] ?? ''] ?? '—') ?></span></td>
  <td data-label="Sipariş durumu"><span class="order-state"><?= e(order_status_label((string)$order['status'])) ?></span></td>
  <td data-label="İşlem"><div class="order-row-actions"><button type="button" class="btn btn-sm btn-outline-success" data-order-toggle="order-detail-<?= $id ?>" aria-expanded="<?= $rowOpen?'true':'false' ?>">Detay</button><button type="button" class="btn btn-sm btn-outline-danger order-delete-trigger" data-order-id="<?= $id ?>" data-order-number="<?= e((string)$order['order_number']) ?>">Sil</button></div></td>
</tr>
<tr class="order-detail-row" id="order-detail-<?= $id ?>" <?= $rowOpen?'':'hidden' ?>><td colspan="8"><section class="order-detail-panel" aria-label="#<?= e($order['order_number']) ?> sipariş ayrıntıları">
  <div class="detail-heading"><div><h2>Sipariş #<?= e($order['order_number']) ?></h2><p><?= e(date('d.m.Y H:i',strtotime($order['created_at']))) ?> · <?= e(order_payment_method_label((string)$order['payment_method'])) ?></p></div><a class="btn btn-sm btn-outline-success" href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp'ta hazır sipariş mesajı aç, yeni sekmede">WhatsApp <span class="visually-hidden">(yeni sekmede açılır)</span></a></div>
  <div class="row g-3"><div class="col-xl-6"><section class="detail-section"><h3>Müşteri ve teslimat</h3><dl class="order-definition-list"><dt>Ad soyad</dt><dd><?= e($order['customer_name']) ?></dd><dt>Telefon</dt><dd><a href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a></dd><dt>E-posta</dt><dd><?= e($order['customer_email'] ?: 'Belirtilmedi') ?></dd><dt>Adres</dt><dd><?= e($order['address']) ?>, <?= e($order['district']) ?> / <?= e($order['city']) ?></dd><dt>Müşteri notu</dt><dd><?= e($order['note'] ?: 'Not yok') ?></dd></dl></section></div>
  <div class="col-xl-6"><section class="detail-section"><h3>Ürünler ve tutar dökümü</h3><div class="table-responsive"><table class="table table-sm detail-items-table"><thead><tr><th>Ürün / gramaj</th><th>Adet</th><th>Birim</th><th>Tutar</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><?= e($item['product_title']) ?><?= $item['variant_label']!==''?' · '.e($item['variant_label']):'' ?></td><td><?= (int)$item['quantity'] ?></td><td><?= e(order_money($item['unit_price'])) ?></td><td><?= e(order_money($item['line_total'])) ?></td></tr><?php endforeach; ?></tbody><tfoot><tr><th colspan="3">Ara toplam</th><td><?= e(order_money($order['subtotal'])) ?></td></tr><tr><th colspan="3">İndirim</th><td>−<?= e(order_money($order['discount_amount'] ?? 0)) ?></td></tr><tr><th colspan="3">Kargo</th><td><?= e(order_money($order['shipping_fee'])) ?></td></tr><tr><th colspan="3">Genel toplam</th><td><strong><?= e(order_money($order['total'])) ?></strong></td></tr></tfoot></table></div></section></div></div>
  <div class="row g-3 mt-1"><div class="col-xl-6"><section class="detail-section"><h3>Sipariş, ödeme ve kargo</h3><form method="post" class="detail-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="email_nonce" value="<?= e($emailNonce) ?>"><?= $returnFields ?>
    <div class="row g-2"><div class="col-md-6"><label class="form-label">Sipariş durumu<select class="form-select" name="status"><?php foreach ($statuses as $key=>$label): ?><option value="<?= e($key) ?>" <?= order_status_key($order['status'])===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label></div><div class="col-md-6"><label class="form-label">Ödeme durumu<select class="form-select" name="payment_status" required><?php if (!isset($paymentStatuses[$order['payment_status'] ?? ''])): ?><option value="" selected disabled>Ödeme durumu seçin</option><?php endif; ?><?php foreach ($paymentStatuses as $key=>$label): ?><option value="<?= e($key) ?>" <?= ($order['payment_status'] ?? 'unspecified')===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label></div><div class="col-md-6"><label class="form-label">Kargo firması<input class="form-control" name="carrier" maxlength="80" value="<?= e($order['carrier']) ?>"></label></div><div class="col-md-6"><label class="form-label">Takip numarası<input class="form-control" name="tracking_number" maxlength="120" value="<?= e($order['tracking_number']) ?>"></label></div><div class="col-12"><label class="form-label">Takip bağlantısı (https)<input class="form-control" type="url" name="tracking_url" maxlength="500" value="<?= e($order['tracking_url'] ?? '') ?>" placeholder="https://"></label></div></div><p class="form-text">“Kargoda” için firma ve takip numarası zorunludur. Ödeme durumunu işaretlemek para tahsil etmez veya iade etmez.</p><div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" name="action" value="save">Değişiklikleri kaydet</button><?php if ($mailHealth['ready'] && filter_var((string)$order['customer_email'], FILTER_VALIDATE_EMAIL)): ?><button class="btn btn-outline-primary" name="action" value="email_status" title="Ödeme, kargo ve sipariş bilgisini kaydedip e-posta gönder">Kaydet ve e-posta gönder</button><?php endif; ?></div>
  </form><?php if ($order['carrier'] || $order['tracking_number'] || !empty($order['tracking_url'])): ?><p class="shipping-summary mt-3"><strong>Kargo:</strong> <?= e($order['carrier'] ?: 'Firma belirtilmedi') ?> <?= e($order['tracking_number']) ?> <?php if (!empty($order['tracking_url'])): ?><a href="<?= e($order['tracking_url']) ?>" target="_blank" rel="noopener noreferrer">Takip sayfasını aç <span class="visually-hidden">(yeni sekmede açılır)</span></a><?php endif; ?></p><?php endif; ?></section></div>
  <div class="col-xl-6"><section class="detail-section"><h3>Personel notu</h3><form method="post" class="detail-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="add_note"><input type="hidden" name="note_request_key" value="<?= e($noteKey) ?>"><?= $returnFields ?><label class="visually-hidden" for="internal-note-<?= $id ?>">Dahili personel notu</label><textarea class="form-control" id="internal-note-<?= $id ?>" name="internal_note" rows="3" maxlength="3000" placeholder="Müşteriye gönderilmeyen iç not"></textarea><button class="btn btn-outline-success mt-2">Not ekle</button></form>
    <?php $notesQuery=$pdo->prepare('SELECT n.note,n.created_at,u.username FROM order_internal_notes n LEFT JOIN admin_users u ON u.id=n.admin_id WHERE n.order_id=:id ORDER BY n.created_at DESC,n.id DESC');$notesQuery->execute(['id'=>$id]);$notes=$notesQuery->fetchAll(PDO::FETCH_ASSOC); ?><div class="detail-history"><?php if (!$notes): ?><p class="text-secondary mb-0">Henüz personel notu yok.</p><?php else: foreach ($notes as $note): ?><article><p><?= nl2br(e($note['note'])) ?></p><small><?= e($note['username'] ?: 'Silinmiş yönetici') ?> · <?= e(date('d.m.Y H:i',strtotime($note['created_at']))) ?></small></article><?php endforeach; endif; ?></div></section></div></div>
  <div class="row g-3 mt-1"><div class="col-lg-6"><section class="detail-section"><h3>İşlem geçmişi</h3><?php $historyQuery=$pdo->prepare('SELECT h.field_name,h.previous_value,h.new_value,h.changed_by,h.changed_at,u.username FROM order_status_history h LEFT JOIN admin_users u ON u.id=h.changed_by WHERE h.order_id=:id ORDER BY h.changed_at DESC,h.id DESC LIMIT 100');$historyQuery->execute(['id'=>$id]);$history=$historyQuery->fetchAll(PDO::FETCH_ASSOC); ?><div class="detail-history"><?php if (!$history): ?><p class="text-secondary">İşlem geçmişi boş.</p><?php else: foreach ($history as $event): ?><article><strong><?= e(match($event['field_name']) {'status'=>'Sipariş durumu','payment_status'=>'Ödeme durumu','carrier'=>'Kargo firması','tracking_number'=>'Takip numarası','tracking_url'=>'Takip bağlantısı','refund'=>'İade','internal_note'=>'Personel notu',default=>$event['field_name']}) ?></strong><div><?= e($event['previous_value'] ?: '—') ?> → <?= e($event['new_value'] ?: '—') ?></div><small><?= e($event['username'] ?: ($event['changed_by']===null && $event['previous_value']===null?'Mağaza sistemi':'Silinmiş yönetici')) ?> · <?= e(date('d.m.Y H:i',strtotime($event['changed_at']))) ?></small></article><?php endforeach; endif; ?></div></section></div>
  <div class="col-lg-6"><section class="detail-section"><h3>E-posta geçmişi</h3><?php $mailQuery=$pdo->prepare('SELECT id,notification_type,recipient,subject,status,attempts,last_error,last_attempt_at,sent_at,created_at FROM order_email_notifications WHERE order_id=:id ORDER BY id DESC LIMIT 30');$mailQuery->execute(['id'=>$id]);$emailLogs=$mailQuery->fetchAll(PDO::FETCH_ASSOC); ?><?php if (!$emailLogs): ?><p class="text-secondary mb-0">E-posta kaydı yok.</p><?php else: foreach ($emailLogs as $mailLog): ?><article class="email-log"><strong><?= e($mailLog['notification_type']==='order_received'?'Yeni sipariş':'Durum güncellemesi') ?> · <?= e(order_email_status_label((string)$mailLog['status'],(string)($mailLog['last_error']??''))) ?></strong><div><?= e($mailLog['recipient']) ?> · <?= e($mailLog['subject']) ?></div><small>Deneme: <?= (int)$mailLog['attempts'] ?> · Son deneme: <?= e($mailLog['last_attempt_at']?date('d.m.Y H:i',strtotime($mailLog['last_attempt_at'])):'—') ?> · İletim: <?= e($mailLog['sent_at']?date('d.m.Y H:i',strtotime($mailLog['sent_at'])):'—') ?><?php if ($mailLog['last_error']): ?> · Teknik kod: <?= e($mailLog['last_error']) ?><?php endif; ?></small><?php if ($mailHealth['ready'] && in_array($mailLog['status'],['failed','pending'],true)): ?><form method="post" class="mt-1"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="notification_id" value="<?= (int)$mailLog['id'] ?>"><input type="hidden" name="action" value="email_retry"><?= $returnFields ?><button class="btn btn-link btn-sm p-0"><?= $mailLog['status']==='pending'?'Gönderimi dene':'Yeniden dene' ?></button></form><?php endif; ?></article><?php endforeach; endif; ?></section></div></div>
</section></td></tr>
<?php endforeach; ?>
<?php if (!$orders): ?><tr><td colspan="8"><div class="empty-state"><strong>Eşleşen sipariş yok</strong><p>Filtreleri değiştirip tekrar deneyin.</p></div></td></tr><?php endif; ?>
</tbody></table></div></div>
<dialog class="order-delete-dialog" id="order-delete-dialog" aria-modal="true" aria-labelledby="order-delete-title" aria-describedby="order-delete-message">
  <form method="post" id="order-delete-form">
    <button type="button" class="order-dialog-close" aria-label="Pencereyi kapat" data-delete-cancel>×</button>
    <h2 id="order-delete-title">Siparişi sil</h2>
    <p id="order-delete-message">Bu siparişi silmek istediğinize emin misiniz?</p>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="soft_delete">
    <input type="hidden" name="order_id" id="order-delete-id" value="">
    <input type="hidden" name="return_query" value="<?= e($returnQuery) ?>">
    <div class="order-dialog-actions"><button type="button" class="btn btn-outline-secondary" data-delete-cancel>Vazgeç</button><button type="submit" class="btn btn-outline-danger" id="order-delete-submit">Evet, sil</button></div>
  </form>
</dialog>
<?php if ($totalPages > 1): ?><nav class="orders-pagination" aria-label="Sipariş sayfaları"><span><?= number_format($totalOrders,0,',','.') ?> sonuçtan <?= $offset+1 ?>–<?= min($offset+$filters['per_page'],$totalOrders) ?> gösteriliyor</span><div><?php if ($filters['page']>1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($pageUrl($filters['page']-1)) ?>">Önceki</a><?php endif; ?><?php for ($page=max(1,$filters['page']-2); $page<=min($totalPages,$filters['page']+2); $page++): ?><a class="btn btn-sm <?= $page===$filters['page']?'btn-primary':'btn-outline-secondary' ?>" href="<?= e($pageUrl($page)) ?>" aria-current="<?= $page===$filters['page']?'page':'false' ?>"><?= $page ?></a><?php endfor; ?><?php if ($filters['page']<$totalPages): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($pageUrl($filters['page']+1)) ?>">Sonraki</a><?php endif; ?></div></nav><?php endif; ?>
<script>
(() => {
  document.querySelectorAll('[data-order-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const target = document.getElementById(button.dataset.orderToggle);
      if (!target) return;
      const open = target.hidden;
      document.querySelectorAll('.order-detail-row').forEach((row) => { row.hidden = true; });
      document.querySelectorAll('[data-order-toggle]').forEach((toggle) => toggle.setAttribute('aria-expanded', 'false'));
      if (open) { target.hidden = false; document.querySelectorAll(`[data-order-toggle="${button.dataset.orderToggle}"]`).forEach((toggle) => toggle.setAttribute('aria-expanded', 'true')); }
    });
  });
  const selectVisible = document.getElementById('select-visible');
  const selected = () => [...document.querySelectorAll('.order-select:checked')].map((box) => box.value);
  if (selectVisible) selectVisible.addEventListener('change', () => document.querySelectorAll('.order-select').forEach((box) => { box.checked = selectVisible.checked; }));
  const goBulk = (mode) => {
    const ids = selected();
    if (!ids.length) { window.alert('Önce sipariş seçin.'); return; }
    const form = document.getElementById('bulk-order-form');
    const url = new URL(form.action, window.location.href);
    const data = new FormData(form);
    for (const [key, value] of data.entries()) url.searchParams.set(key, value);
    ids.forEach((id) => url.searchParams.append('ids[]', id));
    if (mode === 'print') url.searchParams.set('print', '1'); else url.searchParams.set('export', 'csv');
    window.location.href = url.toString();
  };
  document.getElementById('bulk-print')?.addEventListener('click', () => goBulk('print'));
  document.getElementById('bulk-csv')?.addEventListener('click', () => goBulk('csv'));
  const deleteDialog = document.getElementById('order-delete-dialog');
  const deleteForm = document.getElementById('order-delete-form');
  const deleteId = document.getElementById('order-delete-id');
  const deleteMessage = document.getElementById('order-delete-message');
  const deleteSubmit = document.getElementById('order-delete-submit');
  let deleteOpener = null;
  document.querySelectorAll('.order-delete-trigger').forEach((button) => button.addEventListener('click', () => {
    if (!deleteDialog || typeof deleteDialog.showModal !== 'function') return;
    deleteOpener = button;
    deleteId.value = button.dataset.orderId || '';
    deleteMessage.textContent = `#${button.dataset.orderNumber || ''} numaralı siparişi silmek istediğinize emin misiniz?`;
    deleteSubmit.disabled = false;
    deleteSubmit.textContent = 'Evet, sil';
    deleteDialog.showModal();
    deleteDialog.querySelector('[data-delete-cancel]')?.focus();
  }));
  deleteDialog?.querySelectorAll('[data-delete-cancel]').forEach((button) => button.addEventListener('click', () => deleteDialog.close()));
  deleteDialog?.addEventListener('click', (event) => { if (event.target === deleteDialog) deleteDialog.close(); });
  deleteDialog?.addEventListener('close', () => { deleteOpener?.focus(); deleteOpener = null; });
  deleteForm?.addEventListener('submit', () => {
    deleteSubmit.disabled = true;
    deleteSubmit.textContent = 'Siliniyor…';
  });
  document.querySelectorAll('form.detail-form button[value="save"],form.detail-form button[value="email_status"]').forEach((button) => {
    button.form.addEventListener('submit', (event) => {
      const status = button.form.querySelector('[name="status"]');
      const carrier = button.form.querySelector('[name="carrier"]');
      const tracking = button.form.querySelector('[name="tracking_number"]');
      if (status?.value === 'shipped' && (!carrier?.value.trim() || !tracking?.value.trim())) {
        event.preventDefault();
        window.alert('Kargoda durumuna geçmek için kargo firması ve takip numarası girin.');
      }
    });
  });
})();
</script>
<?php require __DIR__ . '/../footer.php'; ?>


