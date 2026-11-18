<?php
declare(strict_types=1);

function order_status_labels(): array
{
    return [
        'preparing' => 'Hazırlanıyor',
        'shipped' => 'Kargoda',
        'completed' => 'Tamamlandı',
        'cancelled' => 'İptal edildi',
    ];
}

function order_status_key(?string $status): string
{
    return in_array($status, ['pending_confirmation', 'confirmed'], true) ? 'preparing' : (string)$status;
}

function order_status_label(?string $status): string
{
    return order_status_labels()[order_status_key($status)] ?? '—';
}

function order_payment_method_label(?string $method): string
{
    return match ($method) {
        'iban' => 'Havale / EFT',
        'kapida' => 'Kapıda Ödeme',
        default => '—',
    };
}

function order_payment_status_labels(): array
{
    return [
        'awaiting_payment' => 'Ödeme bekleniyor',
        'collect_on_delivery' => 'Kapıda tahsil edilecek',
        'paid' => 'Ödendi',
        'partially_refunded' => 'Kısmi iade kaydı',
        'refunded' => 'İade durumu işaretlendi (para transferi yapılmadı)',
    ];
}

function order_payment_status_label(?string $status): string
{
    return order_payment_status_labels()[$status ?? ''] ?? '—';
}

function order_email_status_label(?string $status, ?string $errorCode = null): string
{
    $label = match ($status) {
        'pending' => 'Gönderim bekliyor',
        'sending' => 'Gönderim sürüyor; sonucu kontrol edin',
        'sent' => 'E-posta sunucusuna iletildi',
        'failed' => 'Gönderilemedi',
        default => 'E-posta kaydı yok',
    };
    if ($status === 'failed' && $errorCode === 'not_configured') return 'SMTP yapılandırması eksik';
    if ($status === 'failed' && $errorCode === 'invalid_recipient') return 'Alıcı e-posta adresi geçersiz';
    return $label;
}
