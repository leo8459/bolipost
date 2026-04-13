<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacturacionCart extends Model
{
    protected $table = 'facturacion_carts';

    protected $fillable = [
        'user_id',
        'estado',
        'modalidad_facturacion',
        'canal_emision',
        'tipo_documento',
        'numero_documento',
        'complemento_documento',
        'razon_social',
        'codigo_orden',
        'codigo_seguimiento',
        'estado_emision',
        'mensaje_emision',
        'respuesta_emision',
        'cantidad_items',
        'subtotal',
        'total_extras',
        'total',
        'abierto_en',
        'cerrado_en',
        'emitido_en',
    ];

    protected $casts = [
        'respuesta_emision' => 'array',
        'subtotal' => 'decimal:2',
        'total_extras' => 'decimal:2',
        'total' => 'decimal:2',
        'abierto_en' => 'datetime',
        'cerrado_en' => 'datetime',
        'emitido_en' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FacturacionCartItem::class, 'cart_id');
    }
}
