<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaqueteOrdi extends Model
{
    use HasFactory;

    protected $table = 'paquetes_ordi';

    protected $fillable = [
        'codigo',
        'destinatario',
        'telefono',
        'ciudad',
        'zona',
        'peso',
        'aduana',
        'observaciones',
        'cod_especial',
        'fk_ventanilla',
        'fk_estado',
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
