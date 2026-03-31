<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverIncentiveReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'report_year',
        'report_month',
        'stars_start',
        'stars_end',
        'non_preventive_requests',
        'preventive_requests',
        'discountable_events',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
