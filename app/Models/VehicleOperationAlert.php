<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleOperationAlert extends Model
{
    public const TYPE_ROUTE_STATUS = 'route_status';
    public const TYPE_ROUTE_INCOMPLETE = 'route_incomplete';
    public const TYPE_PHONE_OFF = 'phone_off';
    public const TYPE_GPS_OFF = 'gps_off';
    public const TYPE_GPS_MOCKED = 'gps_mocked';
    public const TYPE_MOBILE_LOCATION_PERMISSION = 'mobile_location_permission_denied';
    public const TYPE_MOBILE_BACKGROUND_PERMISSION = 'mobile_background_location_permission_denied';
    public const TYPE_MOBILE_LIVE_TRACKING_FAILED = 'mobile_live_tracking_failed';
    public const TYPE_MOBILE_BACKGROUND_TRACKING_FAILED = 'mobile_background_tracking_failed';
    public const TYPE_MOBILE_GPS_DISABLED_ATTEMPT = 'mobile_gps_disabled_attempt';
    public const TYPE_MOBILE_GPS_MOCKED_ATTEMPT = 'mobile_gps_mocked_attempt';

    public const STATUS_ACTIVE = 'Activa';
    public const STATUS_RESOLVED = 'Resuelta';

    protected $fillable = [
        'vehicle_id',
        'vehicle_log_session_id',
        'alert_type',
        'severity',
        'status',
        'title',
        'message',
        'current_stage',
        'last_heartbeat_at',
        'detected_at',
        'resolved_at',
        'meta_json',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
        'meta_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(VehicleLogSession::class, 'vehicle_log_session_id');
    }

    public static function mapManagedTypes(): array
    {
        return [
            self::TYPE_ROUTE_STATUS,
            self::TYPE_ROUTE_INCOMPLETE,
            self::TYPE_PHONE_OFF,
            self::TYPE_GPS_OFF,
            self::TYPE_GPS_MOCKED,
        ];
    }

    public static function mobileIncidentTypes(): array
    {
        return [
            self::TYPE_MOBILE_LOCATION_PERMISSION,
            self::TYPE_MOBILE_BACKGROUND_PERMISSION,
            self::TYPE_MOBILE_LIVE_TRACKING_FAILED,
            self::TYPE_MOBILE_BACKGROUND_TRACKING_FAILED,
            self::TYPE_MOBILE_GPS_DISABLED_ATTEMPT,
            self::TYPE_MOBILE_GPS_MOCKED_ATTEMPT,
        ];
    }
}
