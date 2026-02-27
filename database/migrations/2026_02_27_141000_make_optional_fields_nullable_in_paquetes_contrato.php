<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE paquetes_contrato ALTER COLUMN telefono_d DROP NOT NULL');
        DB::statement('ALTER TABLE paquetes_contrato ALTER COLUMN provincia DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE paquetes_contrato SET telefono_d = '' WHERE telefono_d IS NULL");
        DB::statement("UPDATE paquetes_contrato SET provincia = '' WHERE provincia IS NULL");
        DB::statement('ALTER TABLE paquetes_contrato ALTER COLUMN telefono_d SET NOT NULL');
        DB::statement('ALTER TABLE paquetes_contrato ALTER COLUMN provincia SET NOT NULL');
    }
};
