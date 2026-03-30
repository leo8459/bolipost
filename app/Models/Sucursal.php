<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sucursal extends Model
{
    use HasFactory;

    protected $table = 'sucursales';

    protected $fillable = [
        'codigoSucursal',
        'puntoVenta',
        'municipio',
        'departamento',
        'telefono',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'sucursal_id');
    }
}
