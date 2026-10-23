<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = ['solicitudes-cambio.index', 'solicitudes-cambio.store', 'solicitudes-cambio.show', 'feature.solicitudes-cambio.create', 'feature.solicitudes-cambio.manage'];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }
        foreach (Role::where('guard_name', 'web')->whereIn('name', ['administrador', 'regional', 'taller', 'conductor'])->get() as $role) {
            $role->givePermissionTo(array_values(array_filter($this->permissions, fn ($name) => $role->name === 'administrador' || $name !== 'feature.solicitudes-cambio.manage')));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', 'web')->whereIn('name', $this->permissions)->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
