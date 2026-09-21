<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $packageIndexes = [
            ['paquetes_ems', 'estado_id', 'dashboard_ems_state_created_idx'],
            ['paquetes_contrato', 'estados_id', 'dashboard_contrato_state_created_idx'],
            ['paquetes_certi', 'fk_estado', 'dashboard_certi_state_created_idx'],
            ['paquetes_ordi', 'fk_estado', 'dashboard_ordi_state_created_idx'],
        ];

        foreach ($packageIndexes as [$table, $stateColumn, $indexName]) {
            Schema::table($table, function (Blueprint $blueprint) use ($stateColumn, $indexName): void {
                $blueprint->index([$stateColumn, 'created_at'], $indexName);
            });
        }

        $eventIndexes = [
            ['eventos_ems', 'dashboard_eventos_ems_lookup_idx'],
            ['eventos_contrato', 'dashboard_eventos_contrato_lookup_idx'],
            ['eventos_certi', 'dashboard_eventos_certi_lookup_idx'],
            ['eventos_ordi', 'dashboard_eventos_ordi_lookup_idx'],
        ];

        foreach ($eventIndexes as [$table, $indexName]) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->index(['evento_id', 'created_at', 'codigo'], $indexName);
            });
        }

    }

    public function down(): void
    {
        $indexes = [
            ['paquetes_ems', 'dashboard_ems_state_created_idx'],
            ['paquetes_contrato', 'dashboard_contrato_state_created_idx'],
            ['paquetes_certi', 'dashboard_certi_state_created_idx'],
            ['paquetes_ordi', 'dashboard_ordi_state_created_idx'],
            ['eventos_ems', 'dashboard_eventos_ems_lookup_idx'],
            ['eventos_contrato', 'dashboard_eventos_contrato_lookup_idx'],
            ['eventos_certi', 'dashboard_eventos_certi_lookup_idx'],
            ['eventos_ordi', 'dashboard_eventos_ordi_lookup_idx'],
        ];

        foreach ($indexes as [$table, $indexName]) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->dropIndex($indexName);
            });
        }
    }
};
