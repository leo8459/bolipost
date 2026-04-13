<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturacionCartItem extends Model
{
    protected $table = 'facturacion_cart_items';

    protected $fillable = [
        'cart_id',
        'origen_tipo',
        'origen_id',
        'codigo',
        'titulo',
        'nombre_servicio',
        'nombre_destinatario',
        'servicios_extra',
        'resumen_origen',
        'cantidad',
        'monto_base',
        'monto_extras',
        'total_linea',
    ];

    protected $casts = [
        'servicios_extra' => 'array',
        'resumen_origen' => 'array',
        'monto_base' => 'decimal:2',
        'monto_extras' => 'decimal:2',
        'total_linea' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(FacturacionCart::class, 'cart_id');
    }
}
