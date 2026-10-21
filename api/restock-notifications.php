<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if(session_status()!==PHP_SESSION_ACTIVE){$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();}
require_once __DIR__.'/../config/db.php';
function restock_json(array $body,int $status=200):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($_SERVER['REQUEST_METHOD']==='GET'){if(empty($_SESSION['restock_csrf']))$_SESSION['restock_csrf']=bin2hex(random_bytes(32));restock_json(['csrfToken'=>$_SESSION['restock_csrf']]);}
if($_SERVER['REQUEST_METHOD']!=='POST')restock_json(['error'=>'Desteklenmeyen istek.'],405);
$token=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if(!is_string($token)||empty($_SESSION['restock_csrf'])||!hash_equals($_SESSION['restock_csrf'],$token))restock_json(['error'=>'Sayfayı yenileyip tekrar deneyin.'],419);
$input=json_decode((string)file_get_contents('php://input'),true);$id=is_array($input)?filter_var($input['productId']??null,FILTER_VALIDATE_INT):false;$email=is_array($input)?trim((string)($input['email']??'')):'';
if(!$id||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||empty($input['consent']))restock_json(['error'=>'Geçerli e-posta, ürün ve saklama onayı gereklidir.'],422);
$rate=$_SESSION['restock_rate']??['start'=>time(),'count'=>0];if(time()-(int)$rate['start']>3600)$rate=['start'=>time(),'count'=>0];if((int)$rate['count']>=5)restock_json(['error'=>'Saatlik talep sınırına ulaşıldı.'],429);$rate['count']++;$_SESSION['restock_rate']=$rate;
$product=$pdo->prepare('SELECT id,title,stock_qty,is_active FROM products WHERE id=:id');$product->execute(['id'=>$id]);$row=$product->fetch();if(!$row||!(int)$row['is_active'])restock_json(['error'=>'Ürün bulunamadı.'],404);if($row['stock_qty']===null||(int)$row['stock_qty']>0)restock_json(['error'=>'Bu ürün şu anda stokta tükenmiş görünmüyor.'],409);
$email=function_exists('mb_strtolower')?mb_strtolower($email,'UTF-8'):strtolower($email);$save=$pdo->prepare("INSERT INTO restock_notifications(product_id,product_title,email,status) VALUES(:id,:title,:email,'pending') ON DUPLICATE KEY UPDATE product_title=VALUES(product_title),status='pending',requested_at=CURRENT_TIMESTAMP,notified_at=NULL");$save->execute(['id'=>$id,'title'=>$row['title'],'email'=>$email]);restock_json(['ok'=>true,'message'=>'Talebiniz kaydedildi. E-posta gönderimi henüz etkin değil; talep yönetim panelinde saklanıyor.']);
