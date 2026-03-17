<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('drivers')) {
            Schema::create('drivers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('nombre');
                $table->string('licencia', 50)->nullable();
                $table->string('tipo_licencia', 20)->nullable();
                $table->date('fecha_vencimiento_licencia')->nullable();
                $table->string('telefono', 20)->nullable();
                $table->string('email')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};

