CREATE DATABASE IF NOT EXISTS koken_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE koken_admin;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    badges TEXT NULL,
    weight VARCHAR(80) NOT NULL DEFAULT '',
    chip VARCHAR(120) NOT NULL DEFAULT '',
    category_id INT UNSIGNED NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    old_price DECIMAL(10,2) NULL,
    description TEXT NULL,
    ingredients TEXT NULL,
    seo_title VARCHAR(180) NOT NULL DEFAULT '',
    seo_description VARCHAR(320) NOT NULL DEFAULT '',
    image VARCHAR(255) NULL,
    image2 VARCHAR(255) NULL,
    image3 VARCHAR(255) NULL,
    stock_qty INT UNSIGNED NULL DEFAULT NULL,
    is_new_harvest TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_products_active (is_active),
    INDEX idx_products_category (category_id),
    UNIQUE KEY uq_products_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_slug_redirects (
    slug VARCHAR(220) NOT NULL PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_slug_redirect_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_slug_redirect_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limit_buckets (
    bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    window_started_at BIGINT UNSIGNED NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rate_limit_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categories (name) VALUES ('Genel'), ('Kavrulmuş'), ('Çiğ'), ('Ezme'), ('Paketleme'), ('Avantaj Paketleri'), ('Vakumlu paket');

CREATE TABLE IF NOT EXISTS store_settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO store_settings (setting_key, setting_value) VALUES
    ('free_shipping_limit', '750'),
    ('shipping_fee', '60'),
    ('low_stock_threshold', '5');

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(32) NOT NULL UNIQUE,
    tracking_code VARCHAR(32) NULL,
    request_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    order_date DATE NULL,
    customer_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    city VARCHAR(80) NOT NULL,
    district VARCHAR(80) NOT NULL,
    address VARCHAR(500) NOT NULL,
    note VARCHAR(500) NOT NULL DEFAULT '',
    payment_method VARCHAR(20) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    shipping_fee DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending_confirmation',
    carrier VARCHAR(80) NOT NULL DEFAULT '',
    tracking_number VARCHAR(120) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_orders_status (status),
    INDEX idx_orders_tracking (tracking_code, phone, order_date),
    INDEX idx_orders_created (created_at),
    UNIQUE KEY uq_orders_request_key (request_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_code_sequences (
    order_date DATE PRIMARY KEY,
    last_value MEDIUMINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS public_order_code_sequence (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    last_value INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO public_order_code_sequence (id, last_value) VALUES (1, 99);

CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    product_title VARCHAR(180) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_order_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
