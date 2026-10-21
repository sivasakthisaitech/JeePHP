// JeePHP package: CacheO
if (!function_exists('cache')) {
    function cache(string $key, mixed ...$arguments): mixed
    {
        static $store;
        $store ??= new \JeePHP\Cache\FileCache(dirname(__DIR__, 2) . '/storage/cache');
        if ($arguments === []) {
            return $store->get($key);
        }
        if (count($arguments) === 1) {
            return $store->put($key, $arguments[0]);
        }
        return $store->put($key, $arguments[0], (int) $arguments[1]);
    }
}
if (!function_exists('cache_remember')) {
    function cache_remember(string $key, int $seconds, callable $callback): mixed
    {
        static $store;
        $store ??= new \JeePHP\Cache\FileCache(dirname(__DIR__, 2) . '/storage/cache');
        return $store->remember($key, $seconds, $callback);
    }
}
if (!function_exists('cache_forget')) {
    function cache_forget(string $key): bool
    {
        static $store;
        $store ??= new \JeePHP\Cache\FileCache(dirname(__DIR__, 2) . '/storage/cache');
        return $store->forget($key);
    }
}
