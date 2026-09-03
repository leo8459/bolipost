<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'dashboard.generacion-cn',
        'dashboard.generacion-cn.pdf',
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

        $parentId = DB::table('permissions')->where('name', 'dashboard.dir-operaciones')->where('guard_name', 'web')->value('id');
        $roleIds = DB::table('roles')->where('guard_name', 'web')->where(function ($query) use ($parentId): void {
            $query->where('name', 'administrador');
            if ($parentId) {
                $query->orWhereIn('id', DB::table('role_has_permissions')->where('permission_id', $parentId)->select('role_id'));
            }
        })->pluck('id');

        $permissionIds = DB::table('permissions')->whereIn('name', $this->permissions)->where('guard_name', 'web')->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', $this->permissions)->where('guard_name', 'web')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
