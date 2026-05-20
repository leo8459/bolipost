<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
// 💡 ESTA ES LA LÍNEA QUE FALTA:
use App\Models\VehicleLog;
use App\Models\FuelLog;
use App\Models\DriverIncentiveReport;
use App\Models\Workshop;

class Driver extends Model
{
    use SoftDeletes;

    protected $table = 'drivers';

    protected $fillable = [
        'user_id',
        'nombre',
        'licencia',
        'tipo_licencia',
        'fecha_vencimiento_licencia',
        'telefono',
        'email',
        'memorandum_path',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_vencimiento_licencia' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relación con el usuario del sistema
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con las asignaciones de vehículos
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class, 'driver_id');
    }

    /**
     * Obtener el vehículo asignado actualmente
     */
    public function currentVehicle()
    {
        $assignment = $this->assignments()
            ->latest('fecha_inicio')
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if (!$assignment) {
            return null;
        }

        $isActive = (bool) ($assignment->activo ?? false);
        $endDate = $assignment->fecha_fin;
        $startsAt = $assignment->fecha_inicio;
        $today = now()->startOfDay();

        if (!$isActive) {
            return null;
        }

        if ($startsAt && $startsAt->copy()->startOfDay()->gt($today)) {
            return null;
        }

        if ($endDate && $endDate->copy()->startOfDay()->lt($today)) {
            return null;
        }

        return $assignment->vehicle;
    }

    /**
     * Relación con las citas de mantenimiento
     */
    public function maintenanceAppointments(): HasMany
    {
        return $this->hasMany(MaintenanceAppointment::class, 'driver_id');
    }

    public function incentiveReports(): HasMany
    {
        return $this->hasMany(DriverIncentiveReport::class, 'driver_id');
    }

    public function workshops(): HasMany
    {
        return $this->hasMany(Workshop::class, 'driver_id');
    }

    /**
     * Relación con los registros de bitácora del vehículo
     */
    public function vehicleLogs(): HasMany
    {
        return $this->hasMany(VehicleLog::class, 'drivers_id')->active();
    }

    /**
     * Verificar si la licencia está vencida
     */
    public function isLicenseExpired(): bool
    {
        if (!$this->fecha_vencimiento_licencia) {
            return false;
        }
        return $this->fecha_vencimiento_licencia < now();
    }

    /**
     * Obtener días restantes para vencimiento de licencia
     */
    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->fecha_vencimiento_licencia) {
            return null;
        }
        return now()->diffInDays($this->fecha_vencimiento_licencia, false);
    }


    public function fuelLogs(): HasManyThrough
    {
        return $this->hasManyThrough(
            FuelLog::class,      // El modelo final
            VehicleLog::class,   // El modelo intermedio
            'drivers_id',        // FK en VehicleLog -> Driver
            'id',                // FK en FuelLog (su ID propia)
            'id',                // Local en Driver
            'fuel_log_id'        // Local en VehicleLog -> FuelLog
        );
    }
}
