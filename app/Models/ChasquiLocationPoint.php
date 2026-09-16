<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChasquiLocationPoint extends Model
{
    protected $fillable = [
        'user_id',
        'location_id',
        'device_id',
        'device_name',
        'latitude',
        'longitude',
        'accuracy_m',
        'speed_kmh',
        'heading',
        'battery_percent',
        'is_moving',
        'gps_enabled',
        'gps_mocked',
        'is_simulated',
        'route_label',
        'sent_at',
        'received_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy_m' => 'float',
        'speed_kmh' => 'float',
        'heading' => 'float',
        'battery_percent' => 'float',
        'is_moving' => 'boolean',
        'gps_enabled' => 'boolean',
        'gps_mocked' => 'boolean',
        'is_simulated' => 'boolean',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
