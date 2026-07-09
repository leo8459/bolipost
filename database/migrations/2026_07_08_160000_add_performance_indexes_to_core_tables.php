<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = [
            ['users', 'idx_users_empresa_id', ['empresa_id']],
            ['users', 'idx_users_sucursal_id', ['sucursal_id']],
            ['users', 'idx_users_ciudad', ['ciudad']],
            ['users', 'idx_users_deleted_at', ['deleted_at']],

            ['paquetes_contrato', 'idx_paquetes_contrato_user_id', ['user_id']],
            ['paquetes_contrato', 'idx_paquetes_contrato_empresa_id', ['empresa_id']],
            ['paquetes_contrato', 'idx_paquetes_contrato_estados_id', ['estados_id']],
            ['paquetes_contrato', 'idx_paquetes_contrato_fecha_recojo', ['fecha_recojo']],
            ['paquetes_contrato', 'idx_paquetes_contrato_created_at', ['created_at']],
            ['paquetes_contrato', 'idx_paquetes_contrato_origen', ['origen']],
            ['paquetes_contrato', 'idx_paquetes_contrato_destino', ['destino']],
            ['paquetes_contrato', 'idx_paquetes_contrato_cod_especial', ['cod_especial']],
            ['paquetes_contrato', 'idx_paquetes_contrato_estado_origen', ['estados_id', 'origen']],
            ['paquetes_contrato', 'idx_paquetes_contrato_empresa_created', ['empresa_id', 'created_at']],

            ['paquetes_ems', 'idx_paquetes_ems_estado_id', ['estado_id']],
            ['paquetes_ems', 'idx_paquetes_ems_created_at', ['created_at']],
            ['paquetes_ems', 'idx_paquetes_ems_ciudad', ['ciudad']],
            ['paquetes_ems', 'idx_paquetes_ems_origen', ['origen']],
            ['paquetes_ems', 'idx_paquetes_ems_cod_especial', ['cod_especial']],
            ['paquetes_ems', 'idx_paquetes_ems_estado_ciudad', ['estado_id', 'ciudad']],

            ['paquetes_certi', 'idx_paquetes_certi_fk_estado', ['fk_estado']],
            ['paquetes_certi', 'idx_paquetes_certi_fk_ventanilla', ['fk_ventanilla']],
            ['paquetes_certi', 'idx_paquetes_certi_created_at', ['created_at']],
            ['paquetes_certi', 'idx_paquetes_certi_cuidad', ['cuidad']],
            ['paquetes_certi', 'idx_paquetes_certi_cod_especial', ['cod_especial']],

            ['paquetes_ordi', 'idx_paquetes_ordi_fk_estado', ['fk_estado']],
            ['paquetes_ordi', 'idx_paquetes_ordi_fk_ventanilla', ['fk_ventanilla']],
            ['paquetes_ordi', 'idx_paquetes_ordi_created_at', ['created_at']],
            ['paquetes_ordi', 'idx_paquetes_ordi_ciudad', ['ciudad']],
            ['paquetes_ordi', 'idx_paquetes_ordi_cod_especial', ['cod_especial']],

            ['cartero', 'idx_cartero_id_user', ['id_user']],
            ['cartero', 'idx_cartero_id_estados', ['id_estados']],
            ['cartero', 'idx_cartero_created_at', ['created_at']],
            ['cartero', 'idx_cartero_user_estado', ['id_user', 'id_estados']],

            ['bitacoras', 'idx_bitacoras_user_id', ['user_id']],
            ['bitacoras', 'idx_bitacoras_paquetes_ems_id', ['paquetes_ems_id']],
            ['bitacoras', 'idx_bitacoras_paquetes_contrato_id', ['paquetes_contrato_id']],
            ['bitacoras', 'idx_bitacoras_created_at', ['created_at']],
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
            ['users', 'idx_users_empresa_id'],
            ['users', 'idx_users_sucursal_id'],
            ['users', 'idx_users_ciudad'],
            ['users', 'idx_users_deleted_at'],

            ['paquetes_contrato', 'idx_paquetes_contrato_user_id'],
            ['paquetes_contrato', 'idx_paquetes_contrato_empresa_id'],
            ['paquetes_contrato', 'idx_paquetes_contrato_estados_id'],
            ['paquetes_contrato', 'idx_paquetes_contrato_fecha_recojo'],
            ['paquetes_contrato', 'idx_paquetes_contrato_created_at'],
            ['paquetes_contrato', 'idx_paquetes_contrato_origen'],
            ['paquetes_contrato', 'idx_paquetes_contrato_destino'],
            ['paquetes_contrato', 'idx_paquetes_contrato_cod_especial'],
            ['paquetes_contrato', 'idx_paquetes_contrato_estado_origen'],
            ['paquetes_contrato', 'idx_paquetes_contrato_empresa_created'],

            ['paquetes_ems', 'idx_paquetes_ems_estado_id'],
            ['paquetes_ems', 'idx_paquetes_ems_created_at'],
            ['paquetes_ems', 'idx_paquetes_ems_ciudad'],
            ['paquetes_ems', 'idx_paquetes_ems_origen'],
            ['paquetes_ems', 'idx_paquetes_ems_cod_especial'],
            ['paquetes_ems', 'idx_paquetes_ems_estado_ciudad'],

            ['paquetes_certi', 'idx_paquetes_certi_fk_estado'],
            ['paquetes_certi', 'idx_paquetes_certi_fk_ventanilla'],
            ['paquetes_certi', 'idx_paquetes_certi_created_at'],
            ['paquetes_certi', 'idx_paquetes_certi_cuidad'],
            ['paquetes_certi', 'idx_paquetes_certi_cod_especial'],

            ['paquetes_ordi', 'idx_paquetes_ordi_fk_estado'],
            ['paquetes_ordi', 'idx_paquetes_ordi_fk_ventanilla'],
            ['paquetes_ordi', 'idx_paquetes_ordi_created_at'],
            ['paquetes_ordi', 'idx_paquetes_ordi_ciudad'],
            ['paquetes_ordi', 'idx_paquetes_ordi_cod_especial'],

            ['cartero', 'idx_cartero_id_user'],
            ['cartero', 'idx_cartero_id_estados'],
            ['cartero', 'idx_cartero_created_at'],
            ['cartero', 'idx_cartero_user_estado'],

            ['bitacoras', 'idx_bitacoras_user_id'],
            ['bitacoras', 'idx_bitacoras_paquetes_ems_id'],
            ['bitacoras', 'idx_bitacoras_paquetes_contrato_id'],
            ['bitacoras', 'idx_bitacoras_created_at'],
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
        $driver = DB::getDriverName();
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
