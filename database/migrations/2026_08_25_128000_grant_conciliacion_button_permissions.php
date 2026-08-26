<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'feature.conciliacion.conciliaciones.conciliar',
        'feature.conciliacion.conciliaciones.por-cobrar',
        'feature.conciliacion.conciliaciones.pago-recibido',
        'feature.conciliacion.conciliaciones.confirmacion-pago',
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

        $modulePermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'dashboard.conciliacion',
                'dashboard.conciliacion.conciliaciones',
                'dashboard.conciliacion.facturado',
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
