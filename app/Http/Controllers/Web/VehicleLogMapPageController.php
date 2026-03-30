<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\VehicleLog;
use Illuminate\Http\Request;

class VehicleLogMapPageController extends Controller
{
    public function show(Request $request, VehicleLog $vehicleLog)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion', 'conductor'], true), 403);

        if ($request->user()?->role === 'conductor') {
            $driverId = (int) ($request->user()?->resolvedDriver()?->id ?? 0);
            abort_if($driverId <= 0 || $driverId !== (int) $vehicleLog->drivers_id, 403);
        }

        $route = $this->normalizeRoutePoints(is_array($vehicleLog->ruta_json) ? $vehicleLog->ruta_json : []);

        $startLat = $this->toFloat($vehicleLog->latitud_inicio);
        $startLng = $this->toFloat($vehicleLog->logitud_inicio);
        $endLat = $this->toFloat($vehicleLog->latitud_destino);
        $endLng = $this->toFloat($vehicleLog->logitud_destino);

        if (empty($route)) {
            if (!is_null($startLat) && !is_null($startLng)) {
                $route[] = [
                    'lat' => $startLat,
                    'lng' => $startLng,
                    'address' => (string) ($vehicleLog->recorrido_inicio ?? 'Inicio'),
                    'label' => 'Inicio',
                    'is_marked' => true,
                ];
            }

            if (!is_null($endLat) && !is_null($endLng)) {
                $route[] = [
                    'lat' => $endLat,
                    'lng' => $endLng,
                    'address' => (string) ($vehicleLog->recorrido_destino ?? 'Destino'),
                    'label' => 'Destino',
                    'is_marked' => true,
                ];
            }
        }

        if ((is_null($startLat) || is_null($startLng)) && !empty($route)) {
            $startLat = $route[0]['lat'] ?? null;
            $startLng = $route[0]['lng'] ?? null;
        }

        if ((is_null($endLat) || is_null($endLng)) && !empty($route)) {
            $last = $route[count($route) - 1];
            $endLat = $last['lat'] ?? null;
            $endLng = $last['lng'] ?? null;
        }

        return view('vehicle_logs.map-view', [
            'vehicleLog' => $vehicleLog,
            'mapPayload' => [
                'vehicle' => (string) ($vehicleLog->vehicle?->placa ?? 'N/A'),
                'date' => optional($vehicleLog->fecha)->format('d/m/Y') ?? '',
                'startLat' => $startLat,
                'startLng' => $startLng,
                'startName' => (string) ($vehicleLog->recorrido_inicio ?? ''),
                'endLat' => $endLat,
                'endLng' => $endLng,
                'endName' => (string) ($vehicleLog->recorrido_destino ?? ''),
                'route' => array_values($route),
            ],
        ]);
    }

    private function normalizeRoutePoints(array $raw): array
    {
        $points = [];

        foreach ($raw as $point) {
            if (!is_array($point)) {
                continue;
            }

            $lat = $this->toFloat($point['lat'] ?? $point['latitude'] ?? null);
            $lng = $this->toFloat($point['lng'] ?? $point['longitude'] ?? null);

            if (is_null($lat) || is_null($lng)) {
                continue;
            }

            $points[] = [
                'lat' => $lat,
                'lng' => $lng,
                'address' => (string) ($point['address'] ?? ''),
                'label' => (string) ($point['label'] ?? $point['point_label'] ?? ''),
                'is_marked' => !empty($point['is_marked']) || !empty($point['marked']) || !empty($point['isMarked']),
            ];
        }

        return $points;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $float = (float) $value;

        return is_finite($float) ? $float : null;
    }
}
