<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierFlowReceivableMovement extends Model
{
    protected $table = 'cashier_flow_receivable_movements';

    protected $fillable = [
        'movement_key',
        'servicio',
        'anio',
        'mes',
        'venta_id',
        'detalle_id',
        'facturado_por_id',
        'facturado_por_nombre',
        'facturado_por_email',
        'facturado_por_alias',
        'codigo_orden',
        'codigo_seguimiento',
        'fecha',
        'descripcion',
        'monto',
        'cantidad_paquetes',
        'cobro_activo',
        'cobrado_por',
        'cobrado_at',
        'devuelto_por',
        'devuelto_at',
    ];

    protected $casts = [
        'anio' => 'integer',
        'mes' => 'integer',
        'monto' => 'decimal:2',
        'cantidad_paquetes' => 'decimal:2',
        'cobro_activo' => 'boolean',
        'cobrado_at' => 'datetime',
        'devuelto_at' => 'datetime',
    ];

    public function cobradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cobrado_por');
    }

    public function devueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'devuelto_por');
    }
}
