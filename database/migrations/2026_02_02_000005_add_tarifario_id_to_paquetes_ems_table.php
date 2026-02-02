<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paquetes_ems', function (Blueprint $table) {
            $table->foreignId('tarifario_id')
                ->nullable()
                ->constrained('tarifario')
                ->cascadeOnDelete()
                ->after('ciudad');
        });

        DB::statement('ALTER TABLE paquetes_ems ALTER COLUMN peso TYPE numeric(10,3)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE paquetes_ems ALTER COLUMN peso TYPE numeric(10,2)');

        Schema::table('paquetes_ems', function (Blueprint $table) {
            $table->dropForeign(['tarifario_id']);
            $table->dropColumn('tarifario_id');
        });
    }
};
