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
                $message = 'Possible N+1: the same SELECT ran ' . $count . ' times: ' . substr($sql, 0, 240);
                if (class_exists(\JeePHP\Debug\DeveloperConsole::class)
                    && \JeePHP\Debug\DeveloperConsole::isEnabled()) {
                    \JeePHP\Debug\DeveloperConsole::warning(
                        $message,
                        'Loop-kulla ovvoru row-kum query pannina, relationship-ai Model::with() moolama eager-load pannunga.'
                    );
                } else {
                    error_log('[JeePHP query monitor] ' . $message);
                }
            }
        }
    }
}
