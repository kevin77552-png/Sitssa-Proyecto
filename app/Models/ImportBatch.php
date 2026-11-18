<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'file_name',
        'file_path',
        'file_hash',
        'file_size',
        'status',
        'sheet_name',
        'requested_sheet',
        'cutoff_date',
        'rows_read',
        'new_rows',
        'existing_rows',
        'duplicate_rows',
        'invalid_rows',
        'imported_rows',
        'records_before',
        'records_after',
        'approved_by',
        'approved_at',
        'applied_at',
        'reverted_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_date' => 'date',
            'approved_at' => 'datetime',
            'applied_at' => 'datetime',
            'reverted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'batch_id');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'import_batch_id');
    }

    public function scopeBlocksDuplicateUpload(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', ['failed', 'reverted'])
            ->where(function (Builder $query): void {
                $query->where('status', '<>', 'completed')
                    ->orWhere('imported_rows', '>', 0);
            });
    }
}
