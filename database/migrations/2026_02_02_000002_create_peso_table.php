<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peso', function (Blueprint $table) {
            $table->id();
            $table->decimal('peso_inicial', 10, 3);
            $table->decimal('peso_final', 10, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peso');
    }
};
