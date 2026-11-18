<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class InventoryItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'item_number',
        'sigicov',
        'descripcion',
        'inventory_type_id',
        'unidad',
        'pasillo',
        'estante',
        'peldaño',
        'fecha',
        'realizado_por',
        'inventario',
        'resp_accesorios',
        'origen',
        'lote_importacion',
        'import_batch_id',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function inventoryType(): BelongsTo
    {
        return $this->belongsTo(InventoryType::class);
    }

    /**
     * Ensure empty fecha values are stored as NULL to avoid invalid date errors on MySQL.
     */
    public function setFechaAttribute($value)
    {
        $this->attributes['fecha'] = $value ?: null;
    }

    /**
     * Accessor: devuelve `fecha` formateada como d/m/Y para las vistas.
     */
    public function getFechaAttribute($value)
    {
        if (! $value) {
            return null;
        }

        return \Carbon\Carbon::parse($value)->format('d/m/Y');
    }

    /**
     * Booted: asegurar que ciertos campos se guarden siempre en MAYÚSCULAS.
     */
    protected static function booted()
    {
        static::creating(function ($model): void {
            if (blank($model->origen)) {
                $model->origen = 'ingreso_manual';
            }
        });

        static::saving(function ($model) {
            $fields = ['sigicov', 'descripcion', 'unidad', 'pasillo', 'estante', 'peldaño', 'realizado_por', 'resp_accesorios'];
            foreach ($fields as $f) {
                // Sólo procesar si el atributo existe y es string
                if (array_key_exists($f, $model->attributes) && is_string($model->attributes[$f])) {
                    $model->attributes[$f] = mb_strtoupper($model->attributes[$f], 'UTF-8');
                }
            }

            $model->sigicov_active_key = $model->deleted_at === null
                ? static::normalizeSigicovKey($model->sigicov)
                : null;
        });

        static::deleted(function ($model) {
            $model->sigicov_active_key = null;
            DB::table($model->getTable())->where($model->getKeyName(), $model->getKey())
                ->update(['sigicov_active_key' => null]);
        });
    }

    public static function normalizeSigicovKey(?string $sigicov): ?string
    {
        $value = mb_strtoupper(trim((string) $sigicov), 'UTF-8');
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $value = ltrim($value, '0');
        }

        return $value === '' ? '0' : $value;
    }

    public static function activeSigicovExists(?string $sigicov): bool
    {
        $key = static::normalizeSigicovKey($sigicov);

        return $key !== null && static::query()->where('sigicov_active_key', $key)->exists();
    }
}
