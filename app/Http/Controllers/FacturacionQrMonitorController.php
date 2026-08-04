<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class FacturacionQrMonitorController extends Controller
{
    public function signedUrl(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para usar el monitor QR.');

        $monitorKey = 'user-' . (string) $user->getAuthIdentifier();
        $signedUrl = URL::temporarySignedRoute(
            'facturacion.monitor.display',
            now()->addDays(30),
            ['monitor' => $monitorKey]
        );

        return response()->json([
            'ok' => true,
            'url' => $signedUrl,
            'monitor_key' => $monitorKey,
            'expires_at' => now()->addDays(30)->toIso8601String(),
        ]);
    }

    public function display(Request $request, string $monitor): View
    {
        return view('facturacion.monitor-qr', [
            'monitorKey' => $monitor,
            'signedUrl' => $request->fullUrl(),
        ]);
    }
}
