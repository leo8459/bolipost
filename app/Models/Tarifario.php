<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tarifario extends Model
{
    use HasFactory;

    protected $table = 'tarifario';

    protected $fillable = [
        'servicio_id',
        'peso_id',
        'precio',
        'observacion',
        'destino_id',
        'origen_id',
    ];

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function destino()
    {
        return $this->belongsTo(Destino::class, 'destino_id');
    }

    public function peso()
    {
        return $this->belongsTo(Peso::class, 'peso_id');
    }

    public function origen()
    {
        return $this->belongsTo(Origen::class, 'origen_id');
    }
}
