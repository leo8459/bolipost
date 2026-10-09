<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    private const DATE_INDEXES = [
        ['paquetes_ems', 'dashboard_ems_created_at_idx'],
        ['paquetes_contrato', 'dashboard_contrato_created_at_idx'],
        ['paquetes_certi', 'dashboard_certi_created_at_idx'],
        ['paquetes_ordi', 'dashboard_ordi_created_at_idx'],
    ];

    private const CODE_INDEXES = [
        ['paquetes_certi', 'dashboard_certi_codigo_idx'],
        ['paquetes_ordi', 'dashboard_ordi_codigo_idx'],
    ];

    public function up(): void
    {
        foreach (self::DATE_INDEXES as [$table, $indexName]) {
            $this->createIndex($table, $indexName, ['created_at']);
        }

        foreach (self::CODE_INDEXES as [$table, $indexName]) {
            $this->createIndex($table, $indexName, ['codigo']);
        }
    }

    public function down(): void
    {
        foreach (array_merge(self::DATE_INDEXES, self::CODE_INDEXES) as [$table, $indexName]) {
            $this->dropIndex($table, $indexName);
        }
    }

    private function createIndex(string $table, string $indexName, array $columns): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(sprintf(
                'CREATE INDEX CONCURRENTLY IF NOT EXISTS %s ON %s (%s)',
                $indexName,
                $table,
                implode(', ', $columns)
            ));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName, $columns): void {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndex(string $table, string $indexName): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . $indexName);

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
            $blueprint->dropIndex($indexName);
        });
    }
};
