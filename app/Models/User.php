<?php
declare(strict_types=1);

namespace App\Models;

use JeePHP\Database\Model;

final class User extends Model
{
    protected static string $table = 'users';
    protected array $fillable = ['name', 'age', 'phone', 'password'];
}
