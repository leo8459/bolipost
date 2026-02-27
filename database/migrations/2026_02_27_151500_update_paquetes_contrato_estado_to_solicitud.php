<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('paquetes_contrato')
            ->whereRaw('trim(upper(estado)) in (?, ?)', ['CREADO', 'CREADA'])
            ->update(['estado' => 'SOLICITUD']);
    }

    public function down(): void
    {
        DB::table('paquetes_contrato')
            ->whereRaw('trim(upper(estado)) = ?', ['SOLICITUD'])
            ->update(['estado' => 'CREADO']);
    }
};
