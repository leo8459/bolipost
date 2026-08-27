<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private string $permissionName = 'feature.paquetes-ems.en-transito.reprintcn33';

    private string $guardName = 'web';

    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            [
                'name' => $this->permissionName,
                'guard_name' => $this->guardName,
            ],
            [
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $administratorRoleId = DB::table('roles')
            ->where('name', config('acl.super_admin_role', 'administrador'))
            ->where('guard_name', $this->guardName)
            ->value('id');

        $permissionId = DB::table('permissions')
            ->where('name', $this->permissionName)
            ->where('guard_name', $this->guardName)
            ->value('id');

        if ($administratorRoleId && $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $administratorRoleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', $this->permissionName)
            ->where('guard_name', $this->guardName)
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')
                ->where('permission_id', $permissionId)
                ->delete();

            DB::table('permissions')
                ->where('id', $permissionId)
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
