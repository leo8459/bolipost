<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workshop extends Model
{
    use HasFactory;

    public const STATUS_DISPATCHED = 'Despachado';
    public const STATUS_DIAGNOSIS = 'En diagnostico';
    public const STATUS_REPAIR = 'En reparacion';
    public const STATUS_READY = 'Listo';
    public const STATUS_DELIVERED = 'Entregado';

    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'maintenance_appointment_id',
        'maintenance_log_id',
        'workshop_catalog_id',
        'maintenance_alert_id',
        'order_number',
        'nombre_taller',
        'fecha_ingreso',
        'fecha_prometida_entrega',
        'fecha_listo',
        'fecha_salida',
        'estado',
        'pre_entrada_estado',
        'observaciones_tecnicas',
        'diagnostico',
        'observaciones',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_prometida_entrega' => 'date',
        'fecha_listo' => 'date',
        'fecha_salida' => 'date',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function maintenanceAppointment(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAppointment::class);
    }

    public function maintenanceLog(): BelongsTo
    {
        return $this->belongsTo(MaintenanceLog::class);
    }

    public function maintenanceAlert(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAlert::class);
    }

    public function workshopCatalog(): BelongsTo
    {
        return $this->belongsTo(WorkshopCatalog::class);
    }

    public function partChanges(): HasMany
    {
        return $this->hasMany(WorkshopPartChange::class);
    }

    public function isClosedState(): bool
    {
        return in_array($this->estado, [self::STATUS_READY, self::STATUS_DELIVERED], true);
    }
}
