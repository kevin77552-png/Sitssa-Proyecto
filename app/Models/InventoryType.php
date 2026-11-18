<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryType extends Model
{
    protected $fillable = [
        'name',
        'keywords',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function importRows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }
}
