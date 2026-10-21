<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
require_once __DIR__ . '/../config/db.php';
function track_json(array $data, int $status = 200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['track_csrf'])) $_SESSION['track_csrf'] = bin2hex(random_bytes(32));
    track_json(['csrfToken' => $_SESSION['track_csrf']]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') track_json(['error' => 'Desteklenmeyen istek.'], 405);
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($token) || empty($_SESSION['track_csrf']) || !hash_equals($_SESSION['track_csrf'], $token)) track_json(['error' => 'Sayfayı yenileyip tekrar deneyin.'], 419);
$now = time();
$rate = $_SESSION['track_rate'] ?? ['start' => $now, 'count' => 0];
if ($now - (int)$rate['start'] >= 600) $rate = ['start' => $now, 'count' => 0];
if ((int)$rate['count'] >= 10) track_json(['error' => 'Çok fazla sorgu yapıldı. 10 dakika sonra tekrar deneyin.'], 429);
$rate['count'] = (int)$rate['count'] + 1;
$_SESSION['track_rate'] = $rate;
$input = json_decode((string)file_get_contents('php://input'), true);
$code = is_array($input) ? trim((string)($input['code'] ?? '')) : '';
$phone = is_array($input) ? preg_replace('/\D+/', '', (string)($input['phone'] ?? '')) : '';
if (!preg_match('/^\d{3,5}$/', $code) || !preg_match('/^05\d{9}$/', (string)$phone)) track_json(['error' => 'Sipariş kodunu (3-5 rakam) ve siparişte kullandığınız telefonu kontrol edin.'], 422);
$find = $pdo->prepare('SELECT id,tracking_code,order_date,status,created_at,updated_at FROM orders WHERE tracking_code=:code AND phone=:phone ORDER BY order_date DESC,id DESC LIMIT 1');
$find->execute(['code' => $code, 'phone' => $phone]);
$order = $find->fetch();
if (!$order) track_json(['error' => 'Bu kod ve telefonla eşleşen sipariş bulunamadı. Bilgileri kontrol edip tekrar deneyin.'], 404);
$labels = [
    'pending_confirmation' => 'WhatsApp onayı bekleniyor', 'confirmed' => 'Sipariş onaylandı',
    'preparing' => 'Sipariş hazırlanıyor', 'shipped' => 'Sipariş kargoya verildi',
    'completed' => 'Sipariş tamamlandı', 'cancelled' => 'Sipariş iptal edildi',
];
$items = $pdo->prepare('SELECT product_title,quantity FROM order_items WHERE order_id=:id ORDER BY id');
$items->execute(['id' => $order['id']]);
track_json([
    'code' => $order['tracking_code'],
    'date' => $order['order_date'],
    'status' => $labels[$order['status']] ?? 'Sipariş durumu güncelleniyor',
    'statusCode' => $order['status'],
    'updatedAt' => $order['updated_at'],
    'items' => $items->fetchAll(),
]);
