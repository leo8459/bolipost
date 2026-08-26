<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'empresa';

    protected $fillable = [
        'nombre',
        'sigla',
        'codigo_cliente',
        'nit',
        'clasificacion',
        'documentacion_legal',
        'inicio_contrato',
        'fin_contrato',
        'cobertura',
        'presupuesto',
        'documento_pdf_path',
    ];

    public function codigosEmpresa()
    {
        return $this->hasMany(CodigoEmpresa::class, 'empresa_id');
    }

    public function alertas(): BelongsToMany
    {
        return $this->belongsToMany(AlertaEmpresa::class, 'alerta_empresa_destinatarios', 'empresa_id', 'alerta_empresa_id');
    }

    public function historiales(): HasMany
    {
        return $this->hasMany(EmpresaHistorial::class, 'empresa_id');
    }

    public function conciliaciones(): HasMany
    {
        return $this->hasMany(ConciliacionEmpresa::class, 'empresa_id');
    }
}
