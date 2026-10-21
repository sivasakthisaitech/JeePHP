<?php
declare(strict_types=1);

namespace JeePHP\Storage;

use RuntimeException;

final class Upload
{
    /** @var array<string,string> */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/zip' => 'zip',
    ];

    public function store(array $file, array $allowedMimeTypes, int $maxBytes, string $directory = 'uploads'): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::uploadError((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }
        $temporary = $file['tmp_name'] ?? null;
        $size = filter_var($file['size'] ?? null, FILTER_VALIDATE_INT);
        if (!is_string($temporary) || !is_uploaded_file($temporary)) {
            throw new RuntimeException('The uploaded file did not pass PHP upload verification.');
        }
        if ($size === false || $size < 1 || $size > $maxBytes) {
            throw new RuntimeException('File size must be between 1 byte and ' . $maxBytes . ' bytes.');
        }
        if (!class_exists(\finfo::class)) {
            throw new RuntimeException('Enable the PHP fileinfo extension to validate uploads.');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($temporary);
        if (!is_string($mime) || !in_array($mime, $allowedMimeTypes, true)) {
            throw new RuntimeException('This file type is not allowed.');
        }
        $extension = self::EXTENSIONS[$mime] ?? null;
        if ($extension === null) {
            throw new RuntimeException('No safe file extension is configured for MIME type ' . $mime . '.');
        }
        $directory = trim(str_replace('\\', '/', $directory), '/');
        if ($directory === '' || preg_match('/^[A-Za-z0-9._/-]+$/', $directory) !== 1
            || in_array('..', explode('/', $directory), true) || in_array('.', explode('/', $directory), true)) {
            throw new RuntimeException('Upload directory must be a safe relative path.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        $relative = $directory . '/' . $name;
        $root = dirname(__DIR__, 3);
        $destination = $root . '/public/storage/' . $relative;
        $targetDirectory = dirname($destination);
        if (is_link($root . '/public') || is_link($root . '/public/storage')) {
            throw new RuntimeException('Upload folders cannot be symbolic links.');
        }
        $cursor = $root . '/public/storage';
        foreach (explode('/', $directory) as $segment) {
            $cursor .= '/' . $segment;
            if (is_link($cursor)) {
                throw new RuntimeException('Upload paths cannot pass through symbolic links.');
            }
        }
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Could not create the upload folder.');
        }
        if (!move_uploaded_file($temporary, $destination)) {
            throw new RuntimeException('Could not store the uploaded file.');
        }
        @chmod($destination, 0644);
        return $relative;
    }

    private static function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file is larger than the allowed limit.',
            UPLOAD_ERR_PARTIAL => 'The file upload was interrupted. Try again.',
            UPLOAD_ERR_NO_FILE => 'Choose a file before uploading.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server upload folder is not configured.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not save the uploaded file.',
            default => 'The file upload failed.',
        };
    }
}
