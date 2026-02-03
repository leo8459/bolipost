<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaqueteEms extends Model
{
    use HasFactory;

    protected $table = 'paquetes_ems';

    protected $fillable = [
        'origen',
        'tipo_correspondencia',
        'servicio_especial',
        'contenido',
        'cantidad',
        'peso',
        'codigo',
        'precio',
        'nombre_remitente',
        'nombre_envia',
        'carnet',
        'telefono_remitente',
        'nombre_destinatario',
        'telefono_destinatario',
        'ciudad',
        'tarifario_id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tarifario()
    {
        return $this->belongsTo(Tarifario::class, 'tarifario_id');
    }
}
