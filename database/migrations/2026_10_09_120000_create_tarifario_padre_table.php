<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifario_padre', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::table('servicio', function (Blueprint $table) {
            $table->foreignId('tarifario_padre_id')->nullable()
                ->constrained('tarifario_padre')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('servicio', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tarifario_padre_id');
        });

        Schema::dropIfExists('tarifario_padre');
    }
};
