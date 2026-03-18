<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleClass extends Model
{
    protected $table = 'vehicle_classes';

    protected $fillable = [
        'marca_id',
        'modelo',
        'anio',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'anio' => 'integer',
        'activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(VehicleBrand::class, 'marca_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'vehicle_class_id');
    }

    public function maintenanceTypes(): HasMany
    {
        return $this->hasMany(MaintenanceType::class, 'vehicle_class_id');
    }
}

