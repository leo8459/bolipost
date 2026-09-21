<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['eventos_ems', 'dashboard_eventos_ems_code_lookup_idx'],
            ['eventos_contrato', 'dashboard_eventos_contrato_code_lookup_idx'],
            ['eventos_certi', 'dashboard_eventos_certi_code_lookup_idx'],
            ['eventos_ordi', 'dashboard_eventos_ordi_code_lookup_idx'],
        ] as [$table, $indexName]) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->index(['codigo', 'evento_id', 'created_at'], $indexName);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            ['eventos_ems', 'dashboard_eventos_ems_code_lookup_idx'],
            ['eventos_contrato', 'dashboard_eventos_contrato_code_lookup_idx'],
            ['eventos_certi', 'dashboard_eventos_certi_code_lookup_idx'],
            ['eventos_ordi', 'dashboard_eventos_ordi_code_lookup_idx'],
        ] as [$table, $indexName]) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->dropIndex($indexName);
            });
        }
    }
};
