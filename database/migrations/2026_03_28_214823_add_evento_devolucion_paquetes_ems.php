<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NOMBRE_EVENTO = 'Paquete enviado a devolucion.';

    public function up(): void
    {
        DB::table('eventos')->updateOrInsert(
            ['nombre_evento' => self::NOMBRE_EVENTO],
            [
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('eventos')
            ->where('nombre_evento', self::NOMBRE_EVENTO)
            ->delete();
    }
};
