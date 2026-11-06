<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $guard = (string) config('auth.defaults.guard', 'web');

        $administratorRoleIds = Role::query()
            ->where('name', 'administrador')
            ->where('guard_name', $guard)
            ->pluck('id');
        $cashierRoleIds = Role::query()
            ->where('name', 'facturador')
            ->where('guard_name', $guard)
            ->pluck('id');

        $personalKardex = Permission::findOrCreate('mis-ventas.kardex', $guard);
        $branchKardex = Permission::findOrCreate('mis-ventas.kardex-regional', $guard);

        // El rol Facturador solo consulta el Kardex personal; Administrador
        // conserva ambos accesos. Los demás roles no reciben estos permisos.
        $personalKardex->roles()->sync(
            $administratorRoleIds->merge($cashierRoleIds)->unique()->values()->all()
        );
        $branchKardex->roles()->sync($administratorRoleIds->all());

        // Estos accesos se administran por rol para evitar privilegios directos
        // que contradigan la separación entre Kardex personal y por sucursal.
        DB::table('model_has_permissions')
            ->whereIn('permission_id', [$personalKardex->id, $branchKardex->id])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep the role assignments on rollback so an ACL rollback cannot
        // silently restore wider access to the regional Kardex.
    }
};
