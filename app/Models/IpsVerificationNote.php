<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpsVerificationNote extends Model
{
    protected $fillable = [
        'receptacle_code',
        'receptacle_pid',
        'dispatch_code',
        'office_cd',
        'document_type',
        'incident_type',
        'summary_code',
        'package_code',
        'comparison_source',
        'comparison_reference',
        'basis_confirmed',
        'declared_count',
        'observed_count',
        'description',
        'outer_condition',
        'contents_condition',
        'contents_at_risk',
        'cn24_required',
        'evidence_paths',
        'status',
        'external_vn_number',
        'transmission_method',
        'sent_at',
        'resolution',
        'resolved_at',
        'reported_by',
        'ips_snapshot',
    ];

    protected $casts = [
        'basis_confirmed' => 'boolean',
        'contents_at_risk' => 'boolean',
        'cn24_required' => 'boolean',
        'evidence_paths' => 'array',
        'sent_at' => 'datetime',
        'resolved_at' => 'datetime',
        'ips_snapshot' => 'array',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function typeLabel(): string
    {
        return match ($this->incident_type) {
            'receptacle_damaged' => 'Saca abierta, cierre violado o saca dañada',
            'item_damaged' => 'Paquete abierto o dañado',
            'item_missing' => 'Paquete esperado no encontrado',
            'item_missing_theft' => 'Faltante con evidencia de robo',
            'item_excess' => 'Paquete físico no declarado',
            'item_missent' => 'Paquete mal encaminado',
            'bulk_count_difference' => 'Diferencia de cantidad en envío a granel',
            default => 'Otra irregularidad',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'sent' => 'Enviada al operador',
            'resolved' => 'Resuelta',
            default => 'Pendiente de transmisión',
        };
    }
}
