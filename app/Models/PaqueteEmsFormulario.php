<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaqueteEmsFormulario extends Model
{
    use HasFactory;

    protected $table = 'paquetes_ems_formulario';

    protected $fillable = [
        'paquete_ems_id',
        'origen',
        'tipo_correspondencia',
        'servicio_especial',
        'contenido',
        'cantidad',
        'peso',
        'codigo',
        'precio',
        'nombre_remitente',
        'nombre_envia',
        'carnet',
        'telefono_remitente',
        'nombre_destinatario',
        'telefono_destinatario',
        'ciudad',
        'servicio_id',
        'destino_id',
        'tarifario_id',
    ];

    public function paquete()
    {
        return $this->belongsTo(PaqueteEms::class, 'paquete_ems_id');
    }
}
