<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tarifario_tiktoker')->update([
            'peso1' => 20.00,
            'peso2' => null,
            'peso3' => null,
            'peso_extra' => null,
        ]);

        $origenes = DB::table('origen')
            ->get(['id', 'nombre_origen'])
            ->keyBy(fn ($origen) => strtoupper(trim((string) $origen->nombre_origen)));

        $destinos = DB::table('destino')->get(['id', 'nombre_destino']);

        foreach ($destinos as $destino) {
            $nombre = strtoupper(trim((string) $destino->nombre_destino));
            $origen = $origenes->get($nombre);

            if (! $origen) {
                continue;
            }

            DB::table('tarifario_tiktoker')
                ->where('origen_id', (int) $origen->id)
                ->where('destino_id', (int) $destino->id)
                ->update(['peso1' => 15.00]);
        }
    }

    public function down(): void
    {
        DB::table('tarifario_tiktoker')->update(['peso1' => 20.00]);
    }
};
