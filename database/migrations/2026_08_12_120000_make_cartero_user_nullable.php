<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cartero', function (Blueprint $table) {
            $table->unsignedBigInteger('id_user')->nullable()->change();
        });
    }

    public function down(): void
    {
        // La base de datos rechazara el rollback de forma segura si existen
        // asignaciones que ya fueron dejadas sin cartero.
        Schema::table('cartero', function (Blueprint $table) {
            $table->unsignedBigInteger('id_user')->nullable(false)->change();
        });
    }
};
