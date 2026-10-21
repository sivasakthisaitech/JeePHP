<?php
declare(strict_types=1);

namespace JeePHP\Backup;

use RuntimeException;

/** Streams authenticated encryption in independent AES-256-GCM chunks. */
final class BackupCrypto
{
    private const MAGIC = "JEEPHPENC2\n";
    private const CHUNK_BYTES = 1024 * 1024;

    public static function generateKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    public static function validateKey(string $encodedKey): void
    {
        self::decodeKey($encodedKey);
    }

    public static function encryptFile(string $source, string $destination, string $encodedKey): void
    {
        $key = self::decodeKey($encodedKey);
        $input = @fopen($source, 'rb');
        if ($input === false) {
            throw new RuntimeException('Cannot open backup for encryption: ' . $source);
        }

        $temporary = $destination . '.tmp';
        $output = @fopen($temporary, 'xb');
        if ($output === false) {
            fclose($input);
            throw new RuntimeException('Cannot create encrypted backup: ' . $temporary);
        }

        try {
            self::writeAll($output, self::MAGIC);
            $sequence = 0;
            while (!feof($input)) {
                $plain = fread($input, self::CHUNK_BYTES);
                if ($plain === false) {
                    throw new RuntimeException('Could not read the database backup.');
                }
                if ($plain === '') {
                    break;
                }

                $nonce = random_bytes(12);
                $tag = '';
                $cipher = openssl_encrypt(
                    $plain,
                    'aes-256-gcm',
                    $key,
                    OPENSSL_RAW_DATA,
                    $nonce,
                    $tag,
                    'JeePHP-backup-v1:' . $sequence,
                    16
                );
                if ($cipher === false || strlen($tag) !== 16) {
                    throw new RuntimeException('Could not encrypt the database backup.');
                }
                self::writeAll($output, pack('N', strlen($plain)) . $nonce . $tag . $cipher);
                $sequence++;
            }

            if ($sequence === 0) {
                throw new RuntimeException('The database backup is empty.');
            }

            // Authenticated end marker detects a backup that was truncated at a
            // complete chunk boundary, which individual chunk tags cannot catch.
            $nonce = random_bytes(12);
            $tag = '';
            $end = openssl_encrypt('', 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'JeePHP-backup-v2:end:' . $sequence, 16);
            if ($end !== '' || strlen($tag) !== 16) {
                throw new RuntimeException('Could not finalize encrypted backup integrity data.');
            }
            self::writeAll($output, pack('N', 0) . $nonce . $tag);
            fflush($output);
            fclose($output);
            $output = null;
            fclose($input);
            $input = null;
            if (!rename($temporary, $destination)) {
                throw new RuntimeException('Could not finalize the encrypted backup.');
            }
            @chmod($destination, 0600);
        } catch (\Throwable $error) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            @unlink($temporary);
            throw $error;
        }
    }

    public static function decryptFile(string $source, string $destination, string $encodedKey): void
    {
        $key = self::decodeKey($encodedKey);
        $input = @fopen($source, 'rb');
        if ($input === false) {
            throw new RuntimeException('Cannot open encrypted backup: ' . $source);
        }
        $temporary = $destination . '.tmp';
        $output = @fopen($temporary, 'xb');
        if ($output === false) {
            fclose($input);
            throw new RuntimeException('Cannot create decrypted backup: ' . $temporary);
        }

        try {
            if (self::readExactly($input, strlen(self::MAGIC)) !== self::MAGIC) {
                throw new RuntimeException('This is not a JeePHP encrypted backup.');
            }

            $sequence = 0;
            $complete = false;
            while (!feof($input)) {
                $lengthBytes = fread($input, 4);
                if ($lengthBytes === false) {
                    throw new RuntimeException('Could not read the encrypted backup.');
                }
                if ($lengthBytes === '') {
                    break;
                }
                if (strlen($lengthBytes) !== 4) {
                    throw new RuntimeException('Encrypted backup ended unexpectedly.');
                }

                $length = unpack('Nlength', $lengthBytes)['length'];
                if ($length === 0) {
                    $nonce = self::readExactly($input, 12);
                    $tag = self::readExactly($input, 16);
                    $end = openssl_decrypt('', 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'JeePHP-backup-v2:end:' . $sequence);
                    if ($end !== '') {
                        throw new RuntimeException('Backup is incomplete or its integrity check failed.');
                    }
                    if (fread($input, 1) !== '') {
                        throw new RuntimeException('Encrypted backup contains data after its integrity marker.');
                    }
                    $complete = true;
                    break;
                }
                if ($length < 1 || $length > self::CHUNK_BYTES) {
                    throw new RuntimeException('Encrypted backup contains an invalid chunk.');
                }
                $nonce = self::readExactly($input, 12);
                $tag = self::readExactly($input, 16);
                $cipher = self::readExactly($input, $length);
                $plain = openssl_decrypt(
                    $cipher,
                    'aes-256-gcm',
                    $key,
                    OPENSSL_RAW_DATA,
                    $nonce,
                    $tag,
                    'JeePHP-backup-v1:' . $sequence
                );
                if ($plain === false) {
                    throw new RuntimeException('Backup authentication failed. Check BACKUP_ENCRYPTION_KEY or the file integrity.');
                }
                self::writeAll($output, $plain);
                $sequence++;
            }
            if ($sequence === 0 || !$complete) {
                throw new RuntimeException('Encrypted backup has no data.');
            }

            fflush($output);
            fclose($output);
            $output = null;
            fclose($input);
            $input = null;
            if (!rename($temporary, $destination)) {
                throw new RuntimeException('Could not finalize the decrypted backup.');
            }
            @chmod($destination, 0600);
        } catch (\Throwable $error) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            @unlink($temporary);
            throw $error;
        }
    }

    private static function decodeKey(string $encodedKey): string
    {
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('Enable the PHP OpenSSL extension to use encrypted backups.');
        }
        $key = base64_decode(trim($encodedKey), true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY must be a base64-encoded 32-byte key. Generate one with php jeep backup:key-generate.');
        }
        return $key;
    }

    /** @param resource $stream */
    private static function writeAll($stream, string $data): void
    {
        $offset = 0;
        $length = strlen($data);
        while ($offset < $length) {
            $written = fwrite($stream, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not write a backup file.');
            }
            $offset += $written;
        }
    }

    /** @param resource $stream */
    private static function readExactly($stream, int $length): string
    {
        $result = '';
        while (strlen($result) < $length && !feof($stream)) {
            $part = fread($stream, $length - strlen($result));
            if ($part === false || $part === '') {
                break;
            }
            $result .= $part;
        }
        if (strlen($result) !== $length) {
            throw new RuntimeException('Encrypted backup ended unexpectedly.');
        }
        return $result;
    }
}
