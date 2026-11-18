<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatabaseBackup extends Model
{
    protected $guarded = [];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public static function latestVerified(int $maxMinutes = 60): ?self
    {
        foreach (static::query()
            ->where('status', 'verified')
            ->where('verified_at', '>=', now()->subMinutes($maxMinutes))
            ->latest('verified_at')
            ->cursor() as $backup) {
            if (is_file($backup->path)) {
                return $backup;
            }
        }

        return null;
    }
}
