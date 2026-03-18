<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TarifarioTiktoker extends Model
{
    use HasFactory;

    protected $table = 'tarifario_tiktoker';

    protected $fillable = [
        'origen_id',
        'destino_id',
        'servicio_extra_id',
        'peso1',
        'peso2',
        'peso3',
        'peso_extra',
        'tiempo_entrega',
    ];

    protected $casts = [
        'peso1' => 'decimal:2',
        'peso2' => 'decimal:2',
        'peso3' => 'decimal:2',
        'peso_extra' => 'decimal:2',
        'tiempo_entrega' => 'integer',
    ];

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Origen::class, 'origen_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Destino::class, 'destino_id');
    }

    public function servicioExtra(): BelongsTo
    {
        return $this->belongsTo(ServicioExtra::class, 'servicio_extra_id');
    }

    public function solicitudesCliente(): HasMany
    {
        return $this->hasMany(SolicitudCliente::class, 'tarifario_tiktoker_id');
    }
}
