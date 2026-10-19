<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_flow_receivable_movements', function (Blueprint $table): void {
            $table->date('fecha_cobro')->nullable()->after('cobro_activo');
        });
    }

    public function down(): void
    {
        Schema::table('cashier_flow_receivable_movements', function (Blueprint $table): void {
            $table->dropColumn('fecha_cobro');
        });
    }
};
