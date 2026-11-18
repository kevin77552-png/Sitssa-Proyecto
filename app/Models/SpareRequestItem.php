<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpareRequestItem extends Model
{
    protected $fillable = [
        'descripcion',
        'cantidad',
        'detalles',
    ];

    public function spareRequest(): BelongsTo
    {
        return $this->belongsTo(SpareRequest::class);
    }
}
