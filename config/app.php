<?php
declare(strict_types=1);

return [
    'version' => '1.0.0',
    'name' => getenv('APP_NAME') ?: 'JeePHP',
    'providers' => [
        App\Providers\AppServiceProvider::class,
    ],
];
