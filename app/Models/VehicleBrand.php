<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleBrand extends Model
{
    protected $table = 'vehicle_brands';

    protected $fillable = [
        'nombre',
        'pais_origen',
    ];

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'marca_id');
    }
}
