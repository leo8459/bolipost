<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileDbSnapshot extends Model
{
    protected $table = 'mobile_db_snapshots';

    protected $fillable = [
        'user_id',
        'snapshot_key',
        'sent_at',
        'action',
        'model',
        'table_name',
        'record_id',
        'page',
        'total_pages',
        'payload_json',
        'payload_size',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'record_id' => 'integer',
        'page' => 'integer',
        'total_pages' => 'integer',
        'payload_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

