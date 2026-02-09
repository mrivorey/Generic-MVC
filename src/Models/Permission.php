<?php

namespace App\Models;

use App\Core\Model;

class Permission extends Model
{
    protected static string $table = 'permissions';
    protected static array $fillable = ['name', 'slug', 'description'];

    public static function findBySlug(string $slug): ?array
    {
        return self::findBy('slug', $slug);
    }
}
