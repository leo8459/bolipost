<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelLog extends Model
{
    protected $table = 'fuel_invoice_details';

    protected $fillable = [
        'fuel_invoice_id',
        'vehicle_id',
        'driver_id',
        'gas_station_id',
        'fecha_emision',
        'cantidad',       // Nombre real en BD
        'galones',        // Alias para compatibilidad
        'precio_unitario', // Nombre real en BD
        'precio_galon',    // Alias para compatibilidad
        'subtotal',        // Nombre real en BD
        'total_calculado', // Alias para compatibilidad
        'kilometraje',
        'recibo',
        'observaciones',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Mapeo de alias a columnas reales
            if (isset($model->attributes['galones']) && !isset($model->attributes['cantidad'])) {
                $model->attributes['cantidad'] = $model->attributes['galones'];
            }
            if (isset($model->attributes['precio_galon']) && !isset($model->attributes['precio_unitario'])) {
                $model->attributes['precio_unitario'] = $model->attributes['precio_galon'];
            }
            
            // Calcular subtotal
            $cantidad = $model->cantidad ?? $model->galones ?? 0;
            $precio = $model->precio_unitario ?? $model->precio_galon ?? 0;
            if ($cantidad && $precio) {
                $model->attributes['subtotal'] = $cantidad * $precio;
            }
        });

        static::updating(function ($model) {
            // Mapeo de alias a columnas reales
            if (isset($model->attributes['galones']) && !isset($model->attributes['cantidad'])) {
                $model->attributes['cantidad'] = $model->attributes['galones'];
            }
            if (isset($model->attributes['precio_galon']) && !isset($model->attributes['precio_unitario'])) {
                $model->attributes['precio_unitario'] = $model->attributes['precio_galon'];
            }
            
            // Calcular subtotal
            $cantidad = $model->cantidad ?? $model->galones ?? 0;
            $precio = $model->precio_unitario ?? $model->precio_galon ?? 0;
            if ($cantidad && $precio) {
                $model->attributes['subtotal'] = $cantidad * $precio;
            }
        });
    }

    // Accesores para compatibilidad hacia atrás
    public function getGalonesAttribute()
    {
        return $this->cantidad;
    }

    public function setGalonesAttribute($value)
    {
        $this->attributes['cantidad'] = $value;
    }

    public function getPrecioGalonAttribute()
    {
        return $this->precio_unitario;
    }

    public function setPrecioGalonAttribute($value)
    {
        $this->attributes['precio_unitario'] = $value;
    }

    public function getTotalCalculadoAttribute()
    {
        return $this->subtotal;
    }

    public function setTotalCalculadoAttribute($value)
    {
        $this->attributes['subtotal'] = $value;
    }

    // backward‑compatible alias so older views/controllers may still access $fuelLog->fecha
    public function getFechaAttribute()
    {
        return $this->fecha_emision;
    }

    public function setFechaAttribute($value)
    {
        $this->attributes['fecha_emision'] = $value;
    }

    public function vehicleLog()
    {
        return $this->hasOne(VehicleLog::class, 'fuel_log_id');
    }

    public function vehicle()
    {
        return $this->hasOneThrough(
            Vehicle::class,
            VehicleLog::class,
            'fuel_log_id',
            'id',
            'id',
            'vehicles_id'
        );
    }

    public function driver()
    {
        return $this->hasOneThrough(
            Driver::class,
            VehicleLog::class,
            'fuel_log_id',
            'id',
            'id',
            'drivers_id'
        );
    }

    public function gasStation()
    {
        return $this->belongsTo(GasStation::class);
    }

    public function invoice()
    {
        return $this->belongsTo(FuelInvoice::class, 'fuel_invoice_id');
    }
}

