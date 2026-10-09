<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TarifarioPadre extends Model
{
    protected $table = 'tarifario_padre';

    protected $fillable = ['nombre'];

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'tarifario_padre_id');
    }
}
