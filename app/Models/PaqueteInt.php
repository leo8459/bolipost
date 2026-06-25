<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaqueteInt extends Model
{
    protected $table = 'paquetes_int';

    protected $fillable = [
        'cod_especial',
        'codigo',
        'origen',
        'peso',
        'destino',
    ];
}
