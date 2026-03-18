<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id')->nullable()->after('id');
                $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
            });
        }

        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'nombre')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('nombre')->nullable()->after('name');
            });

            DB::table('roles')->whereNull('nombre')->update([
                'nombre' => DB::raw('name'),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            });
        }

        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'nombre')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('nombre');
            });
        }
    }
};

