<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'empresa.guias.index',
        'empresa.guias.excel',
        'empresa.guias.rastreo',
        'feature.empresa.guias.index.search',
        'feature.empresa.guias.index.export',
        'feature.empresa.guias.index.view',
        'feature.empresa.guias.index.print',
    ];

    public function up(): void
    {
        $now = now();

        $obsoletePermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', ['feature.empresa.export', 'feature.empresa.view'])
            ->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $obsoletePermissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $obsoletePermissionIds)->delete();
        DB::table('permissions')->whereIn('id', $obsoletePermissionIds)->delete();

        foreach ($this->permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        $administratorRoleId = DB::table('roles')
            ->where('name', 'administrador')
            ->where('guard_name', 'web')
            ->value('id');

        if ($administratorRoleId) {
            $permissionIds = DB::table('permissions')
                ->where('guard_name', 'web')
                ->whereIn('name', $this->permissions)
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $administratorRoleId,
                ]);
            }
        }

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
};
