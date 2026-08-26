<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->timestamp('confirmacion_pago_at')->nullable();
            $table->foreignId('confirmacion_pago_por')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->dropForeign(['confirmacion_pago_por']);
            $table->dropColumn(['confirmacion_pago_at', 'confirmacion_pago_por']);
        });
    }
};
