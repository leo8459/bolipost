<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->string('formato_nota_cobranza')->nullable()->after('por_cobrar_por');
            $table->string('nombre_empresa_cobranza')->nullable()->after('formato_nota_cobranza');
        });
    }

    public function down(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->dropColumn([
                'formato_nota_cobranza',
                'nombre_empresa_cobranza',
            ]);
        });
    }
};
