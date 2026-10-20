<?php
declare(strict_types=1);

namespace App\Models;

use JeePHP\Database\Model;
use JeePHP\Database\RelationDefinition;

final class Post extends Model
{
    protected static string $table = 'posts';
    protected array $fillable = ['user_id', 'title', 'body'];

    public function author(): RelationDefinition
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
