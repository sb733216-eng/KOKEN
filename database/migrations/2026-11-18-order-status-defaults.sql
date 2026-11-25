-- Run with the intended site's database selected.
-- Only defaults change; existing orders, payment records and history are retained.
ALTER TABLE orders
    ALTER COLUMN status SET DEFAULT 'preparing',
    ALTER COLUMN payment_status SET DEFAULT '';
