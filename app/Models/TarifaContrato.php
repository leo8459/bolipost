<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TarifaContrato extends Model
{
    use HasFactory;

    protected $table = 'tarifa_contrato';

    protected $fillable = [
        'empresa_id',
        'origen',
        'destino',
        'servicio',
        'direccion',
        'zona',
        'peso',
        'kilo',
        'kilo_extra',
        'provincia',
        'provincia_origen',
        'retencion',
        'horas_entrega',
    ];

    protected $casts = [
        'peso' => 'decimal:2',
        'kilo' => 'decimal:2',
        'kilo_extra' => 'decimal:2',
        'retencion' => 'decimal:2',
        'horas_entrega' => 'integer',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function paquetesContrato()
    {
        return $this->hasMany(Recojo::class, 'tarifa_contrato_id');
    }
}
