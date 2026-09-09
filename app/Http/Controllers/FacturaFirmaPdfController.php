<?php

namespace App\Http\Controllers;

use App\Services\FacturaFirmaPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FacturaFirmaPdfController extends Controller
{
    public function __invoke(Request $request, FacturaFirmaPdfService $pdfService)
    {
        $data = $request->validate(['url' => ['required', 'url', 'max:2048']]);
        $url = $data['url'];
        $base = rtrim((string) config('services.facturacion_bridge.sefe_public_base_url'), '/');
        abort_unless(str_starts_with($url, $base . '/public/facturas_pdf/'), 403);

        try {
            $response = Http::connectTimeout(10)->timeout(45)
                ->withoutRedirecting()->accept('application/pdf')->get($url);
            if (!$response->successful() || !str_starts_with($response->body(), '%PDF-')) {
                throw new \RuntimeException('No se recibio el PDF de la factura.');
            }
            $content = $pdfService->appendSignatureFields($response->body());
        } catch (\Throwable $exception) {
            report($exception);
            abort(502, 'No se pudo preparar la factura con los campos de firma. Intenta descargarla nuevamente.');
        }

        $filename = basename((string) parse_url($url, PHP_URL_PATH));

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '', $filename) . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
