<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recojos') && !Schema::hasTable('paquetes_contrato')) {
            Schema::rename('recojos', 'paquetes_contrato');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('paquetes_contrato') && !Schema::hasTable('recojos')) {
            Schema::rename('paquetes_contrato', 'recojos');
        }
    }
};
