-- Sipariş sırasında e-posta bildirimi izni ve admin gönderim kaydı.
ALTER TABLE orders
    ADD COLUMN customer_email VARCHAR(190) NOT NULL DEFAULT '' AFTER phone,
    ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 0 AFTER customer_email,
    ADD COLUMN status_email_sent_at TIMESTAMP NULL DEFAULT NULL AFTER email_notifications;