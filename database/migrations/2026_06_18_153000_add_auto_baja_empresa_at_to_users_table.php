<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'auto_baja_empresa_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('auto_baja_empresa_at')->nullable()->after('deleted_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'auto_baja_empresa_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('auto_baja_empresa_at');
            });
        }
    }
};
