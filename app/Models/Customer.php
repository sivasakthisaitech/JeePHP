<?php
declare(strict_types=1);

namespace App\Models;

use JeePHP\Database\Model;

final class Customer extends Model
{
    protected static string $table = 'customers';

    protected array $fillable = ['name', 'mobile', 'email', 'status'];
}
