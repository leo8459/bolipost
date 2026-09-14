<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $reportPermissions = [
        'dashboard.generacion-cn',
        'dashboard.generacion-cn.pdf',
        'dashboard.marbetes',
        'dashboard.marbetes.pdf',
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->reportPermissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        $operationsPermissionId = DB::table('permissions')
            ->where('name', 'dashboard.dir-operaciones')
            ->where('guard_name', 'web')
            ->value('id');

        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->where(function ($query) use ($operationsPermissionId): void {
                $query->where('name', 'administrador');

                if ($operationsPermissionId) {
                    $query->orWhereIn(
                        'id',
                        DB::table('role_has_permissions')
                            ->where('permission_id', $operationsPermissionId)
                            ->select('role_id')
                    );
                }
            })
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->reportPermissions)
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
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
