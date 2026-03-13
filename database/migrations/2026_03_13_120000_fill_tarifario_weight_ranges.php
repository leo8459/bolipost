<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RANGES = [
        [0.001, 0.250],
        [0.251, 0.500],
        [0.501, 1.000],
        [1.001, 2.000],
        [2.001, 3.000],
        [3.001, 4.000],
        [4.001, 5.000],
        [5.001, 6.000],
        [6.001, 7.000],
        [7.001, 8.000],
        [8.001, 9.000],
        [9.001, 10.000],
        [10.001, 11.000],
        [11.001, 12.000],
        [12.001, 13.000],
        [13.001, 14.000],
        [14.001, 15.000],
        [15.001, 16.000],
        [16.001, 17.000],
        [17.001, 18.000],
        [18.001, 19.000],
        [19.001, 20.000],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::RANGES as [$pesoInicial, $pesoFinal]) {
            $exists = DB::table('peso')
                ->where('peso_inicial', $pesoInicial)
                ->where('peso_final', $pesoFinal)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('peso')->insert([
                'peso_inicial' => $pesoInicial,
                'peso_final' => $pesoFinal,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Migracion de datos no reversible de forma segura.
    }
};
