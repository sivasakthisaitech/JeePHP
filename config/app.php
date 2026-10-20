<?php
declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'JeePHP',
    'providers' => [
        App\Providers\AppServiceProvider::class,
    ],
];
