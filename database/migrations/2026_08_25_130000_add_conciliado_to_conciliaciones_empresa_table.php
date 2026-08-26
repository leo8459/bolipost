<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->timestamp('conciliado_at')->nullable();
            $table->foreignId('conciliado_por')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::table('conciliaciones_empresa')
            ->whereNotNull('documento_path')
            ->update([
                'conciliado_at' => DB::raw('COALESCE(conciliacion_at, documento_at, updated_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->dropForeign(['conciliado_por']);
            $table->dropColumn(['conciliado_at', 'conciliado_por']);
        });
    }
};
