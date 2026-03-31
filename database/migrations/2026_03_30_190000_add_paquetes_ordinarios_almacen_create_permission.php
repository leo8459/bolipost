<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private string $guardName = 'web';

    private string $permissionName = 'feature.paquetes-ordinarios.almacen.create';

    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['name' => $this->permissionName, 'guard_name' => $this->guardName],
            ['updated_at' => $now, 'created_at' => $now]
        );

        $permissionId = DB::table('permissions')
            ->where('name', $this->permissionName)
            ->where('guard_name', $this->guardName)
            ->value('id');

        if (! $permissionId) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            return;
        }

        $normalizedPermission = (string) Str::after($this->permissionName, 'feature.');
        $roleTemplates = (array) config('acl.role_templates', []);

        foreach ($roleTemplates as $roleName => $patterns) {
            $patterns = array_values(array_filter((array) $patterns, fn ($pattern) => is_string($pattern) && $pattern !== ''));

            if (! $this->matchesPatterns($patterns, $this->permissionName, $normalizedPermission)) {
                continue;
            }

            $roleId = DB::table('roles')
                ->where('name', (string) $roleName)
                ->where('guard_name', $this->guardName)
                ->value('id');

            if (! $roleId) {
                continue;
            }

            DB::table('role_has_permissions')->updateOrInsert(
                ['permission_id' => $permissionId, 'role_id' => $roleId],
                []
            );
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('name', $this->permissionName)
            ->where('guard_name', $this->guardName)
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        DB::table('permissions')
            ->where('name', $this->permissionName)
            ->where('guard_name', $this->guardName)
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function matchesPatterns(array $patterns, string $permission, string $normalizedPermission): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $permission) || Str::is($pattern, $normalizedPermission)) {
                return true;
            }
        }

        return false;
    }
};
