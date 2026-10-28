<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\User;
use Spatie\Permission\Models\Role;

class FacturacionModePolicy
{
    public const CONFIGURED_PERMISSION = 'facturacion.modes.configured';

    public const MODE_PERMISSIONS = [
        'efectivo' => 'facturacion.mode.efectivo',
        'qr_factura' => 'facturacion.mode.qr_factura',
        'qr_solo' => 'facturacion.mode.qr_solo',
    ];

    private const BILLING_ACCESS_PERMISSION = 'feature.dashboard.facturacion';

    private const GLOBAL_SETTING_KEYS = [
        'efectivo' => 'facturacion.show_factura_electronica',
        'qr_factura' => 'facturacion.show_qr_factura',
        'qr_solo' => 'facturacion.show_qr_solo',
    ];

    public function allows(User $user, string $mode): bool
    {
        if (! isset(self::MODE_PERMISSIONS[$mode]) || ! $this->isGloballyEnabled($mode)) {
            return false;
        }

        return $this->roleAllows($user, $mode);
    }

    public function roleAllows(User $user, string $mode): bool
    {
        $modePermission = self::MODE_PERMISSIONS[$mode] ?? null;
        if ($modePermission === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $hasBillingRole = false;
        foreach ($user->roles()->with('permissions')->get() as $role) {
            $permissions = $role->permissions->pluck('name')->all();
            if (! in_array(self::BILLING_ACCESS_PERMISSION, $permissions, true)) {
                continue;
            }

            $hasBillingRole = true;
            if (! $this->roleHasExplicitModeConfiguration($role)) {
                // Keep the current behavior for existing roles until an admin
                // saves their per-mode settings from the role editor.
                return true;
            }

            if (in_array($modePermission, $permissions, true)) {
                return true;
            }
        }

        if (! $hasBillingRole && $user->can(self::BILLING_ACCESS_PERMISSION)) {
            $directPermissions = $user->getDirectPermissions()->pluck('name')->all();
            $hasExplicitMode = in_array(self::CONFIGURED_PERMISSION, $directPermissions, true)
                || array_intersect(array_values(self::MODE_PERMISSIONS), $directPermissions) !== [];

            return ! $hasExplicitMode || in_array($modePermission, $directPermissions, true);
        }

        return false;
    }

    public function isGloballyEnabled(string $mode): bool
    {
        $settingKey = self::GLOBAL_SETTING_KEYS[$mode] ?? null;

        return $settingKey !== null && AppSetting::getValue($settingKey, '1') === '1';
    }

    public function allowsAnyQr(User $user): bool
    {
        return $this->allows($user, 'qr_factura') || $this->allows($user, 'qr_solo');
    }

    public function modeFor(string $channel, bool $autoEmitInvoice): string
    {
        $channel = strtolower(trim($channel));

        if ($channel !== 'qr') {
            return 'efectivo';
        }

        return $autoEmitInvoice ? 'qr_factura' : 'qr_solo';
    }

    /** @return array<int, string> */
    public static function modePermissionNames(): array
    {
        return array_values(self::MODE_PERMISSIONS);
    }

    public function roleHasExplicitModeConfiguration(Role $role): bool
    {
        $permissions = $role->permissions->pluck('name')->all();

        return in_array(self::CONFIGURED_PERMISSION, $permissions, true)
            || array_intersect(self::modePermissionNames(), $permissions) !== [];
    }
}
