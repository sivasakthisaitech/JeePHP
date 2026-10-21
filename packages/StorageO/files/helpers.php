// JeePHP package: StorageO
if (!function_exists('store_file')) {
    function store_file(string $path, string $contents, string $disk = 'private'): string
    {
        return (new \JeePHP\Storage\LocalStorage(dirname(__DIR__, 2)))->put($path, $contents, $disk);
    }
}
if (!function_exists('read_file')) {
    function read_file(string $path, string $disk = 'private'): string
    {
        return (new \JeePHP\Storage\LocalStorage(dirname(__DIR__, 2)))->get($path, $disk);
    }
}
if (!function_exists('file_exists_in_storage')) {
    function file_exists_in_storage(string $path, string $disk = 'private'): bool
    {
        return (new \JeePHP\Storage\LocalStorage(dirname(__DIR__, 2)))->exists($path, $disk);
    }
}
if (!function_exists('delete_file')) {
    function delete_file(string $path, string $disk = 'private'): bool
    {
        return (new \JeePHP\Storage\LocalStorage(dirname(__DIR__, 2)))->delete($path, $disk);
    }
}
if (!function_exists('storage_url')) {
    function storage_url(string $path): string
    {
        return url((new \JeePHP\Storage\LocalStorage(dirname(__DIR__, 2)))->publicPath($path));
    }
}
