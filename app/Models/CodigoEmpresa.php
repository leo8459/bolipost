<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodigoEmpresa extends Model
{
    use HasFactory;

    protected $table = 'codigo_empresa';

    protected $fillable = [
        'codigo',
        'barcode',
        'empresa_id',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}

