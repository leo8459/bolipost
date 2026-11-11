<?php

use App\Support\AclPermissionRegistry;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        AclPermissionRegistry::syncPermissions('web');

        $role = Role::findOrCreate('internacional', 'web');
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'like', 'internacional.%')
            ->get();

        $role->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Preserve grants that may have already belonged to this operational role.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
