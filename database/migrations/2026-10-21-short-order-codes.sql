ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS tracking_code VARCHAR(5) NULL AFTER order_number,
    ADD COLUMN IF NOT EXISTS order_date DATE NULL AFTER tracking_code,
    ADD INDEX IF NOT EXISTS idx_orders_tracking (tracking_code, phone, order_date);

CREATE TABLE IF NOT EXISTS order_code_sequences (
    order_date DATE PRIMARY KEY,
    last_value MEDIUMINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders MODIFY tracking_code VARCHAR(5) NULL;
ALTER TABLE order_code_sequences MODIFY last_value MEDIUMINT UNSIGNED NOT NULL;
UPDATE orders SET order_date = DATE(created_at) WHERE order_date IS NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_order_tracking_codes;
CREATE TEMPORARY TABLE tmp_order_tracking_codes AS
SELECT o.id,
       DATE(o.created_at) AS order_date,
       CAST(GREATEST(COALESCE(existing.max_code, 0), 99) + ROW_NUMBER() OVER (PARTITION BY DATE(o.created_at) ORDER BY o.id) AS CHAR) AS tracking_code
FROM orders o
LEFT JOIN (
    SELECT order_date, MAX(CAST(tracking_code AS UNSIGNED)) AS max_code
    FROM orders WHERE tracking_code IS NOT NULL GROUP BY order_date
) existing ON existing.order_date = DATE(o.created_at)
WHERE o.tracking_code IS NULL;

UPDATE orders o
JOIN tmp_order_tracking_codes t ON t.id = o.id
SET o.tracking_code = t.tracking_code, o.order_date = t.order_date;

INSERT INTO order_code_sequences (order_date, last_value)
SELECT order_date, GREATEST(99, MAX(CAST(tracking_code AS UNSIGNED)))
FROM orders WHERE tracking_code IS NOT NULL GROUP BY order_date
ON DUPLICATE KEY UPDATE last_value = GREATEST(last_value, VALUES(last_value));

DROP TEMPORARY TABLE tmp_order_tracking_codes;
