<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paquetes_certi', function (Blueprint $table) {
            $table->unsignedBigInteger('fk_estado')->nullable()->after('aduana');
            $table->foreign('fk_estado')->references('id')->on('estados');
        });
    }

    public function down(): void
    {
        Schema::table('paquetes_certi', function (Blueprint $table) {
            $table->dropForeign(['fk_estado']);
            $table->dropColumn('fk_estado');
        });
    }
};
