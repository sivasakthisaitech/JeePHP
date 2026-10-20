<?php
declare(strict_types=1);

namespace App\Controllers;

use JeePHP\Http\Request;

final class UserController
{
    public function show(Request $request): string
    {
        return 'User ID: ' . e($request->route('id', ''));
    }
}
