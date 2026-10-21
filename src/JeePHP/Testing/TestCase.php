<?php
declare(strict_types=1);

namespace JeePHP\Testing;

use RuntimeException;
use Throwable;

final class TestCase
{
    private int $assertions = 0;

    public function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new RuntimeException($message !== '' ? $message : 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
        }
    }

    public function assertTrue(bool $condition, string $message = 'Expected the condition to be true.'): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public function contains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException($message !== '' ? $message : 'Expected text was not found.');
        }
    }

    public function throws(callable $callback, string $class = Throwable::class): void
    {
        $this->assertions++;
        try {
            $callback();
        } catch (Throwable $error) {
            if ($error instanceof $class) {
                return;
            }
            throw new RuntimeException('Expected ' . $class . ', got ' . get_debug_type($error) . '.', 0, $error);
        }
        throw new RuntimeException('Expected ' . $class . ' to be thrown.');
    }

    public function assertionCount(): int
    {
        return $this->assertions;
    }
}
