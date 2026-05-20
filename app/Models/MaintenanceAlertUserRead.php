<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceAlertUserRead extends Model
{
    protected $table = 'maintenance_alert_user_reads';

    protected $fillable = [
        'maintenance_alert_id',
        'user_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAlert::class, 'maintenance_alert_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
