<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaHistorial extends Model
{
    protected $table = 'empresa_historiales';

    protected $fillable = [
        'empresa_id',
        'archivado_por',
        'nombre',
        'sigla',
        'codigo_cliente',
        'clasificacion',
        'documentacion_legal',
        'inicio_contrato',
        'fin_contrato',
        'cobertura',
        'presupuesto',
        'documento_pdf_path',
        'datos_completos',
    ];

    protected $casts = [
        'inicio_contrato' => 'date',
        'fin_contrato' => 'date',
        'presupuesto' => 'decimal:2',
        'datos_completos' => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function archivadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archivado_por')->withTrashed();
    }
}
