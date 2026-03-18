<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('clientes', 'user_id')) {
            try {
                DB::statement('ALTER TABLE clientes DROP CONSTRAINT IF EXISTS clientes_user_id_foreign');
            } catch (\Throwable) {
            }

            try {
                DB::statement('ALTER TABLE clientes ALTER COLUMN user_id DROP NOT NULL');
            } catch (\Throwable) {
            }
        }

        if (Schema::hasColumn('clientes', 'provider')) {
            DB::statement("ALTER TABLE clientes ALTER COLUMN provider SET DEFAULT 'local'");
        }

        Schema::table('clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('clientes', 'password')) {
                $table->string('password')->nullable()->after('email');
            }

            if (! Schema::hasColumn('clientes', 'rol')) {
                $table->string('rol')->default('tiktokero')->after('password');
            }

            if (! Schema::hasColumn('clientes', 'remember_token')) {
                $table->rememberToken();
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes', 'remember_token')) {
                $table->dropColumn('remember_token');
            }
            if (Schema::hasColumn('clientes', 'rol')) {
                $table->dropColumn('rol');
            }
            if (Schema::hasColumn('clientes', 'password')) {
                $table->dropColumn('password');
            }
        });
    }
};
