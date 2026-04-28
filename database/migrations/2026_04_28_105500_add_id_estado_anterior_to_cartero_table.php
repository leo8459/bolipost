<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cartero', function (Blueprint $table) {
            if (!Schema::hasColumn('cartero', 'id_estado_anterior')) {
                $table->unsignedBigInteger('id_estado_anterior')->nullable()->after('id_estados');
                $table->foreign('id_estado_anterior')->references('id')->on('estados')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cartero', function (Blueprint $table) {
            if (Schema::hasColumn('cartero', 'id_estado_anterior')) {
                try {
                    $table->dropForeign(['id_estado_anterior']);
                } catch (\Throwable $e) {
                    // noop
                }
                $table->dropColumn('id_estado_anterior');
            }
        });
    }
};
