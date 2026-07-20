<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->changeColumnToLongText('cartero', 'imagen');
        $this->changeColumnToLongText('cartero', 'imagen_devolucion');
        $this->changeColumnToLongText('paquetes_ems', 'imagen');
        $this->changeColumnToLongText('paquetes_certi', 'imagen');
        $this->changeColumnToLongText('paquetes_ordi', 'imagen');
        $this->changeColumnToLongText('paquetes_contrato', 'imagen');
        $this->changeColumnToLongText('solicitud_clientes', 'imagen');
    }

    public function down(): void
    {
        $this->changeColumnToString('cartero', 'imagen');
        $this->changeColumnToString('cartero', 'imagen_devolucion');
        $this->changeColumnToString('paquetes_ems', 'imagen');
        $this->changeColumnToString('paquetes_certi', 'imagen');
        $this->changeColumnToString('paquetes_ordi', 'imagen');
        $this->changeColumnToString('paquetes_contrato', 'imagen');
        $this->changeColumnToString('solicitud_clientes', 'imagen');
    }

    private function changeColumnToLongText(string $tableName, string $columnName): void
    {
        if (!Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnName) {
            $table->longText($columnName)->nullable()->change();
        });
    }

    private function changeColumnToString(string $tableName, string $columnName): void
    {
        if (!Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnName) {
            $table->string($columnName)->nullable()->change();
        });
    }
};
