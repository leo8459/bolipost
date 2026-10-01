<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierFlowReceivableCollection extends Model
{
    protected $table = 'cashier_flow_receivable_collections';

    protected $fillable = [
        'scope_hash',
        'servicio',
        'anio',
        'meses',
        'limite',
        'departamento',
        'monto_cobrado',
        'cobrado_por',
        'cobrado_at',
        'cobro_activo',
        'devuelto_por',
        'devuelto_at',
    ];

    protected $casts = [
        'anio' => 'integer',
        'meses' => 'array',
        'limite' => 'integer',
        'monto_cobrado' => 'decimal:2',
        'cobrado_at' => 'datetime',
        'cobro_activo' => 'boolean',
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
