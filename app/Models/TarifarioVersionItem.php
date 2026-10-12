<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarifarioVersionItem extends Model
{
    protected $table = 'tarifario_version_items';

    protected $fillable = [
        'tarifario_version_id',
        'source_tarifario_id',
        'servicio_id',
        'peso_id',
        'peso_inicial',
        'peso_final',
        'precio',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'peso_inicial' => 'decimal:3',
        'peso_final' => 'decimal:3',
        'precio' => 'decimal:2',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(TarifarioVersion::class, 'tarifario_version_id');
    }
}
