<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_flow_receivable_collections', function (Blueprint $table): void {
            $table->boolean('cobro_activo')->default(true)->after('cobrado_at')->index();
            $table->timestamp('devuelto_at')->nullable()->after('cobro_activo');
            $table->foreignId('devuelto_por')->nullable()->after('devuelto_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cashier_flow_receivable_collections', function (Blueprint $table): void {
            $table->dropForeign(['devuelto_por']);
            $table->dropIndex(['cobro_activo']);
            $table->dropColumn(['cobro_activo', 'devuelto_at', 'devuelto_por']);
        });
    }
};
