<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaqueteCerti extends Model
{
    use HasFactory;

    protected $table = 'paquetes_certi';

    protected $fillable = [
        'codigo',
        'cod_especial',
        'servicio_id',
        'destinatario',
        'telefono',
        'cuidad',
        'zona',
        'ventanilla',
        'peso',
        'precio',
        'tipo',
        'aduana',
        'fk_estado',
        'fk_ventanilla',
        'imagen',
    ];

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'fk_estado');
    }

    public function ventanillaRef()
    {
        return $this->belongsTo(Ventanilla::class, 'fk_ventanilla');
    }

    public function malencaminados()
    {
        return $this->hasMany(Malencaminado::class, 'paquetes_certi_id');
    }

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

}
