<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConciliacionEmpresa extends Model
{
    protected $table = 'conciliaciones_empresa';

    protected $fillable = [
        'empresa_id',
        'anio',
        'mes',
        'gestora_at',
        'gestora_por',
        'conciliacion_at',
        'conciliacion_por',
        'documento_path',
        'documento_nombre',
        'documento_mime',
        'documento_tamano',
        'documento_at',
        'documento_por',
        'factura_venta_id',
        'factura_detalle_id',
        'factura_descripcion',
        'factura_codigo_orden',
        'factura_codigo_seguimiento',
        'factura_fecha',
        'factura_monto',
        'facturado_anio',
        'facturado_mes',
        'por_cobrar_at',
        'por_cobrar_por',
        'formato_nota_cobranza',
        'nombre_empresa_cobranza',
        'factura_cuf',
        'factura_numero',
        'factura_pdf_path',
        'factura_razon_social',
        'factura_codigo_cliente',
        'factura_numero_documento',
        'factura_tipo_documento',
        'pago_recibido_at',
        'pago_recibido_por',
        'pago_comprobante_path',
        'pago_comprobante_nombre',
        'pago_comprobante_tamano',
        'confirmacion_pago_at',
        'confirmacion_pago_por',
        'conciliado_at',
        'conciliado_por',
    ];

    protected $casts = [
        'anio' => 'integer',
        'mes' => 'integer',
        'gestora_at' => 'datetime',
        'conciliacion_at' => 'datetime',
        'documento_tamano' => 'integer',
        'documento_at' => 'datetime',
        'factura_fecha' => 'datetime',
        'factura_monto' => 'decimal:2',
        'facturado_anio' => 'integer',
        'facturado_mes' => 'integer',
        'por_cobrar_at' => 'datetime',
        'pago_recibido_at' => 'datetime',
        'pago_comprobante_tamano' => 'integer',
        'confirmacion_pago_at' => 'datetime',
        'conciliado_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
