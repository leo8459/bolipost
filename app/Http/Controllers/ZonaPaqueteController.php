<?php

namespace App\Http\Controllers;

use App\Models\PaqueteCerti;
use App\Models\PaqueteOrdi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZonaPaqueteController extends Controller
{
    public function buscar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
        ]);

        $codigo = trim((string) $validated['codigo']);
        $codigoNorm = mb_strtoupper($codigo);

        $certi = PaqueteCerti::query()
            ->whereRaw('TRIM(UPPER(codigo)) = ?', [$codigoNorm])
            ->first(['id', 'codigo', 'zona', 'cuidad']);

        if ($certi) {
            return response()->json([
                'existe' => true,
                'codigo' => (string) $certi->codigo,
                'zona' => (string) ($certi->zona ?? ''),
                'ciudad' => (string) ($certi->cuidad ?? ''),
                'tipo_paquete' => 'CERTI',
                'tabla_origen' => 'paquetes_certi',
            ]);
        }

        $ordi = PaqueteOrdi::query()
            ->whereRaw('TRIM(UPPER(codigo)) = ?', [$codigoNorm])
            ->first(['id', 'codigo', 'zona', 'ciudad']);

        if ($ordi) {
            return response()->json([
                'existe' => true,
                'codigo' => (string) $ordi->codigo,
                'zona' => (string) ($ordi->zona ?? ''),
                'ciudad' => (string) ($ordi->ciudad ?? ''),
                'tipo_paquete' => 'ORDI',
                'tabla_origen' => 'paquetes_ordi',
            ]);
        }

        return response()->json([
            'existe' => false,
            'codigo' => $codigo,
            'message' => 'No se encontro el codigo en paquetes certificados u ordinarios.',
        ], 404);
    }
}

