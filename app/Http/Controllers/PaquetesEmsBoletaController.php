<?php

namespace App\Http\Controllers;

use App\Models\PaqueteEms;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PaquetesEmsBoletaController extends Controller
{
    public function show(PaqueteEms $paquete)
    {
        $this->authorizeAnyPermission(request(), [
            'feature.paquetes-ems.entregados.print',
        ]);

        $paquete->load(['tarifario.destino', 'tarifario.servicio', 'tarifario.origen', 'tarifario.peso', 'formulario']);

        $pdf = Pdf::loadView('paquetes_ems.boleta', [
            'paquete' => $paquete,
        ])->setPaper([0, 0, 226.77, 538.58], 'portrait');

        return $pdf->download('boleta-' . $paquete->id . '.pdf');
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
