<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Preregistro extends Model
{
    use HasFactory;

    protected $table = 'preregistros';

    protected $fillable = [
        'codigo_preregistro',
        'estado',
        'origen',
        'tipo_correspondencia',
        'servicio_especial',
        'contenido',
        'cantidad',
        'peso',
        'tarifa_estimada',
        'nombre_remitente',
        'nombre_envia',
        'carnet',
        'telefono_remitente',
        'nombre_destinatario',
        'telefono_destinatario',
        'direccion',
        'ciudad',
        'servicio_id',
        'destino_id',
        'validado_por',
        'validado_at',
        'paquete_ems_id',
        'codigo_generado',
    ];

    protected $casts = [
        'peso' => 'decimal:3',
        'tarifa_estimada' => 'decimal:2',
        'validado_at' => 'datetime',
    ];

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function destino()
    {
        return $this->belongsTo(Destino::class, 'destino_id');
    }

    public function validador()
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    public function paqueteEms()
    {
        return $this->belongsTo(PaqueteEms::class, 'paquete_ems_id');
    }
}
