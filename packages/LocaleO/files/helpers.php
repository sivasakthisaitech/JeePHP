// JeePHP package: LocaleO
if (!function_exists('set_locale')) {
    function set_locale(string $locale): void
    {
        \JeePHP\Localization\Translator::setLocale($locale);
    }
}
if (!function_exists('trans')) {
    function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return \JeePHP\Localization\Translator::get($key, $replace, $locale);
    }
}
