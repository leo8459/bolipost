<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConceptoFacturacion extends Model
{
    use HasFactory;

    protected $table = 'conceptos_facturacion';

    protected $fillable = [
        'nombre',
        'actividad_economica',
        'codigo_sin',
        'codigo',
        'unidad_medida',
        'descripcion',
        'precio_base',
        'activo',
    ];

    protected $casts = [
        'unidad_medida' => 'integer',
        'precio_base' => 'float',
        'activo' => 'boolean',
    ];
}
