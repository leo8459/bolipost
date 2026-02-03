<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paquetes_certi', function (Blueprint $table) {
            $table->unsignedBigInteger('fk_ventanilla')->nullable()->after('fk_estado');
            $table->foreign('fk_ventanilla')->references('id')->on('ventanilla');
        });
    }

    public function down(): void
    {
        Schema::table('paquetes_certi', function (Blueprint $table) {
            $table->dropForeign(['fk_ventanilla']);
            $table->dropColumn('fk_ventanilla');
        });
    }
};
