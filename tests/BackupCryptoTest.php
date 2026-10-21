<?php
declare(strict_types=1);

use JeePHP\Backup\BackupCrypto;
use JeePHP\Testing\TestCase;

return static function (TestCase $test): void {
    $directory = sys_get_temp_dir() . '/jeephp-backup-crypto-' . bin2hex(random_bytes(6));
    if (!mkdir($directory, 0700) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the backup crypto test directory.');
    }

    $source = $directory . '/source.sql.gz';
    $encrypted = $directory . '/backup.sql.gz.enc';
    $decrypted = $directory . '/restored.sql.gz';
    $wrongKeyOutput = $directory . '/wrong-key.sql.gz';
    $tampered = $directory . '/tampered.sql.gz.enc';
    $truncated = $directory . '/truncated.sql.gz.enc';

    try {
        $contents = str_repeat('JeePHP database backup encryption test. ', 40000);
        file_put_contents($source, $contents);
        $key = BackupCrypto::generateKey();

        BackupCrypto::encryptFile($source, $encrypted, $key);
        BackupCrypto::decryptFile($encrypted, $decrypted, $key);
        $test->same($contents, file_get_contents($decrypted), 'Encrypted backups must round-trip without changing bytes.');

        $test->throws(
            static fn () => BackupCrypto::decryptFile($encrypted, $wrongKeyOutput, BackupCrypto::generateKey()),
            RuntimeException::class
        );

        $bytes = file_get_contents($encrypted);
        if (!is_string($bytes) || strlen($bytes) < 80) {
            throw new RuntimeException('Encrypted test backup is unexpectedly short.');
        }
        $bytes[60] = chr(ord($bytes[60]) ^ 1);
        file_put_contents($tampered, $bytes);
        $test->throws(
            static fn () => BackupCrypto::decryptFile($tampered, $directory . '/tampered.sql.gz', $key),
            RuntimeException::class
        );

        // The final frame is a 4-byte zero length, 12-byte nonce, and 16-byte tag.
        file_put_contents($truncated, substr(file_get_contents($encrypted), 0, -32));
        $test->throws(
            static fn () => BackupCrypto::decryptFile($truncated, $directory . '/truncated.sql.gz', $key),
            RuntimeException::class
        );
    } finally {
        foreach (glob($directory . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($directory);
    }
};
