<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private string $permission = 'feature.conciliacion.conciliaciones.editar';

    public function up(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['name' => $this->permission, 'guard_name' => 'web'],
            ['created_at' => $now, 'updated_at' => $now]
        );

        $modulePermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'dashboard.conciliacion.conciliaciones',
                'feature.conciliacion.conciliaciones.conciliar',
            ])
            ->pluck('id');
        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->where(function ($query) use ($modulePermissionIds): void {
                $query->where('name', 'administrador')
                    ->orWhereIn('id', DB::table('role_has_permissions')
                        ->whereIn('permission_id', $modulePermissionIds)
                        ->select('role_id'));
            })
            ->pluck('id');
        $permissionId = DB::table('permissions')
            ->where('name', $this->permission)
            ->where('guard_name', 'web')
            ->value('id');

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
        $permissionIds = DB::table('permissions')
            ->where('name', $this->permission)
            ->where('guard_name', 'web')
            ->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
