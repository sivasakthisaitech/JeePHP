<?php
declare(strict_types=1);

namespace JeePHP\Localization;

use RuntimeException;

final class Translator
{
    private static string $locale = 'en';
    private static array $loaded = [];

    public static function setLocale(string $locale): void
    {
        if (preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $locale) !== 1) {
            throw new RuntimeException('Locale must look like en or ta_IN.');
        }
        self::$locale = $locale;
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $key) !== 1) {
            throw new RuntimeException('Translation key contains unsupported characters.');
        }
        $locale ??= self::$locale;
        if (!array_key_exists($locale, self::$loaded)) {
            $path = dirname(__DIR__, 3) . '/lang/' . $locale . '/messages.php';
            $messages = is_file($path) ? require $path : [];
            self::$loaded[$locale] = is_array($messages) ? $messages : [];
        }
        $text = self::$loaded[$locale][$key] ?? $key;
        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }
        return (string) $text;
    }
}
