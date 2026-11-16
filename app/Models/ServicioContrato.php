<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicioContrato extends Model
{
    public const PREDEFINIDOS = [
        'ENVIO NACIONAL (REGULAR)',
        'ENVIO NACIONAL (REGULAR) TERRESTRE',
        'ENVIO NACIONAL (REGULAR) AEREO',
        'ENVIO NACIONAL (EXPRESS)',
        'ENVIO NACIONAL (EXPRESS) TERRESTRE',
        'ENVIO NACIONAL (EXPRESS) AEREO',
        'ENVIO LOCAL(REGULAR)',
        'ENVIO LOCAL(EXPRESS)',
        'ENVIO INTERPROVINCIAL',
        'ENVIO INTERPROVINCIAL TERRESTRE',
        'ENVIO INTERPROVINCIAL AEREO',
    ];

    protected $table = 'servicios_contrato';

    protected $fillable = ['nombre', 'anio'];

    protected $casts = ['anio' => 'integer'];

    public function tarifas(): HasMany
    {
        return $this->hasMany(TarifaContrato::class);
    }

    public function scopeDeAnio(Builder $query, ?int $anio): Builder
    {
        return $anio === null ? $query->whereNull('anio') : $query->where('anio', $anio);
    }

    public static function normalizarNombre(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return str_replace(['LOCAL (REGULAR)', 'LOCAL (EXPRESS)'], ['LOCAL(REGULAR)', 'LOCAL(EXPRESS)'], $value);
    }

    public static function asegurar(string $nombre, ?int $anio): self
    {
        return self::query()->firstOrCreate(['nombre' => self::normalizarNombre($nombre), 'anio' => $anio]);
    }

    public static function catalogoPorAnio(): array
    {
        return self::query()->orderBy('nombre')->get()->groupBy(fn ($item) => $item->anio ?? 'sin-anio')
            ->map(fn ($items) => $items->pluck('nombre')->values()->all())->all();
    }
}
