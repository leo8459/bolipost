<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected static array $columnCache = [];

    protected $fillable = [
        'user_id',
        'action',
        'model',
        'module',
        'record_id',
        'vehicle_log_id',
        'changes_json',
        'details',
        'ip_address',
        'user_agent',
        'fecha',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'changes_json' => 'array',
        'fecha' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el usuario que realizó la acción
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicleLog(): BelongsTo
    {
        return $this->belongsTo(VehicleLog::class, 'vehicle_log_id');
    }

    /**
     * Obtener el modelo relacionado
     */
    public function getModelInstance()
    {
        $modelClass = 'App\\Models\\' . $this->model;
        if (class_exists($modelClass)) {
            return $modelClass::find($this->record_id);
        }
        return null;
    }

    public function scopeLatestEvent(Builder $query): Builder
    {
        $column = self::hasColumn('fecha') ? 'fecha' : 'created_at';

        return $query->orderByDesc($column)->orderByDesc('id');
    }

    public static function prepareAttributes(array $attributes): array
    {
        foreach (['model', 'record_id', 'vehicle_log_id', 'changes_json', 'module', 'details', 'ip_address', 'user_agent', 'fecha'] as $column) {
            if (array_key_exists($column, $attributes) && !self::hasColumn($column)) {
                unset($attributes[$column]);
            }
        }

        return $attributes;
    }

    public static function hasColumn(string $column): bool
    {
        if (array_key_exists($column, self::$columnCache)) {
            return self::$columnCache[$column];
        }

        return self::$columnCache[$column] = Schema::hasColumn('activity_logs', $column);
    }
}
