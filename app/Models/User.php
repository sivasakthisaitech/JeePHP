<?php
declare(strict_types=1);

namespace App\Models;

use JeePHP\Database\Model;
use JeePHP\Database\RelationDefinition;

final class User extends Model
{
    protected static string $table = 'users';
    protected array $fillable = ['name', 'age', 'phone', 'password'];

    public function posts(): RelationDefinition
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}
