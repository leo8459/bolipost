<?php

namespace App\Http\Controllers;

use App\Services\SitraIpsClient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class KardexManualDeliveryTicketController extends Controller
{
    public function __invoke(Request $request, SitraIpsClient $ips): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:35', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);
        $code = strtoupper(trim((string) $data['codigo']));

        try {
            $payload = $ips->package($code);
            $package = (array) data_get($payload, 'package', []);
        } catch (RequestException $exception) {
            if ((int) $exception->response?->status() === 404) {
                return response()->json([
                    'message' => 'No encontramos esa guía en el sistema postal. Verifica el código e intenta nuevamente.',
                ], 404);
            }

            report($exception);

            return response()->json([
                'message' => 'No se pudo consultar el sistema postal en este momento. Intenta nuevamente.',
            ], 502);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo consultar el sistema postal en este momento. Intenta nuevamente.',
            ], 502);
        }

        if ($package === []) {
            return response()->json([
                'message' => 'No encontramos datos postales para esa guía. Verifica el código e intenta nuevamente.',
            ], 404);
        }

        $barcode = null;
        if (class_exists(\Milon\Barcode\DNS1D::class)) {
            try {
                $barcode = (new \Milon\Barcode\DNS1D())->getBarcodePNG($code, 'C128', 1.5, 38);
            } catch (Throwable) {
                $barcode = null;
            }
        }

        $ticket = [
            'codigo' => $this->firstPackageText($package, ['codigo', 'tracking_code', 'code'], $code),
            'remitente' => $this->firstPackageText($package, [
                'nombre_remitente', 'sender_name', 'sender.name', 'sender.full_name', 'remitente',
            ]),
            'destinatario' => $this->firstPackageText($package, [
                'nombre_destinatario', 'recipient_name', 'recipient.name', 'recipient.full_name', 'destinatario',
            ]),
            'servicio' => $this->firstPackageText($package, [
                'service_name', 'mail_class_name', 'mail_class', 'product_type', 'service.name', 'service',
            ]),
            'destino' => $this->firstPackageText($package, [
                'destination_city', 'destination.city', 'destination.name', 'destination_name', 'next_office_name',
            ]),
            'oficina' => $this->firstPackageText($package, ['office_name', 'office.name']),
            'estado' => $this->firstPackageText($package, [
                'stage.label', 'event_name', 'status_label', 'state_name',
            ]),
            'barcode' => $barcode,
            'generado_por' => trim((string) ($request->user()?->name ?? 'Personal de ventanilla')),
            'generado_en' => now(),
        ];

        $pdf = Pdf::loadView('facturacion.ticket-entrega-manual', compact('ticket'))
            ->setPaper([0, 0, 226.77, 680], 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'ticket-entrega-manual-'.$code.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    private function firstPackageText(array $package, array $paths, string $fallback = '—'): string
    {
        foreach ($paths as $path) {
            $value = data_get($package, $path);
            if (is_array($value)) {
                $value = $value['name'] ?? $value['full_name'] ?? $value['label'] ?? $value['description'] ?? '';
            }

            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }
}
