<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'empresas.historial.index',
        'empresas.historial.pdf',
        'feature.empresas.history',
        'feature.empresas.historial.search',
        'feature.empresas.historial.view-pdf',
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

        $empresaPermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', ['empresas.index', 'feature.empresas.edit'])
            ->pluck('id');

        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->where(function ($query) use ($empresaPermissionIds): void {
                $query->where('name', 'administrador')
                    ->orWhereIn('id', DB::table('role_has_permissions')
                        ->whereIn('permission_id', $empresaPermissionIds)
                        ->select('role_id'));
            })
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
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
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
