<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_sucursales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresa')->restrictOnDelete();
            $table->string('nombre');
            $table->string('ciudad')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre']);
            $table->unique(['id', 'empresa_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('empresa_sucursal_id')->nullable()->index();
            $table->foreign(['empresa_sucursal_id', 'empresa_id'])
                ->references(['id', 'empresa_id'])
                ->on('empresa_sucursales')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['empresa_sucursal_id', 'empresa_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['empresa_sucursal_id']);
            $table->dropColumn('empresa_sucursal_id');
        });

        Schema::dropIfExists('empresa_sucursales');
    }
};
