<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough; // <--- IMPORTANTE
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\VehicleLog; // <--- IMPORTANTE
use App\Models\FuelLog;
use App\Models\Workshop;

class Vehicle extends Model
{
    use SoftDeletes;

    public const OPERATIONAL_STATUS_AVAILABLE = 'Disponible';
    public const OPERATIONAL_STATUS_IN_MAINTENANCE = 'En Mantenimiento';

    protected $table = 'vehicles';

    protected $fillable = [
        'placa',
        'marca_id',
        'vehicle_class_id',
        'maintenance_form_type',
        'modelo',
        'tipo_combustible',
        'color',
        'anio',
        'capacidad_tanque',
        'kilometraje_inicial',
        'kilometraje_actual',
        'kilometraje',
        'activo',
        'operational_status',
        'tacometro_danado',
    ];

    protected $casts = [
        'vehicle_class_id' => 'integer',
        'activo' => 'boolean',
        'tacometro_danado' => 'boolean',
        'anio' => 'integer',
        'capacidad_tanque' => 'decimal:2',
        'kilometraje_inicial' => 'decimal:2',
        'kilometraje_actual' => 'decimal:2',
        'kilometraje' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function scopeOperationallyAvailable($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('operational_status')
                ->orWhere('operational_status', self::OPERATIONAL_STATUS_AVAILABLE);
        });
    }

    public function isInMaintenance(): bool
    {
        return (string) ($this->operational_status ?? self::OPERATIONAL_STATUS_AVAILABLE) === self::OPERATIONAL_STATUS_IN_MAINTENANCE;
    }

    /**
     * Relación con las asignaciones de vehículos a conductores
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class, 'vehicle_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(VehicleBrand::class, 'marca_id');
    }

    public function vehicleClass(): BelongsTo
    {
        return $this->belongsTo(VehicleClass::class, 'vehicle_class_id');
    }

    public function getMaintenanceFormTypeLabelAttribute(): string
    {
        return ($this->maintenance_form_type ?? 'vehiculo') === 'moto' ? 'Moto' : 'Vehiculo';
    }

    public function getMarcaAttribute(): string
    {
        if (array_key_exists('marca', $this->attributes) && !empty($this->attributes['marca'])) {
            return (string) $this->attributes['marca'];
        }

        return (string) ($this->brand?->nombre ?? '');
    }

    public function getDisplayNameAttribute(): string
    {
        $parts = array_filter([
            $this->vehicleClass?->nombre,
            $this->brand?->nombre ?: $this->marca,
            $this->placa,
        ], fn ($value) => filled($value));

        return !empty($parts) ? implode(' - ', $parts) : 'N/A';
    }

    public function getBitacoraDisplayNameAttribute(): string
    {
        $parts = array_filter([
            $this->anio,
            $this->brand?->nombre ?: $this->marca,
            $this->placa,
        ], fn ($value) => filled($value));

        return !empty($parts) ? implode(' - ', $parts) : 'N/A';
    }

    public function getKilometrajeAttribute(): ?float
    {
        $actual = $this->attributes['kilometraje_actual'] ?? null;
        if ($actual !== null && $actual !== '') {
            return (float) $actual;
        }

        $inicial = $this->attributes['kilometraje_inicial'] ?? null;
        if ($inicial !== null && $inicial !== '') {
            return (float) $inicial;
        }

        $legacy = $this->attributes['kilometraje'] ?? null;
        if ($legacy !== null && $legacy !== '') {
            return (float) $legacy;
        }

        return null;
    }

    public function setKilometrajeAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['kilometraje_inicial'] = null;
            $this->attributes['kilometraje_actual'] = null;
            return;
        }

        $km = (float) $value;
        $this->attributes['kilometraje_inicial'] = $km;
        $this->attributes['kilometraje_actual'] = $km;
    }

    /**
     * Obtener la asignación activa actual
     */
    public function currentAssignment()
    {
        return $this->assignments()
            ->latest('fecha_inicio')
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    /**
     * Relación con los registros de bitácora del vehículo
     */
    public function vehicleLogs(): HasMany
    {
        return $this->hasMany(VehicleLog::class, 'vehicles_id')->active();
    }

    /**
     * Relación con las citas de mantenimiento
     */
    public function maintenanceAppointments(): HasMany
    {
        return $this->hasMany(MaintenanceAppointment::class, 'vehicle_id');
    }

    /**
     * Relación con los registros de mantenimiento
     */
    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class, 'vehicle_id')->active();
    }

    public function workshops(): HasMany
    {
        return $this->hasMany(Workshop::class, 'vehicle_id');
    }

    public function maintenanceAlerts(): HasMany
    {
        return $this->hasMany(MaintenanceAlert::class, 'vehicle_id');
    }


    /**
     * Obtener el último registro de bitácora
     */
    public function lastVehicleLog()
    {
        return $this->vehicleLogs()->latest('fecha')->first();
    }

    /**
     * Obtener el próximo mantenimiento pendiente
     */
    public function nextPendingMaintenance()
    {
        return $this->maintenanceLogs()
            ->where('proxima_fecha', '>', now())
            ->oldest('proxima_fecha')
            ->first();
    }

    /**
     * Obtener el kilometraje actual (del último registro de bitácora)
     */
    public function getCurrentKilometrage()
    {
        $lastLog = $this->lastVehicleLog();
        if ($lastLog) {
            return $lastLog->kilometraje_llegada ?? $lastLog->kilometraje_salida ?? 0;
        }

        if ($this->kilometraje_actual !== null) {
            return (float) $this->kilometraje_actual;
        }

        if ($this->kilometraje_inicial !== null) {
            return (float) $this->kilometraje_inicial;
        }

        return (float) ($this->kilometraje ?? 0);
    }
    public function fuelLogs(): HasManyThrough
    {
        return $this->hasManyThrough(
            FuelLog::class,      // El modelo final (Combustible)
            VehicleLog::class,   // El modelo intermedio (Bitácora)
            'vehicles_id',       // Llave en vehicle_log que apunta a vehicles
            'id',                // Llave en fuel_logs (su propia ID)
            'id',                // Llave local en vehicles
            'fuel_log_id'        // Llave en vehicle_log que apunta a fuel_logs
        );
    }
}

