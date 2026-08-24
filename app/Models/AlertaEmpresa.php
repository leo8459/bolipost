<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'vence_at',
    ];

    protected $casts = [
        'publicada_at' => 'datetime',
        'vence_at' => 'datetime',
    ];

    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'alerta_empresa_destinatarios', 'alerta_empresa_id', 'empresa_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function lectores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'alerta_empresa_lecturas', 'alerta_empresa_id', 'user_id')
            ->withPivot('leida_at');
    }
}
