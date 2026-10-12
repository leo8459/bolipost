<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TarifarioVersion extends Model
{
    protected $table = 'tarifario_versions';

    protected $fillable = [
        'tarifario_padre_id',
        'numero_version',
        'codigo',
        'estado',
        'publicada_por',
        'publicada_en',
        'cantidad_items',
        'checksum',
    ];

    protected $casts = [
        'publicada_en' => 'datetime',
    ];

    public function tarifarioPadre(): BelongsTo
    {
        return $this->belongsTo(TarifarioPadre::class, 'tarifario_padre_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TarifarioVersionItem::class, 'tarifario_version_id');
    }
}
