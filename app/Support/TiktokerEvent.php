<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class TiktokerEvent
{
    public const SOLICITUD_REGISTRADA = 'Delivery Express registrado.';
    public const RECIBIDA_ALMACEN = 'Delivery Express recibido en almacen.';
    public const ENVIADA_SACA_INTERNA = 'Delivery Express enviado en saca interna.';
    public const RECIBIDA_TRANSITO = 'Delivery Express recibido en oficina origen de transito.';
    public const RECIBIDA_VENTANILLA = 'Delivery Express recibido en ventanilla EMS.';

    public static function resolveId(string $nombreEvento): int
    {
        $nombreEvento = trim($nombreEvento);
        if ($nombreEvento === '') {
            return 0;
        }

        $legacyNames = self::legacyNamesFor($nombreEvento);

        $existingId = (int) (DB::table('eventos')
            ->whereRaw('TRIM(UPPER(nombre_evento)) = ?', [mb_strtoupper($nombreEvento)])
            ->value('id') ?? 0);

        if ($existingId > 0) {
            return $existingId;
        }

        foreach ($legacyNames as $legacyName) {
            $legacyId = (int) (DB::table('eventos')
                ->whereRaw('TRIM(UPPER(nombre_evento)) = ?', [mb_strtoupper($legacyName)])
                ->value('id') ?? 0);

            if ($legacyId <= 0) {
                continue;
            }

            DB::table('eventos')
                ->where('id', $legacyId)
                ->update([
                    'nombre_evento' => $nombreEvento,
                    'updated_at' => now(),
                ]);

            return $legacyId;
        }

        return (int) DB::table('eventos')->insertGetId([
            'nombre_evento' => $nombreEvento,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function legacyNamesFor(string $nombreEvento): array
    {
        return match ($nombreEvento) {
            self::SOLICITUD_REGISTRADA => [
                'Solicitud TikToker registrada.',
            ],
            self::RECIBIDA_ALMACEN => [
                'Solicitud TikToker recibida en almacen.',
            ],
            self::ENVIADA_SACA_INTERNA => [
                'Solicitud TikToker enviada en saca interna.',
            ],
            self::RECIBIDA_TRANSITO => [
                'Solicitud TikToker recibida en oficina origen de transito.',
            ],
            self::RECIBIDA_VENTANILLA => [
                'Solicitud TikToker recibida en ventanilla EMS.',
            ],
            default => [],
        };
    }
}
