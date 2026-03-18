<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicioExtra extends Model
{
    use HasFactory;

    protected $table = 'servicio_extras';

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function tarifariosTiktoker(): HasMany
    {
        return $this->hasMany(TarifarioTiktoker::class, 'servicio_extra_id');
    }
}
