<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = [
        'cartero' => ['imagen', 'imagen_devolucion'],
        'paquetes_ems' => ['imagen'],
        'paquetes_certi' => ['imagen'],
        'paquetes_ordi' => ['imagen'],
        'paquetes_contrato' => ['imagen'],
        'recojos' => ['imagen'],
        'solicitud_clientes' => ['imagen'],
        'bastion_ems' => ['imagen'],
        'bastion_certi' => ['imagen'],
        'bastion_ordi' => ['imagen'],
        'bastion_contratos' => ['imagen'],
    ];

    public function up(): void
    {
        $driver = DB::getDriverName();

        foreach ($this->columns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                if ($driver === 'pgsql') {
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE text");
                } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                    DB::statement("ALTER TABLE {$table} MODIFY {$column} LONGTEXT NULL");
                }
            }
        }
    }

    public function down(): void
    {
        // No se reduce de text a string para no truncar imagenes base64 ya guardadas.
    }
};
