<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleLogInvestigationTicket extends Model
{
    public const STATUS_OPEN = 'Abierto';
    public const STATUS_REVIEWING = 'En revision';
    public const STATUS_CLOSED = 'Cerrado';

    protected $fillable = [
        'ticket_code',
        'session_reference',
        'vehicle_id',
        'responsible_driver_id',
        'current_driver_id',
        'related_user_id',
        'reason_type',
        'status',
        'message',
        'packages_total',
        'packages_open',
        'packages_delivered',
        'meta_json',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'packages_total' => 'integer',
        'packages_open' => 'integer',
        'packages_delivered' => 'integer',
        'meta_json' => 'array',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function responsibleDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'responsible_driver_id');
    }

    public function currentDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'current_driver_id');
    }
}
