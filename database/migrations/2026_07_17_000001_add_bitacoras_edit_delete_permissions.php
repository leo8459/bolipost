<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $guardName = 'web';

    private array $permissions = [
        'bitacoras.edit',
        'bitacoras.update',
        'bitacoras.destroy',
        'feature.bitacoras.index.edit',
        'feature.bitacoras.index.delete',
        'feature.bitacoras.edit',
        'feature.bitacoras.delete',
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

        $roleId = DB::table('roles')
            ->where('name', 'administrador')
            ->where('guard_name', $this->guardName)
            ->value('id');

        if ($roleId) {
            $permissionIds = DB::table('permissions')
                ->whereIn('name', $this->permissions)
                ->where('guard_name', $this->guardName)
                ->pluck('id');

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
            ->whereIn('name', $this->permissions)
            ->where('guard_name', $this->guardName)
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        DB::table('permissions')
            ->whereIn('name', $this->permissions)
            ->where('guard_name', $this->guardName)
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
