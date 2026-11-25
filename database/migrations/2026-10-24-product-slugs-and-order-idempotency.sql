-- Run once against koken_admin before deploying the matching PHP changes.
-- Existing product URLs start with a stable ID-based slug; admins can replace it.
ALTER TABLE products
    ADD COLUMN slug VARCHAR(220) NULL AFTER title,
    ADD COLUMN badges TEXT NULL AFTER ingredients;

UPDATE products SET slug = CONCAT('urun-', id) WHERE slug IS NULL OR slug = '';

ALTER TABLE products
    MODIFY slug VARCHAR(220) NOT NULL,
    ADD UNIQUE KEY uq_products_slug (slug);

CREATE TABLE product_slug_redirects (
    slug VARCHAR(220) NOT NULL PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_slug_redirect_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_slug_redirect_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders
    ADD COLUMN request_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER tracking_code,
    ADD UNIQUE KEY uq_orders_request_key (request_key);
