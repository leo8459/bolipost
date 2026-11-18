<?php

namespace App\Http\Controllers;

use App\Exceptions\FacturacionCartRemoteException;
use App\Services\FacturaFirmaPdfService;
use App\Services\FacturacionCartService;
use App\Services\SitraIpsClient;
use Illuminate\Support\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class KardexManualDeliveryTicketController extends Controller
{
    public function __invoke(
        Request $request,
        SitraIpsClient $ips,
        FacturaFirmaPdfService $pdfService,
        FacturacionCartService $sales,
    ): JsonResponse|StreamedResponse {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:35', 'regex:/^[A-Za-z0-9-]+$/'],
            'confirmar_entrega' => ['required', 'accepted'],
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

        $packageCode = strtoupper($this->firstPackageText($package, ['codigo', 'tracking_code', 'code'], $code));
        $user = $request->user();
        $charge = $user ? $sales->findDeliveryTicketCharge(
            $user,
            $packageCode,
            $request->routeIs('mis-ventas.kardex-regional.ticket-manual'),
        ) : null;
        $weight = $charge['weight'] ?? $package['weight_kg'] ?? null;
        if (!$user) {
            return response()->json(['message' => 'Tu sesión terminó. Vuelve a ingresar y confirma la entrega nuevamente.'], 401);
        }

        try {
            $registration = $sales->registerManualDelivery(
                $user,
                $packageCode,
                is_numeric($weight) && (float) $weight >= 0 ? (float) $weight : null,
            );
        } catch (Throwable $exception) {
            report($exception);

            $apiIsNotReady = $exception instanceof FacturacionCartRemoteException
                && $exception->httpStatus() === 503;

            return response()->json([
                'message' => $apiIsNotReady
                    ? 'La API de facturación todavía no está habilitada para registrar entregas manuales. Aplica su migración y vuelve a intentar.'
                    : 'No se pudo registrar la entrega en el Kardex. Verifica la conexión e intenta nuevamente; repetir la solicitud no duplicará el registro.',
            ], $apiIsNotReady ? 503 : 502);
        }

        $cashierName = trim((string) data_get($registration, 'venta.usuario.nombre', ''));
        $ticket = [
            'packages' => [[
                'codigo' => $packageCode,
                'peso' => is_numeric($weight) && (float) $weight >= 0
                    ? number_format((float) $weight, 3, '.', '') . ' kg'
                    : '',
                'monto' => isset($charge['amount']) ? 'Bs ' . number_format($charge['amount'], 2, '.', '') : '',
            ]],
            'usuario' => $cashierName !== ''
                ? $cashierName
                : ($charge['cashier_name'] ?? trim((string) $user->name)),
            'fecha_entrega' => $this->ticketDeliveryDate($package, $registration),
        ];

        $pdf = $pdfService->manualDeliveryTicket($ticket);

        return response()->streamDownload(
            fn () => print($pdf),
            'ticket-entrega-manual-'.$code.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    private function deliveryDate(array $package): string
    {
        // IPS event 37 confirms final delivery to the recipient.
        if ((int) ($package['operational_event_cd'] ?? $package['event_cd'] ?? 0) !== 37) {
            return '';
        }

        $date = $this->firstPackageText($package, ['event_at_local', 'event_at']);
        if ($date === '') {
            return '';
        }

        try {
            return Carbon::parse($date, 'America/La_Paz')->tz('America/La_Paz')->locale('es')
                ->translatedFormat('d \\d\\e F \\d\\e Y H:i:s');
        } catch (\Carbon\Exceptions\InvalidFormatException) {
            return '';
        }
    }

    private function ticketDeliveryDate(array $package, array $registration): string
    {
        $ipsDate = $this->deliveryDate($package);
        if ($ipsDate !== '') {
            return $ipsDate;
        }

        $registeredAt = trim((string) data_get($registration, 'venta.fechaRegistro', ''));
        try {
            $date = $registeredAt !== ''
                ? Carbon::parse($registeredAt, (string) config('app.timezone', 'America/La_Paz'))
                : Carbon::now('America/La_Paz');

            return $date->tz('America/La_Paz')->locale('es')->translatedFormat('d \\d\\e F \\d\\e Y H:i:s');
        } catch (Throwable) {
            return Carbon::now('America/La_Paz')->locale('es')->translatedFormat('d \\d\\e F \\d\\e Y H:i:s');
        }
    }

    private function firstPackageText(array $package, array $paths, string $fallback = ''): string
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
