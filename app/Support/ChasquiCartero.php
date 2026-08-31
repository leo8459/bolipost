<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class ChasquiCartero
{
    private const ROLES = [
        'auxiliar_urbano',
        'auxiliar_urbano_dnd',
        'auxiliar_7',
        'cartero_ems',
        'carteros_ems',
    ];

    public static function isAllowed(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->getRoleNames()
            ->map(fn ($role): string => mb_strtolower(trim((string) $role)))
            ->contains(fn (string $role): bool => in_array($role, self::ROLES, true));
    }

    /** @return array<int, string> */
    public static function tokenAbilities(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return $user->getRoleNames()
            ->map(fn ($role): string => Str::slug((string) $role, '_'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function abilityMiddleware(): string
    {
        return 'ability:chasqui,'.implode(',', self::ROLES);
    }
}
