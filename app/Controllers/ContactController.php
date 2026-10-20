<?php
declare(strict_types=1);

namespace App\Controllers;

use JeePHP\Http\Request;

final class ContactController
{
    public function index(Request $request): void
    {
        load_view('contact', ['title' => 'Contact JeePHP']);
    }
}
