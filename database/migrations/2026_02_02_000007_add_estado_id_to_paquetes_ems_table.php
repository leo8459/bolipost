<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paquetes_ems', function (Blueprint $table) {
            $table->foreignId('estado_id')
                ->nullable()
                ->constrained('estados')
                ->after('tarifario_id');
        });
    }

    public function down(): void
    {
        Schema::table('paquetes_ems', function (Blueprint $table) {
            $table->dropForeign(['estado_id']);
            $table->dropColumn('estado_id');
        });
    }
};
