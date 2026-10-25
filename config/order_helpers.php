<?php
declare(strict_types=1);

function order_status_labels(): array
{
    return [
        'pending_confirmation' => 'WhatsApp onayı bekleniyor',
        'confirmed' => 'Onaylandı',
        'preparing' => 'Hazırlanıyor',
        'shipped' => 'Kargoda',
        'completed' => 'Tamamlandı',
        'cancelled' => 'İptal edildi',
    ];
}

function order_payment_method_label(?string $method): string
{
    return match ($method) {
        'iban' => 'Havale / EFT',
        'kapida' => 'Kapıda Ödeme',
        default => 'Belirtilmemiş',
    };
}

function order_payment_status_labels(): array
{
    return [
        'unspecified' => 'Belirtilmemiş',
        'awaiting_payment' => 'Ödeme bekleniyor',
        'collect_on_delivery' => 'Kapıda tahsil edilecek',
        'paid' => 'Ödendi (yönetici işaretledi)',
        'refunded' => 'İade edildi (yönetici işaretledi)',
    ];
}

function order_payment_status_label(?string $status): string
{
    return order_payment_status_labels()[$status ?? ''] ?? 'Belirtilmemiş';
}

function order_email_status_label(?string $status, ?string $errorCode = null): string
{
    $label = match ($status) {
        'pending' => 'Gönderim bekliyor',
        'sending' => 'Gönderim sürüyor; sonucu kontrol edin',
        'sent' => 'SMTP sunucusu kabul etti',
        'failed' => 'Gönderilemedi',
        default => 'E-posta kaydı yok',
    };
    if ($status === 'failed' && $errorCode === 'not_configured') return 'SMTP yapılandırması eksik';
    if ($status === 'failed' && $errorCode === 'invalid_recipient') return 'Alıcı e-posta adresi geçersiz';
    return $label;
}
