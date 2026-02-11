<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Saca extends Model
{
    use HasFactory;

    protected $table = 'saca';

    protected $fillable = [
        'nro_saca',
        'identificador',
        'estado',
        'peso',
        'paquetes',
        'busqueda',
        'receptaculo',
        'fk_despacho',
    ];

    public function despacho()
    {
        return $this->belongsTo(Despacho::class, 'fk_despacho');
    }
}
