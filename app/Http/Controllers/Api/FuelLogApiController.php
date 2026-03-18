<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\FuelInvoice;
use App\Models\FuelInvoiceDetail;
use App\Models\FuelLog;
use App\Models\GasStation;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class FuelLogApiController extends Controller
{
    public function index(Request $request)
    {
        $query = FuelLog::query()
            ->with(['vehicleLog.vehicle', 'vehicleLog.driver', 'gasStation', 'invoice']);

        if (Schema::hasColumn('fuel_invoice_details', 'fecha_emision')) {
            $query->orderByDesc('fecha_emision');
        } elseif (Schema::hasColumn('fuel_invoice_details', 'created_at')) {
            $query->orderByDesc('created_at');
        }

        $query->orderByDesc('id');

        if ($request->filled('vehicle_id')) {
            $vehicleId = (int) $request->integer('vehicle_id');
            if (Schema::hasColumn('fuel_invoice_details', 'vehicle_id')) {
                $query->where('vehicle_id', $vehicleId);
            } else {
                $query->whereHas('vehicleLog', fn ($q) => $q->where('vehicles_id', $vehicleId));
            }
        }

        $this->applyOwnershipScope($query, $request);

        if ($request->filled('date_from')) {
            $query->whereDate('fecha_emision', '>=', (string) $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('fecha_emision', '<=', (string) $request->input('date_to'));
        }

        $logs = $query->paginate(20);
        $logs->setCollection(
            $logs->getCollection()->map(fn (FuelLog $log) => $this->toMobileFuelLog($log))
        );

        return response()->json($logs);
    }

    public function show(Request $request, FuelLog $fuelLog)
    {
        if (!$this->canViewFuelLog($fuelLog, $request)) {
            abort(403);
        }

        $fuelLog->load(['vehicleLog.vehicle', 'vehicleLog.driver', 'gasStation', 'invoice']);

        return response()->json($this->toMobileFuelLog($fuelLog));
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'station' => 'nullable|string|max:255',
            'liters' => 'nullable|numeric',
            'total_amount' => 'nullable|numeric',
            'date_time' => 'nullable',
            'invoice_number' => 'nullable|string|max:80',
            'customer_name' => 'nullable|string|max:255',
            'qr_payload' => 'nullable|string|max:5000',
            'station_nit' => 'nullable|string|max:50',
            'station_razon_social' => 'nullable|string|max:255',
            'station_address' => 'nullable|string|max:255',
            'scraped_payload' => 'nullable|array',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'driver_id' => 'nullable|integer|min:1',
            'drivers_id' => 'nullable|integer|min:1',
            'latitude' => 'nullable|numeric|between:-90,90',
            'lat' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'lng' => 'nullable|numeric|between:-180,180',
            'location_label' => 'nullable|string|max:255',
            'point_timestamp' => 'nullable',
        ]);

        $payload = $this->enrichPayloadFromQr($payload);
        $payload['station'] = (string) ($payload['station'] ?? $payload['station_razon_social'] ?? 'ESTACION NO IDENTIFICADA');
        $payload['date_time'] = (string) ($payload['date_time'] ?? now()->format('Y-m-d H:i:s'));
        $payload['total_amount'] = is_numeric($payload['total_amount'] ?? null) ? (float) $payload['total_amount'] : 0.0;
        $payload['liters'] = is_numeric($payload['liters'] ?? null) ? (float) $payload['liters'] : 0.0;

        $payload = validator($payload, [
            'station' => 'required|string|max:255',
            'liters' => 'required|numeric|min:0.00001',
            'total_amount' => 'required|numeric|min:0.01',
            'date_time' => 'required|date',
            'invoice_number' => 'nullable|string|max:80',
            'customer_name' => 'nullable|string|max:255',
            'qr_payload' => 'nullable|string|max:5000',
            'station_nit' => 'nullable|string|max:50',
            'station_razon_social' => 'nullable|string|max:255',
            'station_address' => 'nullable|string|max:255',
            'scraped_payload' => 'nullable|array',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'driver_id' => 'nullable|integer|min:1',
            'drivers_id' => 'nullable|integer|min:1',
            'latitude' => 'nullable|numeric|between:-90,90',
            'lat' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'lng' => 'nullable|numeric|between:-180,180',
            'location_label' => 'nullable|string|max:255',
            'point_timestamp' => 'nullable',
        ])->validate();

        $vehicleId = !empty($payload['vehicle_id']) ? (int) $payload['vehicle_id'] : null;
        $candidateDriverId = $payload['driver_id'] ?? $payload['drivers_id'] ?? null;
        $resolvedDriverId = $this->resolveDriverId($request, $candidateDriverId, $vehicleId);
        $location = $this->resolveFuelLocation($payload, $vehicleId);
        $this->ensureVehicleIsInRoute($vehicleId, $location['status'] ?? null);

        $result = DB::transaction(function () use ($payload, $resolvedDriverId, $location) {
            $station = $this->resolveOrCreateGasStation($payload);

            $dateTime = \Carbon\Carbon::parse((string) $payload['date_time']);
            $liters = (float) $payload['liters'];
            $total = (float) $payload['total_amount'];
            $unitPrice = $liters > 0 ? round($total / $liters, 2) : $total;
            $invoiceNumber = (string) ($payload['invoice_number'] ?? ('MOBILE-' . now()->format('Ymd-His')));
            $customerName = (string) ($payload['customer_name'] ?? 'CONSUMO MOVIL');

            $invoicePayload = [
                'numero_factura' => $invoiceNumber,
                'fecha_emision' => $dateTime->format('Y-m-d H:i'),
                'nombre_cliente' => $customerName,
                'monto_total' => $total,
            ];
            if (Schema::hasColumn('fuel_invoices', 'numero')) {
                $invoicePayload['numero'] = $invoiceNumber;
            }
            if (Schema::hasColumn('fuel_invoices', 'gas_station_id')) {
                $invoicePayload['gas_station_id'] = $station->id ?? null;
            }

            $invoice = $this->resolveOrCreateInvoice($invoicePayload, $invoiceNumber);

            $detail = FuelLog::query()
                ->where('fuel_invoice_id', $invoice->id)
                ->orderByDesc('id')
                ->first();

            $legacyFuelLogId = null;
            if (!$detail && Schema::hasColumn('fuel_invoice_details', 'fuel_log_id')) {
                $legacyFuelLogId = $this->createLegacyFuelLogIfAvailable($payload, $dateTime, $liters, $unitPrice, $total);
            }

            $detailPayload = [
                'fuel_invoice_id' => $invoice->id,
                'cantidad' => $liters,
                'precio_unitario' => $unitPrice,
                'subtotal' => $total,
            ];
            if (Schema::hasColumn('fuel_invoice_details', 'fuel_log_id') && $legacyFuelLogId) {
                $detailPayload['fuel_log_id'] = $legacyFuelLogId;
            }

            if (Schema::hasColumn('fuel_invoice_details', 'gas_station_id')) {
                $detailPayload['gas_station_id'] = $station->id ?? null;
            }
            if (Schema::hasColumn('fuel_invoice_details', 'fecha_emision')) {
                $detailPayload['fecha_emision'] = $dateTime->format('Y-m-d H:i:s');
            }
            if (Schema::hasColumn('fuel_invoice_details', 'vehicle_id') && !empty($payload['vehicle_id'])) {
                $detailPayload['vehicle_id'] = (int) $payload['vehicle_id'];
            }
            if (Schema::hasColumn('fuel_invoice_details', 'driver_id') && !empty($resolvedDriverId)) {
                $detailPayload['driver_id'] = (int) $resolvedDriverId;
            }
            if (Schema::hasColumn('fuel_invoice_details', 'observaciones') && !empty($payload['qr_payload'])) {
                $obs = 'QR: ' . mb_substr((string) $payload['qr_payload'], 0, 1200);
                if (!empty($payload['scraped_payload']) && is_array($payload['scraped_payload'])) {
                    $obs .= ' | SCRAPED: ' . mb_substr(json_encode($payload['scraped_payload'], JSON_UNESCAPED_UNICODE) ?: '', 0, 3200);
                }
                $detailPayload['observaciones'] = $obs;
            }

            if ($detail) {
                $detail->fill($detailPayload)->save();
            } else {
                $detail = FuelLog::create($detailPayload);
            }

            if (!empty($payload['vehicle_id'])) {
                $locationLabel = $this->normalizeLocationLabel(
                    (string) ($payload['location_label'] ?? ''),
                    $location['label'] ?? null
                );
                $locationLat = $this->toNullableFloat($location['lat'] ?? null);
                $locationLng = $this->toNullableFloat($location['lng'] ?? null);
                $pointTimestamp = (string) ($payload['point_timestamp'] ?? $dateTime->toIso8601String());
                $vehicleLogPayload = [
                    'drivers_id' => !empty($resolvedDriverId) ? (int) $resolvedDriverId : null,
                    'vehicles_id' => (int) $payload['vehicle_id'],
                    'fuel_log_id' => $detail->id,
                    'fecha' => $dateTime->toDateString(),
                    'kilometraje_salida' => 0,
                    'kilometraje_llegada' => 0,
                    'recorrido_inicio' => $locationLabel,
                    'recorrido_destino' => $locationLabel,
                    'abastecimiento_combustible' => true,
                ];
                if (Schema::hasColumn('vehicle_log', 'firma_digital')) {
                    $vehicleLogPayload['firma_digital'] = null;
                }
                if (Schema::hasColumn('vehicle_log', 'latitud_inicio') && !is_null($locationLat)) {
                    $vehicleLogPayload['latitud_inicio'] = $locationLat;
                }
                if (Schema::hasColumn('vehicle_log', 'logitud_inicio') && !is_null($locationLng)) {
                    $vehicleLogPayload['logitud_inicio'] = $locationLng;
                }
                if (Schema::hasColumn('vehicle_log', 'latitud_destino') && !is_null($locationLat)) {
                    $vehicleLogPayload['latitud_destino'] = $locationLat;
                }
                if (Schema::hasColumn('vehicle_log', 'logitud_destino') && !is_null($locationLng)) {
                    $vehicleLogPayload['logitud_destino'] = $locationLng;
                }
                if (Schema::hasColumn('vehicle_log', 'ruta_json')) {
                    $vehicleLogPayload['ruta_json'] = [[
                        'lat' => $locationLat,
                        'lng' => $locationLng,
                        't' => $pointTimestamp,
                        'address' => $locationLabel,
                        'label' => 'Punto final de bitacora',
                        'is_marked' => true,
                        'point_type' => 'bitacora_final_fuel',
                        'status' => 'EN_RUTA',
                        'index' => 0,
                    ]];
                }

                $existingVehicleLog = VehicleLog::query()
                    ->where('fuel_log_id', $detail->id)
                    ->latest('id')
                    ->first();

                if ($existingVehicleLog) {
                    $existingVehicleLog->fill($vehicleLogPayload)->save();
                } else {
                    VehicleLog::create($vehicleLogPayload);
                }
            }

            return $this->toMobileFuelLog($detail->load(['vehicleLog.vehicle', 'vehicleLog.driver', 'gasStation', 'invoice']));
        });

        return response()->json($result, 201);
    }

    private function createLegacyFuelLogIfAvailable(array $payload, \Carbon\Carbon $dateTime, float $liters, float $unitPrice, float $total): ?int
    {
        if (!Schema::hasTable('fuel_logs')) {
            return null;
        }

        $legacyPayload = [];
        if (Schema::hasColumn('fuel_logs', 'fecha')) {
            $legacyPayload['fecha'] = $dateTime->format('Y-m-d H:i:s');
        }
        if (Schema::hasColumn('fuel_logs', 'galones')) {
            $legacyPayload['galones'] = $liters;
        }
        if (Schema::hasColumn('fuel_logs', 'precio_galon')) {
            $legacyPayload['precio_galon'] = $unitPrice;
        }
        if (Schema::hasColumn('fuel_logs', 'total_calculado')) {
            $legacyPayload['total_calculado'] = $total;
        }
        if (Schema::hasColumn('fuel_logs', 'kilometraje')) {
            $legacyPayload['kilometraje'] = 0;
        }
        if (Schema::hasColumn('fuel_logs', 'recibo')) {
            $legacyPayload['recibo'] = (string) ($payload['invoice_number'] ?? '');
        }
        if (Schema::hasColumn('fuel_logs', 'observaciones')) {
            $legacyPayload['observaciones'] = (string) ($payload['station'] ?? $payload['station_razon_social'] ?? 'REGISTRO MOVIL');
        }

        if (empty($legacyPayload)) {
            return null;
        }

        $id = DB::table('fuel_logs')->insertGetId($legacyPayload);

        return $id ? (int) $id : null;
    }

    private function resolveOrCreateInvoice(array $invoicePayload, string $invoiceNumber): FuelInvoice
    {
        $invoice = null;

        if (Schema::hasColumn('fuel_invoices', 'numero') && $invoiceNumber !== '') {
            $invoice = FuelInvoice::query()->where('numero', $invoiceNumber)->first();
        }

        if (!$invoice && !empty($invoicePayload['numero_factura'])) {
            $invoice = FuelInvoice::query()
                ->where('numero_factura', (string) $invoicePayload['numero_factura'])
                ->first();
        }

        if ($invoice) {
            $invoice->fill($invoicePayload)->save();
            return $invoice;
        }

        return FuelInvoice::create($invoicePayload);
    }

    public function byVehicle(Vehicle $vehicle)
    {
        $query = FuelLog::query();
        if (Schema::hasColumn('fuel_invoice_details', 'vehicle_id')) {
            $query->where('vehicle_id', (int) $vehicle->id);
        } else {
            $query->whereHas('vehicleLog', fn ($q) => $q->where('vehicles_id', (int) $vehicle->id));
        }

        $this->applyOwnershipScope($query, request());

        $logs = $query
            ->with(['gasStation', 'invoice'])
            ->when(
                Schema::hasColumn('fuel_invoice_details', 'fecha_emision'),
                fn ($q) => $q->orderByDesc('fecha_emision'),
                fn ($q) => Schema::hasColumn('fuel_invoice_details', 'created_at')
                    ? $q->orderByDesc('created_at')
                    : $q
            )
            ->orderByDesc('id')
            ->paginate(20);

        $logs->setCollection(
            $logs->getCollection()->map(fn (FuelLog $log) => $this->toMobileFuelLog($log))
        );

        return response()->json($logs);
    }

    private function toMobileFuelLog(FuelLog $log): array
    {
        $stationName = (string) (
            $log->gasStation?->razon_social
            ?? $log->gasStation?->nombre
            ?? 'SIN ESTACION'
        );
        $dateTime = optional($log->fecha_emision)->toIso8601String()
            ?? optional($log->invoice?->fecha_emision)->toIso8601String()
            ?? optional($log->created_at)->toIso8601String();
        $driverId = $log->driver_id ?? $log->vehicleLog?->drivers_id;
        $userId = $log->vehicleLog?->driver?->user_id;
        $vehicleId = $log->vehicle_id ?? $log->vehicleLog?->vehicles_id;

        return [
            'id' => (int) $log->id,
            'station_name' => $stationName,
            'estacion' => $stationName,
            'total_amount' => (float) ($log->subtotal ?? 0),
            'monto_total' => (float) ($log->subtotal ?? 0),
            'date_time' => $dateTime,
            'fecha_emision' => $dateTime,
            'invoice_number' => (string) ($log->invoice?->numero_factura ?? ''),
            'numero_factura' => (string) ($log->invoice?->numero_factura ?? ''),
            'customer_name' => (string) ($log->invoice?->nombre_cliente ?? ''),
            'vehicle_plate' => (string) ($log->vehicleLog?->vehicle?->placa ?? ''),
            'user_id' => $userId ? (int) $userId : null,
            'driver_id' => $driverId ? (int) $driverId : null,
            'drivers_id' => $driverId ? (int) $driverId : null,
            'vehicle_id' => $vehicleId ? (int) $vehicleId : null,
            'liters' => (float) ($log->cantidad ?? 0),
            'precio_unitario' => (float) ($log->precio_unitario ?? 0),
            'created_at' => optional($log->created_at)->toIso8601String(),
            'raw' => [
                'fuel_log_id' => $log->id,
                'fuel_invoice_id' => $log->fuel_invoice_id,
                'vehicle_id' => $vehicleId ? (int) $vehicleId : null,
                'driver_id' => $driverId ? (int) $driverId : null,
                'user_id' => $userId ? (int) $userId : null,
                'gas_station_id' => $log->gas_station_id,
            ],
        ];
    }

    private function applyOwnershipScope($query, Request $request): void
    {
        $authUser = $request->user();
        $authDriver = $authUser?->resolvedDriver();
        $role = strtolower((string) ($authUser?->role ?? ''));
        $canUseExplicitFilters = in_array($role, ['admin', 'recepcion'], true);

        $requestedDriverId = $request->filled('driver_id') ? (int) $request->integer('driver_id') : null;
        $requestedUserId = $request->filled('user_id') ? (int) $request->integer('user_id') : null;

        if ($canUseExplicitFilters) {
            if ($requestedDriverId) {
                if (Schema::hasColumn('fuel_invoice_details', 'driver_id')) {
                    $query->where('driver_id', $requestedDriverId);
                } else {
                    $query->whereHas('vehicleLog', fn ($q) => $q->where('drivers_id', $requestedDriverId));
                }
                return;
            }

            if ($requestedUserId) {
                $query->whereHas('vehicleLog.driver', fn ($q) => $q->where('user_id', $requestedUserId));
                return;
            }
        }

        if ($authDriver && ($role === 'conductor' || $role === 'driver')) {
            $driverId = (int) $authDriver->id;
            if (Schema::hasColumn('fuel_invoice_details', 'driver_id')) {
                $query->where('driver_id', $driverId);
            } else {
                $query->whereHas('vehicleLog', fn ($q) => $q->where('drivers_id', $driverId));
            }
        }
    }

    private function canViewFuelLog(FuelLog $fuelLog, Request $request): bool
    {
        $authUser = $request->user();
        if (!$authUser) {
            return false;
        }

        $role = strtolower((string) ($authUser->role ?? ''));
        if (in_array($role, ['admin', 'recepcion'], true)) {
            return true;
        }

        $authDriver = $authUser->resolvedDriver();
        if (!$authDriver || !in_array($role, ['conductor', 'driver'], true)) {
            return false;
        }

        $driverId = (int) $authDriver->id;
        $logDriverId = (int) ($fuelLog->driver_id ?? $fuelLog->vehicleLog?->drivers_id ?? 0);

        return $driverId > 0 && $logDriverId === $driverId;
    }

    private function resolveDriverId(Request $request, mixed $candidateDriverId, ?int $vehicleId): ?int
    {
        $candidate = (int) ($candidateDriverId ?? 0);

        if ($candidate > 0) {
            $driverById = Driver::query()->find($candidate);
            if ($driverById) {
                return (int) $driverById->id;
            }

            $driverByUser = Driver::query()->where('user_id', $candidate)->first();
            if ($driverByUser) {
                return (int) $driverByUser->id;
            }
        }

        $authDriver = $request->user()?->resolvedDriver();
        if ($authDriver) {
            return (int) $authDriver->id;
        }

        if (($vehicleId ?? 0) > 0) {
            $assignment = VehicleAssignment::query()
                ->where('vehicle_id', (int) $vehicleId)
                ->where(function ($q) {
                    $q->where('activo', true)->orWhereNull('activo');
                })
                ->orderByDesc('fecha_inicio')
                ->orderByDesc('id')
                ->first();

            if ($assignment && (int) $assignment->driver_id > 0) {
                return (int) $assignment->driver_id;
            }
        }

        return null;
    }

    private function enrichPayloadFromQr(array $payload): array
    {
        $payload = $this->enrichPayloadFromScrapedArray($payload, $payload['scraped_payload'] ?? null);

        $qrUrl = trim((string) ($payload['qr_payload'] ?? ''));
        if ($qrUrl === '' || !preg_match('/^https?:\/\//i', $qrUrl)) {
            return $payload;
        }

        try {
            $scrapeRequest = Request::create('/api/fuel-logs/scrape-from-qr', 'POST', [
                'url' => $qrUrl,
            ]);

            /** @var JsonResponse $scrapeResponse */
            $scrapeResponse = app(FuelScrapeApiController::class)->scrapeFromQr($scrapeRequest);
            $scraped = $scrapeResponse->getData(true);
            $data = is_array($scraped['data'] ?? null) ? $scraped['data'] : null;
            $payload = $this->enrichPayloadFromScrapedArray($payload, $data);
        } catch (\Throwable) {
            // Si el scraping falla, conservar payload original para no bloquear registro.
        }

        if (empty($payload['station_nit'])) {
            $payload['station_nit'] = $this->extractNitFromQrPayload((string) ($payload['qr_payload'] ?? ''));
        }

        return $payload;
    }

    private function enrichPayloadFromScrapedArray(array $payload, mixed $rawData): array
    {
        if (!is_array($rawData) || empty($rawData)) {
            return $payload;
        }

        $details = $rawData['details'] ?? null;
        $firstDetail = is_array($details) && isset($details[0]) && is_array($details[0])
            ? $details[0]
            : [];

        $station = trim((string) ($rawData['gas_station']['razon_social'] ?? ''));
        $stationNit = trim((string) ($rawData['gas_station']['nit_emisor'] ?? ''));
        $stationAddress = trim((string) ($rawData['gas_station']['direccion'] ?? ''));
        $liters = $rawData['cantidad']
            ?? $rawData['cantidad_combustible']
            ?? $rawData['galones']
            ?? $rawData['litros']
            ?? ($firstDetail['cantidad'] ?? null)
            ?? ($firstDetail['cantidadProducto'] ?? null)
            ?? ($firstDetail['litros'] ?? null)
            ?? ($firstDetail['galones'] ?? null)
            ?? null;
        $totalAmount = $rawData['monto_total']
            ?? $rawData['total_calculado']
            ?? $rawData['total']
            ?? $rawData['subtotal']
            ?? ($firstDetail['subtotal'] ?? null)
            ?? ($firstDetail['subTotal'] ?? null)
            ?? null;
        $unitPrice = $rawData['precio_unitario']
            ?? $rawData['precio_galon']
            ?? $rawData['precio']
            ?? ($firstDetail['precio_unitario'] ?? null)
            ?? ($firstDetail['precioUnitario'] ?? null)
            ?? ($firstDetail['precio'] ?? null)
            ?? null;
        $dateTime = (string) ($rawData['fecha_emision'] ?? '');
        $invoiceNumber = (string) ($rawData['numero_factura'] ?? '');
        $customerName = (string) ($rawData['nombre_cliente'] ?? '');

        if ($station !== '') {
            $payload['station'] = $station;
            $payload['station_razon_social'] = $station;
        }
        if ($stationNit !== '') {
            $payload['station_nit'] = $stationNit;
        }
        if ($stationAddress !== '') {
            $payload['station_address'] = $stationAddress;
        }
        if (is_numeric($liters) && (float) $liters > 0) {
            $payload['liters'] = (float) $liters;
        } elseif (
            is_numeric($totalAmount) && (float) $totalAmount > 0 &&
            is_numeric($unitPrice) && (float) $unitPrice > 0
        ) {
            $payload['liters'] = round(((float) $totalAmount) / ((float) $unitPrice), 5);
        }
        if (is_numeric($totalAmount) && (float) $totalAmount > 0) {
            $payload['total_amount'] = (float) $totalAmount;
        }
        if ($dateTime !== '') {
            $payload['date_time'] = $dateTime;
        }
        if ($invoiceNumber !== '') {
            $payload['invoice_number'] = $invoiceNumber;
        }
        if ($customerName !== '') {
            $payload['customer_name'] = $customerName;
        }

        return $payload;
    }

    private function resolveOrCreateGasStation(array $payload): GasStation
    {
        $stationName = trim((string) ($payload['station_razon_social'] ?? $payload['station'] ?? ''));
        $stationNit = trim((string) ($payload['station_nit'] ?? ''));
        $stationAddress = trim((string) ($payload['station_address'] ?? ''));
        if ($stationNit === '') {
            $stationNit = $this->buildFallbackStationNit($stationName, $stationAddress);
        }

        $station = null;

        if ($stationNit !== '' && Schema::hasColumn('gas_stations', 'nit_emisor')) {
            $station = GasStation::query()->where('nit_emisor', $stationNit)->first();
        }

        if (!$station) {
            $station = GasStation::query()
                ->where(function ($q) use ($stationName) {
                    if ($stationName === '') {
                        return;
                    }
                    if (Schema::hasColumn('gas_stations', 'razon_social')) {
                        $q->orWhere('razon_social', $stationName);
                    }
                    if (Schema::hasColumn('gas_stations', 'nombre')) {
                        $q->orWhere('nombre', $stationName);
                    }
                })
                ->first();
        }

        if ($station) {
            $updates = [];
            if ($stationNit !== '' && Schema::hasColumn('gas_stations', 'nit_emisor')) {
                $updates['nit_emisor'] = $stationNit;
            }
            if ($stationName !== '' && Schema::hasColumn('gas_stations', 'razon_social')) {
                $updates['razon_social'] = $stationName;
            }
            if ($stationName !== '' && Schema::hasColumn('gas_stations', 'nombre')) {
                $updates['nombre'] = $stationName;
            }
            if ($stationAddress !== '' && Schema::hasColumn('gas_stations', 'direccion')) {
                $updates['direccion'] = $stationAddress;
            }
            if ($stationAddress !== '' && Schema::hasColumn('gas_stations', 'ubicacion')) {
                $updates['ubicacion'] = $stationAddress;
            }
            if (!empty($updates)) {
                $station->fill($updates)->save();
            }
            return $station;
        }

        $stationCreate = [];
        if (Schema::hasColumn('gas_stations', 'nit_emisor') && $stationNit !== '') {
            $stationCreate['nit_emisor'] = $stationNit;
        }
        if (Schema::hasColumn('gas_stations', 'razon_social')) {
            $stationCreate['razon_social'] = $stationName !== '' ? $stationName : 'SIN RAZON SOCIAL';
        }
        if (Schema::hasColumn('gas_stations', 'nombre')) {
            $stationCreate['nombre'] = $stationName !== '' ? $stationName : 'SIN NOMBRE';
        }
        if (Schema::hasColumn('gas_stations', 'activa')) {
            $stationCreate['activa'] = true;
        }
        if (Schema::hasColumn('gas_stations', 'direccion')) {
            $stationCreate['direccion'] = $stationAddress !== '' ? $stationAddress : 'SIN DIRECCION';
        }
        if (Schema::hasColumn('gas_stations', 'ubicacion')) {
            $stationCreate['ubicacion'] = $stationAddress !== '' ? $stationAddress : 'SIN UBICACION';
        }

        return GasStation::create($stationCreate);
    }

    private function resolveFuelLocation(array $payload, ?int $vehicleId): array
    {
        $lat = $this->toNullableFloat($payload['latitude'] ?? $payload['lat'] ?? null);
        $lng = $this->toNullableFloat($payload['longitude'] ?? $payload['lng'] ?? null);
        $label = trim((string) ($payload['location_label'] ?? ''));
        $status = null;

        if (($vehicleId ?? 0) > 0) {
            $heartbeat = Cache::get("mobile:heartbeat:vehicle:{$vehicleId}");
            if (is_array($heartbeat)) {
                if (is_null($lat)) {
                    $lat = $this->toNullableFloat($heartbeat['latitude'] ?? $heartbeat['lat'] ?? null);
                }
                if (is_null($lng)) {
                    $lng = $this->toNullableFloat($heartbeat['longitude'] ?? $heartbeat['lng'] ?? null);
                }
                if ($label === '') {
                    $label = trim((string) ($heartbeat['address'] ?? $heartbeat['label'] ?? ''));
                }
                $status = strtoupper(trim((string) ($heartbeat['estado'] ?? '')));
            }
        }

        if ($label === '') {
            $label = (!is_null($lat) && !is_null($lng))
                ? 'Punto final de bitacora'
                : 'Punto final de bitacora (sin coordenadas)';
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'label' => $label,
            'status' => $status,
        ];
    }

    private function ensureVehicleIsInRoute(?int $vehicleId, ?string $status): void
    {
        if (($vehicleId ?? 0) <= 0) {
            return;
        }

        $normalized = strtoupper(trim((string) $status));
        if ($normalized === 'EN_RUTA') {
            return;
        }

        throw ValidationException::withMessages([
            'vehicle_id' => 'Solo se puede registrar el vale de combustible al finalizar bitacora y con el vehiculo EN_RUTA.',
        ]);
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function normalizeLocationLabel(string $requested, ?string $fallback): string
    {
        $label = trim($requested);
        if ($label === '') {
            $label = trim((string) ($fallback ?? ''));
        }
        if ($label === '') {
            $label = 'Punto final de bitacora';
        }

        return mb_substr($label, 0, 255);
    }

    private function extractNitFromQrPayload(string $qrPayload): string
    {
        if ($qrPayload === '' || !preg_match('/^https?:\/\//i', $qrPayload)) {
            return '';
        }

        $query = parse_url($qrPayload, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return '';
        }

        parse_str($query, $params);
        $candidate = (string) ($params['nit'] ?? $params['nit_emisor'] ?? '');
        $digits = preg_replace('/\D+/', '', $candidate) ?: '';

        return mb_substr($digits, 0, 20);
    }

    private function buildFallbackStationNit(string $name, string $address): string
    {
        $seed = trim($name . '|' . $address);
        if ($seed === '|') {
            $seed = 'station';
        }

        return 'SN-' . strtoupper(substr(sha1($seed), 0, 16));
    }
}
