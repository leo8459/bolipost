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
        'delivery_agent_cd',
        'delivery_agent_full_cd',
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

    public function offices(): array
    {
        return self::officesFromSnapshot($this->last_snapshot ?: [
            'office_cd' => $this->ips_office_cd,
            'office_fcd' => $this->ips_office_fcd,
            'office_name' => $this->ips_office_name,
        ]);
    }

    public static function officesFromSnapshot(array $snapshot): array
    {
        $offices = (array) ($snapshot['offices'] ?? []);
        if (isset($snapshot['office_cd'])) {
            array_unshift($offices, [
                'office_cd' => $snapshot['office_cd'],
                'office_fcd' => $snapshot['office_fcd'] ?? '',
                'office_name' => $snapshot['office_name'] ?? '',
            ]);
        }

        return collect($offices)
            ->filter(fn ($office) => is_array($office)
                && isset($office['office_cd'])
                && filter_var($office['office_cd'], FILTER_VALIDATE_INT) !== false
                && (int) $office['office_cd'] >= 0
                && (int) $office['office_cd'] <= 32767)
            ->map(fn (array $office): array => [
                'office_cd' => (int) $office['office_cd'],
                'office_fcd' => trim((string) ($office['office_fcd'] ?? '')),
                'office_name' => trim((string) ($office['office_name'] ?? '')),
            ])
            ->unique('office_cd')->values()->all();
    }

    public function officeSessionSuffix(): string
    {
        $primaryOffice = $this->last_snapshot['office_cd'] ?? ($this->offices()[0]['office_cd'] ?? $this->ips_office_cd);

        return (int) $primaryOffice === (int) $this->ips_office_cd
            ? ''
            : '_office_'.$this->ips_office_cd;
    }
}
