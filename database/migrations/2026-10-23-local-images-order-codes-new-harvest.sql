ALTER TABLE products
    ADD COLUMN IF NOT EXISTS is_new_harvest TINYINT(1) NOT NULL DEFAULT 0 AFTER stock_qty;

CREATE TABLE IF NOT EXISTS public_order_code_sequence (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    last_value INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO public_order_code_sequence (id, last_value) VALUES (1, 99);
UPDATE public_order_code_sequence
SET last_value = GREATEST(last_value, (
    SELECT COALESCE(MAX(CAST(order_number AS UNSIGNED)), 99)
    FROM orders
    WHERE order_number REGEXP '^[0-9]+$'
))
WHERE id = 1;

DROP TEMPORARY TABLE IF EXISTS tmp_public_order_codes;
CREATE TEMPORARY TABLE tmp_public_order_codes AS
SELECT id, CAST(99 + ROW_NUMBER() OVER (ORDER BY id) AS CHAR) AS order_number
FROM orders;
UPDATE orders o
JOIN tmp_public_order_codes n ON n.id = o.id
SET o.order_number = n.order_number;
DROP TEMPORARY TABLE tmp_public_order_codes;
UPDATE public_order_code_sequence
SET last_value = GREATEST(last_value, (
    SELECT COALESCE(MAX(CAST(order_number AS UNSIGNED)), 99)
    FROM orders
    WHERE order_number REGEXP '^[0-9]+$'
))
WHERE id = 1;

UPDATE products
SET image = CASE WHEN image = 'https://raw.githubusercontent.com/sb733216-eng/KOKEN/refs/heads/main/cifte-kavrulmus.jpg' THEN '21a3ac5ea773474b9d0e7e992b61270d.jpg' ELSE image END,
    image2 = CASE WHEN image2 = 'https://raw.githubusercontent.com/sb733216-eng/KOKEN/refs/heads/main/cifte-kavrulmus.jpg' THEN '21a3ac5ea773474b9d0e7e992b61270d.jpg' ELSE image2 END,
    image3 = CASE WHEN image3 = 'https://raw.githubusercontent.com/sb733216-eng/KOKEN/refs/heads/main/cifte-kavrulmus.jpg' THEN '21a3ac5ea773474b9d0e7e992b61270d.jpg' ELSE image3 END
WHERE image = 'https://raw.githubusercontent.com/sb733216-eng/KOKEN/refs/heads/main/cifte-kavrulmus.jpg'
   OR image2 = 'https://raw.githubusercontent.com/sb733216-eng/KOKEN/refs/heads/main/cifte-kavrulmus.jpg'
   OR image3 = 'https://raw.githubusercontent.com/sb733216-eng/KOKEN/refs/heads/main/cifte-kavrulmus.jpg';
UPDATE products
SET image = CASE WHEN image = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSkMBqlarpE8-CqSxaMWpcuo2U-hPhohJWEWyrWiTq9SgQChOwJRQdK30k&s=10' THEN '6c0b499acb9f8bb9f47161ce8781de2dfff94d40.jpg' ELSE image END,
    image2 = CASE WHEN image2 = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSkMBqlarpE8-CqSxaMWpcuo2U-hPhohJWEWyrWiTq9SgQChOwJRQdK30k&s=10' THEN '6c0b499acb9f8bb9f47161ce8781de2dfff94d40.jpg' ELSE image2 END,
    image3 = CASE WHEN image3 = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSkMBqlarpE8-CqSxaMWpcuo2U-hPhohJWEWyrWiTq9SgQChOwJRQdK30k&s=10' THEN '6c0b499acb9f8bb9f47161ce8781de2dfff94d40.jpg' ELSE image3 END
WHERE image = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSkMBqlarpE8-CqSxaMWpcuo2U-hPhohJWEWyrWiTq9SgQChOwJRQdK30k&s=10'
   OR image2 = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSkMBqlarpE8-CqSxaMWpcuo2U-hPhohJWEWyrWiTq9SgQChOwJRQdK30k&s=10'
   OR image3 = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSkMBqlarpE8-CqSxaMWpcuo2U-hPhohJWEWyrWiTq9SgQChOwJRQdK30k&s=10';
