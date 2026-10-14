<?php
declare(strict_types=1);

if (!function_exists('app_title')) {
    function app_title(): string
    {
        return getenv('APP_NAME') ?: 'My JeePHP App';
    }
}
