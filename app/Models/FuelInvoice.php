<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelInvoice extends Model
{
    protected $table = 'fuel_invoices';

    protected $fillable = [
        'numero',
        'numero_factura',
        'fecha_emision',
        'gas_station_id',
        'nombre_cliente',
        'monto_total',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'monto_total' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function gasStation()
    {
        return $this->belongsTo(GasStation::class);
    }

    public function details()
    {
        return $this->hasMany(FuelInvoiceDetail::class, 'fuel_invoice_id');
    }
}
