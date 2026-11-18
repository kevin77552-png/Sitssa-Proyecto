<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehiculo extends Model
{
    use HasFactory;

    public const ESTADOS = [
        'activa' => 'Activa',
        'inactiva' => 'Inactiva',
    ];

    protected $fillable = [
        'placa',
        'descripcion',
        'tipo',
        'modelo',
        'estado',
        'external_id',
        'source',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('estado', 'activa');
    }

    public function salidas(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'vehiculo_id')
            ->where('tipo', 'salida')
            ->orderByDesc('occurred_at');
    }

    public function etiqueta(): string
    {
        return trim($this->placa.' · '.($this->descripcion ?: 'Sin descripción'));
    }
}
