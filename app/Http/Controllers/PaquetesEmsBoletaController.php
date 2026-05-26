<?php

namespace App\Http\Controllers;

use App\Models\PaqueteEms;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PaquetesEmsBoletaController extends Controller
{
    public function show(Request $request, PaqueteEms $paquete)
    {
        $this->authorizeAnyPermission($request, [
            'feature.paquetes-ems.index.print',
            'feature.paquetes-ems.almacen.print',
            'feature.paquetes-ems.ventanilla.print',
            'feature.paquetes-ems.devolucion.print',
            'feature.paquetes-ems.recibir-regional.print',
            'feature.paquetes-ems.en-transito.print',
            'feature.paquetes-ems.entregados.print',
        ]);

        $paquete->load(['tarifario.destino', 'tarifario.servicio', 'tarifario.origen', 'tarifario.peso', 'formulario']);

        $formato = strtolower(trim((string) $request->query('formato', 'termica')));

        if ($formato === 'carta') {
            $pdf = Pdf::loadView('paquetes_ems.boleta-carta', [
                'paquete' => $paquete,
            ])->setPaper('letter', 'portrait');

            return $pdf->download('boleta-carta-' . $paquete->id . '.pdf');
        }

        $pdf = Pdf::loadView('paquetes_ems.boleta', [
            'paquete' => $paquete,
        ])->setPaper([0, 0, 226.77, 595.28], 'portrait');

        return $pdf->download('boleta-termica-' . $paquete->id . '.pdf');
    }

    private function authorizeAnyPermission(Request $request, array $permissions): void
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'No autenticado.');
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return;
            }
        }

        abort(403, 'No tienes permiso para realizar esta accion.');
    }
}
