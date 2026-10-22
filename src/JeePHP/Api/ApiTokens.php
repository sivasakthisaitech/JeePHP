<?php
declare(strict_types=1);

namespace JeePHP\Api;

use JeePHP\Database\Database;
use PDO;
use RuntimeException;

final class ApiTokens
{
    private static string $cachedTokenHash = '';
    private static ?array $cachedRecord = null;
    private static bool $cacheResolved = false;

    public static function create(int $userId, string $name, array $abilities = ['*'], ?string $expiresAt = null): string
    {
        if ($userId < 1 || trim($name) === '' || mb_strlen($name) > 100) {
            throw new RuntimeException('Provide a valid user ID and a token name up to 100 characters.');
        }
        foreach ($abilities as $ability) {
            if (!is_string($ability) || preg_match('/^[A-Za-z][A-Za-z0-9._*:-]{0,99}$/', $ability) !== 1) {
                throw new RuntimeException('API abilities must be short names such as users.read or *.');
            }
        }
        if ($expiresAt !== null && strtotime($expiresAt) === false) {
            throw new RuntimeException('Token expiry must be a valid date and time.');
        }
        $token = 'jp_' . bin2hex(random_bytes(32));
        $statement = Database::connection()->prepare(
            'INSERT INTO api_tokens (user_id, name, token_hash, abilities, expires_at, created_at) '
            . 'VALUES (:user_id, :name, :hash, :abilities, :expires_at, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'user_id' => $userId,
            'name' => $name,
            'hash' => hash('sha256', $token),
            'abilities' => json_encode(array_values($abilities), JSON_THROW_ON_ERROR),
            'expires_at' => $expiresAt,
        ]);
        return $token;
    }

    public static function authenticate(string $token): ?array
    {
        if (!preg_match('/^jp_[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $tokenHash = hash('sha256', $token);
        if (self::$cacheResolved && hash_equals(self::$cachedTokenHash, $tokenHash)) {
            return self::$cachedRecord;
        }
        $statement = Database::connection()->prepare(
            'SELECT id, user_id, name, token_hash, abilities, expires_at FROM api_tokens '
            . 'WHERE token_hash = :hash LIMIT 1'
        );
        $statement->execute(['hash' => $tokenHash]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($record) || !hash_equals((string) $record['token_hash'], $tokenHash)) {
            self::$cachedTokenHash = $tokenHash;
            self::$cachedRecord = null;
            self::$cacheResolved = true;
            return null;
        }
        if ($record['expires_at'] !== null && strtotime((string) $record['expires_at'] . ' UTC') <= time()) {
            self::$cachedTokenHash = $tokenHash;
            self::$cachedRecord = null;
            self::$cacheResolved = true;
            return null;
        }
        $update = Database::connection()->prepare('UPDATE api_tokens SET last_used_at = UTC_TIMESTAMP() WHERE id = :id');
        $update->execute(['id' => $record['id']]);
        $record['abilities'] = json_decode((string) $record['abilities'], true) ?: [];
        unset($record['token_hash']);
        self::$cachedTokenHash = $tokenHash;
        self::$cachedRecord = $record;
        self::$cacheResolved = true;
        return $record;
    }

    public static function revoke(int $id, int $userId): bool
    {
        $statement = Database::connection()->prepare('DELETE FROM api_tokens WHERE id = :id AND user_id = :user_id');
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        $revoked = $statement->rowCount() === 1;
        if ($revoked && (int) (self::$cachedRecord['id'] ?? 0) === $id) {
            self::$cachedTokenHash = '';
            self::$cachedRecord = null;
            self::$cacheResolved = false;
        }
        return $revoked;
    }
}
