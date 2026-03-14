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
        'empresa_id',
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
        'precio',
        'tarifa_contrato_id',
        'fecha_recojo',
        'observacion',
        'justificacion',
        'imagen',
    ];

    protected $casts = [
        'peso' => 'decimal:3',
        'precio' => 'decimal:2',
        'fecha_recojo' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function estadoRegistro()
    {
        return $this->belongsTo(Estado::class, 'estados_id');
    }

    public function tarifaContrato()
    {
        return $this->belongsTo(TarifaContrato::class, 'tarifa_contrato_id');
    }

    public function bitacoras()
    {
        return $this->hasMany(Bitacora::class, 'paquetes_contrato_id');
    }
}
