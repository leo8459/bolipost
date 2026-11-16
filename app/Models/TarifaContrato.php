<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TarifaContrato extends Model
{
    use HasFactory;

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
        });
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
