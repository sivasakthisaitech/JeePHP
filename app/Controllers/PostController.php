<?php
declare(strict_types=1);

namespace App\Controllers;

use JeePHP\Http\Request;

final class PostController
{
    public function store(Request $request): string
    {
        return 'Saved: ' . e($request->input('name', ''));
    }
}
