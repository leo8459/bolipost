<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despacho', function (Blueprint $table) {
            $table->string('nro_envase')->nullable()->change();
            $table->decimal('peso', 10, 3)->nullable()->change();
            $table->string('identificador')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('despacho', function (Blueprint $table) {
            $table->string('nro_envase')->nullable(false)->change();
            $table->decimal('peso', 10, 3)->nullable(false)->change();
            $table->string('identificador')->nullable(false)->change();
        });
    }
};
