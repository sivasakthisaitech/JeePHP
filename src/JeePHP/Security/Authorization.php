<?php
declare(strict_types=1);

namespace JeePHP\Security;

/** Check an actual record's owner on every protected read/write request. */
final class Authorization
{
    public static function owns(?array $record, int $userId, string $ownerColumn = 'user_id'): bool
    {
        if ($record === null || $userId < 1 || $ownerColumn === '' || !array_key_exists($ownerColumn, $record)) {
            return false;
        }
        $owner = $record[$ownerColumn];
        return (is_int($owner) || is_string($owner))
            && preg_match('/^[1-9][0-9]*$/D', (string) $owner) === 1
            && (string) $owner === (string) $userId;
    }
}
