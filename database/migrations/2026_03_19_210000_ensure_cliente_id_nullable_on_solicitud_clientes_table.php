<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('solicitud_clientes') || !Schema::hasColumn('solicitud_clientes', 'cliente_id')) {
            return;
        }

        DB::statement('ALTER TABLE solicitud_clientes ALTER COLUMN cliente_id DROP NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('solicitud_clientes') || !Schema::hasColumn('solicitud_clientes', 'cliente_id')) {
            return;
        }

        DB::statement('ALTER TABLE solicitud_clientes ALTER COLUMN cliente_id SET NOT NULL');
    }
};
