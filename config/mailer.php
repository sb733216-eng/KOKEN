<?php
declare(strict_types=1);

require_once __DIR__ . '/order_helpers.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

final class OrderMailException extends RuntimeException
{
    public function __construct(public readonly string $reasonCode)
    {
        parent::__construct($reasonCode);
    }
}

function order_mail_configuration(): array
{
    static $configuration;
    if (is_array($configuration ?? null)) return $configuration;

    $localPath = trim((string)(getenv('KOKEN_MAIL_CONFIG') ?: ''));
    if ($localPath === '') $localPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'koken-mail.php';
    $local = [];
    $configError = null;
    if (is_file($localPath)) {
        $realConfig = realpath($localPath);
        $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($realConfig === false || ($documentRoot && str_starts_with(strtolower($realConfig), strtolower(rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)))) {
            $configError = 'config_path_invalid';
        } else {
            try {
                $loaded = require $realConfig;
                if (is_array($loaded)) $local = $loaded;
                else $configError = 'config_file_invalid';
            } catch (Throwable) {
                $configError = 'config_file_invalid';
            }
        }
    }

    $readSetting = static function (string $environmentName, string $key, mixed $default = '') use ($local): mixed {
        $environmentValue = getenv($environmentName);
        if ($environmentValue !== false) return $environmentValue;
        return $local[$key] ?? $default;
    };
    $configuration = [
        'host' => trim((string)$readSetting('SMTP_HOST', 'host', 'smtp.gmail.com')),
        'port' => (int)$readSetting('SMTP_PORT', 'port', 587),
        'encryption' => strtolower(trim((string)$readSetting('SMTP_ENCRYPTION', 'encryption', 'starttls'))),
        'username' => trim((string)$readSetting('SMTP_USERNAME', 'username')),
        'password' => (string)$readSetting('SMTP_PASSWORD', 'password'),
        'from_address' => trim((string)$readSetting('MAIL_FROM_ADDRESS', 'from_address')),
        'from_name' => trim((string)$readSetting('MAIL_FROM_NAME', 'from_name', 'Köken Fındık')),
        'config_error' => $configError,
    ];
    return $configuration;
}

function order_mail_health(): array
{
    $config = order_mail_configuration();
    $missing = [];
    if ($config['config_error'] !== null) $missing[] = 'KOKEN_MAIL_CONFIG';
    if ($config['host'] !== 'smtp.gmail.com') $missing[] = 'SMTP_HOST (smtp.gmail.com olmalı)';
    if ($config['port'] !== 587) $missing[] = 'SMTP_PORT (587 olmalı)';
    if ($config['encryption'] !== 'starttls') $missing[] = 'SMTP_ENCRYPTION (starttls olmalı)';
    if ($config['username'] === '' || filter_var($config['username'], FILTER_VALIDATE_EMAIL) === false) $missing[] = 'SMTP_USERNAME';
    if ($config['password'] === '') $missing[] = 'SMTP_PASSWORD';
    if ($config['from_address'] === '' || filter_var($config['from_address'], FILTER_VALIDATE_EMAIL) === false) $missing[] = 'MAIL_FROM_ADDRESS';
    if ($config['from_name'] === '') $missing[] = 'MAIL_FROM_NAME';
    if (!is_file(dirname(__DIR__) . '/vendor/autoload.php')) $missing[] = 'PHPMailer (composer install)';
    return ['ready' => $missing === [], 'missing' => array_values(array_unique($missing))];
}

function order_mail_html_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function order_mail_money(float|string $value): string
{
    return '₺' . number_format((float)$value, 2, ',', '.');
}

function order_mail_whatsapp_url(array $order): string
{
    $phone = '905313385955';
    $number = preg_replace('/[^0-9]/', '', (string)($order['order_number'] ?? ''));
    $method = order_payment_method_label((string)($order['payment_method'] ?? ''));
    $total = order_mail_money((string)($order['total'] ?? 0));
    $message = 'Merhaba, #' . $number . ' numaralı siparişimi kaydettim. Toplam: ' . $total . '. Ödeme yöntemi: ' . $method . '. Siparişimin onaylanmasını rica ederim.';
    return 'https://api.whatsapp.com/send?phone=' . rawurlencode($phone) . '&text=' . rawurlencode($message);
}

function order_mail_content(array $order, array $items, string $notificationType): array
{
    $number = preg_replace('/[^0-9]/', '', (string)($order['order_number'] ?? ''));
    $name = trim((string)($order['customer_name'] ?? 'Müşteri'));
    $status = order_status_labels()[(string)($order['status'] ?? '')] ?? 'Güncellendi';
    $method = order_payment_method_label((string)($order['payment_method'] ?? ''));
    $paymentStatus = order_payment_status_label((string)($order['payment_status'] ?? 'unspecified'));
    $received = $notificationType === 'order_received';
    $subject = $received
        ? 'Sipariş kaydınız alındı — WhatsApp onayı bekleniyor (#' . $number . ')'
        : 'Sipariş #' . $number . ' durum ve kargo bilgisi güncellendi';

    $plain = [
        'Merhaba ' . $name . ',',
        '',
        $received ? 'Sipariş kaydınız alındı. Sipariş #' . $number . ' veritabanına kaydedildi.' : 'Sipariş #' . $number . ' bilgileriniz güncellendi.',
        'Durum: ' . $status,
        '',
        'Ürünler:',
    ];
    foreach ($items as $item) $plain[] = '• ' . (string)$item['product_title'] . ' × ' . (int)$item['quantity'] . ' — ' . order_mail_money((string)$item['line_total']);
    $plain[] = '';
    $plain[] = 'Ara toplam: ' . order_mail_money((string)$order['subtotal']);
    $plain[] = 'Kargo: ' . order_mail_money((string)$order['shipping_fee']);
    $plain[] = 'Genel toplam: ' . order_mail_money((string)$order['total']);
    $plain[] = 'Ödeme yöntemi: ' . $method;
    $plain[] = 'Ödeme durumu: ' . $paymentStatus;
    if ((string)($order['carrier'] ?? '') !== '' || (string)($order['tracking_number'] ?? '') !== '') {
        $plain[] = 'Kargo firması: ' . ((string)$order['carrier'] !== '' ? (string)$order['carrier'] : 'Belirtilmedi');
        $plain[] = 'Kargo takip numarası: ' . ((string)$order['tracking_number'] !== '' ? (string)$order['tracking_number'] : 'Henüz eklenmedi');
    }
    $plain[] = '';
    $plain[] = 'Önemli: Sipariş kaydı, satıcı onayından ayrıdır. Siparişinizin işleme alınması için WhatsApp üzerinden #' . $number . ' sipariş numarasıyla bildirim yapın. Satıcı onayından sonra sipariş işleme alınır.';
    $plain[] = 'Bu e-posta bilgilendirme içindir; WhatsApp onayının yerine geçmez. WhatsApp bağlantısına tıklamak mesajın gönderildiği veya siparişin onaylandığı anlamına gelmez.';
    $plain[] = '';
    $plain[] = 'Köken Fındık';

    $rowHtml = '';
    foreach ($items as $item) {
        $rowHtml .= '<tr><td style="padding:8px;border-bottom:1px solid #e5e7eb">' . order_mail_html_escape((string)$item['product_title']) . ' × ' . (int)$item['quantity'] . '</td><td style="padding:8px;text-align:right;border-bottom:1px solid #e5e7eb">' . order_mail_money((string)$item['line_total']) . '</td></tr>';
    }
    $whatsappUrl = order_mail_html_escape(order_mail_whatsapp_url($order));
    $html = '<!doctype html><html lang="tr"><body style="margin:0;padding:24px;background:#f5f7f4;font-family:Arial,sans-serif;color:#202820"><main style="max-width:640px;margin:auto;padding:24px;background:#fff;border:1px solid #dce5dc;border-radius:12px">'
        . '<h1 style="font-size:22px;color:#1c5a27">' . order_mail_html_escape($subject) . '</h1>'
        . '<p>Merhaba ' . order_mail_html_escape($name) . ',</p><p>' . ($received ? 'Sipariş kaydınız alındı; sipariş satıcı onayı bekliyor.' : 'Sipariş bilgileriniz güncellendi.') . '</p>'
        . '<p><strong>Sipariş durumu:</strong> ' . order_mail_html_escape($status) . '</p>'
        . '<table role="presentation" style="width:100%;border-collapse:collapse"><thead><tr><th align="left" style="padding:8px;border-bottom:2px solid #dce5dc">Ürün</th><th align="right" style="padding:8px;border-bottom:2px solid #dce5dc">Tutar</th></tr></thead><tbody>' . $rowHtml . '</tbody></table>'
        . '<p>Ara toplam: ' . order_mail_money((string)$order['subtotal']) . '<br>Kargo: ' . order_mail_money((string)$order['shipping_fee']) . '<br><strong>Genel toplam: ' . order_mail_money((string)$order['total']) . '</strong></p>'
        . '<p>Ödeme yöntemi: ' . order_mail_html_escape($method) . '<br>Ödeme durumu: ' . order_mail_html_escape($paymentStatus) . '</p>';
    if ((string)($order['carrier'] ?? '') !== '' || (string)($order['tracking_number'] ?? '') !== '') {
        $html .= '<p>Kargo firması: ' . order_mail_html_escape((string)($order['carrier'] ?: 'Belirtilmedi')) . '<br>Kargo takip numarası: ' . order_mail_html_escape((string)($order['tracking_number'] ?: 'Henüz eklenmedi')) . '</p>';
    }
    $html .= '<div style="margin:20px 0;padding:14px;background:#fff4d6;border-radius:8px"><strong>Sipariş onayı gereklidir.</strong> Siparişinizi WhatsApp üzerinden #' . order_mail_html_escape($number) . ' numarasıyla bildirin. Satıcı onayından sonra siparişiniz işleme alınır. E-posta bildirimi bu onayın yerine geçmez.</div>'
        . '<p><a href="' . $whatsappUrl . '" style="display:inline-block;padding:12px 18px;background:#1c5a27;color:#fff;text-decoration:none;border-radius:8px">WhatsApp ile sipariş numarasını bildir</a></p>'
        . '<p style="font-size:13px;color:#5b665b">Bağlantıyı açmak mesajın gönderildiği veya siparişin onaylandığı anlamına gelmez. Mesaj gönderilemezse siparişiniz kayıtlı kalır; WhatsApp üzerinden tekrar deneyebilirsiniz.</p>'
        . '<p>Köken Fındık</p></main></body></html>';

    return ['subject' => $subject, 'text' => implode("\r\n", $plain), 'html' => $html];
}

function order_mail_send(string $recipient, array $content): void
{
    $health = order_mail_health();
    if (!$health['ready']) throw new OrderMailException('not_configured');
    if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) throw new OrderMailException('invalid_recipient');

    require_once dirname(__DIR__) . '/vendor/autoload.php';
    if (!class_exists(PHPMailer::class)) throw new OrderMailException('mailer_unavailable');
    $config = order_mail_configuration();
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 15;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($config['from_address'], $config['from_name']);
        $mail->addAddress($recipient);
        $mail->Subject = (string)$content['subject'];
        $mail->isHTML(true);
        $mail->Body = (string)$content['html'];
        $mail->AltBody = (string)$content['text'];
        $mail->send();
    } catch (PHPMailerException $exception) {
        throw new OrderMailException('smtp_failure');
    } catch (Throwable $exception) {
        throw new OrderMailException('smtp_failure');
    }
}

function queue_order_email_notification(PDO $pdo, int $orderId, string $type, string $idempotencyKey, string $recipient, string $subject): int
{
    $insert = $pdo->prepare("INSERT INTO order_email_notifications (order_id,notification_type,idempotency_key,recipient,subject,status) VALUES (:order_id,:type,:key,:recipient,:subject,'pending') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $insert->execute(['order_id' => $orderId, 'type' => $type, 'key' => $idempotencyKey, 'recipient' => $recipient, 'subject' => $subject]);
    $id = (int)$pdo->lastInsertId();
    if ($id > 0) return $id;
    $find = $pdo->prepare('SELECT id FROM order_email_notifications WHERE idempotency_key=:key');
    $find->execute(['key' => $idempotencyKey]);
    return (int)$find->fetchColumn();
}

function dispatch_order_email_notification(PDO $pdo, int $notificationId): array
{
    $health = order_mail_health();
    try {
        $pdo->beginTransaction();
        $query = $pdo->prepare('SELECT id,order_id,notification_type,recipient,status FROM order_email_notifications WHERE id=:id FOR UPDATE');
        $query->execute(['id' => $notificationId]);
        $notification = $query->fetch(PDO::FETCH_ASSOC);
        if (!$notification) { $pdo->rollBack(); return ['status' => 'missing', 'error' => 'notification_missing']; }
        if ($notification['status'] === 'sent') { $pdo->commit(); return ['status' => 'sent', 'error' => null]; }
        if ($notification['status'] === 'sending') { $pdo->commit(); return ['status' => 'sending', 'error' => null]; }
        if (!in_array($notification['status'], ['pending', 'failed'], true)) { $pdo->commit(); return ['status' => (string)$notification['status'], 'error' => null]; }
        if (!$health['ready']) {
            $pdo->prepare("UPDATE order_email_notifications SET status='failed',last_error='not_configured',last_attempt_at=NOW() WHERE id=:id")->execute(['id' => $notificationId]);
            $pdo->commit();
            return ['status' => 'failed', 'error' => 'not_configured'];
        }
        if (filter_var((string)$notification['recipient'], FILTER_VALIDATE_EMAIL) === false) {
            $pdo->prepare("UPDATE order_email_notifications SET status='failed',last_error='invalid_recipient',last_attempt_at=NOW() WHERE id=:id")->execute(['id' => $notificationId]);
            $pdo->commit();
            return ['status' => 'failed', 'error' => 'invalid_recipient'];
        }
        $pdo->prepare("UPDATE order_email_notifications SET status='sending',attempts=attempts+1,last_error=NULL,last_attempt_at=NOW() WHERE id=:id")->execute(['id' => $notificationId]);
        $pdo->commit();

        $load = $pdo->prepare('SELECT n.notification_type,n.recipient,o.* FROM order_email_notifications n JOIN orders o ON o.id=n.order_id WHERE n.id=:id');
        $load->execute(['id' => $notificationId]);
        $order = $load->fetch(PDO::FETCH_ASSOC);
        if (!$order) throw new OrderMailException('order_missing');
        $itemsQuery = $pdo->prepare('SELECT product_title,quantity,line_total FROM order_items WHERE order_id=:id ORDER BY id');
        $itemsQuery->execute(['id' => $order['id']]);
        $content = order_mail_content($order, $itemsQuery->fetchAll(PDO::FETCH_ASSOC), (string)$order['notification_type']);
        order_mail_send((string)$order['recipient'], $content);
        $pdo->prepare("UPDATE order_email_notifications SET status='sent',last_error=NULL,sent_at=NOW() WHERE id=:id AND status='sending'")->execute(['id' => $notificationId]);
        if ($order['notification_type'] === 'status_update') {
            $pdo->prepare('UPDATE orders SET status_email_sent_at=NOW() WHERE id=:id')->execute(['id' => $order['id']]);
        }
        return ['status' => 'sent', 'error' => null];
    } catch (OrderMailException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        try { $pdo->prepare("UPDATE order_email_notifications SET status='failed',last_error=:error WHERE id=:id AND status='sending'")->execute(['error' => $exception->reasonCode, 'id' => $notificationId]); } catch (Throwable) { }
        error_log('Order email failed; notification_id=' . $notificationId . '; reason=' . $exception->reasonCode);
        return ['status' => 'failed', 'error' => $exception->reasonCode];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        try { $pdo->prepare("UPDATE order_email_notifications SET status='failed',last_error='smtp_failure' WHERE id=:id AND status='sending'")->execute(['id' => $notificationId]); } catch (Throwable) { }
        error_log('Order email failed; notification_id=' . $notificationId . '; reason=internal_failure');
        return ['status' => 'failed', 'error' => 'smtp_failure'];
    }
}

function order_email_delivery(PDO $pdo, int $orderId, string $type = 'order_received'): ?array
{
    $query = $pdo->prepare('SELECT id,status,last_error,attempts,last_attempt_at,sent_at FROM order_email_notifications WHERE order_id=:order_id AND notification_type=:type ORDER BY id DESC LIMIT 1');
    $query->execute(['order_id' => $orderId, 'type' => $type]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
