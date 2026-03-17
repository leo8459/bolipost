<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GasStation extends Model
{
    protected $table = 'gas_stations';

    protected $fillable = [
        'nit_emisor',
        'razon_social',
        'direccion',
        // campos antiguos conservados por compatibilidad
        'nombre',
        'ubicacion',
        'ciudad',
        'provincia',
        'telefono',
        'email',
        'latitud',
        'longitud',
        'activa',
    ];

    public function fuelLogs()
    {
        return $this->hasMany(FuelLog::class);
    }

    public function fuelInvoices()
    {
        return $this->hasMany(FuelInvoice::class);
    }
}
