<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    private const INDEXES = [
        ['eventos_ems', 'dashboard_eventos_ems_start_lookup_idx'],
        ['eventos_contrato', 'dashboard_eventos_contrato_start_lookup_idx'],
        ['eventos_certi', 'dashboard_eventos_certi_start_lookup_idx'],
        ['eventos_ordi', 'dashboard_eventos_ordi_start_lookup_idx'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as [$table, $indexName]) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement(sprintf(
                    'CREATE INDEX CONCURRENTLY IF NOT EXISTS %s ON %s (evento_id, codigo, created_at)',
                    $indexName,
                    $table
                ));

                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                // Acelera la busqueda del primer evento operativo por codigo.
                $blueprint->index(['evento_id', 'codigo', 'created_at'], $indexName);
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as [$table, $indexName]) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . $indexName);

                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->dropIndex($indexName);
            });
        }
    }
};
