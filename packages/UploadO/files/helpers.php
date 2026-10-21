// JeePHP package: UploadO
if (!function_exists('upload_file')) {
    function upload_file(string $field, array $allowedMimeTypes, int $maxBytes = 2097152, string $directory = 'uploads'): string
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            throw new \RuntimeException('Upload field not found: ' . $field);
        }
        return (new \JeePHP\Storage\Upload())->store($_FILES[$field], $allowedMimeTypes, $maxBytes, $directory);
    }
}
