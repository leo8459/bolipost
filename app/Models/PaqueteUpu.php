<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaqueteUpu extends Model
{
    protected $table = 'paquetes_upu';

    protected $fillable = [
        'codigo',
        'destinatario',
        'telefono',
        'destino',
        'zona',
        'peso',
    ];

    protected $casts = [
        'peso' => 'decimal:3',
    ];
}
