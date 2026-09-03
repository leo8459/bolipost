<?php

namespace App\Http\Controllers;

use App\Models\PaqueteEms;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

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
            'paquetes-ems.encargado',
            'feature.paquetes-ems.encargado.print',
        ]);

        $paquete->load(['tarifario.destino', 'tarifario.servicio', 'tarifario.origen', 'tarifario.peso', 'formulario']);

        $formato = strtolower(trim((string) $request->query('formato', 'termica')));

        if ($formato === 'carta') {
            $pdf = Pdf::loadView('paquetes_ems.boleta-carta', [
                'paquete' => $paquete,
                'verificationUrl' => $this->verificationUrlFor($paquete),
            ])->setPaper('letter', 'portrait');

            return $pdf->download('boleta-carta-'.$paquete->id.'.pdf');
        }

        $pdf = Pdf::loadView('paquetes_ems.boleta', [
            'paquete' => $paquete,
            'verificationUrl' => $this->verificationUrlFor($paquete),
        ])->setPaper([0, 0, 226.77, 595.28], 'portrait');

        return $pdf->download('boleta-termica-'.$paquete->id.'.pdf');
    }

    public function verify(Request $request)
    {
        $paquete = $this->paqueteFromVerificationRequest($request);
        $paquete->load(['tarifario.destino', 'tarifario.servicio', 'tarifario.origen', 'tarifario.peso', 'formulario']);

        return view('paquetes_ems.verificacion', [
            'paquete' => $paquete,
            'reimprimirUrl' => $this->verificationPdfUrlFor($paquete),
            'rastrearUrl' => URL::signedRoute('tracking.demo.signed', [
                'codigo' => $paquete->codigo,
            ]),
        ]);
    }

    public function verifyPdf(Request $request)
    {
        $paquete = $this->paqueteFromVerificationRequest($request);
        $paquete->load(['tarifario.destino', 'tarifario.servicio', 'tarifario.origen', 'tarifario.peso', 'formulario']);

        $pdf = Pdf::loadView('paquetes_ems.boleta', [
            'paquete' => $paquete,
            'verificationUrl' => $this->verificationUrlFor($paquete),
        ])->setPaper([0, 0, 226.77, 595.28], 'portrait');

        return $pdf->stream('guia-ems-verificacion-'.$paquete->codigo.'.pdf');
    }

    private function verificationUrlFor(PaqueteEms $paquete): string
    {
        return route('paquetes-ems.verificar-guia', [
            't' => $this->verificationTokenFor($paquete),
        ]);
    }

    private function verificationPdfUrlFor(PaqueteEms $paquete): string
    {
        return route('paquetes-ems.verificar-guia.pdf', [
            't' => $this->verificationTokenFor($paquete),
        ]);
    }

    private function verificationTokenFor(PaqueteEms $paquete): string
    {
        return Crypt::encryptString((string) $paquete->getKey());
    }

    private function paqueteFromVerificationRequest(Request $request): PaqueteEms
    {
        $token = trim((string) $request->query('t', ''));
        abort_if($token === '', 404);

        try {
            $id = Crypt::decryptString($token);
        } catch (\Throwable $e) {
            abort(404);
        }

        abort_unless(ctype_digit((string) $id), 404);

        return PaqueteEms::query()->findOrFail((int) $id);
    }

    private function authorizeAnyPermission(Request $request, array $permissions): void
    {
        $user = $request->user();

        if (! $user) {
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
