<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingLocalEventRule extends Model
{
    protected $fillable = [
        'source_table',
        'event_id',
        'raw_name',
        'display_name',
        'is_visible',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];
}
