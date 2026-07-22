<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalApiToken extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'jti',
        'token_hash',
        'token_encrypted',
        'token_plain',
        'abilities',
        'is_active',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function isUsable(): bool
    {
        return $this->is_active
            && $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
