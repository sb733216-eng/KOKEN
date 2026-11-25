<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

try {
    $pdo->beginTransaction();
    $ids = $pdo->query("SELECT id FROM orders WHERE tracking_code REGEXP '^[0-9]{3,5}$' ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
    $update = $pdo->prepare('UPDATE orders SET tracking_code = :code WHERE id = :id');
    foreach ($ids as $id) {
        $update->execute(['code' => bin2hex(random_bytes(16)), 'id' => (int)$id]);
    }
    $pdo->commit();
    fwrite(STDOUT, count($ids) . " eski sipariş kodu yenilendi.\n");
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Kod yenileme başarısız: " . $exception->getMessage() . "\n");
    exit(1);
}
