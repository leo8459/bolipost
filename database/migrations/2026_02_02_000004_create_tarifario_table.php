<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('servicio')->cascadeOnDelete();
            $table->foreignId('destino_id')->constrained('destino')->cascadeOnDelete();
            $table->foreignId('peso_id')->constrained('peso')->cascadeOnDelete();
            $table->foreignId('origen_id')->constrained('origen')->cascadeOnDelete();
            $table->decimal('precio', 10, 2);
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifario');
    }
};
