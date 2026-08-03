<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $guardName = 'web';

    private array $permissions = [
        'area-contratos.contratos-escaneados',
        'area-contratos.contratos-escaneados.view',
        'area-contratos.contratos-escaneados.download',
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
        $permissionIds = DB::table('permissions')
            ->where('guard_name', $this->guardName)
            ->whereIn('name', $this->permissions)
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        DB::table('permissions')
            ->where('guard_name', $this->guardName)
            ->whereIn('name', $this->permissions)
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
