<?php
declare(strict_types=1);

namespace JeePHP\Security;

use JeePHP\Database\Database;
use PDO;
use RuntimeException;

/** Atomic, database-backed counters shared across sessions and PHP workers. */
final class AuthRateLimiter
{
    public static function installSchema(PDO $database): void
    {
        $database->exec('CREATE TABLE IF NOT EXISTS auth_rate_limits (
            bucket_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
            attempts INT UNSIGNED NOT NULL,
            expires_at DATETIME NOT NULL,
            INDEX auth_rate_limits_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public static function hit(string $action, string $identity, int $limit, int $windowSeconds): bool
    {
        if ($action === '' || $identity === '' || $limit < 1 || $windowSeconds < 1) {
            throw new RuntimeException('Invalid authentication rate limit settings.');
        }

        $database = Database::connection();
        $bucket = self::bucketKey($action, $identity, $windowSeconds);
        // A single InnoDB upsert serializes concurrent requests to this bucket.
        $statement = $database->prepare(
            'INSERT INTO auth_rate_limits (bucket_key, attempts, expires_at) '
            . "VALUES (:bucket, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$windowSeconds} SECOND)) "
            . 'ON DUPLICATE KEY UPDATE attempts = LEAST(attempts + 1, 1000000)'
        );
        $statement->execute(['bucket' => $bucket]);
        $query = $database->prepare('SELECT attempts FROM auth_rate_limits WHERE bucket_key = :bucket');
        $query->execute(['bucket' => $bucket]);
        $attempts = $query->fetchColumn();
        if ($attempts === false) {
            throw new RuntimeException('Could not read authentication rate limit state.');
        }

        // Small opportunistic cleanup keeps the table bounded without a cron job.
        if (random_int(1, 256) === 1) {
            $database->exec('DELETE FROM auth_rate_limits WHERE expires_at < UTC_TIMESTAMP() LIMIT 1000');
        }

        return (int) $attempts <= $limit;
    }

    public static function clientIp(): string
    {
        // REMOTE_ADDR is set by the web server; untrusted forwarding headers
        // can be freely spoofed and must never control the rate-limit bucket.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
    }

    public static function bucketKey(string $action, string $identity, int $windowSeconds, ?int $now = null): string
    {
        if ($windowSeconds < 1) {
            throw new RuntimeException('Rate limit window must be positive.');
        }
        return hash('sha256', $action . "\0" . $identity . "\0" . intdiv($now ?? time(), $windowSeconds));
    }
}
