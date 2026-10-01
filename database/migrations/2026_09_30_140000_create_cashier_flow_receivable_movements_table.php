<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_flow_receivable_movements', function (Blueprint $table): void {
            $table->id();
            $table->char('movement_key', 64)->unique();
            $table->string('servicio', 180);
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('venta_id', 180)->nullable();
            $table->string('detalle_id', 180)->nullable();
            $table->string('codigo_orden', 180)->nullable();
            $table->string('codigo_seguimiento', 180)->nullable();
            $table->string('fecha', 80)->nullable();
            $table->text('descripcion')->nullable();
            $table->decimal('monto', 16, 2);
            $table->boolean('cobro_activo')->default(true);
            $table->foreignId('cobrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cobrado_at');
            $table->foreignId('devuelto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('devuelto_at')->nullable();
            $table->timestamps();

            $table->index(['anio', 'mes', 'servicio']);
            $table->index(['cobro_activo', 'anio', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_flow_receivable_movements');
    }
};
