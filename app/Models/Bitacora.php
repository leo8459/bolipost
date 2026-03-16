<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bitacora extends Model
{
    use HasFactory;

    protected $table = 'bitacoras';

    protected $fillable = [
        'paquetes_ems_id',
        'paquetes_contrato_id',
        'paquetes_ordi_id',
        'paquetes_certi_id',
        'user_id',
        'cod_especial',
        'transportadora',
        'provincia',
        'factura',
        'precio_total',
        'peso',
        'imagen_factura',
    ];

    protected $casts = [
        'precio_total' => 'decimal:2',
        'peso' => 'decimal:3',
    ];

    public function paqueteEms()
    {
        return $this->belongsTo(PaqueteEms::class, 'paquetes_ems_id');
    }

    public function paqueteContrato()
    {
        return $this->belongsTo(Recojo::class, 'paquetes_contrato_id');
    }

    public function paqueteOrdi()
    {
        return $this->belongsTo(PaqueteOrdi::class, 'paquetes_ordi_id');
    }

    public function paqueteCerti()
    {
        return $this->belongsTo(PaqueteCerti::class, 'paquetes_certi_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
