<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceAlert extends Model
{
    public const STATUS_ACTIVE = 'Activa';
    public const STATUS_RESOLVED = 'Resuelta';
    public const STATUS_OMITTED = 'Omitida';

    protected $table = 'maintenance_alerts';

    protected $fillable = [
        'vehicle_id',
        'maintenance_type_id',
        'maintenance_appointment_id',
        'tipo',
        'mensaje',
        'leida',
        'status',
        'fecha_resolucion',
        'postponed_until',
        'postponed_once',
        'usuario_id',
        'kilometraje_actual',
        'kilometraje_objetivo',
        'faltante_km',
    ];

    protected $casts = [
        'leida' => 'boolean',
        'fecha_resolucion' => 'datetime',
        'postponed_until' => 'datetime',
        'postponed_once' => 'boolean',
        'kilometraje_actual' => 'decimal:2',
        'kilometraje_objetivo' => 'decimal:2',
        'faltante_km' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class, 'maintenance_type_id');
    }

    public function maintenanceAppointment(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAppointment::class, 'maintenance_appointment_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function workshops(): HasMany
    {
        return $this->hasMany(Workshop::class, 'maintenance_alert_id');
    }
}
