<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TarifarioTiktoker extends Model
{
    use HasFactory;

    public const PRECIO_MISMO_DESTINO = 15.00;

    public const PRECIO_OTRO_DESTINO = 20.00;

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

    protected static function booted(): void
    {
        static::saving(function (TarifarioTiktoker $tarifario): void {
            $origen = (string) Origen::query()->whereKey($tarifario->origen_id)->value('nombre_origen');
            $destino = (string) Destino::query()->whereKey($tarifario->destino_id)->value('nombre_destino');

            $mismoDestino = self::normalizePlace($origen) !== ''
                && self::normalizePlace($origen) === self::normalizePlace($destino);

            $tarifario->peso1 = $mismoDestino
                ? self::PRECIO_MISMO_DESTINO
                : self::PRECIO_OTRO_DESTINO;
            $tarifario->peso2 = null;
            $tarifario->peso3 = null;
            $tarifario->peso_extra = null;
        });
    }

    private static function normalizePlace(string $value): string
    {
        return strtoupper(trim($value));
    }

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
