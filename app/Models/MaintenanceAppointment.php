<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class MaintenanceAppointment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'Pendiente';
    public const STATUS_APPROVED = 'Aprobado';
    public const STATUS_COMPLETED = 'Realizado';
    public const STATUS_REJECTED = 'Rechazado';
    public const STATUS_CANCELLED = 'Cancelado';

    protected $table = 'maintenance_appointments';

    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'requested_by_user_id',
        'tipo_mantenimiento_id',
        'fecha_programada',
        'solicitud_fecha',
        'origen_solicitud',
        'es_accidente',
        'evidencia_path',
        'formulario_documento_path',
        'estado',
        'approved_at',
        'approved_by_user_id',
        'activo',
    ];

    protected $casts = [
        'fecha_programada' => 'datetime',
        'solicitud_fecha' => 'datetime',
        'approved_at' => 'datetime',
        'es_accidente' => 'boolean',
        'activo' => 'boolean',
    ];

    public function scopeActive($query)
    {
        if (Schema::hasColumn($this->getTable(), 'activo')) {
            $query->where($this->qualifyColumn('activo'), true);
        }

        return $query;
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function tipoMantenimiento()
    {
        return $this->belongsTo(MaintenanceType::class, 'tipo_mantenimiento_id');
    }

    public function workshops(): HasMany
    {
        return $this->hasMany(Workshop::class);
    }

    public function isOverdue()
    {
        return in_array($this->estado, [self::STATUS_PENDING, self::STATUS_APPROVED], true)
            && $this->fecha_programada < now();
    }

    public function isCompleted()
    {
        return $this->estado === self::STATUS_COMPLETED;
    }

    public function isPendingApproval()
    {
        return $this->estado === self::STATUS_PENDING;
    }
}
