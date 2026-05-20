<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class VehicleBrand extends Model
{
    protected $table = 'vehicle_brands';

    protected $fillable = [
        'nombre',
        'pais_origen',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function scopeActive($query)
    {
        if (Schema::hasColumn($this->getTable(), 'activo')) {
            $query->where($this->qualifyColumn('activo'), true);
        }

        return $query;
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'marca_id');
    }
}
