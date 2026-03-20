<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Estado;

class SolicitudCliente extends Model
{
    use HasFactory;

    protected $table = 'solicitud_clientes';

    protected $fillable = [
        'cliente_id',
        'codigo_solicitud',
        'barcode',
        'cod_especial',
        'estado_id',
        'origen',
        'tipo_correspondencia',
        'servicio_especial',
        'contenido',
        'cantidad',
        'peso',
        'precio',
        'pago_destinatario',
        'servicio_extra_id',
        'nombre_remitente',
        'nombre_envia',
        'carnet',
        'telefono_remitente',
        'nombre_destinatario',
        'telefono_destinatario',
        'direccion_recojo',
        'direccion',
        'ciudad',
        'servicio_id',
        'destino_id',
        'tarifario_tiktoker_id',
    ];

    protected $casts = [
        'peso' => 'decimal:3',
        'precio' => 'decimal:2',
        'pago_destinatario' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function servicioExtra(): BelongsTo
    {
        return $this->belongsTo(ServicioExtra::class, 'servicio_extra_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Destino::class, 'destino_id');
    }

    public function tarifarioTiktoker(): BelongsTo
    {
        return $this->belongsTo(TarifarioTiktoker::class, 'tarifario_tiktoker_id');
    }

    public function estadoRegistro(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }
}
