<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->string('clasificacion')->nullable()->after('codigo_cliente');
            $table->string('documentacion_legal')->nullable()->after('clasificacion');
            $table->date('inicio_contrato')->nullable()->after('documentacion_legal');
            $table->date('fin_contrato')->nullable()->after('inicio_contrato');
            $table->string('cobertura')->nullable()->after('fin_contrato');
            $table->decimal('presupuesto', 14, 2)->nullable()->after('cobertura');
        });
    }

    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn([
                'clasificacion',
                'documentacion_legal',
                'inicio_contrato',
                'fin_contrato',
                'cobertura',
                'presupuesto',
            ]);
        });
    }
};
