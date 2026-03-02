<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('paquetes_contrato', 'empresa_id')) {
            Schema::table('paquetes_contrato', function (Blueprint $table) {
                $table->foreignId('empresa_id')
                    ->nullable()
                    ->constrained('empresa')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('paquetes_contrato', 'empresa_id')) {
            Schema::table('paquetes_contrato', function (Blueprint $table) {
                $table->dropForeign(['empresa_id']);
                $table->dropColumn('empresa_id');
            });
        }
    }
};

