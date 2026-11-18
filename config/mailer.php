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
   if ($localPath === '') $localPath = dirname(__DIR__, 2) . '/private/koken-mail.php';
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
    $mailAddress = trim((string)$readSetting('MAIL_ADDRESS', 'address', 'info@kokenfindik.com'));
    $smtpUsername = trim((string)$readSetting('SMTP_USERNAME', 'username', ''));
    $fromAddress = trim((string)$readSetting('MAIL_FROM_ADDRESS', 'from_address', ''));
    if ($smtpUsername === '') $smtpUsername = $mailAddress;
    if ($fromAddress === '') $fromAddress = $mailAddress;
    $configuration = [
        'host' => trim((string)$readSetting('SMTP_HOST', 'host', 'smtp.gmail.com')),
        'port' => (int)$readSetting('SMTP_PORT', 'port', 587),
        'encryption' => strtolower(trim((string)$readSetting('SMTP_ENCRYPTION', 'encryption', 'starttls'))),
        'address' => $mailAddress,
        'username' => $smtpUsername,
        'password' => (string)$readSetting('SMTP_PASSWORD', 'password'),
        'from_address' => $fromAddress,
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
    if ($config['host'] !== 'smtp.turkticaret.net') { $missing[] = 'SMTP_HOST (smtp.turkticaret.net olmalı)';}
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
    return 'https://api.whatsapp.com/send?phone=905395982499';
}

function order_mail_status_presentation(string $status, bool $received): array
{
    if ($received) return [
        'subject' => 'Siparişiniz kaydedildi',
        'label' => 'Kayıt alındı',
        'description' => 'Siparişinizi kaydettik. Siparişiniz satıcı onayından sonra işleme alınacaktır.',
    ];

    return match ($status) {
        'confirmed' => ['subject'=>'Siparişiniz onaylandı','label'=>'Onaylandı','description'=>'Siparişiniz onaylandı ve işleme alındı.'],
        'preparing' => ['subject'=>'Siparişiniz hazırlanıyor','label'=>'Hazırlanıyor','description'=>'Siparişiniz özenle hazırlanıyor.'],
        'shipped' => ['subject'=>'Siparişiniz kargoya verildi','label'=>'Kargoya verildi','description'=>'Siparişiniz kargoya verildi. Kargo bilgilerini aşağıda bulabilirsiniz.'],
        'completed' => ['subject'=>'Siparişiniz tamamlandı','label'=>'Tamamlandı','description'=>'Siparişiniz tamamlandı. Bizi tercih ettiğiniz için teşekkür ederiz.'],
        'cancelled' => ['subject'=>'Siparişiniz iptal edildi','label'=>'İptal edildi','description'=>'Siparişiniz iptal edildi.'],
        'pending_confirmation' => ['subject'=>'Siparişiniz kaydedildi','label'=>'Beklemede','description'=>'Siparişiniz satıcı onayından sonra işleme alınacaktır.'],
        default => ['subject'=>'Sipariş bilgileriniz güncellendi','label'=>'Güncellendi','description'=>'Sipariş bilgileriniz güncellendi.'],
    };
}

function order_mail_notification_subject(string $status, string $orderNumber, string $notificationType): string
{
    $number = trim(preg_replace('/[\r\n]+/', '', $orderNumber));
    $presentation = order_mail_status_presentation($status, $notificationType === 'order_received');
    return $presentation['subject'] . ($number !== '' ? ' (#' . $number . ')' : '');
}

function order_mail_valid_tracking_url(mixed $url): ?string
{
    if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    if (strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return null;
    $host = strtolower(rtrim((string)$parts['host'], '.'));
    if (in_array($host, ['localhost','localhost.localdomain'], true) || preg_match('/\.(?:localhost|local|internal)$/i', $host)) return null;
    if (filter_var($host, FILTER_VALIDATE_IP) !== false && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return null;
    if (preg_match('~(?:^|/)admin(?:/|$)~i', (string)($parts['path'] ?? ''))) return null;
    return $url;
}

function order_mail_date(array $order): string
{
    $raw = trim((string)($order['created_at'] ?? $order['order_date'] ?? ''));
    if ($raw === '') return 'Belirtilmedi';
    try { return (new DateTimeImmutable($raw))->format('d.m.Y H:i'); }
    catch (Throwable) { return 'Belirtilmedi'; }
}

function order_mail_content(array $order, array $items, string $notificationType): array
{
    $number = trim((string)($order['order_number'] ?? ''));
    $name = trim((string)($order['customer_name'] ?? 'Müşteri')) ?: 'Müşteri';
    $received = $notificationType === 'order_received';
    $statusCode = (string)($order['status'] ?? '');
    $presentation = order_mail_status_presentation($statusCode, $received);
    $subject = order_mail_notification_subject($statusCode, $number, $notificationType);
    $method = order_payment_method_label((string)($order['payment_method'] ?? ''));
    $paymentStatus = order_payment_status_label((string)($order['payment_status'] ?? 'unspecified'));
    $shippingFee = (float)($order['shipping_fee'] ?? 0);
    $discount = (float)($order['discount_amount'] ?? 0);
    $shippingLabel = $shippingFee > 0 ? order_mail_money((string)$shippingFee) : 'Ücretsiz';
    $addressParts = array_values(array_filter([
        trim((string)($order['address'] ?? '')),
        trim((string)($order['district'] ?? '')),
        trim((string)($order['city'] ?? '')),
    ], static fn(string $part): bool => $part !== ''));
    $address = implode(', ', $addressParts);
    $trackingUrl = order_mail_valid_tracking_url($order['tracking_url'] ?? null);
    $carrier = trim((string)($order['carrier'] ?? ''));
    $trackingNumber = trim((string)($order['tracking_number'] ?? ''));
    $hasTracking = $carrier !== '' || $trackingNumber !== '' || $trackingUrl !== null;
    $storeUrl = 'https://kokenfindik.com/';
    $whatsappUrl = order_mail_whatsapp_url($order);

    $plain = [
        'Köken Fındık — Fındığın Kökünden Gelen Lezzet',
        '',
        'Durum: ' . $presentation['label'],
        $presentation['subject'],
        'Merhaba ' . $name . ',',
        '',
        $presentation['description'],
        '',
        'Sipariş no: #' . $number,
        'Tarih: ' . order_mail_date($order),
        '',
        'Ürünler:',
    ];
    foreach ($items as $item) {
        $title = trim((string)($item['product_title'] ?? 'Ürün'));
        $variant = trim((string)($item['variant_label'] ?? ''));
        $plain[] = $title . ($variant !== '' ? ' · ' . $variant : '') . ' × ' . (int)($item['quantity'] ?? 0) . ' — ' . order_mail_money((string)($item['line_total'] ?? 0));
    }
    $plain[] = '';
    $plain[] = 'Ara toplam: ' . order_mail_money((string)($order['subtotal'] ?? 0));
    if ($discount > 0) $plain[] = 'İndirim: −' . order_mail_money((string)$discount);
    $plain[] = 'Kargo: ' . $shippingLabel;
    $plain[] = 'Genel toplam: ' . order_mail_money((string)($order['total'] ?? 0));
    $plain[] = 'Ödeme yöntemi: ' . $method;
    $plain[] = 'Ödeme durumu: ' . $paymentStatus;
    if ($address !== '') $plain[] = 'Teslimat adresi: ' . $address;
    if ($hasTracking) {
        $plain[] = 'Kargo firması: ' . ($carrier !== '' ? $carrier : 'Belirtilmedi');
        $plain[] = 'Kargo takip numarası: ' . ($trackingNumber !== '' ? $trackingNumber : 'Henüz eklenmedi');
        if ($trackingUrl !== null) $plain[] = 'Kargomu takip et: ' . $trackingUrl;
    }
    $plain[] = '';
    $plain[] = 'Mağaza: ' . $storeUrl;
    $plain[] = 'WhatsApp destek: ' . $whatsappUrl;

    $productRows = '';
    foreach ($items as $item) {
        $title = trim((string)($item['product_title'] ?? 'Ürün'));
        $variant = trim((string)($item['variant_label'] ?? ''));
        $productLabel = order_mail_html_escape($title) . ($variant !== '' ? '<br><span style="color:#66706A;font-size:12px">' . order_mail_html_escape($variant) . '</span>' : '');
        $productRows .= '<tr>'
            . '<td style="padding:12px 10px;border-bottom:1px solid #DCE5DE;color:#29352D;font-family:Arial,sans-serif;font-size:14px;line-height:1.5;word-break:break-word">' . $productLabel . '</td>'
            . '<td align="center" style="padding:12px 8px;border-bottom:1px solid #DCE5DE;color:#29352D;font-family:Arial,sans-serif;font-size:14px;white-space:nowrap">' . (int)($item['quantity'] ?? 0) . '</td>'
            . '<td align="right" style="padding:12px 10px;border-bottom:1px solid #DCE5DE;color:#29352D;font-family:Arial,sans-serif;font-size:14px;white-space:nowrap">' . order_mail_money((string)($item['line_total'] ?? 0)) . '</td>'
            . '</tr>';
    }
    if ($productRows === '') $productRows = '<tr><td colspan="3" style="padding:14px 10px;color:#66706A;font-family:Arial,sans-serif;font-size:14px">Ürün bilgisi bulunamadı.</td></tr>';

    $trackingHtml = '';
    if ($hasTracking) {
        $trackingHtml = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-top:18px;background:#F5F7F6;border:1px solid #DCE5DE;border-radius:8px"><tr><td style="padding:14px;font-family:Arial,sans-serif;font-size:14px;line-height:1.7;color:#29352D"><strong>Kargo bilgileri</strong><br>Kargo firması: ' . order_mail_html_escape($carrier !== '' ? $carrier : 'Belirtilmedi') . '<br>Takip numarası: <span style="word-break:break-all">' . order_mail_html_escape($trackingNumber !== '' ? $trackingNumber : 'Henüz eklenmedi') . '</span>';
        if ($trackingUrl !== null) $trackingHtml .= '<br><br><a href="' . order_mail_html_escape($trackingUrl) . '" style="display:inline-block;padding:10px 16px;background:#1C5A27;color:#FFFFFF;text-decoration:none;border-radius:6px;font-weight:bold">Kargomu takip et</a>';
        $trackingHtml .= '</td></tr></table>';
    }
    $addressHtml = $address !== ''
        ? '<tr><td style="padding:6px 0;color:#66706A;font-family:Arial,sans-serif;font-size:13px;vertical-align:top">Teslimat adresi</td><td style="padding:6px 0;color:#29352D;font-family:Arial,sans-serif;font-size:13px;line-height:1.5;text-align:right">' . order_mail_html_escape($address) . '</td></tr>'
        : '';
    $totalsRows = '<tr><td style="padding:5px 0;color:#66706A;font-family:Arial,sans-serif;font-size:14px">Ara toplam</td><td align="right" style="padding:5px 0;color:#29352D;font-family:Arial,sans-serif;font-size:14px;white-space:nowrap">' . order_mail_money((string)($order['subtotal'] ?? 0)) . '</td></tr>';
    if ($discount > 0) $totalsRows .= '<tr><td style="padding:5px 0;color:#66706A;font-family:Arial,sans-serif;font-size:14px">İndirim</td><td align="right" style="padding:5px 0;color:#1C5A27;font-family:Arial,sans-serif;font-size:14px;white-space:nowrap">−' . order_mail_money((string)$discount) . '</td></tr>';
    $totalsRows .= '<tr><td style="padding:5px 0;color:#66706A;font-family:Arial,sans-serif;font-size:14px">Kargo</td><td align="right" style="padding:5px 0;color:#29352D;font-family:Arial,sans-serif;font-size:14px;white-space:nowrap">' . $shippingLabel . '</td></tr>'
        . '<tr><td style="padding:12px 0 0;border-top:1px solid #DCE5DE;color:#14421C;font-family:Arial,sans-serif;font-size:15px;font-weight:bold">Genel toplam</td><td align="right" style="padding:12px 0 0;border-top:1px solid #DCE5DE;color:#14421C;font-family:Arial,sans-serif;font-size:18px;font-weight:bold;white-space:nowrap">' . order_mail_money((string)($order['total'] ?? 0)) . '</td></tr>';

    $html = '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . order_mail_html_escape($subject) . '</title></head>'
        . '<body style="margin:0;padding:20px 10px;background:#F5F7F6;color:#29352D;font-family:Arial,sans-serif">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F5F7F6" style="width:100%;background:#F5F7F6"><tr><td align="center" style="padding:0">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#FFFFFF" style="width:100%;max-width:640px;background:#FFFFFF;border:1px solid #DCE5DE;border-radius:10px;border-collapse:separate;border-spacing:0;overflow:hidden">'
        . '<tr><td bgcolor="#14421C" style="padding:24px 26px;background:#14421C;color:#FFFFFF;font-family:Arial,sans-serif">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="font-family:Georgia,serif;font-size:25px;font-weight:bold;line-height:1.2;color:#FFFFFF">Köken Fındık</td></tr>'
        . '<tr><td style="padding-top:6px;color:#D8E8DB;font-family:Georgia,serif;font-size:13px;font-style:italic">Fındığın Kökünden Gelen Lezzet</td></tr></table>'
        . '</td></tr><tr><td style="padding:24px 26px 10px;font-family:Arial,sans-serif">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:6px 11px;border:1px solid #B8D2BD;border-radius:20px;background:#E6EFE7;color:#14421C;font-family:Arial,sans-serif;font-size:12px;font-weight:bold">' . order_mail_html_escape($presentation['label']) . '</td></tr></table>'
        . '<h1 style="margin:18px 0 10px;color:#14421C;font-family:Georgia,serif;font-size:25px;line-height:1.3">' . order_mail_html_escape($presentation['subject']) . '</h1>'
        . '<p style="margin:0 0 10px;color:#29352D;font-family:Arial,sans-serif;font-size:15px;line-height:1.6">Merhaba ' . order_mail_html_escape($name) . ',</p>'
        . '<p style="margin:0 0 18px;color:#66706A;font-family:Arial,sans-serif;font-size:14px;line-height:1.65">' . order_mail_html_escape($presentation['description']) . '</p>'
        . '</td></tr><tr><td style="padding:0 26px 20px;font-family:Arial,sans-serif">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#F5F7F6;border:1px solid #DCE5DE;border-radius:8px"><tr>'
        . '<td style="padding:12px 14px;color:#66706A;font-family:Arial,sans-serif;font-size:13px">Sipariş no<br><strong style="display:inline-block;padding-top:4px;color:#29352D;font-size:15px;white-space:nowrap">#' . order_mail_html_escape($number) . '</strong></td>'
        . '<td align="right" style="padding:12px 14px;color:#66706A;font-family:Arial,sans-serif;font-size:13px">Tarih<br><strong style="display:inline-block;padding-top:4px;color:#29352D;font-size:14px;white-space:nowrap">' . order_mail_html_escape(order_mail_date($order)) . '</strong></td>'
        . '</tr></table></td></tr><tr><td style="padding:0 26px 20px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse">'
        . '<tr><th align="left" style="padding:10px;border-bottom:2px solid #1C5A27;color:#14421C;font-family:Arial,sans-serif;font-size:12px">Ürün</th><th align="center" style="padding:10px 8px;border-bottom:2px solid #1C5A27;color:#14421C;font-family:Arial,sans-serif;font-size:12px;white-space:nowrap">Adet</th><th align="right" style="padding:10px;border-bottom:2px solid #1C5A27;color:#14421C;font-family:Arial,sans-serif;font-size:12px;white-space:nowrap">Tutar</th></tr>' . $productRows . '</table>'
        . '</td></tr><tr><td align="right" style="padding:0 26px 20px">'
        . '<table role="presentation" width="270" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:270px;margin-left:auto;border-collapse:collapse">' . $totalsRows . '</table>'
        . '</td></tr><tr><td style="padding:0 26px 22px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-top:1px solid #DCE5DE"><tr>'
        . '<td style="padding:14px 14px 6px;color:#66706A;font-family:Arial,sans-serif;font-size:13px;vertical-align:top">Ödeme yöntemi</td><td align="right" style="padding:14px 14px 6px;color:#29352D;font-family:Arial,sans-serif;font-size:13px">' . order_mail_html_escape($method) . '</td></tr>'
        . '<tr><td style="padding:6px 14px;color:#66706A;font-family:Arial,sans-serif;font-size:13px;vertical-align:top">Ödeme durumu</td><td align="right" style="padding:6px 14px;color:#29352D;font-family:Arial,sans-serif;font-size:13px">' . order_mail_html_escape($paymentStatus) . '</td></tr>'
        . $addressHtml . '</table>' . $trackingHtml
        . '</td></tr><tr><td style="padding:18px 26px;background:#F5F7F6;border-top:1px solid #DCE5DE;text-align:center;font-family:Arial,sans-serif;font-size:12px;line-height:1.8;color:#66706A">'
        . '<a href="' . $storeUrl . '" style="color:#1C5A27;text-decoration:underline">Köken Fındık mağazasını ziyaret edin</a>'
        . '&nbsp;&nbsp;·&nbsp;&nbsp;<a href="' . order_mail_html_escape($whatsappUrl) . '" style="color:#1C5A27;text-decoration:underline">WhatsApp destek</a>'
        . '</td></tr></table></td></tr></table></body></html>';

    return ['subject'=>$subject,'text'=>implode("\r\n",$plain),'html'=>$html];
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
        $itemsQuery = $pdo->prepare('SELECT product_title,variant_label,quantity,line_total FROM order_items WHERE order_id=:id ORDER BY id');
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
