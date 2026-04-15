<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Workshop extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'Pendiente';
    public const STATUS_DISPATCHED = 'Despachado a taller';
    public const STATUS_DIAGNOSIS = 'Diagnosticado';
    public const STATUS_APPROVED = 'Aprobado';
    public const STATUS_REPAIR = 'En reparacion';
    public const STATUS_READY = 'Listo para recoger';
    public const STATUS_DELIVERED = 'Entregado';
    public const STATUS_CLOSED = 'Cerrado';
    public const STATUS_REJECTED = 'Rechazado';
    public const STATUS_CANCELLED = 'Cancelado';

    public const FLOW_LIGHT = 'Leve';
    public const FLOW_HEAVY = 'Grave';

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
        'workflow_kind',
        'approval_required',
        'fixed_catalog_cost',
        'labor_cost',
        'additional_cost',
        'total_cost',
        'diagnosis_requested_at',
        'fecha_aprobacion',
        'fecha_cierre',
        'dispatched_by_user_id',
        'diagnosed_by_user_id',
        'approved_by_user_id',
        'closed_by_user_id',
        'reassigned_from_workshop_catalog_id',
        'rejection_reason',
        'cancellation_reason',
        'reception_photo_path',
        'damage_photo_path',
        'invoice_file_path',
        'receipt_file_path',
        'pre_entrada_estado',
        'observaciones_tecnicas',
        'diagnostico',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_prometida_entrega' => 'date',
        'fecha_listo' => 'date',
        'fecha_salida' => 'date',
        'diagnosis_requested_at' => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'fecha_cierre' => 'datetime',
        'approval_required' => 'boolean',
        'fixed_catalog_cost' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'additional_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function scopeActive($query)
    {
        if (Schema::hasColumn($this->getTable(), 'activo')) {
            $query->where($this->qualifyColumn('activo'), true);
        }

        return $query;
    }

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

    public function reassignedFromWorkshopCatalog(): BelongsTo
    {
        return $this->belongsTo(WorkshopCatalog::class, 'reassigned_from_workshop_catalog_id');
    }

    public function partChanges(): HasMany
    {
        return $this->hasMany(WorkshopPartChange::class);
    }

    public function isClosedState(): bool
    {
        return in_array($this->estado, [self::STATUS_DELIVERED, self::STATUS_CLOSED], true);
    }

    public function isOpenState(): bool
    {
        return in_array($this->estado, [
            self::STATUS_PENDING,
            self::STATUS_DISPATCHED,
            self::STATUS_DIAGNOSIS,
            self::STATUS_APPROVED,
            self::STATUS_REPAIR,
            self::STATUS_READY,
        ], true);
    }
}
