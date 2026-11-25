<?php
declare(strict_types=1);

require_once __DIR__ . '/order_helpers.php';

function order_telegram_config(): array
{
    $local = [];
    $documentRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    $root = $documentRoot !== '' ? realpath($documentRoot) : false;
    $privateParent = $root !== false ? dirname($root) : dirname(__DIR__, 3);
    $customPath = trim((string)getenv('KOKEN_TELEGRAM_CONFIG'));
    $path = $customPath !== '' ? $customPath : $privateParent . '/private/koken-telegram.json';
    if ($customPath !== '' && !is_file($path)) {
        throw new RuntimeException('private_config_missing');
    }
    if (is_file($path)) {
        $resolved = realpath($path);
        $normalizedPath = strtolower(str_replace('\\', '/', (string)$resolved));
        $normalizedRoot = rtrim(strtolower(str_replace('\\', '/', (string)$root)), '/');
        if ($resolved === false || ($normalizedRoot !== '' && str_starts_with($normalizedPath, $normalizedRoot . '/'))) {
            throw new RuntimeException('private_config_path_invalid');
        }
        $contents = @file_get_contents($resolved);
        if ($contents === false) {
            throw new RuntimeException('private_config_unreadable');
        }
        $local = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($local)) {
            throw new RuntimeException('private_config_invalid');
        }
    }

    $setting = static function (string $environmentKey, string $fileKey) use ($local): string {
        $environmentValue = getenv($environmentKey);
        $value = $environmentValue === false ? ($local[$fileKey] ?? '') : $environmentValue;
        return is_string($value) ? trim($value) : '';
    };

    return [
        'token' => $setting('TELEGRAM_BOT_TOKEN', 'token'),
        'chat_id' => $setting('TELEGRAM_CHAT_ID', 'chat_id'),
        'admin_orders_url' => $setting('TELEGRAM_ADMIN_ORDERS_URL', 'admin_orders_url'),
    ];
}

function order_telegram_payload(
    array $config,
    int $orderId,
    string $orderNumber,
    int $totalCents,
    string $payment,
    string $paymentStatus
): array {
    $total = number_format($totalCents / 100, 2, ',', '.') . ' TL';
    $paymentLabel = order_payment_method_label($payment);
    $statusLabel = order_payment_status_label($paymentStatus);
    $payload = [
        'chat_id' => $config['chat_id'],
        'text' => "Yeni sipariş #{$orderNumber}\n{$total} · {$paymentLabel}\n{$statusLabel}",
    ];

    $url = $config['admin_orders_url'];
    $parts = parse_url($url);
    if (filter_var($url, FILTER_VALIDATE_URL) !== false
        && ($parts['scheme'] ?? '') === 'https'
        && !isset($parts['user']) && !isset($parts['pass'])
        && !isset($parts['query']) && !isset($parts['fragment'])) {
        $orderUrl = $url . '?' . http_build_query(['q' => $orderNumber, 'open' => $orderId]);
        $payload['reply_markup'] = [
            'inline_keyboard' => [[
                ['text' => 'Siparişi panelde aç', 'url' => $orderUrl],
            ]],
        ];
    }

    return $payload;
}

function notify_telegram_new_order(
    int $orderId,
    string $orderNumber,
    int $totalCents,
    string $payment,
    string $paymentStatus
): void {
    try {
        $config = order_telegram_config();
        if ($config['token'] === '' && $config['chat_id'] === '') {
            return;
        }
        if (!preg_match('/^\d+:[A-Za-z0-9_-]+$/D', $config['token'])
            || !preg_match('/^-?[1-9]\d*$/D', $config['chat_id'])) {
            error_log('Order Telegram notification not sent; reason=invalid_config');
            return;
        }
        if (!function_exists('curl_init')) {
            error_log('Order Telegram notification not sent; reason=curl_unavailable');
            return;
        }

        $body = json_encode(
            order_telegram_payload($config, $orderId, $orderNumber, $totalCents, $payment, $paymentStatus),
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $request = curl_init('https://api.telegram.org/bot' . $config['token'] . '/sendMessage');
        if ($request === false) {
            throw new RuntimeException('curl_init_failed');
        }
        try {
            curl_setopt_array($request, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT_MS => 1500,
                CURLOPT_TIMEOUT_MS => 3500,
            ]);
            $response = curl_exec($request);
            $httpStatus = (int)curl_getinfo($request, CURLINFO_RESPONSE_CODE);
            $networkError = curl_errno($request);
        } finally {
            curl_close($request);
        }
        $result = is_string($response) ? json_decode($response, true) : null;
        if ($httpStatus !== 200 || ($result['ok'] ?? false) !== true) {
            error_log('Order Telegram notification failed; order=' . $orderNumber . '; http=' . $httpStatus . '; curl=' . $networkError);
        }
    } catch (Throwable) {
        error_log('Order Telegram notification failed; order=' . $orderNumber . '; reason=internal_error');
    }
}
