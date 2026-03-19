<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permissions = [
        'paquetes-ems.contrato-rapido.create',
        'paquetes-ems.contrato-rapido.store',
        'feature.paquetes-ems.contrato-rapido.create.create',
        'feature.paquetes-ems.contrato-rapido.create.save',
        'feature.paquetes-ems.contrato-rapido.create.delete',
    ];

    public function up(): void
    {
        $guardName = 'web';
        $now = now();

        foreach ($this->permissions as $permissionName) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permissionName, 'guard_name' => $guardName],
                ['updated_at' => $now, 'created_at' => $now]
            );
        }

        $roleId = DB::table('roles')
            ->where('name', 'administrador')
            ->where('guard_name', $guardName)
            ->value('id');

        if (! $roleId) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $this->permissions)
            ->where('guard_name', $guardName)
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['permission_id' => $permissionId, 'role_id' => $roleId],
                []
            );
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $guardName = 'web';

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $this->permissions)
            ->where('guard_name', $guardName)
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        DB::table('permissions')
            ->whereIn('name', $this->permissions)
            ->where('guard_name', $guardName)
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
