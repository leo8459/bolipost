<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    private const LEGACY_PERMISSION = 'feature.dashboard.facturacion';

    private const KARDEX_PERMISSIONS = [
        'mis-ventas.kardex',
        'mis-ventas.kardex-regional',
    ];

    public function up(): void
    {
        $guard = (string) config('auth.defaults.guard', 'web');
        $legacy = Permission::query()
            ->where('name', self::LEGACY_PERMISSION)
            ->where('guard_name', $guard)
            ->first();

        if (! $legacy) {
            return;
        }

        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $legacy->id)
            ->pluck('role_id')
            ->all();
        $directAssignments = DB::table('model_has_permissions')
            ->where('permission_id', $legacy->id)
            ->get(['model_type', 'model_id']);

        foreach (self::KARDEX_PERMISSIONS as $permissionName) {
            $permission = Permission::findOrCreate($permissionName, $guard);

            foreach ($roleIds as $roleId) {
                $exists = DB::table('role_has_permissions')
                    ->where('permission_id', $permission->id)
                    ->where('role_id', $roleId)
                    ->exists();

                if (! $exists) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $permission->id,
                        'role_id' => $roleId,
                    ]);
                }
            }

            foreach ($directAssignments as $assignment) {
                $exists = DB::table('model_has_permissions')
                    ->where('permission_id', $permission->id)
                    ->where('model_type', $assignment->model_type)
                    ->where('model_id', $assignment->model_id)
                    ->exists();

                if (! $exists) {
                    DB::table('model_has_permissions')->insert([
                        'permission_id' => $permission->id,
                        'model_type' => $assignment->model_type,
                        'model_id' => $assignment->model_id,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Keep the split permissions and grants on rollback: they are safe
        // route permissions and may have been adjusted independently in ACL.
    }
};
