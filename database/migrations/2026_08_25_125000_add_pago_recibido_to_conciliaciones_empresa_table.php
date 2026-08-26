<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->timestamp('pago_recibido_at')->nullable();
            $table->foreignId('pago_recibido_por')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->dropForeign(['pago_recibido_por']);
            $table->dropColumn(['pago_recibido_at', 'pago_recibido_por']);
        });
    }
};
