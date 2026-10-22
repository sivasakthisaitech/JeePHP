<?php
declare(strict_types=1);

namespace JeePHP\Security;

use RuntimeException;

/** PHP project releases that contain the 21 October 2026 security fixes. */
final class Platform
{
    private const MINIMUM_PATCHED = [
        802 => 80235,
        803 => 80336,
        804 => 80427,
        805 => 80512,
    ];

    public static function minimum(int $versionId = PHP_VERSION_ID): ?string
    {
        $branch = intdiv($versionId, 100);
        return isset(self::MINIMUM_PATCHED[$branch])
            ? self::version(self::MINIMUM_PATCHED[$branch])
            : null;
    }

    public static function isPatched(int $versionId = PHP_VERSION_ID): bool
    {
        $minimum = self::MINIMUM_PATCHED[intdiv($versionId, 100)] ?? null;
        return $minimum !== null && $versionId >= $minimum;
    }

    public static function assertPatched(): void
    {
        if (!self::isPatched()) {
            throw new RuntimeException(
                'This PHP runtime (' . PHP_VERSION . ') is not an approved JeePHP release. '
                . 'Use PHP 8.2.35+, 8.3.36+, 8.4.27+, or 8.5.12+ on its matching branch. '
                . 'PHP 8.1 and older are unsupported. Run php artisan security:check for details.'
            );
        }
    }

    private static function version(int $versionId): string
    {
        return intdiv($versionId, 10000) . '.'
            . intdiv($versionId % 10000, 100) . '.' . $versionId % 100;
    }
}
