<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelInvoiceDetail extends Model
{
    protected $table = 'fuel_invoice_details';

    protected $fillable = [
        'fuel_invoice_id',
        'cantidad',
        'precio_unitario',
        'subtotal',
    ];

    public function invoice()
    {
        return $this->belongsTo(FuelInvoice::class, 'fuel_invoice_id');
    }
}
