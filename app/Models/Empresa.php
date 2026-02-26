<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'empresa';

    protected $fillable = [
        'nombre',
        'sigla',
        'codigo_cliente',
    ];

    public function codigosEmpresa()
    {
        return $this->hasMany(CodigoEmpresa::class, 'empresa_id');
    }
}

