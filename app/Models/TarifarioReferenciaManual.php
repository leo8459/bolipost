<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TarifarioReferenciaManual extends Model
{
    protected $table = 'tarifario_referencias_manuales';

    protected $fillable = [
        'codigo',
        'nombre',
        'estado',
        'observacion',
        'publicada_por',
        'publicada_en',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected $casts = [
        'publicada_en' => 'datetime',
        'vigente_desde' => 'datetime',
        'vigente_hasta' => 'datetime',
    ];

    public function scopeVigente(Builder $query): Builder
    {
        return $query->where('estado', 'VIGENTE')
            ->where('vigente_desde', '<=', now())
            ->where(function (Builder $scope): void {
                $scope->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', now());
            });
    }

    public static function actual(): ?self
    {
        return static::query()->vigente()->orderByDesc('vigente_desde')->orderByDesc('id')->first();
    }

    public function snapshot(): array
    {
        return [
            'version_id' => (int) $this->id,
            'version_codigo' => (string) $this->codigo,
            'version_nombre' => (string) $this->nombre,
            'publicada_en' => $this->publicada_en?->toIso8601String(),
            'vigente_desde' => $this->vigente_desde?->toIso8601String(),
            'modalidad_precio' => 'MANUAL',
        ];
    }
}
