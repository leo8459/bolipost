<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleLogSession extends Model
{
    protected $fillable = [
        'session_reference',
        'vehicle_id',
        'responsible_driver_id',
        'current_driver_id',
        'origin_vehicle_log_id',
        'started_at',
        'last_reassigned_at',
        'ended_at',
        'status',
        'meta_json',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_reassigned_at' => 'datetime',
        'ended_at' => 'datetime',
        'meta_json' => 'array',
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

    public function stageEvents(): HasMany
    {
        return $this->hasMany(VehicleLogStageEvent::class, 'vehicle_log_session_id');
    }
}
