<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceLog extends Model
{
    use SoftDeletes;

    protected $table = 'maintenance_logs';

    protected $fillable = [
        'vehicle_id',
        'maintenance_type_id',
        'tipo',
        'fecha',
        'proxima_fecha',
        'costo',
        'kilometraje',
        'proximo_kilometraje',
        'taller',
        'descripcion',
        'comprobante',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'proxima_fecha' => 'date',
        'costo' => 'decimal:2',
        'kilometraje' => 'decimal:2',
        'proximo_kilometraje' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relación con el vehículo
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class, 'maintenance_type_id');
    }

    /**
     * Obtener el estado del mantenimiento
     */
    public function getStatusAttribute()
    {
        if (!$this->proxima_fecha) {
            return 'completado';
        }

        if ($this->proxima_fecha < now()) {
            return 'vencido';
        }

        $daysDifference = now()->diffInDays($this->proxima_fecha);

        if ($daysDifference <= 7) {
            return 'proximo';
        }

        return 'pendiente';
    }

    /**
     * Verificar si el mantenimiento está próximo (7 días o menos)
     */
    public function isUpcoming(): bool
    {
        if (!$this->proxima_fecha) {
            return false;
        }

        return $this->proxima_fecha > now() && 
               $this->proxima_fecha < now()->addDays(7);
    }

    /**
     * Verificar si el mantenimiento está vencido
     */
    public function isOverdue(): bool
    {
        if (!$this->proxima_fecha) {
            return false;
        }

        return $this->proxima_fecha < now();
    }

    /**
     * Obtener el tipo de mantenimiento de forma más legible
     */
    public function getTipoLegible()
    {
        $tipos = [
            'cambio_aceite' => 'Cambio de Aceite',
            'revision_frenos' => 'Revisión de Frenos',
            'cambio_filtro_aire' => 'Cambio de Filtro de Aire',
            'cambio_filtro_combustible' => 'Cambio de Filtro de Combustible',
            'alineacion' => 'Alineación',
            'balanceo' => 'Balanceo',
            'cambio_llantas' => 'Cambio de Llantas',
            'revision_suspension' => 'Revisión de Suspensión',
            'revision_motor' => 'Revisión de Motor',
            'cambio_bateria' => 'Cambio de Batería',
            'reparacion_carroceria' => 'Reparación de Carrocería',
            'revision_electrica' => 'Revisión Eléctrica',
            'otro' => 'Otro',
        ];

        return $tipos[$this->tipo] ?? $this->tipo;
    }
}
