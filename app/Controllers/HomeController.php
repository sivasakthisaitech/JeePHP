<?php
declare(strict_types=1);

namespace App\Controllers;

use JeePHP\Http\Request;

final class HomeController
{
    public function index(Request $request): string
    {
        ob_start();
        view('home', ['framework' => 'JeePHP']);
        return (string) ob_get_clean();
    }
}
