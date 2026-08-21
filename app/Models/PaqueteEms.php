<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaqueteEms extends Model
{
    use HasFactory;

    protected $table = 'paquetes_ems';

    protected $fillable = [
        'origen',
        'tipo_correspondencia',
        'servicio_especial',
        'contenido',
        'cantidad',
        'peso',
        'codigo',
        'cod_especial',
        'envio_cn33',
        'precio',
        'nombre_remitente',
        'nombre_envia',
        'carnet',
        'telefono_remitente',
        'nombre_destinatario',
        'telefono_destinatario',
        'correo_electronico',
        'direccion',
        'referencia',
        'ciudad',
        'tarifario_id',
        'estado_id',
        'user_id',
        'imagen',
        'observacion',
        'justificacion',
    ];

    protected $casts = [
        'envio_cn33' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tarifario()
    {
        return $this->belongsTo(Tarifario::class, 'tarifario_id');
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    public function formulario()
    {
        return $this->hasOne(PaqueteEmsFormulario::class, 'paquete_ems_id');
    }

    public function bitacoras()
    {
        return $this->hasMany(Bitacora::class, 'paquetes_ems_id');
    }

    public function malencaminados()
    {
        return $this->hasMany(Malencaminado::class, 'paquetes_ems_id');
    }
}
