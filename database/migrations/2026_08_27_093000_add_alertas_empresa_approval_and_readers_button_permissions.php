<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'feature.alertas-empresa.approve',
        'feature.alertas-empresa.readers',
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        $this->grantToRoles(
            ['administrador', 'gestor', 'administrador_operaciones'],
            $this->permissions
        );
        $this->grantToRoles(['empresa'], ['feature.alertas-empresa.readers']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
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
};
