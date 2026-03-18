<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'action',
        'model',
        'module',
        'record_id',
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
}
