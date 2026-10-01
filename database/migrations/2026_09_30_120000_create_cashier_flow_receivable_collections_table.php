<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_flow_receivable_collections', function (Blueprint $table): void {
            $table->id();
            $table->char('scope_hash', 64)->unique();
            $table->string('servicio', 180);
            $table->unsignedSmallInteger('anio');
            $table->json('meses');
            $table->unsignedSmallInteger('limite')->default(200);
            $table->string('departamento', 120)->nullable();
            $table->decimal('monto_cobrado', 16, 2);
            $table->foreignId('cobrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cobrado_at');
            $table->timestamps();

            $table->index(['anio', 'servicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_flow_receivable_collections');
    }
};
