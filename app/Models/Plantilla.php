<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plantilla extends Model
{
    use HasFactory;

    // 🔹 Nombre de la tabla (opcional, Laravel lo infiere bien)
    protected $table = 'plantillas';

    // 🔹 Campos que se pueden insertar/actualizar masivamente
    protected $fillable = [
        'nombre',
        'ciudad',
        'destinatario',
        'remitente',
        'telefono',
        'ciudad_destino',
        'estado',
        'observacion',
    ];

    // 🔹 Valores por defecto a nivel de modelo (opcional)
    protected $attributes = [
        'estado' => 'LISTO',
    ];
}
