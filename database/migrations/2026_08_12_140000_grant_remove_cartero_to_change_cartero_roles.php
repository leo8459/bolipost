<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private string $removePermission = 'feature.paquetes-ems.encargado.removecartero';

    private string $changePermission = 'feature.paquetes-ems.encargado.changecartero';

    public function up(): void
    {
        $removePermissionId = DB::table('permissions')
            ->where('name', $this->removePermission)
            ->where('guard_name', 'web')
            ->value('id');

        $changePermissionId = DB::table('permissions')
            ->where('name', $this->changePermission)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $removePermissionId) {
            return;
        }

        $roleIds = DB::table('role_has_permissions')
            ->when($changePermissionId, fn ($query) => $query->where('permission_id', $changePermissionId))
            ->when(! $changePermissionId, fn ($query) => $query->whereRaw('1 = 0'))
            ->pluck('role_id')
            ->push(
                DB::table('roles')
                    ->where('name', 'administrador')
                    ->where('guard_name', 'web')
                    ->value('id')
            )
            ->filter()
            ->unique()
            ->values();

        if ($roleIds->isNotEmpty()) {
            DB::table('role_has_permissions')->insertOrIgnore(
                $roleIds->map(fn ($roleId) => [
                    'permission_id' => $removePermissionId,
                    'role_id' => $roleId,
                ])->all()
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $removePermissionId = DB::table('permissions')
            ->where('name', $this->removePermission)
            ->where('guard_name', 'web')
            ->value('id');

        if ($removePermissionId) {
            DB::table('role_has_permissions')
                ->where('permission_id', $removePermissionId)
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
