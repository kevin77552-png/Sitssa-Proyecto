<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public const TIPOS = [
        'entrada' => 'Entrada',
        'salida' => 'Salida',
        'ajuste' => 'Ajuste',
        'saldo_inicial' => 'Saldo inicial',
        'inventario_inicial' => 'Inventario inicial (migración)',
    ];

    protected $guarded = [];

    protected $casts = [
        'delta' => 'integer',
        'stock_antes' => 'integer',
        'stock_despues' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id')->withTrashed();
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }
}
