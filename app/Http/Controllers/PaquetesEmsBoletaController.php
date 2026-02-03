<?php

namespace App\Http\Controllers;

use App\Models\PaqueteEms;
use Barryvdh\DomPDF\Facade\Pdf;

class PaquetesEmsBoletaController extends Controller
{
    public function show(PaqueteEms $paquete)
    {
        $paquete->load(['tarifario.destino', 'tarifario.servicio', 'tarifario.origen', 'tarifario.peso']);

        $pdf = Pdf::loadView('paquetes_ems.boleta', [
            'paquete' => $paquete,
        ])->setPaper('letter', 'portrait');

        return $pdf->download('boleta-' . $paquete->id . '.pdf');
    }
}
