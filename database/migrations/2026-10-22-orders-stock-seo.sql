-- Sipariş kargo bilgileri, stok defteri ve stok gelince haber ver talepleri.
-- phpMyAdmin'de koken_admin veritabanını seçip bu dosyanın SQL içeriğini çalıştırın.
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS carrier VARCHAR(80) NOT NULL DEFAULT '' AFTER status,
    ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(120) NOT NULL DEFAULT '' AFTER carrier;
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS seo_title VARCHAR(180) NOT NULL DEFAULT '' AFTER ingredients,
    ADD COLUMN IF NOT EXISTS seo_description VARCHAR(320) NOT NULL DEFAULT '' AFTER seo_title;

CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NULL,
    product_title VARCHAR(180) NOT NULL,
    change_qty INT NOT NULL,
    stock_before INT NULL,
    stock_after INT NULL,
    movement_type VARCHAR(32) NOT NULL,
    reference_id BIGINT UNSIGNED NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_movement_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_stock_movement_date (created_at), INDEX idx_stock_movement_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS restock_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NULL,
    product_title VARCHAR(180) NOT NULL,
    email VARCHAR(190) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notified_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_restock_notification_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    UNIQUE KEY uq_restock_product_email (product_id, email), INDEX idx_restock_status_date (status, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eski stokların mevcut değerini ilk kurulum anı için başlangıç hareketi olarak kaydet.
INSERT INTO stock_movements(product_id,product_title,change_qty,stock_before,stock_after,movement_type,note)
SELECT p.id,p.title,p.stock_qty,NULL,p.stock_qty,'manual_update','Stok defteri kurulurken mevcut stok başlangıç değeri olarak alındı'
FROM products p
WHERE p.stock_qty IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM stock_movements sm WHERE sm.product_id=p.id AND sm.note='Stok defteri kurulurken mevcut stok başlangıç değeri olarak alındı');
