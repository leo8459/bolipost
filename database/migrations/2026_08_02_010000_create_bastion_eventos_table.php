<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SOURCES = [
        'eventos_ems' => 'EMS',
        'eventos_certi' => 'CERTI',
        'eventos_ordi' => 'ORDI',
        'eventos_contrato' => 'CONTRATO',
        'eventos_tiktoker' => 'TIKTOKER',
    ];

    private const DESTINATION_COLUMNS = [
        'tabla_origen',
        'id_origen',
        'tipo_paquete',
        'codigo',
        'evento_id',
        'user_id',
        'cliente_id',
        'created_at',
        'updated_at',
    ];

    public function up(): void
    {
        if (Schema::hasTable('bastion_eventos')) {
            return;
        }

        Schema::create('bastion_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('tabla_origen', 40);
            $table->unsignedBigInteger('id_origen');
            $table->string('tipo_paquete', 20);
            $table->string('codigo')->index();
            $table->unsignedBigInteger('evento_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->timestamps();

            $table->unique(['tabla_origen', 'id_origen']);
            $table->index(['tipo_paquete', 'created_at']);
            $table->index('created_at');
        });

        foreach (self::SOURCES as $sourceTable => $packageType) {
            if (! Schema::hasTable($sourceTable)) {
                continue;
            }

            $clienteId = Schema::hasColumn($sourceTable, 'cliente_id')
                ? 'cliente_id'
                : 'NULL AS cliente_id';

            DB::table('bastion_eventos')->insertUsing(
                self::DESTINATION_COLUMNS,
                DB::table($sourceTable)->selectRaw(
                    "'{$sourceTable}' AS tabla_origen, "
                    . "id AS id_origen, "
                    . "'{$packageType}' AS tipo_paquete, "
                    . 'codigo, evento_id, user_id, '
                    . $clienteId
                    . ', created_at, updated_at'
                )
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bastion_eventos');
    }
};
