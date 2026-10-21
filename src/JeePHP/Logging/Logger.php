<?php
declare(strict_types=1);

namespace JeePHP\Logging;

use InvalidArgumentException;
use RuntimeException;

final class Logger
{
    private const LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical'];

    public function __construct(private readonly string $directory) {}

    public function write(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);
        if (!in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException('Log level must be one of: ' . implode(', ', self::LEVELS));
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Could not create application log folder.');
        }
        $record = [
            'time' => gmdate(DATE_ATOM),
            'level' => $level,
            'message' => mb_substr($message, 0, 4000),
            'context' => self::redact($context),
        ];
        $line = json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES) . "\n";
        $path = $this->directory . '/' . gmdate('Y-m-d') . '.log';
        if (file_put_contents($path, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Could not write the application log.');
        }
        @chmod($path, 0600);
    }

    private static function redact(array $context): array
    {
        $clean = [];
        foreach ($context as $key => $value) {
            if (preg_match('/password|secret|token|authorization|cookie|api[_-]?key/i', (string) $key)) {
                $clean[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $clean[$key] = self::redact($value);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            } else {
                $clean[$key] = '[' . get_debug_type($value) . ']';
            }
        }
        return $clean;
    }
}
