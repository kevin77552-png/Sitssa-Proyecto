<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    protected $fillable = [
        'batch_id',
        'excel_row',
        'status',
        'sigicov',
        'inventory_type_id',
        'suggested_inventory_type_id',
        'reason',
        'original_values',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'original_values' => 'array',
            'payload' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function inventoryType(): BelongsTo
    {
        return $this->belongsTo(InventoryType::class);
    }

    public function suggestedInventoryType(): BelongsTo
    {
        return $this->belongsTo(InventoryType::class, 'suggested_inventory_type_id');
    }
}
