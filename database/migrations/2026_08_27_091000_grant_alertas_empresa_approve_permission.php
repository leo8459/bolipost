<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['name' => 'alertas-empresa.approve', 'guard_name' => 'web'],
            ['created_at' => $now, 'updated_at' => $now]
        );

        $permissionId = DB::table('permissions')
            ->where('name', 'alertas-empresa.approve')
            ->where('guard_name', 'web')
            ->value('id');

        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['administrador', 'gestor', 'administrador_operaciones'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'alertas-empresa.approve')
            ->where('guard_name', 'web')
            ->value('id');

        DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
