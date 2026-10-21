<?php
declare(strict_types=1);

return [
    'directory' => dirname(__DIR__) . '/storage/backups',
    'drive_folder_name' => getenv('BACKUP_DRIVE_FOLDER') ?: 'JeePHP Backups',
    'encryption_key' => getenv('BACKUP_ENCRYPTION_KEY') ?: '',
    'chunk_bytes' => 8 * 1024 * 1024,
];
