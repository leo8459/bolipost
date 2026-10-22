<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteSolicitudQr extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'solicitud_data' => 'array',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
        'checked_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCliente::class, 'solicitud_id');
    }
}
