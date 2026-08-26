<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $tables = ['bastion_ems', 'bastion_contratos', 'bastion_certi', 'bastion_ordi'];

    private array $permissions = ['bastiones.index', 'bastiones.recuperar'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'id')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->id());
            }
            if (! Schema::hasColumn($table, 'id_origen')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('id_origen')->nullable()->index());
            }
        }

        $now = now();
        foreach ($this->permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        $administratorId = DB::table('roles')->where('name', 'administrador')->where('guard_name', 'web')->value('id');
        if ($administratorId) {
            $permissionIds = DB::table('permissions')->whereIn('name', $this->permissions)->where('guard_name', 'web')->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $administratorId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', $this->permissions)->where('guard_name', 'web')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
