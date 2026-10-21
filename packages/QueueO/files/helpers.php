// JeePHP package: QueueO
if (!function_exists('queue_dispatch')) {
    function queue_dispatch(string $job, array $payload = [], int $delaySeconds = 0, int $maxAttempts = 3): int
    {
        return \JeePHP\Jobs\Queue::dispatch($job, $payload, $delaySeconds, $maxAttempts);
    }
}
