<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RemitenteEms extends Model
{
    use HasFactory;

    protected $table = 'remitentes_ems';

    protected $fillable = [
        'nombre_remitente',
        'telefono_remitente',
        'carnet',
        'nombre_envia',
    ];
}
