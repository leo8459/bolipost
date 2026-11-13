<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AlertaEmpresa extends Model
{
    use HasFactory;

    protected $table = 'alertas_empresa';

    protected $fillable = [
        'titulo',
        'mensaje',
        'portada_path',
        'pdf_path',
        'creado_por',
        'publicada_at',
        'aprobada_at',
        'aprobada_por',
        'vence_at',
    ];

    protected $casts = [
        'publicada_at' => 'datetime',
        'aprobada_at' => 'datetime',
        'vence_at' => 'datetime',
    ];

    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'alerta_empresa_destinatarios', 'alerta_empresa_id', 'empresa_id');
    }

    public function usuariosDestinatarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'alerta_empresa_usuarios', 'alerta_empresa_id', 'user_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobada_por');
    }

    public function lectores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'alerta_empresa_lecturas', 'alerta_empresa_id', 'user_id')
            ->withPivot('leida_at');
    }

    public function lectoresAgrupadosPorEmpresa(): Collection
    {
        return $this->lectores
            ->groupBy(fn (User $lector) => $lector->empresa?->id ?? 'sin-empresa')
            ->sortBy(fn (Collection $lectores) => $lectores->first()->empresa
                ? '0'.Str::lower(Str::ascii($lectores->first()->empresa->nombre))
                : '1')
            ->map(fn (Collection $lectores) => $lectores
                ->sortBy(fn (User $lector) => Str::lower(Str::ascii($lector->name)), SORT_NATURAL)
                ->values())
            ->values();
    }
}
