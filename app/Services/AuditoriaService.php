<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuditoriaService
{
    public function registrar(string $tipoEvento, string $codigo, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $tipoNormalizado = strtoupper(trim($tipoEvento));
        if ($tipoNormalizado === '') {
            return;
        }

        $codigoNormalizado = trim($codigo);
        if ($codigoNormalizado === '') {
            return;
        }

        $auditoriaId = DB::table('auditoria')
            ->whereRaw('trim(upper(nombre_evento)) = ?', [$tipoNormalizado])
            ->value('id');

        if (!$auditoriaId) {
            $auditoriaId = DB::table('auditoria')->insertGetId([
                'nombre_evento' => $tipoNormalizado,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('eventos_auditoria')->insert([
            'codigo' => mb_substr($codigoNormalizado, 0, 255),
            'auditoria_id' => (int) $auditoriaId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

