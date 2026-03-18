<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceType extends Model
{
    use HasFactory;

    protected $table = 'maintenance_types';

    protected $fillable = [
        'nombre',
        'vehicle_class_id',
        'cada_km',
        'intervalo_km',
        'intervalo_km_init',
        'intervalo_km_fh',
        'km_alerta_previa',
        'descripcion',
    ];

    protected $casts = [
        'vehicle_class_id' => 'integer',
        'cada_km' => 'integer',
        'intervalo_km' => 'integer',
        'intervalo_km_init' => 'integer',
        'intervalo_km_fh' => 'integer',
        'km_alerta_previa' => 'integer',
    ];

    public function maintenanceAppointments()
    {
        return $this->hasMany(MaintenanceAppointment::class, 'tipo_mantenimiento_id');
    }

    public function maintenanceLogs()
    {
        return $this->hasMany(MaintenanceLog::class, 'maintenance_type_id');
    }

    public function vehicleClass()
    {
        return $this->belongsTo(VehicleClass::class, 'vehicle_class_id');
    }
}
