<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recojo extends Model
{
    use HasFactory;

    protected $table = 'paquetes_contrato';

    protected $fillable = [
        'user_id',
        'codigo',
        'cod_especial',
        'estados_id',
        'origen',
        'destino',
        'nombre_r',
        'telefono_r',
        'contenido',
        'direccion_r',
        'nombre_d',
        'telefono_d',
        'direccion_d',
        'mapa',
        'provincia',
        'peso',
        'fecha_recojo',
        'observacion',
        'justificacion',
        'imagen',
    ];

    protected $casts = [
        'peso' => 'decimal:3',
        'fecha_recojo' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function estadoRegistro()
    {
        return $this->belongsTo(Estado::class, 'estados_id');
    }
}
