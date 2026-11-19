<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TarifaContrato extends Model
{
    use HasFactory;

    public const ZONAS_LOCALES = ['Zona 1', 'Zona 2', 'Zona 3'];

    protected $table = 'tarifa_contrato';

    protected $fillable = [
        'tarifario_contrato_id',
        'servicio_contrato_id',
        'empresa_id',
        'origen',
        'destino',
        'servicio',
        'direccion',
        'zona',
        'peso',
        'kilo',
        'kilo_extra',
        'provincia',
        'provincia_origen',
        'retencion',
        'horas_entrega',
    ];

    protected $casts = [
        'peso' => 'decimal:2',
        'kilo' => 'decimal:2',
        'kilo_extra' => 'decimal:2',
        'retencion' => 'decimal:2',
        'horas_entrega' => 'integer',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $tarifa) {
            $tarifario = TarifarioContrato::query()->findOrFail($tarifa->tarifario_contrato_id);
            $servicio = ServicioContrato::deAnio($tarifario->anio)
                ->where('nombre', ServicioContrato::normalizarNombre((string) $tarifa->servicio))->lockForUpdate()->first();
            if (! $servicio) {
                throw ValidationException::withMessages(['servicio' => 'El servicio no está registrado para el año del tarifario seleccionado.']);
            }
            $tarifa->servicio_contrato_id = $servicio->id;
            $tarifa->servicio = $servicio->nombre;
            if (ServicioContrato::esLocal($tarifa->servicio)) {
                $tarifa->zona = self::normalizarZona($tarifa->zona);
            }
        });
    }

    public static function normalizarZona(?string $zona): ?string
    {
        $zona = mb_strtoupper(trim((string) $zona));
        $zona = trim(preg_replace('/\s+/u', ' ', $zona) ?? $zona);

        if (preg_match('/^(?:ZONA *)?0*([1-3])$/', $zona, $coincidencias)) {
            return self::ZONAS_LOCALES[(int) $coincidencias[1] - 1];
        }

        return $zona === '' ? null : $zona;
    }

    public function zonaLocal(): ?string
    {
        return ServicioContrato::esLocal((string) $this->servicio) ? self::normalizarZona($this->zona) : null;
    }

    public function servicioCatalogo()
    {
        return $this->belongsTo(ServicioContrato::class, 'servicio_contrato_id');
    }

    public function tarifario()
    {
        return $this->belongsTo(TarifarioContrato::class, 'tarifario_contrato_id');
    }

    public function scopeParaEmpresa(Builder $query, int $empresaId, bool $soloActivo = true): Builder
    {
        return $query->whereIn('tarifario_contrato_id', function ($assignments) use ($empresaId, $soloActivo) {
            $assignments->select('tarifario_contrato_id')->from('tarifario_contrato_empresa')->where('empresa_id', $empresaId);
            if ($soloActivo) {
                $assignments->where('activo', true);
            }
        });
    }

    public function paquetesContrato()
    {
        return $this->hasMany(Recojo::class, 'tarifa_contrato_id');
    }
}
