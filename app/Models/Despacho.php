<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Despacho extends Model
{
    use HasFactory;

    protected $table = 'despacho';

    protected $fillable = [
        'oforigen',
        'ofdestino',
        'categoria',
        'subclase',
        'nro_despacho',
        'nro_envase',
        'peso',
        'identificador',
        'anio',
        'departamento',
        'estado',
    ];

    public function sacas()
    {
        return $this->hasMany(Saca::class, 'fk_despacho');
    }
}
