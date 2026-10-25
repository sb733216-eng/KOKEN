<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../../config/mailer.php';
require_once __DIR__ . '/../../config/order_helpers.php';

$statuses = order_status_labels();
$paymentStatuses = order_payment_status_labels();
$error = '';

function record_order_change(PDO $pdo, int $orderId, string $field, ?string $before, ?string $after): void
{
    if ($before === $after) return;
    $statement = $pdo->prepare('INSERT INTO order_status_history (order_id,field_name,previous_value,new_value,changed_by) VALUES (:order_id,:field,:previous,:next,:admin)');
    $statement->execute(['order_id'=>$orderId,'field'=>$field,'previous'=>$before,'next'=>$after,'admin'=>$_SESSION['admin_id'] ?? null]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $action = (string)($_POST['action'] ?? 'status');
    $validActions = ['status','email_status','payment_status','email_retry','delete'];
    if (!$orderId || !in_array($action, $validActions, true)) {
        $error = 'Sipariş işlemi geçersiz.';
    } elseif ($action === 'email_retry') {
        $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
        try {
            $check = $pdo->prepare("SELECT id,status FROM order_email_notifications WHERE id=:notification AND order_id=:order_id AND status IN ('failed','pending')");
            $check->execute(['notification'=>$notificationId,'order_id'=>$orderId]);
            if (!$check->fetch()) throw new RuntimeException('Yalnızca bekleyen veya başarısız e-posta bildirimi gönderilebilir.');
            $result = dispatch_order_email_notification($pdo, (int)$notificationId);
            flash($result['status'] === 'sent' ? 'E-posta sunucuya iletildi. Teslimatı alıcı posta kutusunda ayrıca doğrulayın.' : 'E-posta gönderilemedi (' . ($result['error'] ?? 'smtp_failure') . '). Ayarları kontrol edip yeniden deneyin.', $result['status'] === 'sent' ? 'success' : 'warning');
            header('Location: '.app_url('admin/orders/index.php')); exit;
        } catch (Throwable $exception) {
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'E-posta yeniden gönderilemedi.';
            if (!($exception instanceof RuntimeException)) error_log('Order email retry action failed: '.$exception->getMessage());
        }
    } else {
        $nonce = trim((string)($_POST['email_nonce'] ?? ''));
        if ($action === 'email_status' && !preg_match('/^[a-f0-9]{64}$/', $nonce)) $error = 'E-posta isteği geçersiz. Sayfayı yenileyin.';
        elseif ($action === 'email_status' && !empty($_SESSION['used_order_email_nonces'][$nonce])) $error = 'Bu e-posta isteği daha önce işlendi. Yeni bir istek için sayfayı yenileyin.';
        else try {
            $pdo->beginTransaction();
            $get = $pdo->prepare('SELECT * FROM orders WHERE id=:id FOR UPDATE'); $get->execute(['id'=>$orderId]); $order = $get->fetch();
            if (!$order) throw new RuntimeException('Sipariş bulunamadı.');
            if ($action === 'delete') {
                if ($order['status'] !== 'cancelled') {
                    $items = $pdo->prepare('SELECT oi.product_id,oi.product_title,oi.quantity,p.stock_qty FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=:id FOR UPDATE'); $items->execute(['id'=>$orderId]);
                    foreach ($items->fetchAll() as $it) if ($it['product_id'] !== null && $it['stock_qty'] !== null) {
                        $before=(int)$it['stock_qty']; $qty=(int)$it['quantity']; $pdo->prepare('UPDATE products SET stock_qty=stock_qty+:q WHERE id=:id')->execute(['q'=>$qty,'id'=>$it['product_id']]);
                        $pdo->prepare("INSERT INTO stock_movements(product_id,product_title,change_qty,stock_before,stock_after,movement_type,reference_id,note) VALUES(:pid,:title,:change,:before,:after,'order_restore',:ref,'Sipariş silindi; stok iade edildi')")->execute(['pid'=>$it['product_id'],'title'=>$it['product_title'],'change'=>$qty,'before'=>$before,'after'=>$before+$qty,'ref'=>$orderId]);
                    }
                }
                $pdo->prepare('DELETE FROM orders WHERE id=:id')->execute(['id'=>$orderId]); $pdo->commit(); flash('Sipariş silindi; takip edilen stok geri eklendi.'); header('Location: '.app_url('admin/orders/index.php')); exit;
            }
            if (filter_var((string)$order['customer_email'], FILTER_VALIDATE_EMAIL) === false && $action === 'email_status') throw new RuntimeException('Bu siparişte geçerli bir e-posta adresi kayıtlı değil.');
            $newStatus = $action === 'payment_status' ? (string)$order['status'] : (string)($_POST['status'] ?? $order['status']);
            $newPaymentStatus = (string)($_POST['payment_status'] ?? $order['payment_status'] ?? 'unspecified');
            if (!isset($statuses[$newStatus])) throw new RuntimeException('Sipariş durumu geçersiz.');
            if (!isset($paymentStatuses[$newPaymentStatus])) throw new RuntimeException('Ödeme durumu geçersiz.');
            $carrier = $action === 'payment_status' ? (string)($order['carrier'] ?? '') : trim((string)($_POST['carrier'] ?? $order['carrier'] ?? ''));
            $tracking = $action === 'payment_status' ? (string)($order['tracking_number'] ?? '') : trim((string)($_POST['tracking_number'] ?? $order['tracking_number'] ?? ''));
            $length = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value,'UTF-8') : strlen($value);
            if ($length($carrier)>80 || $length($tracking)>120) throw new RuntimeException('Kargo firması veya takip numarası çok uzun.');

            if (($order['status'] !== $newStatus && ($newStatus === 'cancelled' || $order['status'] === 'cancelled'))) {
                $items=$pdo->prepare('SELECT oi.product_id,oi.product_title,oi.quantity,p.stock_qty FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=:id FOR UPDATE'); $items->execute(['id'=>$orderId]);
                foreach ($items->fetchAll() as $it) if ($it['product_id']!==null && $it['stock_qty']!==null) {
                    $before=(int)$it['stock_qty']; $qty=(int)$it['quantity']; $delta=$newStatus==='cancelled' ? $qty : -$qty;
                    if ($delta<0 && $before<$qty) throw new RuntimeException('Sipariş yeniden açılamadı; yeterli stok yok.');
                    $pdo->prepare('UPDATE products SET stock_qty=stock_qty+:delta WHERE id=:id')->execute(['delta'=>$delta,'id'=>$it['product_id']]);
                    $pdo->prepare("INSERT INTO stock_movements(product_id,product_title,change_qty,stock_before,stock_after,movement_type,reference_id,note) VALUES(:pid,:title,:change,:before,:after,:type,:ref,:note)")->execute(['pid'=>$it['product_id'],'title'=>$it['product_title'],'change'=>$delta,'before'=>$before,'after'=>$before+$delta,'type'=>$delta>0?'order_restore':'order_reserve','ref'=>$orderId,'note'=>$delta>0?'Sipariş iptali: stok iade':'İptal edilen sipariş yeniden açıldı']);
                }
            }
            record_order_change($pdo,$orderId,'status',(string)$order['status'],$newStatus);
            record_order_change($pdo,$orderId,'payment_status',(string)($order['payment_status'] ?? 'unspecified'),$newPaymentStatus);
            record_order_change($pdo,$orderId,'carrier',(string)($order['carrier'] ?? ''),$carrier);
            record_order_change($pdo,$orderId,'tracking_number',(string)($order['tracking_number'] ?? ''),$tracking);
            $pdo->prepare('UPDATE orders SET status=:status,payment_status=:payment_status,carrier=:carrier,tracking_number=:tracking WHERE id=:id')->execute(['status'=>$newStatus,'payment_status'=>$newPaymentStatus,'carrier'=>$carrier,'tracking'=>$tracking,'id'=>$orderId]);
            $notificationId = null;
            if ($action === 'email_status') {
                $subject = 'Sipariş #' . preg_replace('/[^0-9]/','',(string)$order['order_number']) . ' durum ve kargo bilgisi güncellendi';
                $idempotencyKey = hash('sha256', 'status_update:' . $orderId . ':' . $nonce);
                $notificationId = queue_order_email_notification($pdo,$orderId,'status_update',$idempotencyKey,(string)$order['customer_email'],$subject);
            }
            $pdo->commit();
            if ($action === 'email_status') {
                $_SESSION['used_order_email_nonces'][$nonce] = true;
                if (count($_SESSION['used_order_email_nonces']) > 200) $_SESSION['used_order_email_nonces'] = array_slice($_SESSION['used_order_email_nonces'],-100,null,true);
                $result = dispatch_order_email_notification($pdo,(int)$notificationId);
                flash($result['status'] === 'sent' ? 'Sipariş güncellendi ve e-posta sunucuya iletildi. Teslimatı alıcı posta kutusunda ayrıca doğrulayın.' : 'Sipariş güncellendi; e-posta gönderilemedi (' . ($result['error'] ?? 'smtp_failure') . '). Ayarları kontrol edip başarısız bildirimi yeniden deneyin.', $result['status'] === 'sent' ? 'success' : 'warning');
            } else flash($action === 'payment_status' ? 'Ödeme durumu güncellendi.' : 'Sipariş, durum ve kargo bilgileri güncellendi.');
            header('Location: '.app_url('admin/orders/index.php').'?'.http_build_query($_GET)); exit;
        } catch(Throwable $exception) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $error=$exception instanceof RuntimeException?$exception->getMessage():'Sipariş işlemi tamamlanamadı.';
            if(!($exception instanceof RuntimeException))error_log('Order admin action failed: '.$exception->getMessage());
        }
    }
}

$where=[];$params=[];$statusFilter=(string)($_GET['status']??'');$paymentFilter=(string)($_GET['payment_status']??'');$from=trim((string)($_GET['from']??''));$to=trim((string)($_GET['to']??''));$search=trim((string)($_GET['q']??''));
if(isset($statuses[$statusFilter])){$where[]='status=:status';$params['status']=$statusFilter;}
if(isset($paymentStatuses[$paymentFilter])){$where[]='payment_status=:payment_status';$params['payment_status']=$paymentFilter;}
foreach(['from'=>$from,'to'=>$to] as $key=>$date) if($date!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if($parsed&&$parsed->format('Y-m-d')===$date){if($key==='from'){$where[]='created_at>=:from';$params['from']=$date.' 00:00:00';}else{$where[]='created_at<:to';$params['to']=$parsed->modify('+1 day')->format('Y-m-d').' 00:00:00';}}}
if($search!==''){$where[]='(customer_name LIKE :q1 OR phone LIKE :q2 OR order_number LIKE :q3)';$params['q1']=$params['q2']=$params['q3']='%'.$search.'%';}
$sql='SELECT * FROM orders'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY created_at DESC,id DESC LIMIT 300';$find=$pdo->prepare($sql);$find->execute($params);$orders=$find->fetchAll();
$itemQuery=$pdo->prepare('SELECT product_title,quantity,line_total FROM order_items WHERE order_id=:id ORDER BY id');
$notificationQuery=$pdo->prepare('SELECT id,notification_type,status,last_error,attempts,last_attempt_at,sent_at FROM order_email_notifications WHERE order_id=:id ORDER BY id DESC LIMIT 5');
$pageTitle='Siparişler';$current='orders';require __DIR__.'/../header.php';
$mailHealth=order_mail_health();
?>
<div class="page-heading"><div><h1>Siparişler</h1><p class="text-secondary">Durum, ödeme, tarih ve müşteri bilgisine göre filtreleyin. En fazla 300 kayıt gösterilir.</p></div></div>
<?php if(!$mailHealth['ready']):?><div class="alert alert-warning"><strong>E-posta bildirimi yapılandırılmamış.</strong> Eksik/uyumsuz ayar: <?=e(implode(', ',$mailHealth['missing']))?>. Gizli SMTP bilgilerini web kökü dışındaki yerel ayar dosyasında tanımlayın.</div><?php endif;?>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<form class="content-card p-3 mb-3 row g-2 align-items-end" method="get"><div class="col-md-3"><label class="form-label">Müşteri / telefon / sipariş kodu</label><input class="form-control" name="q" value="<?=e($search)?>"></div><div class="col-md-2"><label class="form-label">Sipariş durumu</label><select class="form-select" name="status"><option value="">Tüm durumlar</option><?php foreach($statuses as $key=>$label):?><option value="<?=e($key)?>" <?=$statusFilter===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div><div class="col-md-2"><label class="form-label">Ödeme durumu</label><select class="form-select" name="payment_status"><option value="">Tümü</option><?php foreach($paymentStatuses as $key=>$label):?><option value="<?=e($key)?>" <?=$paymentFilter===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div><div class="col-md-2"><label class="form-label">Başlangıç</label><input class="form-control" type="date" name="from" value="<?=e($from)?>"></div><div class="col-md-2"><label class="form-label">Bitiş</label><input class="form-control" type="date" name="to" value="<?=e($to)?>"></div><div class="col-md-1 d-flex gap-2"><button class="btn btn-primary">Ara</button></div></form>
<div class="content-card"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>SİPARİŞ</th><th>MÜŞTERİ / TESLİMAT</th><th>ÜRÜNLER</th><th>TOPLAM / ÖDEME</th><th>GÜNCELLE</th></tr></thead><tbody>
<?php foreach($orders as $order):$itemQuery->execute(['id'=>$order['id']]);$items=$itemQuery->fetchAll();$notificationQuery->execute(['id'=>$order['id']]);$notifications=$notificationQuery->fetchAll();$phone=preg_replace('/\D+/','',$order['phone']);if(str_starts_with($phone,'0'))$phone='9'.$phone;$message='Merhaba '. $order['customer_name'].', #'.$order['order_number'].' numaralı siparişinizin durumu: '.($statuses[$order['status']]??$order['status']).'.';if($order['carrier']||$order['tracking_number'])$message.=' Kargo: '.$order['carrier'].' '.$order['tracking_number'];$wa='https://wa.me/'.$phone.'?text='.rawurlencode($message);$nonce=bin2hex(random_bytes(32));?>
<tr><td><strong>#<?=e($order['order_number']??'—')?></strong><small class="d-block text-secondary"><?=e(date('d.m.Y H:i',strtotime($order['created_at'])))?></small><small class="d-block"><?=e($statuses[$order['status']]??$order['status'])?></small></td><td><?=e($order['customer_name'])?><small class="d-block"><a href="tel:<?=e($order['phone'])?>"><?=e($order['phone'])?></a></small><?php if(!empty($order['customer_email'])):?><small class="d-block"><?=e($order['customer_email'])?></small><?php endif;?><small class="d-block text-secondary"><?=e($order['district'])?> / <?=e($order['city'])?> · <?=e($order['address'])?></small></td><td><?php foreach($items as $item):?><div><?=e($item['product_title'])?> × <?= (int)$item['quantity']?> <small class="text-secondary">(₺<?=number_format((float)$item['line_total'],2,',','.')?>)</small></div><?php endforeach;?></td><td><strong>₺<?=number_format((float)$order['total'],2,',','.')?></strong><small class="d-block">Yöntem: <?=e(order_payment_method_label((string)($order['payment_method']??'')))?></small><small class="d-block">Ödeme: <?=e(order_payment_status_label((string)($order['payment_status']??'unspecified')))?></small><small class="d-block text-secondary">Kargo: <?=e($order['carrier']?:'—')?> <?=e($order['tracking_number'])?></small></td><td><form method="post" class="d-grid gap-2"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="order_id" value="<?=(int)$order['id']?>"><input type="hidden" name="email_nonce" value="<?=e($nonce)?>"><label class="small">Sipariş durumu<select class="form-select form-select-sm" name="status"><?php foreach($statuses as $key=>$label):?><option value="<?=e($key)?>" <?=$order['status']===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label><label class="small">Ödeme durumu<select class="form-select form-select-sm" name="payment_status"><?php foreach($paymentStatuses as $key=>$label):?><option value="<?=e($key)?>" <?=($order['payment_status']??'unspecified')===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label><input class="form-control form-control-sm" name="carrier" maxlength="80" placeholder="Kargo firması" value="<?=e($order['carrier'])?>"><input class="form-control form-control-sm" name="tracking_number" maxlength="120" placeholder="Kargo takip no" value="<?=e($order['tracking_number'])?>"><div class="d-flex flex-wrap gap-1"><button class="btn btn-sm btn-primary" name="action" value="status">Siparişi kaydet</button><button class="btn btn-sm btn-outline-secondary" name="action" value="payment_status">Ödeme durumunu kaydet</button><?php if(filter_var((string)$order['customer_email'],FILTER_VALIDATE_EMAIL)!==false):?><button class="btn btn-sm btn-outline-primary" name="action" value="email_status" title="Durum, ödeme ve kargo bilgisini kaydedip e-posta gönder">Kaydet ve e-posta gönder</button><?php endif;?><a class="btn btn-sm btn-success" href="<?=e($wa)?>" target="_blank" rel="noopener noreferrer">WhatsApp mesajı</a></div></form>
<?php if($notifications):?><div class="small mt-2"><strong>E-posta kayıtları</strong><?php foreach($notifications as $notice):?><div><?=e($notice['notification_type']==='order_received'?'Sipariş alındı':'Durum güncellemesi')?>: <?=e(order_email_status_label((string)$notice['status'], (string)($notice['last_error'] ?? '')))?><?php if($notice['last_error']):?> · <?=e((string)$notice['last_error'])?><?php endif;?><?php if(in_array($notice['status'],['failed','pending'],true)):?><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="order_id" value="<?=(int)$order['id']?>"><input type="hidden" name="notification_id" value="<?=(int)$notice['id']?>"><button class="btn btn-link btn-sm p-0" name="action" value="email_retry"><?=$notice['status']==='pending'?'Gönderimi dene':'Yeniden dene'?></button></form><?php endif;?></div><?php endforeach;?></div><?php endif;?></td></tr>
<?php endforeach;?><?php if(!$orders):?><tr><td colspan="5"><div class="empty-state"><strong>Eşleşen sipariş yok</strong><p>Filtreleri değiştirip tekrar deneyin.</p></div></td></tr><?php endif;?></tbody></table></div></div>
<?php require __DIR__.'/../footer.php'; ?>