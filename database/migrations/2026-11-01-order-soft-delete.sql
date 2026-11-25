-- Siparişleri geri alınabilir biçimde listeden kaldırır; sipariş ve kalem kayıtlarını saklar.
-- Yalnızca bu projenin veritabanında çalıştırın.
USE koken_admin;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL AFTER tracking_url,
    ADD COLUMN IF NOT EXISTS deleted_by INT UNSIGNED NULL DEFAULT NULL AFTER deleted_at;

CREATE INDEX idx_orders_deleted_created ON orders (deleted_at, created_at);

ALTER TABLE orders
    ADD CONSTRAINT fk_orders_deleted_by
        FOREIGN KEY (deleted_by) REFERENCES admin_users(id) ON DELETE SET NULL;
