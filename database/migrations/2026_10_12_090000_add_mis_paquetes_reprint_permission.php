<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private string $permissionName = 'feature.paquetes-contrato.mis-paquetes.print';

    private string $guardName = 'web';

    private array $misPaquetesAccessPermissions = [
        'paquetes-contrato.mis-paquetes',
        'paquetes-contrato.index',
        'feature.paquetes-contrato.index.create',
        'feature.paquetes-contrato.create.create',
        'feature.paquetes-contrato.index.manage',
        'feature.paquetes-contrato.create-con-tarifa.create',
    ];

    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            [
                'name' => $this->permissionName,
                'guard_name' => $this->guardName,
            ],
            [
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permissionId = DB::table('permissions')
            ->where('name', $this->permissionName)
            ->where('guard_name', $this->guardName)
            ->value('id');

        if (! $permissionId) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            return;
        }

        $pageAccessPermissionIds = DB::table('permissions')
            ->where('guard_name', $this->guardName)
            ->whereIn('name', $this->misPaquetesAccessPermissions)
            ->pluck('id');

        $roleIds = DB::table('roles')
            ->where('guard_name', $this->guardName)
            ->where(function ($query) use ($pageAccessPermissionIds): void {
                $query->where('name', config('acl.super_admin_role', 'administrador'));

                if ($pageAccessPermissionIds->isNotEmpty()) {
                    $query->orWhereIn(
                        'id',
                        DB::table('role_has_permissions')
                            ->whereIn('permission_id', $pageAccessPermissionIds)
                            ->select('role_id')
                    );
                }
            })
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->updateOrInsert(
                [
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ],
                []
            );
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

            DB::table('model_has_permissions')
                ->where('permission_id', $permissionId)
                ->delete();

            DB::table('permissions')
                ->where('id', $permissionId)
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
