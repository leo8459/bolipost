<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleAssignment extends Model
{
    use HasFactory;

    protected $table = 'vehicle_assignments';

    protected $fillable = [
        'driver_id',
        'vehicle_id',
        'tipo_asignacion',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'activo' => 'boolean',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function isActive()
    {
        return $this->activo && (!$this->fecha_fin || $this->fecha_fin >= now());
    }
}
