<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'cantidad',
        'descripcion',
        'solicitado_por',
        'pedido_por',
        'detalles',
        'status', // pendiente, en_proceso, completado
        'attachment', // ruta del archivo subido por el proveedor (pdf/jpg/png)
        'approved_at', // timestamp cuando se aprobó (se subió factura)
        'spare_request_id', // relación a la solicitud que originó la orden
        'reject_reason', // motivo del rechazo por el gerente
        'provider_id',
    ];

    /**
     * Cast dates to Carbon instances so view helpers like ->locale() work.
     *
     * @var array
     */
    protected $casts = [
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Si la orden proviene de una solicitud, esta relación apunta a ella.
     */
    public function spareRequest()
    {
        return $this->belongsTo(SpareRequest::class, 'spare_request_id');
    }

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
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
                'detalles' => null,
            ],
        ]);
    }
}
