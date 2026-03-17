<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fuel_logs')) {
            Schema::create('fuel_logs', function (Blueprint $table) {
                $table->id();
                $table->dateTime('fecha');
                $table->decimal('galones', 10, 2)->nullable();
                $table->decimal('precio_galon', 10, 2)->nullable();
                $table->decimal('total_calculado', 10, 2)->nullable();
                $table->decimal('kilometraje', 10, 2)->nullable();
                $table->string('recibo')->nullable();
                $table->text('observaciones')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('fecha');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_logs');
    }
};

