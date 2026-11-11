<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

final class PackageControlScope
{
    public static function forUser(?User $user): ?array
    {
        if (! $user) {
            return [];
        }

        $roles = $user->getRoleNames()->map(fn ($role) => str_replace(
            [' ', '-'], '_', mb_strtolower(Str::ascii(trim((string) $role)))
        ));

        if ($user->hasGlobalDepartmentAccess() || $roles->contains(fn ($role) => in_array($role, [
            'admin', 'administrador', 'super_admin', 'superadmin', 'super_administrador',
            'superadministrador', 'gestor', 'administrador_operaciones', 'administrador_de_operaciones',
        ], true))) {
            return null;
        }

        return $user->regionalesLista();
    }
}
