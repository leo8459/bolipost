<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TarifarioContrato extends Model
{
    protected $table = 'tarifarios_contrato';

    protected $fillable = ['nombre', 'anio', 'publicado_en'];

    protected $casts = ['anio' => 'integer', 'publicado_en' => 'datetime'];

    public function tarifas(): HasMany
    {
        return $this->hasMany(TarifaContrato::class);
    }

    public function serviciosDisponibles()
    {
        return ServicioContrato::deAnio($this->anio)->orderBy('nombre');
    }

    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'tarifario_contrato_empresa')
            ->withPivot(['activo', 'asignado_en', 'finalizado_en']);
    }

    public function empresasActivas(): BelongsToMany
    {
        return $this->empresas()->wherePivot('activo', true);
    }

    public function getEtiquetaAttribute(): string
    {
        return $this->nombre.' · '.($this->anio ?? 'Año por definir');
    }
}
