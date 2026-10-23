<?php
require_once __DIR__ . '/../auth.php'; require_admin();
$statuses = ['pending_confirmation'=>'WhatsApp onayı bekleniyor','confirmed'=>'Onaylandı','preparing'=>'Hazırlanıyor','shipped'=>'Kargoda','completed'=>'Tamamlandı','cancelled'=>'İptal edildi'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT); $newStatus = (string)($_POST['status'] ?? ''); $action = (string)($_POST['action'] ?? 'status');
    if (!$orderId) $error = 'Sipariş bilgisi geçersiz.';
    elseif ($action === 'status' && !isset($statuses[$newStatus])) $error = 'Sipariş durumu geçersiz.';
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
        if ($order['status'] !== $newStatus && in_array($newStatus,['cancelled'],true) || ($order['status']==='cancelled' && $newStatus!=='cancelled')) {
            $items=$pdo->prepare('SELECT oi.product_id,oi.product_title,oi.quantity,p.stock_qty FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=:id FOR UPDATE'); $items->execute(['id'=>$orderId]);
            foreach ($items->fetchAll() as $it) if ($it['product_id']!==null && $it['stock_qty']!==null) {
                $before=(int)$it['stock_qty']; $qty=(int)$it['quantity']; $delta=$newStatus==='cancelled' ? $qty : -$qty;
                if ($delta<0 && $before<$qty) throw new RuntimeException('Sipariş yeniden açılamadı; yeterli stok yok.');
                $pdo->prepare('UPDATE products SET stock_qty=stock_qty+:d WHERE id=:id')->execute(['d'=>$delta,'id'=>$it['product_id']]);
                $pdo->prepare("INSERT INTO stock_movements(product_id,product_title,change_qty,stock_before,stock_after,movement_type,reference_id,note) VALUES(:pid,:title,:change,:before,:after,:type,:ref,:note)")->execute(['pid'=>$it['product_id'],'title'=>$it['product_title'],'change'=>$delta,'before'=>$before,'after'=>$before+$delta,'type'=>$delta>0?'order_restore':'order_reserve','ref'=>$orderId,'note'=>$delta>0?'Sipariş iptali: stok iade':'İptal edilen sipariş yeniden açıldı']);
            }
        }
        $carrier=trim((string)($_POST['carrier']??'')); $tracking=trim((string)($_POST['tracking_number']??''));
        $length=static fn(string $s):int=>function_exists('mb_strlen')?mb_strlen($s,'UTF-8'):strlen($s);
        if ($length($carrier)>80 || $length($tracking)>120) throw new RuntimeException('Kargo firması veya takip numarası çok uzun.');
        $pdo->prepare('UPDATE orders SET status=:status,carrier=:carrier,tracking_number=:tracking WHERE id=:id')->execute(['status'=>$newStatus,'carrier'=>$carrier,'tracking'=>$tracking,'id'=>$orderId]);
        $pdo->commit(); flash('Sipariş, durum ve kargo bilgileri güncellendi.'); header('Location: '.app_url('admin/orders/index.php').'?'.http_build_query($_GET)); exit;
    } catch(Throwable $ex) { if($pdo->inTransaction())$pdo->rollBack(); $error=$ex instanceof RuntimeException?$ex->getMessage():'Sipariş işlemi tamamlanamadı.'; if(!($ex instanceof RuntimeException))error_log('Order admin action failed: '.$ex->getMessage()); }
}
$where=[];$params=[];$statusFilter=(string)($_GET['status']??'');$from=trim((string)($_GET['from']??''));$to=trim((string)($_GET['to']??''));$search=trim((string)($_GET['q']??''));
if(isset($statuses[$statusFilter])){$where[]='status=:status';$params['status']=$statusFilter;}
foreach(['from'=>$from,'to'=>$to] as $key=>$date) if($date!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if($parsed&&$parsed->format('Y-m-d')===$date){if($key==='from'){$where[]='created_at>=:from';$params['from']=$date.' 00:00:00';}else{$where[]='created_at<:to';$params['to']=$parsed->modify('+1 day')->format('Y-m-d').' 00:00:00';}}}
if($search!==''){$where[]='(customer_name LIKE :q1 OR phone LIKE :q2 OR order_number LIKE :q3)';$params['q1']=$params['q2']=$params['q3']='%'.$search.'%';}
$sql='SELECT * FROM orders'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY created_at DESC,id DESC LIMIT 300';$find=$pdo->prepare($sql);$find->execute($params);$orders=$find->fetchAll();
$itemQuery=$pdo->prepare('SELECT product_title,quantity,line_total FROM order_items WHERE order_id=:id ORDER BY id');
$pageTitle='Siparişler';$current='orders';require __DIR__.'/../header.php';
?>
<div class="page-heading"><div><p class="eyebrow">SATIŞ</p><h1>Siparişler</h1><p class="text-secondary">Durum, tarih, müşteri adı, telefon veya sipariş koduyla filtreleyin. En fazla 300 kayıt gösterilir.</p></div></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<form class="content-card p-3 mb-3 row g-2 align-items-end" method="get"><div class="col-md-4"><label class="form-label">Müşteri / telefon / sipariş kodu</label><input class="form-control" name="q" value="<?=e($search)?>"></div><div class="col-md-2"><label class="form-label">Durum</label><select class="form-select" name="status"><option value="">Tüm durumlar</option><?php foreach($statuses as $key=>$label):?><option value="<?=e($key)?>" <?=$statusFilter===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div><div class="col-md-2"><label class="form-label">Başlangıç</label><input class="form-control" type="date" name="from" value="<?=e($from)?>"></div><div class="col-md-2"><label class="form-label">Bitiş</label><input class="form-control" type="date" name="to" value="<?=e($to)?>"></div><div class="col-md-2 d-flex gap-2"><button class="btn btn-primary">Filtrele</button><a class="btn btn-light" href="<?=e(app_url('admin/orders/index.php'))?>">Temizle</a></div></form>
<div class="content-card"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>SİPARİŞ</th><th>MÜŞTERİ / TESLİMAT</th><th>ÜRÜNLER</th><th>TOPLAM</th><th>GÜNCELLE</th></tr></thead><tbody>
<?php foreach($orders as $order):$itemQuery->execute(['id'=>$order['id']]);$items=$itemQuery->fetchAll();$phone=preg_replace('/\D+/','',$order['phone']);if(str_starts_with($phone,'0'))$phone='9'.$phone;$message='Merhaba '. $order['customer_name'].', #'.$order['order_number'].' numaralı siparişinizin durumu: '.($statuses[$order['status']]??$order['status']).'.';if($order['carrier']||$order['tracking_number'])$message.=' Kargo: '.$order['carrier'].' '.$order['tracking_number'];$wa='https://wa.me/'.$phone.'?text='.rawurlencode($message);?>
<tr><td><strong>#<?=e($order['order_number']??'—')?></strong><small class="d-block text-secondary"><?=e(date('d.m.Y H:i',strtotime($order['created_at'])))?></small></td><td><?=e($order['customer_name'])?><small class="d-block"><a href="tel:<?=e($order['phone'])?>"><?=e($order['phone'])?></a></small><small class="d-block text-secondary"><?=e($order['district'])?> / <?=e($order['city'])?> · <?=e($order['address'])?></small></td><td><?php foreach($items as $item):?><div><?=e($item['product_title'])?> × <?= (int)$item['quantity']?> <small class="text-secondary">(₺<?=number_format((float)$item['line_total'],2,',','.')?>)</small></div><?php endforeach;?></td><td><strong>₺<?=number_format((float)$order['total'],2,',','.')?></strong><small class="d-block text-secondary">Kargo: <?=e($order['carrier']?:'—')?> <?=e($order['tracking_number'])?></small></td><td><form method="post" class="d-grid gap-2"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="order_id" value="<?=(int)$order['id']?>"><select class="form-select form-select-sm" name="status"><?php foreach($statuses as $key=>$label):?><option value="<?=e($key)?>" <?=$order['status']===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select><input class="form-control form-control-sm" name="carrier" maxlength="80" placeholder="Kargo firması" value="<?=e($order['carrier'])?>"><input class="form-control form-control-sm" name="tracking_number" maxlength="120" placeholder="Kargo takip no" value="<?=e($order['tracking_number'])?>"><div class="d-flex gap-1"><button class="btn btn-sm btn-primary" name="action" value="status">Kaydet</button><a class="btn btn-sm btn-success" href="<?=e($wa)?>" target="_blank" rel="noopener">WhatsApp mesajı</a></div></form><form method="post" class="mt-1" onsubmit="return confirm('Bu siparişi silmek istediğinize emin misiniz?')"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="order_id" value="<?=(int)$order['id']?>"><button class="btn btn-sm btn-outline-danger" name="action" value="delete">Sil</button></form></td></tr>
<?php endforeach;?><?php if(!$orders):?><tr><td colspan="5"><div class="empty-state"><strong>Eşleşen sipariş yok</strong><p>Filtreleri değiştirip tekrar deneyin.</p></div></td></tr><?php endif;?></tbody></table></div></div>
<?php require __DIR__.'/../footer.php';?>
