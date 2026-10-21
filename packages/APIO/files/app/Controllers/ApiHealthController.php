<?php
declare(strict_types=1);

namespace App\Controllers;

use JeePHP\Http\Request;

final class ApiHealthController
{
    public function index(Request $request): string
    {
        return json_data(['status' => 'ok', 'framework' => 'JeePHP']);
    }
}
