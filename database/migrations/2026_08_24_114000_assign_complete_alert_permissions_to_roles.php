<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $managementPermissions = [
        'alertas-empresa.index',
        'alertas-empresa.store',
        'alertas-empresa.destroy',
        'alertas-empresa.portada',
        'alertas-empresa.pdf',
        'alertas-empresa.read',
        'feature.alertas-empresa.view',
        'feature.alertas-empresa.create',
        'feature.alertas-empresa.delete',
        'feature.alertas-empresa.export',
    ];

    private array $recipientPermissions = [
        'alertas-empresa.portada',
        'alertas-empresa.pdf',
        'alertas-empresa.read',
    ];

    public function up(): void
    {
        $now = now();

        foreach (array_unique(array_merge($this->managementPermissions, $this->recipientPermissions)) as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        $this->grantToRoles(
            ['administrador', 'gestor', 'administrador_operaciones'],
            $this->managementPermissions
        );
        $this->grantToRoles(['empresa'], $this->recipientPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $this->revokeFromRoles(
            ['gestor', 'administrador_operaciones'],
            $this->managementPermissions
        );
        $this->revokeFromRoles(['empresa'], $this->recipientPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function grantToRoles(array $roles, array $permissions): void
    {
        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', $roles)
            ->pluck('id');
        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $permissions)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    private function revokeFromRoles(array $roles, array $permissions): void
    {
        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', $roles)
            ->pluck('id');
        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $permissions)
            ->pluck('id');

        DB::table('role_has_permissions')
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
