<?php

namespace App\Http\Controllers;

use App\Services\FacturaFirmaPdfService;
use App\Services\FacturacionCartService;
use App\Services\SitraIpsClient;
use Illuminate\Http\Client\RequestException;
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
        SitraIpsClient $ips,
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

        try {
            $ipsResult = $ips->package($code);
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                return response()->json([
                    'message' => 'La guía no aparece en IPS. Verifica el código antes de generar el ticket.',
                ], 404);
            }

            report($exception);

            return response()->json([
                'message' => 'No se pudo consultar el peso en IPS. Intenta nuevamente; todavía no se registró la entrega.',
            ], 503);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo consultar el peso en IPS. Intenta nuevamente; todavía no se registró la entrega.',
            ], 503);
        }

        $ipsPackage = data_get($ipsResult, 'package', []);
        if (! is_array($ipsPackage) || $ipsPackage === []) {
            return response()->json([
                'message' => 'IPS no devolvió los datos de esta guía. Verifica el código e intenta nuevamente.',
            ], 404);
        }

        $weight = data_get($ipsPackage, 'weight_kg', data_get($ipsPackage, 'peso_kg'));
        if (! is_numeric($weight) || (float) $weight < 0) {
            return response()->json([
                'message' => 'IPS no tiene un peso válido registrado para esta guía. No se generó el ticket ni se registró la entrega.',
            ], 409);
        }
        $weight = round((float) $weight, 3);

        // The optional amount is read from billing history, but the weight always comes from IPS.
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
                    $weight,
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
