<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleLogStageEvent extends Model
{
    protected $fillable = [
        'vehicle_log_session_id',
        'vehicle_log_id',
        'session_reference',
        'vehicle_id',
        'responsible_driver_id',
        'acting_driver_id',
        'stage_name',
        'event_kind',
        'address',
        'latitude',
        'longitude',
        'event_at',
        'photo_path',
        'notes',
        'payload_json',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'event_at' => 'datetime',
        'payload_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(VehicleLogSession::class, 'vehicle_log_session_id');
    }

    public function vehicleLog(): BelongsTo
    {
        return $this->belongsTo(VehicleLog::class, 'vehicle_log_id');
    }
}
