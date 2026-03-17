<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceAppointment extends Model
{
    use HasFactory;

    protected $table = 'maintenance_appointments';

    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'tipo_mantenimiento_id',
        'fecha_programada',
        'es_accidente',
        'descripcion_problema',
        'estado',
    ];

    protected $casts = [
        'fecha_programada' => 'datetime',
        'es_accidente' => 'boolean',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function tipoMantenimiento()
    {
        return $this->belongsTo(MaintenanceType::class, 'tipo_mantenimiento_id');
    }

    public function isOverdue()
    {
        return $this->estado === 'Pendiente' && $this->fecha_programada < now();
    }

    public function isCompleted()
    {
        return $this->estado === 'Realizado';
    }
}
