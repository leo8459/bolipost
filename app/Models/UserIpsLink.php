<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIpsLink extends Model
{
    protected $fillable = [
        'user_id',
        'ips_user_pid',
        'ips_user_domain',
        'ips_user_fid',
        'ips_user_name',
        'ips_office_cd',
        'ips_office_fcd',
        'ips_office_name',
        'ipsweb',
        'restrict_user_offices',
        'active',
        'last_verified_at',
        'last_snapshot',
    ];

    protected $casts = [
        'ipsweb' => 'boolean',
        'restrict_user_offices' => 'boolean',
        'active' => 'boolean',
        'last_verified_at' => 'datetime',
        'last_snapshot' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        $domain = trim((string) $this->ips_user_domain);
        $fid = trim((string) $this->ips_user_fid);
        $name = trim((string) $this->ips_user_name);

        return ($domain !== '' ? $domain.'\\' : '').$fid.($name !== '' ? ' - '.$name : '');
    }
}
