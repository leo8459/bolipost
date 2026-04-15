<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class MaintenanceType extends Model
{
    use HasFactory;

    protected $table = 'maintenance_types';

    protected $fillable = [
        'nombre',
        'vehicle_class_id',
        'maintenance_form_type',
        'es_preventivo',
        'cada_km',
        'intervalo_km',
        'intervalo_km_init',
        'intervalo_km_fh',
        'km_alerta_previa',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'vehicle_class_id' => 'integer',
        'es_preventivo' => 'boolean',
        'cada_km' => 'integer',
        'intervalo_km' => 'integer',
        'intervalo_km_init' => 'integer',
        'intervalo_km_fh' => 'integer',
        'km_alerta_previa' => 'integer',
        'activo' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        if (Schema::hasColumn($this->getTable(), 'activo')) {
            $query->where($this->qualifyColumn('activo'), true);
        }

        return $query;
    }

    public function getMaintenanceFormTypeLabelAttribute(): string
    {
        return ($this->maintenance_form_type ?? 'vehiculo') === 'moto' ? 'Moto' : 'Vehiculo';
    }

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

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'maintenance_type_vehicle', 'maintenance_type_id', 'vehicle_id')
            ->withTimestamps();
    }

    public function scopeApplicableToVehicle(Builder $query, ?Vehicle $vehicle): Builder
    {
        if (!$vehicle) {
            return $query;
        }

        $vehicleFormType = trim((string) ($vehicle->maintenance_form_type ?: $vehicle->vehicleClass?->maintenance_form_type ?: ''));
        if ($vehicleFormType !== '' && Schema::hasColumn('maintenance_types', 'maintenance_form_type')) {
            $query->where(function ($typeQuery) use ($vehicleFormType) {
                $typeQuery->whereNull('maintenance_form_type')
                    ->orWhere('maintenance_form_type', $vehicleFormType);
            });
        }

        if (Schema::hasColumn('maintenance_types', 'vehicle_class_id')) {
            $vehicleClassId = $vehicle->vehicle_class_id ? (int) $vehicle->vehicle_class_id : null;
            if ($vehicleClassId) {
                $query->where(function ($typeQuery) use ($vehicleClassId) {
                    $typeQuery->whereNull('vehicle_class_id')
                        ->orWhere('vehicle_class_id', $vehicleClassId);
                });
            } else {
                $query->whereNull('vehicle_class_id');
            }
        }

        $vehicleScopedMatches = (clone $query)->where(function ($typeQuery) use ($vehicle) {
            $typeQuery->whereDoesntHave('vehicles')
                ->orWhereHas('vehicles', fn ($vehicleQuery) => $vehicleQuery->where('vehicles.id', (int) $vehicle->id));
        })->exists();

        if ($vehicleScopedMatches) {
            $query->where(function ($typeQuery) use ($vehicle) {
                $typeQuery->whereDoesntHave('vehicles')
                    ->orWhereHas('vehicles', fn ($vehicleQuery) => $vehicleQuery->where('vehicles.id', (int) $vehicle->id));
            });
        }

        return $query;
    }
}
