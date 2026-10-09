<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Servicio extends Model
{
    use HasFactory;

    protected $table = 'servicio';

    protected $fillable = [
        'tarifario_padre_id',
        'nombre_servicio',
        'actividadEconomica',
        'codigoSin',
        'codigo',
        'descripcion',
        'unidadMedida',
    ];

    public function tarifarioPadre(): BelongsTo
    {
        return $this->belongsTo(TarifarioPadre::class, 'tarifario_padre_id');
    }
}
