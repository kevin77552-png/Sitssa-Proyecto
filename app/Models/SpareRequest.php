<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class SpareRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'cantidad',
        'descripcion',
        'detalles',
        'solicitado_por',
        'prioridad',
        'tipo',
        'inventory_item_id',
        'comentario',
        'almacenista_id',
        'status',
        'reject_reason',
    ];

    /**
     * Usuario que realizó la solicitud (almacenista)
     */
    public function almacenista()
    {
        return $this->belongsTo(User::class, 'almacenista_id');
    }

    /**
     * Relación opcional a la PurchaseOrder generada a partir de esta solicitud.
     */
    public function purchaseOrder()
    {
        return $this->hasOne(PurchaseOrder::class, 'spare_request_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(SpareRequestItem::class);
    }

    public function displayItems(): Collection
    {
        $items = $this->relationLoaded('items')
            ? $this->getRelation('items')
            : $this->items()->get();

        if ($items->isNotEmpty()) {
            return $items;
        }

        return collect([
            (object) [
                'descripcion' => $this->descripcion,
                'cantidad' => $this->cantidad,
                'detalles' => $this->detalles,
            ],
        ]);
    }

}
