<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifarios_contrato', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 180);
            $table->unsignedSmallInteger('anio')->nullable();
            $table->timestamp('publicado_en')->nullable();
            $table->timestamps();
            $table->unique(['nombre', 'anio']);
        });
        Schema::table('tarifa_contrato', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->change();
            $table->foreignId('tarifario_contrato_id')->nullable()
                ->constrained('tarifarios_contrato')->restrictOnDelete();
        });
        Schema::create('tarifario_contrato_empresa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresa')->restrictOnDelete();
            $table->foreignId('tarifario_contrato_id')->constrained('tarifarios_contrato')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamp('asignado_en');
            $table->timestamp('finalizado_en')->nullable();
            $table->unique(['empresa_id', 'tarifario_contrato_id'], 'tarifario_empresa_unico');
            $table->index(['empresa_id', 'activo']);
        });

        // Preserve every existing tariff ID and current company access, without guessing its year.
        DB::transaction(function () {
            foreach (DB::table('tarifa_contrato')->distinct()->pluck('empresa_id') as $empresaId) {
                $nombre = $empresaId ? DB::table('empresa')->where('id', $empresaId)->value('nombre') : 'Sin empresa';
                $id = DB::table('tarifarios_contrato')->insertGetId([
                    'nombre' => mb_substr('Tarifario existente - '.$nombre, 0, 180),
                    'anio' => null,
                    'publicado_en' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('tarifa_contrato')->where('empresa_id', $empresaId)->update(['tarifario_contrato_id' => $id]);
                if ($empresaId) {
                    DB::table('tarifario_contrato_empresa')->insert([
                        'empresa_id' => $empresaId, 'tarifario_contrato_id' => $id,
                        'activo' => true, 'asignado_en' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        if (DB::table('tarifa_contrato')->whereNull('empresa_id')->exists()) {
            throw new RuntimeException('No se puede revertir mientras existan tarifas compartidas entre empresas.');
        }
        Schema::dropIfExists('tarifario_contrato_empresa');
        Schema::table('tarifa_contrato', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tarifario_contrato_id');
            $table->foreignId('empresa_id')->nullable(false)->change();
        });
        Schema::dropIfExists('tarifarios_contrato');
    }
};
