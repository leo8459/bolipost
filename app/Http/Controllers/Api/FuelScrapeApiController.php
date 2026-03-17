<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\FuelLogController as WebFuelLogController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\GasStation;

class FuelScrapeApiController extends Controller
{
    public function scrapeFromQr(Request $request)
    {
        $response = app(WebFuelLogController::class)->scrapeFromQr($request);

        if (!$response instanceof JsonResponse) {
            return response()->json([
                'success' => false,
                'message' => 'Respuesta no valida del scraper.',
            ], 422);
        }

        $payload = $response->getData(true);
        if (!is_array($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'Respuesta no valida del scraper.',
            ], 422);
        }

        $normalized = $this->normalizeScrapePayload($payload);
        if ($normalized !== null && $this->hasMeaningfulInvoiceData($normalized)) {
            return response()->json([
                'success' => true,
                'message' => 'Datos de factura normalizados para movil.',
                'data' => $normalized,
            ]);
        }

        return response()->json($payload, $response->getStatusCode());
    }

    private function normalizeScrapePayload(array $payload): ?array
    {
        $rawData = $payload['data'] ?? $payload;
        if (is_array($rawData) && !empty($rawData['numero_factura'])) {
            return $this->normalizeStructuredData($rawData);
        }

        $snapshot = $payload['snapshot'] ?? null;
        if (!is_string($snapshot) || trim($snapshot) === '') {
            $components = $payload['components'] ?? [];
            if (is_array($components) && isset($components[0]['snapshot']) && is_string($components[0]['snapshot'])) {
                $snapshot = $components[0]['snapshot'];
            }
        }

        if (!is_string($snapshot) || trim($snapshot) === '') {
            return null;
        }

        $decodedSnapshot = json_decode($snapshot, true);
        if (!is_array($decodedSnapshot)) {
            return null;
        }

        $data = $decodedSnapshot['data'] ?? [];
        if (!is_array($data)) {
            return null;
        }

        $numeroFactura = trim((string) ($data['numero_factura'] ?? ''));
        if ($numeroFactura === '') {
            return null;
        }

        return $this->normalizeStructuredData($data);
    }

    private function normalizeStructuredData(array $data): array
    {
        $firstDetail = null;
        if (!empty($data['details']) && is_array($data['details']) && isset($data['details'][0]) && is_array($data['details'][0])) {
            $firstDetail = $data['details'][0];
        }

        $gasStation = is_array($data['gas_station'] ?? null) ? $data['gas_station'] : [];
        $gasStationId = (int) ($data['gas_station_id'] ?? 0);
        $resolvedStation = null;
        if ($gasStationId > 0) {
            $resolvedStation = GasStation::query()->find($gasStationId);
        }

        return [
            'numero_factura' => (string) ($data['numero_factura'] ?? $data['numeroFactura'] ?? ''),
            'nombre_cliente' => (string) ($data['nombre_cliente'] ?? $data['nombreRazonSocialReceptor'] ?? $data['nombreCliente'] ?? $data['customer_name'] ?? ''),
            'fecha_emision' => (string) ($data['fecha_emision'] ?? $data['fechaEmision'] ?? $data['issue_date'] ?? ''),
            'monto_total' => $data['monto_total'] ?? $data['montoTotal'] ?? $data['total'] ?? $data['total_calculado'] ?? null,
            'cantidad' => $data['cantidad_combustible']
                ?? $data['galones']
                ?? $data['litros']
                ?? $data['volumen']
                ?? $data['cantidad']
                ?? ($firstDetail['cantidad'] ?? $firstDetail['cantidadProducto'] ?? $firstDetail['litros'] ?? $firstDetail['galones'] ?? null),
            'precio_unitario' => $data['precio_galon']
                ?? $data['precio_unitario']
                ?? $data['precioUnitario']
                ?? $data['precio']
                ?? ($firstDetail['precio_unitario'] ?? $firstDetail['precioUnitario'] ?? $firstDetail['precio'] ?? null),
            'gas_station' => [
                'nit_emisor' => (string) ($gasStation['nit_emisor'] ?? $data['nit_emisor'] ?? $data['nitEmisor'] ?? $resolvedStation?->nit_emisor ?? ''),
                'razon_social' => (string) ($gasStation['razon_social'] ?? $data['razon_social_emisor'] ?? $data['razonSocialEmisor'] ?? $resolvedStation?->razon_social ?? $resolvedStation?->nombre ?? ''),
                'direccion' => (string) ($gasStation['direccion'] ?? $data['direccion_emisor'] ?? $data['direccion'] ?? $resolvedStation?->direccion ?? ''),
            ],
            'qr_url' => (string) ($data['qr_url'] ?? ''),
        ];
    }

    private function hasMeaningfulInvoiceData(array $data): bool
    {
        $station = is_array($data['gas_station'] ?? null) ? $data['gas_station'] : [];

        return !empty($data['cantidad'])
            || !empty($data['monto_total'])
            || !empty($data['precio_unitario'])
            || !empty($data['fecha_emision'])
            || !empty($data['nombre_cliente'])
            || !empty($station['nit_emisor'])
            || !empty($station['razon_social'])
            || !empty($station['direccion']);
    }
}
