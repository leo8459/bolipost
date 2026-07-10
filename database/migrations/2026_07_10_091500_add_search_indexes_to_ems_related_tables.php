<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = [
            ['paquetes_ems', 'idx_paquetes_ems_estado_origen', ['estado_id', 'origen']],
            ['paquetes_ems', 'idx_paquetes_ems_tarifario_id', ['tarifario_id']],

            ['paquetes_ems_formulario', 'idx_paquetes_ems_formulario_codigo', ['codigo']],
            ['paquetes_ems_formulario', 'idx_paquetes_ems_formulario_ciudad', ['ciudad']],
            ['paquetes_ems_formulario', 'idx_paquetes_ems_formulario_destino_id', ['destino_id']],
            ['paquetes_ems_formulario', 'idx_paquetes_ems_formulario_tarifario_id', ['tarifario_id']],

            ['paquetes_int', 'idx_paquetes_int_estado_id', ['estado_id']],
            ['paquetes_int', 'idx_paquetes_int_codigo', ['codigo']],
            ['paquetes_int', 'idx_paquetes_int_created_at', ['created_at']],
            ['paquetes_int', 'idx_paquetes_int_estado_origen', ['estado_id', 'origen']],
            ['paquetes_int', 'idx_paquetes_int_estado_destino', ['estado_id', 'destino']],

            ['solicitud_clientes', 'idx_solicitud_clientes_estado_id', ['estado_id']],
            ['solicitud_clientes', 'idx_solicitud_clientes_origen', ['origen']],
            ['solicitud_clientes', 'idx_solicitud_clientes_ciudad', ['ciudad']],
            ['solicitud_clientes', 'idx_solicitud_clientes_barcode', ['barcode']],
            ['solicitud_clientes', 'idx_solicitud_clientes_cod_especial', ['cod_especial']],
            ['solicitud_clientes', 'idx_solicitud_clientes_created_at', ['created_at']],
            ['solicitud_clientes', 'idx_solicitud_clientes_estado_origen', ['estado_id', 'origen']],
            ['solicitud_clientes', 'idx_solicitud_clientes_estado_ciudad', ['estado_id', 'ciudad']],
            ['solicitud_clientes', 'idx_solicitud_clientes_destino_id', ['destino_id']],
            ['solicitud_clientes', 'idx_solicitud_clientes_servicio_extra_id', ['servicio_extra_id']],
        ];

        foreach ($indexes as [$table, $index, $columns]) {
            if (!Schema::hasTable($table) || !$this->columnsExist($table, $columns) || $this->indexExists($table, $index)) {
                continue;
            }

            $this->createIndex($table, $index, $columns);
        }
    }

    public function down(): void
    {
        $indexes = [
            ['paquetes_ems', 'idx_paquetes_ems_estado_origen'],
            ['paquetes_ems', 'idx_paquetes_ems_tarifario_id'],

            ['paquetes_ems_formulario', 'idx_paquetes_ems_formulario_codigo'],
            ['paquetes_ems_formulario', 'idx_paquetes_ems_formulario_ciudad'],
            ['paquetes_ems_formulario', 'idx_paquetes_ems_formulario_destino_id'],
            ['paquetes_ems_formulario', 'idx_paquetes_ems_formulario_tarifario_id'],

            ['paquetes_int', 'idx_paquetes_int_estado_id'],
            ['paquetes_int', 'idx_paquetes_int_codigo'],
            ['paquetes_int', 'idx_paquetes_int_created_at'],
            ['paquetes_int', 'idx_paquetes_int_estado_origen'],
            ['paquetes_int', 'idx_paquetes_int_estado_destino'],

            ['solicitud_clientes', 'idx_solicitud_clientes_estado_id'],
            ['solicitud_clientes', 'idx_solicitud_clientes_origen'],
            ['solicitud_clientes', 'idx_solicitud_clientes_ciudad'],
            ['solicitud_clientes', 'idx_solicitud_clientes_barcode'],
            ['solicitud_clientes', 'idx_solicitud_clientes_cod_especial'],
            ['solicitud_clientes', 'idx_solicitud_clientes_created_at'],
            ['solicitud_clientes', 'idx_solicitud_clientes_estado_origen'],
            ['solicitud_clientes', 'idx_solicitud_clientes_estado_ciudad'],
            ['solicitud_clientes', 'idx_solicitud_clientes_destino_id'],
            ['solicitud_clientes', 'idx_solicitud_clientes_servicio_extra_id'],
        ];

        foreach ($indexes as [$table, $index]) {
            if (!Schema::hasTable($table) || !$this->indexExists($table, $index)) {
                continue;
            }

            $this->dropIndex($table, $index);
        }
    }

    private function columnsExist(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function createIndex(string $table, string $index, array $columns): void
    {
        $tableName = $this->quoteIdentifier($table);
        $indexName = $this->quoteIdentifier($index);
        $columnList = implode(', ', array_map(fn ($column) => $this->quoteIdentifier($column), $columns));

        DB::statement("CREATE INDEX {$indexName} ON {$tableName} ({$columnList})");
    }

    private function dropIndex(string $table, string $index): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS ' . $this->quoteIdentifier($index));

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('DROP INDEX ' . $this->quoteIdentifier($index) . ' ON ' . $this->quoteIdentifier($table));

            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement('DROP INDEX ' . $this->quoteIdentifier($index) . ' ON ' . $this->quoteIdentifier($table));
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'pgsql' => (bool) DB::table('pg_indexes')
                ->where('schemaname', 'public')
                ->where('tablename', $table)
                ->where('indexname', $index)
                ->value('indexname'),
            'mysql' => (bool) DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->value('index_name'),
            'sqlsrv' => (bool) DB::table('sys.indexes')
                ->where('name', $index)
                ->where('object_id', DB::raw("OBJECT_ID('{$table}')"))
                ->value('name'),
            default => false,
        };
    }

    private function quoteIdentifier(string $identifier): string
    {
        return match (DB::getDriverName()) {
            'pgsql' => '"' . str_replace('"', '""', $identifier) . '"',
            'mysql' => '`' . str_replace('`', '``', $identifier) . '`',
            'sqlsrv' => '[' . str_replace(']', ']]', $identifier) . ']',
            default => $identifier,
        };
    }
};
