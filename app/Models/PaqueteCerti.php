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
        'destinatario',
        'telefono',
        'cuidad',
        'zona',
        'ventanilla',
        'peso',
        'tipo',
        'aduana',
        'fk_estado',
        'fk_ventanilla',
    ];

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'fk_estado');
    }

    public function ventanillaRef()
    {
        return $this->belongsTo(Ventanilla::class, 'fk_ventanilla');
    }

}
