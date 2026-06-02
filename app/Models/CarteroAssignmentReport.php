<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarteroAssignmentReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'token',
        'assigned_user_id',
        'actor_user_id',
        'regional',
        'assigned_at',
        'total_assigned',
        'summary_by_type',
        'rows',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'summary_by_type' => 'array',
        'rows' => 'array',
        'total_assigned' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CarteroAssignmentReportItem::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
