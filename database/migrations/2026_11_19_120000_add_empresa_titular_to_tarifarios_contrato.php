<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarifarios_contrato', function (Blueprint $table) {
            $table->dropUnique(['nombre', 'anio']);
            $table->string('nombre', 255)->change();
            $table->foreignId('empresa_titular_id')->nullable()->constrained('empresa')->restrictOnDelete();
            $table->unique(['empresa_titular_id', 'anio']);
        });

        DB::table('tarifarios_contrato')->whereNotNull('anio')->orderBy('id')->eachById(function ($tarifario) {
            $empresas = DB::table('empresa')->whereRaw('UPPER(TRIM(nombre)) = UPPER(TRIM(?))', [$tarifario->nombre])
                ->limit(2)->pluck('id');
            if ($empresas->count() !== 1) {
                return;
            }

            $empresaId = $empresas->first();
            if ($tarifario->publicado_en && ! DB::table('tarifario_contrato_empresa')
                ->where('tarifario_contrato_id', $tarifario->id)->where('empresa_id', $empresaId)->exists()) {
                return;
            }

            $yaVinculado = DB::table('tarifarios_contrato')->where('empresa_titular_id', $empresaId)
                ->where('anio', $tarifario->anio)->exists();
            if (! $yaVinculado) {
                DB::table('tarifarios_contrato')->where('id', $tarifario->id)->update(['empresa_titular_id' => $empresaId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tarifarios_contrato', function (Blueprint $table) {
            $table->dropUnique(['empresa_titular_id', 'anio']);
            $table->dropConstrainedForeignId('empresa_titular_id');
            $table->string('nombre', 180)->change();
            $table->unique(['nombre', 'anio']);
        });
    }
};
