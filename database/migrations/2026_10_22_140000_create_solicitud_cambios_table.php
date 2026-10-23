<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha');
            foreach (['ciudad', 'solicitante', 'cargo', 'unidad', 'sistema', 'modulo', 'ambiente', 'tipo', 'prioridad'] as $field) {
                $table->string($field);
            }
            $table->string('otro_tipo')->nullable();
            $table->text('antecedentes');
            $table->text('detalle');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_cambios');
    }
};
