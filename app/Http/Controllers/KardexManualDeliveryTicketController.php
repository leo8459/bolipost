<?php

namespace App\Http\Controllers;

use App\Services\FacturaFirmaPdfService;
use App\Services\FacturacionCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class KardexManualDeliveryTicketController extends Controller
{
    public function __invoke(
        Request $request,
        FacturaFirmaPdfService $pdfService,
        FacturacionCartService $sales,
    ): JsonResponse|StreamedResponse {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:35', 'regex:/^[A-Za-z0-9-]+$/'],
            'confirmar_entrega' => ['required', 'accepted'],
            'modo' => ['required', 'in:pre_entrega,entrega'],
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Tu sesión terminó. Vuelve a ingresar e intenta nuevamente.'], 401);
        }

        $code = strtoupper(trim((string) $data['codigo']));
        $mode = (string) $data['modo'];
        $isDelivery = $mode === 'entrega';
        $regional = $request->routeIs('mis-ventas.kardex-regional.ticket-manual');

        // The ticket may reuse weight/charge information already in the billing API.
        // This is a read-only lookup and never calls IPS or inspects postal events.
        try {
            $charge = $sales->findDeliveryTicketCharge($user, $code, $regional);
        } catch (Throwable) {
            $charge = null;
        }

        $registration = [];
        if ($isDelivery) {
            try {
                $registration = $sales->registerManualDelivery(
                    $user,
                    $code,
                    isset($charge['weight']) && is_numeric($charge['weight']) && (float) $charge['weight'] >= 0
                        ? (float) $charge['weight']
                        : null,
                );
            } catch (Throwable $exception) {
                report($exception);

                return response()->json([
                    'message' => 'No se pudo registrar la entrega en el Kardex. Verifica la conexión e intenta nuevamente; repetir la solicitud no duplicará el registro.',
                ], 502);
            }
        }

        $user->loadMissing('sucursal');
        $officeName = trim((string) (
            $user->sucursal?->nombre
            ?? $user->sucursal?->descripcion
            ?? $user->sucursal?->municipio
            ?? ''
        ));
        $weight = $charge['weight'] ?? null;
        $ticket = [
            'packages' => [[
                'codigo' => $code,
                'peso' => is_numeric($weight) && (float) $weight >= 0
                    ? number_format((float) $weight, 3, '.', '') . ' kg'
                    : '',
                'monto' => isset($charge['amount'])
                    ? 'Bs ' . number_format((float) $charge['amount'], 2, '.', '')
                    : '',
            ]],
            'usuario' => trim((string) $user->name),
            'oficina' => $officeName,
            'generado' => Carbon::now('America/La_Paz')->locale('es')
                ->translatedFormat('d \\d\\e F \\d\\e Y H:i:s'),
            'estado_entrega' => $isDelivery ? 'ENTREGADO' : 'PRE-ENTREGA',
            'fecha_entrega' => '',
        ];

        $pdf = $pdfService->manualDeliveryTicket($ticket);
        $alreadyRegistered = $isDelivery && (bool) data_get($registration, 'ya_registrada', false);

        return response()->streamDownload(
            fn () => print($pdf),
            'ticket-entrega-manual-'.$code.'.pdf',
            [
                'Content-Type' => 'application/pdf',
                'X-Manual-Delivery-Mode' => $mode,
                'X-Kardex-Record-Exists' => $alreadyRegistered ? 'true' : 'false',
            ]
        );
    }
}
