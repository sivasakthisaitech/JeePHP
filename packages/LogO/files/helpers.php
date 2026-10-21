// JeePHP package: LogO
if (!function_exists('app_log')) {
    function app_log(string $level, string $message, array $context = []): void
    {
        static $logger;
        $logger ??= new \JeePHP\Logging\Logger(dirname(__DIR__, 2) . '/storage/logs');
        $logger->write($level, $message, $context);
    }
}
