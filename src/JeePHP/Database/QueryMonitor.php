<?php
declare(strict_types=1);

namespace JeePHP\Database;

final class QueryMonitor
{
    private static array $selects = [];
    private static bool $reported = false;

    public static function record(string $sql): void
    {
        if (getenv('APP_DEBUG') !== 'true' || !str_starts_with(strtoupper(ltrim($sql)), 'SELECT')) {
            return;
        }

        $fingerprint = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);
        $fingerprint = preg_replace('/:([A-Za-z_][A-Za-z0-9_]*)/', '?', $fingerprint) ?? $fingerprint;
        self::$selects[$fingerprint] = (self::$selects[$fingerprint] ?? 0) + 1;
    }

    public static function report(): void
    {
        if (self::$reported) {
            return;
        }
        self::$reported = true;

        foreach (self::$selects as $sql => $count) {
            if ($count >= 5) {
                error_log('[JeePHP query monitor] Possible N+1: the same SELECT ran ' . $count . ' times: ' . substr($sql, 0, 240));
            }
        }
    }
}
