<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacturacionClienteFrecuente extends Model
{
    protected $table = 'facturacion_clientes_frecuentes';

    protected $fillable = [
        'tipo_documento',
        'numero_documento',
        'complemento_documento',
        'razon_social',
        'correo_facturacion',
        'ultima_venta_id',
        'usos',
    ];

    protected $casts = [
        'ultima_venta_id' => 'integer',
        'usos' => 'integer',
    ];

    public function tipoDocumentoLabel(): string
    {
        return Cliente::tiposDocumentoIdentidad()[(string) $this->tipo_documento]
            ?? (string) $this->tipo_documento;
    }
}
