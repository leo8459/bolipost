<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

class EmpresaSucursal extends Model
{
    public const CIUDADES = [
        'LA PAZ' => 'La Paz',
        'COCHABAMBA' => 'Cochabamba',
        'SANTA CRUZ' => 'Santa Cruz',
        'ORURO' => 'Oruro',
        'POTOSI' => 'Potosí',
        'TARIJA' => 'Tarija',
        'SUCRE' => 'Sucre (Chuquisaca)',
        'TRINIDAD' => 'Trinidad (Beni)',
        'COBIJA' => 'Cobija (Pando)',
    ];

    protected $table = 'empresa_sucursales';

    protected $fillable = ['nombre', 'ciudad'];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function getEtiquetaAttribute(): string
    {
        return $this->ciudad ? "{$this->nombre} - {$this->ciudad}" : $this->nombre;
    }

    public static function assignmentRules(mixed $empresaId): array
    {
        return [
            'bail',
            'required',
            'integer',
            Rule::exists('empresa_sucursales', 'id')->where('empresa_id', filter_var($empresaId, FILTER_VALIDATE_INT) ?: null),
        ];
    }
}
