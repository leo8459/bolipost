<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'livewire.maintenance-types')
            ->where('guard_name', 'web')
            ->value('id');

        $adminRoleId = DB::table('roles')
            ->where('name', 'administrador')
            ->where('guard_name', 'web')
            ->value('id');

        if (!$permissionId || !$adminRoleId) {
            return;
        }

        $exists = DB::table('role_has_permissions')
            ->where('permission_id', (int) $permissionId)
            ->where('role_id', (int) $adminRoleId)
            ->exists();

        if (!$exists) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => (int) $permissionId,
                'role_id' => (int) $adminRoleId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'livewire.maintenance-types')
            ->where('guard_name', 'web')
            ->value('id');

        $adminRoleId = DB::table('roles')
            ->where('name', 'administrador')
            ->where('guard_name', 'web')
            ->value('id');

        if (!$permissionId || !$adminRoleId) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('permission_id', (int) $permissionId)
            ->where('role_id', (int) $adminRoleId)
            ->delete();
    }
};

