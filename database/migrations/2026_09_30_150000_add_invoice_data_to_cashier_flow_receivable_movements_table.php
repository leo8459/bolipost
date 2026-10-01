<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_flow_receivable_movements', function (Blueprint $table): void {
            $table->string('facturado_por_id', 80)->nullable();
            $table->string('facturado_por_nombre', 180)->nullable();
            $table->string('facturado_por_email', 180)->nullable();
            $table->string('facturado_por_alias', 180)->nullable();
            $table->decimal('cantidad_paquetes', 16, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('cashier_flow_receivable_movements', function (Blueprint $table): void {
            $table->dropColumn([
                'facturado_por_id',
                'facturado_por_nombre',
                'facturado_por_email',
                'facturado_por_alias',
                'cantidad_paquetes',
            ]);
        });
    }
};
