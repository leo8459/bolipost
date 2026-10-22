<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    private const INDEXES = [
        ['eventos_contrato', 'eventos_contrato_created_id_idx', ['created_at', 'id']],
        ['bastion_eventos', 'bastion_eventos_contrato_created_id_idx', ['tipo_paquete', 'created_at', 'id_origen']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as [$table, $indexName, $columns]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement(sprintf(
                    'CREATE INDEX CONCURRENTLY IF NOT EXISTS %s ON %s (%s)',
                    $indexName,
                    $table,
                    implode(', ', $columns)
                ));

                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName): void {
                $blueprint->index($columns, $indexName);
            });
        }

        if (Schema::hasTable('bastion_contratos') && Schema::hasColumn('bastion_contratos', 'codigo')) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement(
                    'CREATE INDEX CONCURRENTLY IF NOT EXISTS bastion_contratos_codigo_normalizado_idx '
                    . 'ON bastion_contratos (UPPER(TRIM(codigo)))'
                );
            } else {
                Schema::table('bastion_contratos', function (Blueprint $blueprint): void {
                    $blueprint->index('codigo', 'bastion_contratos_codigo_normalizado_idx');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as [$table, $indexName]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . $indexName);

                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->dropIndex($indexName);
            });
        }

        if (Schema::hasTable('bastion_contratos')) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bastion_contratos_codigo_normalizado_idx');
            } else {
                Schema::table('bastion_contratos', function (Blueprint $blueprint): void {
                    $blueprint->dropIndex('bastion_contratos_codigo_normalizado_idx');
                });
            }
        }
    }
};
