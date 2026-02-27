<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cartero extends Model
{
    use HasFactory;

    protected $table = 'cartero';

    protected $fillable = [
        'id_paquetes_ems',
        'id_paquetes_certi',
        'id_paquetes_contrato',
        'id_estados',
        'id_user',
        'intento',
        'recibido_por',
        'descripcion',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
