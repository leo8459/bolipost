<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudCambio extends Model
{
    protected $fillable = ['user_id', 'fecha', 'ciudad', 'solicitante', 'cargo', 'unidad', 'sistema', 'modulo', 'ambiente', 'tipo', 'otro_tipo', 'antecedentes', 'detalle', 'prioridad'];

    protected $casts = ['fecha' => 'date'];

    public const TIPOS = ['Incidencia o error', 'Mejora', 'Nueva función', 'Acceso', 'Consulta', 'Otro'];

    public function getNumeroAttribute(): string
    {
        return 'SIS-'.$this->fecha->format('Y').'-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
