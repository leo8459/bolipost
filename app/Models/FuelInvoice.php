<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

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
        'siat_source_url',
        'siat_snapshot_json',
        'siat_document_path',
        'siat_rollo_document_path',
        'invoice_photo_path',
        'activo',
        'fuel_latitude',
        'fuel_longitude',
        'fuel_location_label',
        'fuel_recorded_at',
        'antifraud_payload_json',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'monto_total' => 'decimal:2',
        'activo' => 'boolean',
        'siat_snapshot_json' => 'array',
        'fuel_latitude' => 'decimal:7',
        'fuel_longitude' => 'decimal:7',
        'fuel_recorded_at' => 'datetime',
        'antifraud_payload_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function gasStation()
    {
        return $this->belongsTo(GasStation::class);
    }

    public function details()
    {
        return $this->hasMany(FuelInvoiceDetail::class, 'fuel_invoice_id')->active();
    }

    public function allDetails()
    {
        return $this->hasMany(FuelInvoiceDetail::class, 'fuel_invoice_id');
    }

    public function scopeActive($query)
    {
        if (Schema::hasColumn($this->getTable(), 'activo')) {
            $query->where($this->qualifyColumn('activo'), true);
        }

        return $query;
    }
}
