<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despacho', function (Blueprint $table) {
            $table->dropColumn('servicio');
        });
    }

    public function down(): void
    {
        Schema::table('despacho', function (Blueprint $table) {
            $table->string('servicio')->after('anio');
        });
    }
};
