<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerta_empresa_usuarios', function (Blueprint $table): void {
            $table->foreignId('alerta_empresa_id')->constrained('alertas_empresa')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['alerta_empresa_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerta_empresa_usuarios');
    }
};
