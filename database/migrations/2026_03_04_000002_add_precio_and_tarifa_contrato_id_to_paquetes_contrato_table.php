<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('paquetes_contrato', 'precio')) {
            Schema::table('paquetes_contrato', function (Blueprint $table) {
                $table->decimal('precio', 10, 2)->nullable()->after('peso');
            });
        }

        if (!Schema::hasColumn('paquetes_contrato', 'tarifa_contrato_id')) {
            Schema::table('paquetes_contrato', function (Blueprint $table) {
                $table->foreignId('tarifa_contrato_id')
                    ->nullable()
                    ->after('precio')
                    ->constrained('tarifa_contrato')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('paquetes_contrato', 'tarifa_contrato_id')) {
            Schema::table('paquetes_contrato', function (Blueprint $table) {
                $table->dropForeign(['tarifa_contrato_id']);
                $table->dropColumn('tarifa_contrato_id');
            });
        }

        if (Schema::hasColumn('paquetes_contrato', 'precio')) {
            Schema::table('paquetes_contrato', function (Blueprint $table) {
                $table->dropColumn('precio');
            });
        }
    }
};

