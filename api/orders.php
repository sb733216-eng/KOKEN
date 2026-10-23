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
require_once __DIR__ . '/../config/rate_limit.php';

function order_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function order_text_length(string $value): int { return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value); }
function order_replay(PDO $pdo, string $requestKey): ?array
{
    $find = $pdo->prepare('SELECT id,order_number,order_date,subtotal,shipping_fee,total FROM orders WHERE request_key=:request_key LIMIT 1');
    $find->execute(['request_key' => $requestKey]);
    $order = $find->fetch();
    if (!$order) return null;
    $itemsQuery = $pdo->prepare('SELECT product_title,quantity,line_total FROM order_items WHERE order_id=:id ORDER BY id');
    $itemsQuery->execute(['id' => $order['id']]);
    $items = array_map(static fn(array $item): array => ['title' => $item['product_title'], 'quantity' => (int)$item['quantity'], 'lineTotal' => (float)$item['line_total']], $itemsQuery->fetchAll());
    return ['ok' => true, 'orderNumber' => $order['order_number'], 'orderDate' => $order['order_date'], 'items' => $items, 'subtotal' => (float)$order['subtotal'], 'shippingFee' => (float)$order['shipping_fee'], 'total' => (float)$order['total']];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['order_csrf'])) $_SESSION['order_csrf'] = bin2hex(random_bytes(32));
    order_json(['csrfToken' => $_SESSION['order_csrf']]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') order_json(['error' => 'Desteklenmeyen istek.'], 405);
$submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($submittedToken) || empty($_SESSION['order_csrf']) || !hash_equals($_SESSION['order_csrf'], $submittedToken)) {
    order_json(['error' => 'Oturum doğrulaması geçersiz. Sayfayı yenileyin.'], 419);
}
 $input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) order_json(['error' => 'Sipariş verisi okunamadı.'], 400);
$requestKey = (string)($input['requestKey'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/i', $requestKey)) order_json(['error' => 'Sipariş isteği doğrulanamadı. Lütfen yeniden deneyin.'], 422);
try {
    $replay = order_replay($pdo, $requestKey);
    if ($replay !== null) order_json($replay);
} catch (Throwable $exception) {
    error_log('Order idempotency lookup failed: ' . $exception->getMessage());
    order_json(['error' => 'Sipariş hizmeti şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.'], 503);
}
try {
    if (!rate_limit_consume($pdo, 'order-create-ip', rate_limit_client_ip(), 10, 600)) {
        order_json(['error' => 'Çok sık sipariş isteği gönderildi. Lütfen daha sonra tekrar deneyin.'], 429);
    }
} catch (Throwable $exception) {
    error_log('Order rate limit unavailable: ' . $exception->getMessage());
    order_json(['error' => 'Sipariş hizmeti şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.'], 503);
}
$name = trim((string)($input['name'] ?? ''));
$phone = trim((string)($input['phone'] ?? ''));
$city = trim((string)($input['city'] ?? ''));
$district = trim((string)($input['district'] ?? ''));
$address = trim((string)($input['address'] ?? ''));
$note = trim((string)($input['note'] ?? ''));
$payment = (string)($input['payment'] ?? '');
if (order_text_length($name) < 3 || order_text_length($name) > 100 || !preg_match('/^05\d{9}$/', $phone) || $city === '' || order_text_length($city) > 80 || $district === '' || order_text_length($district) > 80 || order_text_length($address) < 10 || order_text_length($address) > 500 || order_text_length($note) > 500 || !in_array($payment, ['iban', 'kapida'], true)) {
    order_json(['error' => 'Teslimat veya ödeme bilgileri geçersiz.'], 422);
}
$itemsInput = $input['items'] ?? null;
if (!is_array($itemsInput) || count($itemsInput) < 1 || count($itemsInput) > 50) order_json(['error' => 'Sepet içeriği geçersiz.'], 422);
$quantities = [];
foreach ($itemsInput as $item) {
    if (!is_array($item)) order_json(['error' => 'Sepet içeriği geçersiz.'], 422);
    $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $qty = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
    if ($id === false || $qty === false) order_json(['error' => 'Ürün veya adet bilgisi geçersiz.'], 422);
    $quantities[$id] = ($quantities[$id] ?? 0) + $qty;
    if ($quantities[$id] > 99) order_json(['error' => 'Ürün adedi en fazla 99 olabilir.'], 422);
}

try {
    $pdo->beginTransaction();
    $placeholders = implode(',', array_fill(0, count($quantities), '?'));
    $productQuery = $pdo->prepare("SELECT id,title,price,stock_qty,is_active FROM products WHERE id IN ($placeholders) FOR UPDATE");
    $productQuery->execute(array_keys($quantities));
    $products = [];
    foreach ($productQuery->fetchAll() as $product) $products[(int)$product['id']] = $product;
    if (count($products) !== count($quantities)) throw new DomainException('Sepetinizde artık bulunmayan bir ürün var.');

    $subtotalCents = 0;
    $responseItems = [];
    foreach ($quantities as $id => $qty) {
        $product = $products[$id];
        if (!(int)$product['is_active']) throw new DomainException('Sepetinizde satışta olmayan bir ürün var.');
        if ($product['stock_qty'] !== null && (int)$product['stock_qty'] < $qty) throw new DomainException($product['title'] . ' için yeterli stok yok.');
        $unitCents = (int)round((float)$product['price'] * 100);
        $subtotalCents += $unitCents * $qty;
    }
    $settings = $pdo->query("SELECT setting_key,setting_value FROM store_settings WHERE setting_key IN ('free_shipping_limit','shipping_fee')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $limitCents = (int)round((float)($settings['free_shipping_limit'] ?? 750) * 100);
    $feeCents = $subtotalCents > 0 && $subtotalCents < $limitCents ? (int)round((float)($settings['shipping_fee'] ?? 60) * 100) : 0;
    $totalCents = $subtotalCents + $feeCents;

    $orderDate = (string)$pdo->query('SELECT CURDATE()')->fetchColumn();
    $trackingCode = bin2hex(random_bytes(16));
    $pdo->exec('INSERT IGNORE INTO public_order_code_sequence (id, last_value) VALUES (1, 99)');
    $sequence = $pdo->query('SELECT last_value FROM public_order_code_sequence WHERE id=1 FOR UPDATE');
    $orderNumber = (string)max(100, ((int)$sequence->fetchColumn()) + 1);
    $nextCode = $pdo->prepare('UPDATE public_order_code_sequence SET last_value=:value WHERE id=1');
    $nextCode->execute(['value' => $orderNumber]);
    $insertOrder = $pdo->prepare('INSERT INTO orders (order_number,tracking_code,request_key,order_date,customer_name,phone,city,district,address,note,payment_method,subtotal,shipping_fee,total,status) VALUES (:number,:tracking_code,:request_key,:order_date,:name,:phone,:city,:district,:address,:note,:payment,:subtotal,:shipping,:total,:status)');
    $insertOrder->execute(['number' => $orderNumber, 'tracking_code' => $trackingCode, 'request_key' => $requestKey, 'order_date' => $orderDate, 'name' => $name, 'phone' => $phone, 'city' => $city, 'district' => $district, 'address' => $address, 'note' => $note, 'payment' => $payment, 'subtotal' => number_format($subtotalCents / 100, 2, '.', ''), 'shipping' => number_format($feeCents / 100, 2, '.', ''), 'total' => number_format($totalCents / 100, 2, '.', ''), 'status' => 'pending_confirmation']);
    $orderId = (int)$pdo->lastInsertId();
    $insertItem = $pdo->prepare('INSERT INTO order_items (order_id,product_id,product_title,unit_price,quantity,line_total) VALUES (:order_id,:product_id,:title,:price,:quantity,:line_total)');
    $updateStock = $pdo->prepare('UPDATE products SET stock_qty = stock_qty - :quantity WHERE id = :id AND stock_qty IS NOT NULL AND stock_qty >= :quantity_check');
    $movement = $pdo->prepare("INSERT INTO stock_movements(product_id,product_title,change_qty,stock_before,stock_after,movement_type,reference_id,note) VALUES(:id,:title,:delta,:before,:after,'order',:reference,'Stok, sipariş verildiğinde düşüldü')");
    foreach ($quantities as $id => $qty) {
        $product = $products[$id];
        $unitCents = (int)round((float)$product['price'] * 100);
        $insertItem->execute(['order_id' => $orderId, 'product_id' => $id, 'title' => $product['title'], 'price' => number_format($unitCents / 100, 2, '.', ''), 'quantity' => $qty, 'line_total' => number_format($unitCents * $qty / 100, 2, '.', '')]);
        $responseItems[] = ['title' => $product['title'], 'quantity' => $qty, 'lineTotal' => $unitCents * $qty / 100];
        if ($product['stock_qty'] !== null) {
            $updateStock->execute(['quantity' => $qty, 'id' => $id, 'quantity_check' => $qty]);
            if ($updateStock->rowCount() !== 1) throw new DomainException($product['title'] . ' stok miktarı aynı anda değişti; lütfen tekrar deneyin.');
            $movement->execute(['id'=>$id,'title'=>$product['title'],'delta'=>-$qty,'before'=>(int)$product['stock_qty'],'after'=>(int)$product['stock_qty']-$qty,'reference'=>$orderId]);
        }
    }
    $pdo->commit();
    order_json(['ok' => true, 'orderNumber' => $orderNumber, 'orderDate' => $orderDate, 'items' => $responseItems, 'subtotal' => $subtotalCents / 100, 'shippingFee' => $feeCents / 100, 'total' => $totalCents / 100]);
} catch (DomainException $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    order_json(['error' => $exception->getMessage()], 409);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($exception instanceof PDOException && (string)$exception->getCode() === '23000') {
        try {
            $replay = order_replay($pdo, $requestKey);
            if ($replay !== null) order_json($replay);
        } catch (Throwable $lookupException) {
            error_log('Order idempotency replay failed: ' . $lookupException->getMessage());
        }
    }
    error_log('Order create failed: ' . $exception->getMessage());
    order_json(['error' => 'Sipariş kaydedilemedi. Lütfen tekrar deneyin veya WhatsApp üzerinden bize ulaşın.'], 500);
}
