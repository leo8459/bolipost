<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleLog extends Model
{
    // Nombre de la tabla
    protected $table = 'vehicle_log';

    protected $fillable = [
        'drivers_id',
        'vehicles_id',
        'fuel_log_id',
        'fecha',
        'kilometraje_salida',
        'kilometraje_llegada',
        'kilometraje_recorrido',
        'recorrido_inicio',
        'latitud_inicio',
        'logitud_inicio',
        'recorrido_destino',
        'latitud_destino',
        'logitud_destino',
        'abastecimiento_combustible',
        'firma_digital',
        'odometro_photo_path',
        'ruta_json', // <--- Agregamos esto
        'points_json',
    ];

    protected $casts = [
        'fecha' => 'date',
        'kilometraje_salida' => 'decimal:2',
        'kilometraje_llegada' => 'decimal:2',
        'kilometraje_recorrido' => 'decimal:2',
        'latitud_inicio' => 'decimal:8',
        'logitud_inicio' => 'decimal:8',
        'latitud_destino' => 'decimal:8',
        'logitud_destino' => 'decimal:8',
        'abastecimiento_combustible' => 'boolean',
        'ruta_json' => 'array', // <--- Importante: esto lo convierte en array de PHP
        'points_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    protected $appends = [
        'points_json',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $log): void {
            $log->normalizeRouteData();
        });
    }

    /**
     * Relación: La bitácora pertenece a un vehículo
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicles_id')->withTrashed();
    }

    /**
     * Relación: La bitácora pertenece a un conductor
     */
    public function driver(): BelongsTo
    {
        // Usamos 'drivers_id' porque así está en tu script SQL
        return $this->belongsTo(Driver::class, 'drivers_id')->withTrashed();
    }

    /**
     * Relación: La bitácora puede estar asociada a un registro de combustible
     */
    public function fuelLog(): BelongsTo
    {
        return $this->belongsTo(FuelLog::class, 'fuel_log_id');
    }

    /**
     * Calcular distancia recorrida
     */
    public function getDistanceTravelledAttribute(): ?float
    {
        if ($this->kilometraje_recorrido !== null) {
            return (float) $this->kilometraje_recorrido;
        }

        if ($this->kilometraje_llegada && $this->kilometraje_salida) {
            return $this->kilometraje_llegada - $this->kilometraje_salida;
        }
        return null;
    }

    public function getPointsJsonAttribute(): array
    {
        $value = $this->attributes['ruta_json'] ?? null;

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($this->ruta_json) ? $this->ruta_json : [];
    }

    public function setPointsJsonAttribute(mixed $value): void
    {
        $this->setAttribute('ruta_json', $value);
    }

    public function normalizeRouteData(): void
    {
        [$latInicio, $lngInicio] = $this->resolveCoordinatePair(
            $this->latitud_inicio,
            $this->logitud_inicio,
            (string) ($this->recorrido_inicio ?? '')
        );
        [$latDestino, $lngDestino] = $this->resolveCoordinatePair(
            $this->latitud_destino,
            $this->logitud_destino,
            (string) ($this->recorrido_destino ?? '')
        );

        $this->latitud_inicio = $latInicio;
        $this->logitud_inicio = $lngInicio;
        $this->latitud_destino = $latDestino;
        $this->logitud_destino = $lngDestino;

        $this->recorrido_inicio = $this->normalizeRouteLabel(
            (string) ($this->recorrido_inicio ?? ''),
            $latInicio,
            $lngInicio,
            'Ubicacion de salida',
            'start'
        );
        $this->recorrido_destino = $this->normalizeRouteLabel(
            (string) ($this->recorrido_destino ?? ''),
            $latDestino,
            $lngDestino,
            'Ubicacion de llegada',
            'end'
        );
    }

    private function resolveCoordinatePair(mixed $latRaw, mixed $lngRaw, string $routeText): array
    {
        $lat = $this->normalizeLatitude($this->toNullableFloat($latRaw));
        $lng = $this->normalizeLongitude($this->toNullableFloat($lngRaw));

        if ($lat !== null && $lng !== null) {
            return [$lat, $lng];
        }

        [$textLat, $textLng] = $this->extractCoordinatesFromText($routeText);
        $textLat = $this->normalizeLatitude($textLat);
        $textLng = $this->normalizeLongitude($textLng);

        return [$textLat, $textLng];
    }

    private function normalizeRouteLabel(string $raw, ?float $lat, ?float $lng, string $fallback, string $pointType): string
    {
        $label = $this->cleanRouteLabel($raw);
        $isPlaceholder = $this->isPlaceholderRouteLabel($label);
        $isCoordsText = $this->looksLikeCoordinateText($label);

        if ($label !== '' && !$isPlaceholder && !$isCoordsText) {
            return $this->limitRouteLabel($label);
        }

        $fromRoute = $this->extractAddressFromRoutePoints($pointType);
        if ($fromRoute !== null) {
            return $fromRoute;
        }

        if ($lat !== null && $lng !== null) {
            return 'Ubicacion marcada';
        }

        return $fallback;
    }

    private function cleanRouteLabel(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $squished = preg_replace('/\s+/', ' ', $trimmed);
        return is_string($squished) ? $squished : $trimmed;
    }

    private function isPlaceholderRouteLabel(string $value): bool
    {
        $needle = mb_strtolower(trim($value));
        if ($needle === '') {
            return true;
        }

        if ($this->looksLikeDateTimeLabel($needle)) {
            return true;
        }

        return in_array($needle, [
            'sincronizacion app',
            'en ruta',
            'no definido',
            'offline snapshot',
            'heartbeat',
            'punto a',
            'punto b',
            'punto de salida',
            'punto de llegada',
        ], true);
    }

    private function looksLikeCoordinateText(string $value): bool
    {
        return preg_match('/^\s*-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?\s*$/', $value) === 1;
    }

    private function looksLikeDateTimeLabel(string $value): bool
    {
        return preg_match('/^\d{1,2}\/\d{1,2}\/\d{4},\s*\d{1,2}:\d{2}/', $value) === 1
            || preg_match('/^\d{4}-\d{2}-\d{2}t\d{2}:\d{2}/', $value) === 1;
    }

    private function extractCoordinatesFromText(string $value): array
    {
        if (preg_match('/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/', $value, $matches) !== 1) {
            return [null, null];
        }

        $lat = $this->toNullableFloat($matches[1] ?? null);
        $lng = $this->toNullableFloat($matches[2] ?? null);

        return [$lat, $lng];
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function normalizeLatitude(?float $value): ?float
    {
        if ($value === null || $value < -90 || $value > 90) {
            return null;
        }

        return round($value, 8);
    }

    private function normalizeLongitude(?float $value): ?float
    {
        if ($value === null || $value < -180 || $value > 180) {
            return null;
        }

        return round($value, 8);
    }

    private function formatCoordinateLabel(float $lat, float $lng): string
    {
        return sprintf('%.6f, %.6f', $lat, $lng);
    }

    private function limitRouteLabel(string $value): string
    {
        if (mb_strlen($value) <= 255) {
            return $value;
        }

        return mb_substr($value, 0, 255);
    }

    private function extractAddressFromRoutePoints(string $pointType): ?string
    {
        $route = is_array($this->ruta_json) ? $this->ruta_json : [];
        if (empty($route)) {
            return null;
        }

        $point = null;
        if ($pointType === 'start') {
            $point = $route[0] ?? null;
        } else {
            $point = $route[array_key_last($route)] ?? null;
        }

        if (!is_array($point)) {
            return null;
        }

        $candidate = (string) ($point['address'] ?? $point['label'] ?? $point['point_label'] ?? '');
        $candidate = $this->cleanRouteLabel($candidate);

        if ($candidate === '' || $this->isPlaceholderRouteLabel($candidate) || $this->looksLikeCoordinateText($candidate)) {
            return null;
        }

        return $this->limitRouteLabel($candidate);
    }
}
