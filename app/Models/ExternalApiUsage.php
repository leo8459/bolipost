<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalApiUsage extends Model
{
    public $timestamps = false;

    protected $casts = [
        'last_used_at' => 'datetime',
        'request_count' => 'integer',
    ];
}
