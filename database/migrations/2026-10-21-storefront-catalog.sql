-- Upgrade the existing admin schema with storefront product fields.
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS weight VARCHAR(80) NOT NULL DEFAULT '' AFTER title,
    ADD COLUMN IF NOT EXISTS chip VARCHAR(120) NOT NULL DEFAULT '' AFTER weight,
    ADD COLUMN IF NOT EXISTS old_price DECIMAL(10,2) NULL AFTER price,
    ADD COLUMN IF NOT EXISTS ingredients TEXT NULL AFTER description,
    ADD COLUMN IF NOT EXISTS image2 VARCHAR(255) NULL AFTER image,
    ADD COLUMN IF NOT EXISTS image3 VARCHAR(255) NULL AFTER image2;

INSERT IGNORE INTO categories (name) VALUES
    ('Genel'), ('Kavrulmuş'), ('Çiğ'), ('Ezme'), ('Paketleme'), ('Avantaj Paketleri'), ('Vakumlu paket');

-- Product records are managed through the admin panel and are not seeded here.
