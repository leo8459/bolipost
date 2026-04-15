<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class FuelInvoiceDetail extends Model
{
    protected $table = 'fuel_invoice_details';

    protected $fillable = [
        'fuel_invoice_id',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'activo',
        'estado',
        'gas_station_id',
        'fecha_emision',
        'vehicle_id',
        'driver_id',
        'observaciones',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_emision' => 'datetime',
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(FuelInvoice::class, 'fuel_invoice_id');
    }

    public function scopeActive($query)
    {
        if (Schema::hasColumn($this->getTable(), 'activo')) {
            $query->where($this->qualifyColumn('activo'), true);
        }

        return $query;
    }
}
