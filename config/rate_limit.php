<?php
declare(strict_types=1);

/** Database-backed limits survive session changes and are shared by app workers. */
function rate_limit_key(string $scope, string $identifier): string
{
    return hash('sha256', $scope . "\0" . $identifier);
}

function rate_limit_client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

function rate_limit_consume(PDO $pdo, string $scope, string $identifier, int $limit, int $windowSeconds): bool
{
    if ($limit < 1 || $windowSeconds < 1) throw new InvalidArgumentException('Rate limit values must be positive.');
    $now = time();
    $cutoff = $now - $windowSeconds;
    $key = rate_limit_key($scope, $identifier);
    $upsert = $pdo->prepare(
        'INSERT INTO rate_limit_buckets (bucket_hash, window_started_at, request_count) VALUES (?, ?, 1) '
        . 'ON DUPLICATE KEY UPDATE request_count = IF(window_started_at <= ?, 1, LEAST(request_count + 1, ?)), '
        . 'window_started_at = IF(window_started_at <= ?, ?, window_started_at)'
    );
    $upsert->execute([$key, $now, $cutoff, $limit + 1, $cutoff, $now]);
    $read = $pdo->prepare('SELECT request_count FROM rate_limit_buckets WHERE bucket_hash = ?');
    $read->execute([$key]);
    $count = (int)$read->fetchColumn();
    if (random_int(1, 250) === 1) {
        $cleanup = $pdo->prepare('DELETE FROM rate_limit_buckets WHERE window_started_at < ? LIMIT 500');
        $cleanup->execute([$now - 86400]);
    }
    return $count <= $limit;
}

function rate_limit_is_blocked(PDO $pdo, string $scope, string $identifier, int $limit, int $windowSeconds): bool
{
    $read = $pdo->prepare('SELECT window_started_at, request_count FROM rate_limit_buckets WHERE bucket_hash = ?');
    $read->execute([rate_limit_key($scope, $identifier)]);
    $bucket = $read->fetch(PDO::FETCH_ASSOC);
    return $bucket && (int)$bucket['window_started_at'] > time() - $windowSeconds && (int)$bucket['request_count'] >= $limit;
}

function rate_limit_record_failure(PDO $pdo, string $scope, string $identifier, int $windowSeconds): void
{
    $now = time();
    $cutoff = $now - $windowSeconds;
    $key = rate_limit_key($scope, $identifier);
    $upsert = $pdo->prepare(
        'INSERT INTO rate_limit_buckets (bucket_hash, window_started_at, request_count) VALUES (?, ?, 1) '
        . 'ON DUPLICATE KEY UPDATE request_count = IF(window_started_at <= ?, 1, LEAST(request_count + 1, 1000)), '
        . 'window_started_at = IF(window_started_at <= ?, ?, window_started_at)'
    );
    $upsert->execute([$key, $now, $cutoff, $cutoff, $now]);
    if (random_int(1, 250) === 1) {
        $cleanup = $pdo->prepare('DELETE FROM rate_limit_buckets WHERE window_started_at < ? LIMIT 500');
        $cleanup->execute([$now - 86400]);
    }
}

function rate_limit_reset(PDO $pdo, string $scope, string $identifier): void
{
    $delete = $pdo->prepare('DELETE FROM rate_limit_buckets WHERE bucket_hash = ?');
    $delete->execute([rate_limit_key($scope, $identifier)]);
}
