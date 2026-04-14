<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Malencaminado extends Model
{
    use HasFactory;

    protected $table = 'malencaminados';

    protected $fillable = [
        'codigo',
        'departamento_origen',
        'observacion',
        'malencaminamiento',
        'paquetes_ems_id',
        'paquetes_contrato_id',
        'paquetes_certi_id',
        'paquetes_ordi_id',
        'destino_anterior',
        'destino_nuevo',
    ];

    public function paqueteEms()
    {
        return $this->belongsTo(PaqueteEms::class, 'paquetes_ems_id');
    }

    public function paqueteContrato()
    {
        return $this->belongsTo(Recojo::class, 'paquetes_contrato_id');
    }

    public function paqueteCerti()
    {
        return $this->belongsTo(PaqueteCerti::class, 'paquetes_certi_id');
    }

    public function paqueteOrdi()
    {
        return $this->belongsTo(PaqueteOrdi::class, 'paquetes_ordi_id');
    }
}
