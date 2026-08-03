<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $guardName = 'web';

    private array $roles = [
        'administrador',
        'gestor',
        'administrador_operaciones',
    ];

    private array $permissions = [
        'paquetes-contrato.recoger-envios',
        'feature.paquetes-contrato.recoger-envios.assign',
        'feature.paquetes-contrato.recoger-envios.print',
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->permissions as $permissionName) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permissionName, 'guard_name' => $this->guardName],
                ['updated_at' => $now, 'created_at' => $now]
            );
        }

        $roleIds = DB::table('roles')
            ->where('guard_name', $this->guardName)
            ->whereIn('name', $this->roles)
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->where('guard_name', $this->guardName)
            ->whereIn('name', $this->permissions)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $roleId],
                    []
                );
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')
            ->where('guard_name', $this->guardName)
            // Administrador y administrador_operaciones ya contaban con estos
            // permisos antes de esta migracion; solo Gestor fue incorporado.
            ->where('name', 'gestor')
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->where('guard_name', $this->guardName)
            ->whereIn('name', $this->permissions)
            ->pluck('id');

        if ($roleIds->isNotEmpty() && $permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')
                ->whereIn('role_id', $roleIds)
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
