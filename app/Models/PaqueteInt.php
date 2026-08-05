<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaqueteInt extends Model
{
    protected $table = 'paquetes_int';

    protected $fillable = [
        'cod_especial',
        'envio_cn33',
        'codigo',
        'servicio_id',
        'estado_id',
        'origen',
        'peso',
        'precio',
        'destino',
        'tramo',
        'enviado_admision_at',
    ];

    protected $casts = [
        'envio_cn33' => 'datetime',
    ];

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }
}
