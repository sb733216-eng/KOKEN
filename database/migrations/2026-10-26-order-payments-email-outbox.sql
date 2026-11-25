-- Run once in phpMyAdmin with the koken_admin database selected.
-- Existing orders retain payment_method and receive payment_status=unspecified.
ALTER TABLE orders
    ADD COLUMN payment_status VARCHAR(32) NOT NULL DEFAULT 'unspecified' AFTER payment_method,
    ADD INDEX idx_orders_payment_status (payment_status);

CREATE TABLE order_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    field_name VARCHAR(32) NOT NULL,
    previous_value VARCHAR(190) NULL,
    new_value VARCHAR(190) NULL,
    changed_by INT UNSIGNED NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_status_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_status_history_order_time (order_id, changed_at),
    INDEX idx_order_status_history_admin (changed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_email_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    notification_type VARCHAR(32) NOT NULL,
    idempotency_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    recipient VARCHAR(190) NOT NULL,
    subject VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(32) NULL,
    last_attempt_at TIMESTAMP NULL DEFAULT NULL,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_email_notification_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    UNIQUE KEY uq_order_email_idempotency (idempotency_key),
    INDEX idx_order_email_order_type (order_id, notification_type, id),
    INDEX idx_order_email_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
