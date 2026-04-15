<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\FuelInvoice;
use App\Models\FuelInvoiceDetail;
use App\Models\FuelLog;
use App\Models\GasStation;
use App\Models\ActivityLog;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleLog;
use App\Services\FuelInvoiceDocumentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FuelLogApiController extends Controller
{
    public function index(Request $request)
    {
        $query = FuelLog::query()
            ->active()
            ->with(['vehicleLog.vehicle', 'vehicleLog.driver', 'gasStation', 'invoice']);

        if ($request->filled('vehicle_id')) {
            $vehicleId = (int) $request->integer('vehicle_id');
            if (Schema::hasColumn('fuel_invoice_details', 'vehicle_id')) {
                $query->where('vehicle_id', $vehicleId);
            } else {
                $query->whereHas('vehicleLog', fn ($q) => $q->where('vehicles_id', $vehicleId));
            }
        }

        $this->applyOwnershipScope($query, $request);
        $this->applyFuelLogDateFilters(
            $query,
            $request->filled('date_from') ? (string) $request->input('date_from') : null,
            $request->filled('date_to') ? (string) $request->input('date_to') : null
        );
        $this->applyFuelLogOrdering($query);

        $logs = $query->paginate(20);
        $logs->setCollection(
            $logs->getCollection()->map(fn (FuelLog $log) => $this->toMobileFuelLog($log))
        );

        return response()->json($logs);
    }

    public function show(Request $request, FuelLog $fuelLog)
    {
        if (isset($fuelLog->activo) && !$fuelLog->activo) {
            abort(404);
        }

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
            'unit_price' => 'nullable|numeric',
            'total_amount' => 'nullable|numeric',
            'date_time' => 'nullable',
            'invoice_number' => 'nullable|string|max:80',
            'customer_name' => 'nullable|string|max:255',
            'invoice_photo_base64' => 'nullable|string',
            'fuel_meter_photo_base64' => 'nullable|string',
            'qr_payload' => 'nullable|string|max:5000',
            'station_nit' => 'nullable|string|max:50',
            'station_razon_social' => 'nullable|string|max:255',
            'station_address' => 'nullable|string|max:255',
            'scraped_payload' => 'nullable|array',
            'estado' => 'nullable|string|max:40',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'driver_id' => 'nullable|integer|min:1',
            'drivers_id' => 'nullable|integer|min:1',
            'latitude' => 'nullable|numeric|between:-90,90',
            'lat' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'lng' => 'nullable|numeric|between:-180,180',
            'location_label' => 'nullable|string|max:255',
            'route_start_label' => 'nullable|string|max:255',
            'route_end_label' => 'nullable|string|max:255',
            'route_start_latitude' => 'nullable|numeric|between:-90,90',
            'route_start_longitude' => 'nullable|numeric|between:-180,180',
            'route_end_latitude' => 'nullable|numeric|between:-90,90',
            'route_end_longitude' => 'nullable|numeric|between:-180,180',
            'point_timestamp' => 'nullable',
            'antifraud_context' => 'nullable|array',
        ]);

        $payload = $this->enrichPayloadFromQr($payload);
        $payload['station'] = (string) ($payload['station'] ?? $payload['station_razon_social'] ?? 'ESTACION NO IDENTIFICADA');
        $payload['date_time'] = (string) ($payload['date_time'] ?? now()->format('Y-m-d H:i:s'));
        $payload['unit_price'] = is_numeric($payload['unit_price'] ?? null) ? (float) $payload['unit_price'] : null;
        $payload['total_amount'] = is_numeric($payload['total_amount'] ?? null) ? (float) $payload['total_amount'] : 0.0;
        $payload['liters'] = is_numeric($payload['liters'] ?? null) ? (float) $payload['liters'] : 0.0;
        if (($payload['total_amount'] ?? 0) <= 0 && ($payload['liters'] ?? 0) > 0 && ($payload['unit_price'] ?? 0) > 0) {
            $payload['total_amount'] = round(((float) $payload['liters']) * ((float) $payload['unit_price']), 2);
        }
        if (($payload['liters'] ?? 0) <= 0 && ($payload['total_amount'] ?? 0) > 0 && ($payload['unit_price'] ?? 0) > 0) {
            $payload['liters'] = round(((float) $payload['total_amount']) / ((float) $payload['unit_price']), 5);
        }

        $payload = validator($payload, [
            'station' => 'required|string|max:255',
            'liters' => 'required|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'date_time' => 'required|date',
            'invoice_number' => 'nullable|string|max:80',
            'customer_name' => 'nullable|string|max:255',
            'invoice_photo_base64' => 'nullable|string',
            'fuel_meter_photo_base64' => 'nullable|string',
            'qr_payload' => 'nullable|string|max:5000',
            'station_nit' => 'nullable|string|max:50',
            'station_razon_social' => 'nullable|string|max:255',
            'station_address' => 'nullable|string|max:255',
            'scraped_payload' => 'nullable|array',
            'estado' => 'nullable|string|max:40',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'driver_id' => 'nullable|integer|min:1',
            'drivers_id' => 'nullable|integer|min:1',
            'latitude' => 'nullable|numeric|between:-90,90',
            'lat' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'lng' => 'nullable|numeric|between:-180,180',
            'location_label' => 'nullable|string|max:255',
            'route_start_label' => 'nullable|string|max:255',
            'route_end_label' => 'nullable|string|max:255',
            'route_start_latitude' => 'nullable|numeric|between:-90,90',
            'route_start_longitude' => 'nullable|numeric|between:-180,180',
            'route_end_latitude' => 'nullable|numeric|between:-90,90',
            'route_end_longitude' => 'nullable|numeric|between:-180,180',
            'point_timestamp' => 'nullable',
            'antifraud_context' => 'nullable|array',
        ])->validate();

        if ($this->toNullableFloat($payload['latitude'] ?? $payload['lat'] ?? null) === null
            || $this->toNullableFloat($payload['longitude'] ?? $payload['lng'] ?? null) === null) {
            throw ValidationException::withMessages([
                'latitude' => 'La ubicacion GPS del carguio es obligatoria.',
                'longitude' => 'La ubicacion GPS del carguio es obligatoria.',
            ]);
        }

        $vehicleId = !empty($payload['vehicle_id']) ? (int) $payload['vehicle_id'] : null;
        $candidateDriverId = $payload['driver_id'] ?? $payload['drivers_id'] ?? null;
        $resolvedDriverId = $this->resolveDriverId($request, $candidateDriverId, $vehicleId);
        $location = $this->resolveFuelLocation($payload, $vehicleId);
        $this->ensureVehicleIsInRoute($vehicleId, $location['status'] ?? null);
        $vehicle = $vehicleId ? Vehicle::query()->find($vehicleId) : null;
        $vehicleReferenceKm = $this->resolveVehicleReferenceKilometraje($vehicle);

        $kilometrajeSalida = $this->toNullableFloat(
            $payload['kilometraje_salida']
                ?? $payload['odometer_start']
                ?? $payload['km_start']
                ?? $vehicleReferenceKm
                ?? null
        );
        $kilometrajeLlegada = $this->toNullableFloat(
            $payload['kilometraje_llegada']
                ?? $payload['odometer_end']
                ?? $payload['km_end']
                ?? $kilometrajeSalida
        );

        if (!is_null($kilometrajeSalida) && $kilometrajeSalida <= 0) {
            throw ValidationException::withMessages([
                'kilometraje_salida' => 'El kilometraje de salida debe ser mayor a 0.',
            ]);
        }

        if (!is_null($kilometrajeLlegada) && $kilometrajeLlegada <= 0) {
            throw ValidationException::withMessages([
                'kilometraje_llegada' => 'El kilometraje de llegada debe ser mayor a 0.',
            ]);
        }

        if (!is_null($kilometrajeSalida) && !is_null($kilometrajeLlegada) && $kilometrajeLlegada < $kilometrajeSalida) {
            throw ValidationException::withMessages([
                'kilometraje_llegada' => 'El kilometraje de llegada no puede ser menor al kilometraje de salida.',
            ]);
        }

        if (is_null($kilometrajeSalida) && !empty($payload['vehicle_id'])) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'No se pudo determinar el kilometraje actual del vehiculo para registrar la bitacora del combustible.',
            ]);
        }

        if ($vehicle && !is_null($vehicle->capacidad_tanque) && (float) $payload['liters'] > ((float) $vehicle->capacidad_tanque * 1.05)) {
            $this->registerCapacityExceededAlert(
                $request,
                $vehicle,
                (float) $payload['liters'],
                (float) $vehicle->capacidad_tanque,
                $resolvedDriverId
            );
            throw ValidationException::withMessages([
                'liters' => 'La cantidad de combustible excede la capacidad registrada del tanque del vehiculo.',
            ]);
        }

        $stationNit = trim((string) ($payload['station_nit'] ?? ''));
        $stationName = trim((string) ($payload['station_razon_social'] ?? $payload['station'] ?? ''));
        $invoiceNumber = trim((string) ($payload['invoice_number'] ?? ''));
        if ($invoiceNumber !== '') {
            $duplicateLog = $this->findDuplicateInvoiceLog($invoiceNumber, $stationNit, $stationName);

            if ($duplicateLog) {
                $this->registerDuplicateInvoiceAlert(
                    $request,
                    $invoiceNumber,
                    $duplicateLog,
                    $vehicleId,
                    $resolvedDriverId
                );

                throw ValidationException::withMessages([
                    'invoice_number' => 'La factura ya fue registrada previamente y quedo marcada para verificacion por duplicidad.',
                ]);
            }
        }

        $result = DB::transaction(function () use ($payload, $resolvedDriverId, $location, $kilometrajeSalida, $kilometrajeLlegada) {
            $station = $this->resolveOrCreateGasStation($payload);

            $dateTime = \Carbon\Carbon::parse((string) $payload['date_time']);
            $liters = (float) $payload['liters'];
            $total = (float) $payload['total_amount'];
            $unitPrice = is_numeric($payload['unit_price'] ?? null)
                ? (float) $payload['unit_price']
                : ($liters > 0 ? round($total / $liters, 2) : $total);
            $invoiceNumber = (string) ($payload['invoice_number'] ?? ('MOBILE-' . now()->format('Ymd-His')));
            $customerName = (string) ($payload['customer_name'] ?? 'CONSUMO MOVIL');
            $verificationStatus = $this->resolveFuelVerificationStatus(
                (string) ($payload['qr_payload'] ?? ''),
                (string) ($payload['estado'] ?? '')
            );

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

            $invoice = $this->resolveOrCreateInvoice($invoicePayload, $invoiceNumber, $station);
            $invoice = $this->syncInvoiceHeaderWithStation(
                $invoice,
                $station,
                $invoiceNumber,
                $customerName,
                $total,
                $dateTime
            );
            $invoice = $this->persistInvoiceSourceDocument($invoice, $payload, $liters, $unitPrice, $total);
            $fuelMeterPhotoPath = $this->persistFuelMeterPhoto($payload);
            $invoice = $this->persistInvoicePhoto($invoice, $payload);
            $invoice = $this->persistAntifraudEvidence($invoice, $payload, $location, $fuelMeterPhotoPath);

            $detail = FuelLog::query()
                ->where('fuel_invoice_id', $invoice->id)
                ->orderByDesc('id')
                ->first();

            $legacyFuelLogId = null;

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
            if (Schema::hasColumn('fuel_invoice_details', 'estado')) {
                $detailPayload['estado'] = $verificationStatus;
            }
            if (Schema::hasColumn('fuel_invoice_details', 'activo')) {
                $detailPayload['activo'] = true;
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

            $legacyFuelLogId = $this->upsertLegacyFuelLog($detail, $payload, $dateTime, $liters, $unitPrice, $total);
            if (
                $legacyFuelLogId
                && Schema::hasColumn('fuel_invoice_details', 'fuel_log_id')
                && (int) ($detail->fuel_log_id ?? 0) !== $legacyFuelLogId
            ) {
                $detail->forceFill(['fuel_log_id' => $legacyFuelLogId])->save();
            }

            if (!empty($payload['vehicle_id'])) {
                $locationLabel = $this->normalizeLocationLabel(
                    (string) ($payload['location_label'] ?? ''),
                    $location['label'] ?? null
                );
                $routeStartLabel = $this->normalizeLocationLabel(
                    (string) ($payload['route_start_label'] ?? ''),
                    $locationLabel
                );
                $routeEndLabel = $this->normalizeLocationLabel(
                    (string) ($payload['route_end_label'] ?? ''),
                    $locationLabel
                );
                $locationLat = $this->toNullableFloat($location['lat'] ?? null);
                $locationLng = $this->toNullableFloat($location['lng'] ?? null);
                $routeStartLat = $this->toNullableFloat($payload['route_start_latitude'] ?? null) ?? $locationLat;
                $routeStartLng = $this->toNullableFloat($payload['route_start_longitude'] ?? null) ?? $locationLng;
                $routeEndLat = $this->toNullableFloat($payload['route_end_latitude'] ?? null) ?? $locationLat;
                $routeEndLng = $this->toNullableFloat($payload['route_end_longitude'] ?? null) ?? $locationLng;
                $pointTimestamp = (string) ($payload['point_timestamp'] ?? $dateTime->toIso8601String());
                $kmSalida = $this->toNullableFloat(
                    $payload['kilometraje_salida']
                        ?? $payload['odometer_start']
                        ?? $payload['km_start']
                        ?? $kilometrajeSalida
                );
                $kmLlegada = $this->toNullableFloat(
                    $payload['kilometraje_llegada']
                        ?? $payload['odometer_end']
                        ?? $payload['km_end']
                        ?? $kilometrajeLlegada
                        ?? $kmSalida
                );
                $vehicleLogPayload = [
                    'drivers_id' => !empty($resolvedDriverId) ? (int) $resolvedDriverId : null,
                    'vehicles_id' => (int) $payload['vehicle_id'],
                    'fuel_log_id' => $detail->id,
                    'fecha' => $dateTime->toDateString(),
                    'kilometraje_salida' => $kmSalida,
                    'kilometraje_llegada' => $kmLlegada,
                    'recorrido_inicio' => $routeStartLabel,
                    'recorrido_destino' => $routeEndLabel,
                    'abastecimiento_combustible' => true,
                ];
                if (Schema::hasColumn('vehicle_log', 'firma_digital')) {
                    $vehicleLogPayload['firma_digital'] = null;
                }
                if (Schema::hasColumn('vehicle_log', 'activo')) {
                    $vehicleLogPayload['activo'] = true;
                }
                if (Schema::hasColumn('vehicle_log', 'latitud_inicio') && !is_null($routeStartLat)) {
                    $vehicleLogPayload['latitud_inicio'] = $routeStartLat;
                }
                if (Schema::hasColumn('vehicle_log', 'logitud_inicio') && !is_null($routeStartLng)) {
                    $vehicleLogPayload['logitud_inicio'] = $routeStartLng;
                }
                if (Schema::hasColumn('vehicle_log', 'latitud_destino') && !is_null($routeEndLat)) {
                    $vehicleLogPayload['latitud_destino'] = $routeEndLat;
                }
                if (Schema::hasColumn('vehicle_log', 'logitud_destino') && !is_null($routeEndLng)) {
                    $vehicleLogPayload['logitud_destino'] = $routeEndLng;
                }
                if (Schema::hasColumn('vehicle_log', 'ruta_json')) {
                    $routeJson = [];

                    if (!is_null($routeStartLat) && !is_null($routeStartLng)) {
                        $routeJson[] = [
                            'lat' => $routeStartLat,
                            'lng' => $routeStartLng,
                            't' => $pointTimestamp,
                            'address' => $routeStartLabel,
                            'label' => $routeStartLabel,
                            'is_marked' => true,
                            'point_type' => 'bitacora_inicio',
                            'status' => 'EN_RUTA',
                            'index' => 0,
                        ];
                    }

                    if (!is_null($routeEndLat) && !is_null($routeEndLng)) {
                        $routeJson[] = [
                            'lat' => $routeEndLat,
                            'lng' => $routeEndLng,
                            't' => $pointTimestamp,
                            'address' => $routeEndLabel,
                            'label' => $routeEndLabel,
                            'is_marked' => true,
                            'point_type' => 'bitacora_final_fuel',
                            'status' => 'EN_RUTA',
                            'index' => count($routeJson),
                        ];
                    }

                    $vehicleLogPayload['ruta_json'] = $routeJson;
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

            $invoice = $this->syncInvoiceHeaderWithStation(
                $invoice->fresh() ?? $invoice,
                $station,
                $invoiceNumber,
                $customerName,
                $total,
                $dateTime
            );

            return $this->toMobileFuelLog($detail->load(['vehicleLog.vehicle', 'vehicleLog.driver', 'gasStation', 'invoice']));
        });

        return response()->json($result, 201);
    }

    private function buildLegacyFuelLogPayload(array $payload, \Carbon\Carbon $dateTime, float $liters, float $unitPrice, float $total): array
    {
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
            $legacyPayload['kilometraje'] = $this->toNullableFloat(
                $payload['kilometraje_llegada']
                    ?? $payload['odometer_end']
                    ?? $payload['km_end']
                    ?? $payload['kilometraje_salida']
                    ?? $payload['odometer_start']
                    ?? $payload['km_start']
                    ?? null
            ) ?? null;
        }
        if (Schema::hasColumn('fuel_logs', 'recibo')) {
            $legacyPayload['recibo'] = (string) ($payload['invoice_number'] ?? '');
        }
        if (Schema::hasColumn('fuel_logs', 'observaciones')) {
            $legacyPayload['observaciones'] = (string) ($payload['station'] ?? $payload['station_razon_social'] ?? 'REGISTRO MOVIL');
        }

        return $legacyPayload;
    }

    private function upsertLegacyFuelLog(FuelLog $detail, array $payload, \Carbon\Carbon $dateTime, float $liters, float $unitPrice, float $total): ?int
    {
        if (!Schema::hasTable('fuel_logs') || !Schema::hasColumn('fuel_invoice_details', 'fuel_log_id')) {
            return null;
        }

        $legacyPayload = $this->buildLegacyFuelLogPayload($payload, $dateTime, $liters, $unitPrice, $total);
        if (empty($legacyPayload)) {
            return null;
        }

        $existingId = (int) ($detail->fuel_log_id ?? 0);
        if ($existingId > 0 && DB::table('fuel_logs')->where('id', $existingId)->exists()) {
            DB::table('fuel_logs')->where('id', $existingId)->update($legacyPayload);
            return $existingId;
        }

        $id = DB::table('fuel_logs')->insertGetId($legacyPayload);

        return $id ? (int) $id : null;
    }

    private function resolveOrCreateInvoice(array $invoicePayload, string $invoiceNumber, ?GasStation $station = null): FuelInvoice
    {
        $invoice = null;
        $stationId = $station?->id;

        if (Schema::hasColumn('fuel_invoices', 'numero') && $invoiceNumber !== '') {
            $query = FuelInvoice::query()->active()->where('numero', $invoiceNumber);
            if (Schema::hasColumn('fuel_invoices', 'gas_station_id') && $stationId) {
                $query->where('gas_station_id', $stationId);
            }
            $invoice = $query->first();
        }

        if (!$invoice && !empty($invoicePayload['numero_factura'])) {
            $query = FuelInvoice::query()
                ->active()
                ->where('numero_factura', (string) $invoicePayload['numero_factura']);
            if (Schema::hasColumn('fuel_invoices', 'gas_station_id') && $stationId) {
                $query->where('gas_station_id', $stationId);
            }
            $invoice = $query->first();
        }

        if ($invoice) {
            $invoice->fill($invoicePayload)->save();
            return $invoice;
        }

        return FuelInvoice::create($invoicePayload);
    }

    private function syncInvoiceHeaderWithStation(
        FuelInvoice $invoice,
        ?GasStation $station,
        string $invoiceNumber,
        string $customerName,
        float $total,
        \Carbon\Carbon $dateTime
    ): FuelInvoice {
        $updates = [];

        if (Schema::hasColumn('fuel_invoices', 'gas_station_id') && $station?->id) {
            $updates['gas_station_id'] = (int) $station->id;
        }
        if (Schema::hasColumn('fuel_invoices', 'numero') && $invoiceNumber !== '') {
            $updates['numero'] = $invoiceNumber;
        }
        if (Schema::hasColumn('fuel_invoices', 'numero_factura') && $invoiceNumber !== '') {
            $updates['numero_factura'] = $invoiceNumber;
        }
        if (Schema::hasColumn('fuel_invoices', 'nombre_cliente') && $customerName !== '') {
            $updates['nombre_cliente'] = $customerName;
        }
        if (Schema::hasColumn('fuel_invoices', 'monto_total')) {
            $updates['monto_total'] = $total;
        }
        if (Schema::hasColumn('fuel_invoices', 'fecha_emision')) {
            $updates['fecha_emision'] = $dateTime->format('Y-m-d');
        }

        if (!empty($updates)) {
            $invoice->forceFill($updates)->save();
        }

        return $invoice->fresh() ?? $invoice;
    }

    private function findDuplicateInvoiceLog(string $invoiceNumber, string $stationNit = '', string $stationName = ''): ?FuelLog
    {
        $query = FuelLog::query()
            ->active()
            ->with(['invoice', 'vehicleLog.vehicle', 'vehicleLog.driver', 'gasStation'])
            ->whereHas('invoice', fn ($q) => $q->where('numero_factura', $invoiceNumber));

        if ($stationNit !== '') {
            $query->whereHas('gasStation', fn ($q) => $q->where('nit_emisor', $stationNit));
        } elseif ($stationName !== '') {
            $query->whereHas('gasStation', function ($q) use ($stationName) {
                $q->where('razon_social', $stationName)
                    ->orWhere('nombre', $stationName);
            });
        }

        return $query->orderByDesc('id')->first();
    }

    private function persistInvoiceSourceDocument(
        FuelInvoice $invoice,
        array $payload,
        float $liters,
        float $unitPrice,
        float $total
    ): FuelInvoice {
        if (!Schema::hasTable('fuel_invoices')) {
            return $invoice;
        }

        $sourceUrl = trim((string) ($payload['qr_payload'] ?? ''));
        if ($sourceUrl !== '' && !preg_match('/^https?:\/\//i', $sourceUrl)) {
            $sourceUrl = '';
        }

        $snapshot = $this->buildSiatInvoiceSnapshot($invoice, $payload, $liters, $unitPrice, $total, $sourceUrl);
        if (empty($snapshot)) {
            return $invoice;
        }

        return app(FuelInvoiceDocumentService::class)
            ->persistFromSnapshot($invoice, $snapshot, $sourceUrl !== '' ? $sourceUrl : null);
    }

    private function persistInvoicePhoto(FuelInvoice $invoice, array $payload): FuelInvoice
    {
        if (!Schema::hasColumn('fuel_invoices', 'invoice_photo_path')) {
            return $invoice;
        }

        $encoded = trim((string) ($payload['invoice_photo_base64'] ?? ''));
        if ($encoded === '') {
            return $invoice;
        }

        if (Str::startsWith($encoded, 'data:')) {
            $encoded = explode(',', $encoded, 2)[1] ?? '';
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false) {
            throw ValidationException::withMessages([
                'invoice_photo_base64' => 'La foto de la factura no tiene un formato base64 valido.',
            ]);
        }

        $path = sprintf(
            'fuel-invoices/photos/factura-%d-%s.jpg',
            (int) $invoice->id,
            now()->format('YmdHis') . '-' . Str::lower(Str::random(8))
        );

        Storage::disk('public')->put($path, $binary);
        $invoice->forceFill(['invoice_photo_path' => $path])->save();

        return $invoice->fresh() ?? $invoice;
    }

    private function buildSiatInvoiceSnapshot(
        FuelInvoice $invoice,
        array $payload,
        float $liters,
        float $unitPrice,
        float $total,
        string $sourceUrl
    ): array {
        $scraped = is_array($payload['scraped_payload'] ?? null) ? $payload['scraped_payload'] : [];
        $gasStation = is_array($scraped['gas_station'] ?? null) ? $scraped['gas_station'] : [];
        $details = [];

        if (!empty($scraped['details']) && is_array($scraped['details'])) {
            foreach ($scraped['details'] as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $details[] = [
                    'codigo' => (string) ($row['codigo'] ?? $row['codigoProducto'] ?? ''),
                    'descripcion' => (string) ($row['descripcion'] ?? $row['descripcionProducto'] ?? 'Combustible'),
                    'cantidad' => $row['cantidad'] ?? $row['cantidadProducto'] ?? null,
                    'precio_unitario' => $row['precio_unitario'] ?? $row['precioUnitario'] ?? $row['precio'] ?? null,
                    'subtotal' => $row['subtotal'] ?? $row['subTotal'] ?? null,
                ];
            }
        }

        if (empty($details)) {
            $details[] = [
                'codigo' => '',
                'descripcion' => 'Combustible',
                'cantidad' => $scraped['cantidad'] ?? $liters,
                'precio_unitario' => $scraped['precio_unitario'] ?? $unitPrice,
                'subtotal' => $scraped['monto_total'] ?? $total,
            ];
        }

        return [
            'numero_factura' => (string) ($invoice->numero_factura ?? $payload['invoice_number'] ?? ''),
            'fecha_emision' => optional($invoice->fecha_emision)->format('d/m/Y H:i:s')
                ?: (string) ($payload['date_time'] ?? ''),
            'nombre_cliente' => (string) ($invoice->nombre_cliente ?? $payload['customer_name'] ?? ''),
            'monto_total' => $scraped['monto_total'] ?? $invoice->monto_total ?? $total,
            'cuf' => (string) ($scraped['cuf'] ?? ''),
            'nit_emisor' => (string) ($gasStation['nit_emisor'] ?? $payload['station_nit'] ?? ''),
            'razon_social_emisor' => (string) ($gasStation['razon_social'] ?? $payload['station_razon_social'] ?? $payload['station'] ?? ''),
            'direccion_emisor' => (string) ($gasStation['direccion'] ?? $payload['station_address'] ?? ''),
            'gas_station' => [
                'nit_emisor' => (string) ($gasStation['nit_emisor'] ?? $payload['station_nit'] ?? ''),
                'razon_social' => (string) ($gasStation['razon_social'] ?? $payload['station_razon_social'] ?? $payload['station'] ?? ''),
                'direccion' => (string) ($gasStation['direccion'] ?? $payload['station_address'] ?? ''),
            ],
            'details' => $details,
            'siat_source_url' => $sourceUrl,
        ];
    }

    public function byVehicle(Vehicle $vehicle)
    {
        $query = FuelLog::query()->active();
        if (Schema::hasColumn('fuel_invoice_details', 'vehicle_id')) {
            $query->where('vehicle_id', (int) $vehicle->id);
        } else {
            $query->whereHas('vehicleLog', fn ($q) => $q->where('vehicles_id', (int) $vehicle->id));
        }

        $this->applyOwnershipScope($query, request());
        $this->applyFuelLogDateFilters(
            $query,
            request()->filled('date_from') ? (string) request()->input('date_from') : null,
            request()->filled('date_to') ? (string) request()->input('date_to') : null
        );
        $this->applyFuelLogOrdering($query);

        $logs = $query
            ->with(['gasStation', 'invoice'])
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
            'unit_price' => (float) ($log->precio_unitario ?? 0),
            'estado' => (string) ($log->estado ?? 'Falta verificar'),
            'created_at' => optional($log->created_at)->toIso8601String(),
            'siat_document_url' => !empty($log->invoice?->siat_document_path)
                ? route('fuel-invoices.document', ['fuelInvoice' => $log->fuel_invoice_id])
                : null,
            'invoice_photo_url' => !empty($log->invoice?->invoice_photo_path)
                ? route('fuel-invoices.photo', ['fuelInvoice' => $log->fuel_invoice_id])
                : null,
            'fuel_meter_photo_url' => !empty(data_get($log->invoice?->antifraud_payload_json, 'evidence.fuel_meter_photo_path'))
                ? Storage::disk('public')->url((string) data_get($log->invoice?->antifraud_payload_json, 'evidence.fuel_meter_photo_path'))
                : null,
            'siat_source_url' => (string) ($log->invoice?->siat_source_url ?? ''),
            'siat_rollo_document_url' => !empty($log->invoice?->siat_rollo_document_path)
                ? route('fuel-invoices.rollo', ['fuelInvoice' => $log->fuel_invoice_id])
                : null,
            'fuel_latitude' => $log->invoice?->fuel_latitude !== null ? (float) $log->invoice->fuel_latitude : null,
            'fuel_longitude' => $log->invoice?->fuel_longitude !== null ? (float) $log->invoice->fuel_longitude : null,
            'fuel_location_label' => (string) ($log->invoice?->fuel_location_label ?? ''),
            'fuel_recorded_at' => optional($log->invoice?->fuel_recorded_at)->toIso8601String(),
            'km_per_liter' => $this->calculateKmPerLiter($log),
            'raw' => [
                'fuel_log_id' => $log->id,
                'fuel_invoice_id' => $log->fuel_invoice_id,
                'vehicle_id' => $vehicleId ? (int) $vehicleId : null,
                'driver_id' => $driverId ? (int) $driverId : null,
                'user_id' => $userId ? (int) $userId : null,
                'gas_station_id' => $log->gas_station_id,
                'estado' => (string) ($log->estado ?? 'Falta verificar'),
                'fuel_latitude' => $log->invoice?->fuel_latitude !== null ? (float) $log->invoice->fuel_latitude : null,
                'fuel_longitude' => $log->invoice?->fuel_longitude !== null ? (float) $log->invoice->fuel_longitude : null,
                'fuel_location_label' => (string) ($log->invoice?->fuel_location_label ?? ''),
                'fuel_meter_photo_url' => !empty(data_get($log->invoice?->antifraud_payload_json, 'evidence.fuel_meter_photo_path'))
                    ? Storage::disk('public')->url((string) data_get($log->invoice?->antifraud_payload_json, 'evidence.fuel_meter_photo_path'))
                    : null,
            ],
        ];
    }

    private function persistAntifraudEvidence(FuelInvoice $invoice, array $payload, array $location, ?string $fuelMeterPhotoPath = null): FuelInvoice
    {
        if (!Schema::hasTable('fuel_invoices')) {
            return $invoice;
        }

        $updates = [];
        if (Schema::hasColumn('fuel_invoices', 'fuel_latitude')) {
            $updates['fuel_latitude'] = $this->toNullableFloat($payload['latitude'] ?? $payload['lat'] ?? $location['lat'] ?? null);
        }
        if (Schema::hasColumn('fuel_invoices', 'fuel_longitude')) {
            $updates['fuel_longitude'] = $this->toNullableFloat($payload['longitude'] ?? $payload['lng'] ?? $location['lng'] ?? null);
        }
        if (Schema::hasColumn('fuel_invoices', 'fuel_location_label')) {
            $updates['fuel_location_label'] = $this->normalizeLocationLabel(
                (string) ($payload['location_label'] ?? ''),
                $location['label'] ?? null
            );
        }
        if (Schema::hasColumn('fuel_invoices', 'fuel_recorded_at')) {
            $updates['fuel_recorded_at'] = $payload['point_timestamp'] ?? $payload['date_time'] ?? now();
        }
        if (Schema::hasColumn('fuel_invoices', 'antifraud_payload_json')) {
            $updates['antifraud_payload_json'] = [
                'gps' => [
                    'latitude' => $updates['fuel_latitude'] ?? null,
                    'longitude' => $updates['fuel_longitude'] ?? null,
                    'label' => $updates['fuel_location_label'] ?? null,
                    'recorded_at' => $updates['fuel_recorded_at'] ?? null,
                ],
                'qr_payload' => $payload['qr_payload'] ?? null,
                'route' => [
                    'start_label' => $payload['route_start_label'] ?? null,
                    'end_label' => $payload['route_end_label'] ?? null,
                    'start_latitude' => $payload['route_start_latitude'] ?? null,
                    'start_longitude' => $payload['route_start_longitude'] ?? null,
                    'end_latitude' => $payload['route_end_latitude'] ?? null,
                    'end_longitude' => $payload['route_end_longitude'] ?? null,
                ],
                'odometer' => [
                    'start' => $payload['kilometraje_salida'] ?? $payload['odometer_start'] ?? null,
                    'end' => $payload['kilometraje_llegada'] ?? $payload['odometer_end'] ?? null,
                ],
                'scraped_payload' => is_array($payload['scraped_payload'] ?? null) ? $payload['scraped_payload'] : null,
                'mobile_payload' => [
                    'station' => $payload['station'] ?? null,
                    'invoice_number' => $payload['invoice_number'] ?? null,
                    'customer_name' => $payload['customer_name'] ?? null,
                    'liters' => $payload['liters'] ?? null,
                    'unit_price' => $payload['unit_price'] ?? null,
                    'total_amount' => $payload['total_amount'] ?? null,
                    'date_time' => $payload['date_time'] ?? null,
                    'vehicle_id' => $payload['vehicle_id'] ?? null,
                    'driver_id' => $payload['driver_id'] ?? $payload['drivers_id'] ?? null,
                    'uploaded_at' => now()->toIso8601String(),
                ],
                'evidence' => [
                    'invoice_photo_uploaded' => !empty($payload['invoice_photo_base64']),
                    'fuel_meter_photo_uploaded' => !empty($payload['fuel_meter_photo_base64']),
                    'fuel_meter_photo_path' => $fuelMeterPhotoPath,
                ],
                'client_context' => is_array($payload['antifraud_context'] ?? null) ? $payload['antifraud_context'] : null,
            ];
        }

        if (!empty($updates)) {
            $invoice->forceFill($updates)->save();
        }

        return $invoice->fresh() ?? $invoice;
    }

    private function persistFuelMeterPhoto(array $payload): ?string
    {
        $encoded = trim((string) ($payload['fuel_meter_photo_base64'] ?? ''));
        if ($encoded === '') {
            return null;
        }

        if (Str::startsWith($encoded, 'data:')) {
            $encoded = explode(',', $encoded, 2)[1] ?? '';
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false) {
            throw ValidationException::withMessages([
                'fuel_meter_photo_base64' => 'La foto del medidor no tiene un formato base64 valido.',
            ]);
        }

        $path = sprintf(
            'fuel-invoices/photos/medidor-%s.jpg',
            now()->format('YmdHis') . '-' . Str::lower(Str::random(8))
        );

        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function calculateKmPerLiter(FuelLog $log): ?float
    {
        $liters = (float) ($log->cantidad ?? 0);
        if ($liters <= 0) {
            return null;
        }

        $start = $this->toNullableFloat($log->vehicleLog?->kilometraje_salida ?? null);
        $end = $this->toNullableFloat($log->vehicleLog?->kilometraje_llegada ?? null);
        if ($start === null || $end === null || $end < $start) {
            return null;
        }

        return round(($end - $start) / $liters, 3);
    }

    private function resolveVehicleReferenceKilometraje(?Vehicle $vehicle): ?float
    {
        if (!$vehicle) {
            return null;
        }

        $current = $this->toNullableFloat($vehicle->kilometraje_actual ?? null);
        if (!is_null($current) && $current > 0) {
            return $current;
        }

        $initial = $this->toNullableFloat($vehicle->kilometraje_inicial ?? null);
        if (!is_null($initial) && $initial > 0) {
            return $initial;
        }

        $legacy = $this->toNullableFloat($vehicle->kilometraje ?? null);
        if (!is_null($legacy) && $legacy > 0) {
            return $legacy;
        }

        $computed = $this->toNullableFloat($vehicle->getCurrentKilometrage());
        if (!is_null($computed) && $computed > 0) {
            return $computed;
        }

        return null;
    }

    private function applyFuelLogOrdering($query): void
    {
        if (Schema::hasColumn('fuel_invoices', 'fecha_emision')) {
            $query->orderByDesc(
                FuelInvoice::query()
                    ->select('fecha_emision')
                    ->whereColumn('fuel_invoices.id', 'fuel_invoice_details.fuel_invoice_id')
                    ->limit(1)
            )->orderByDesc('id');
            return;
        }

        if (Schema::hasColumn('fuel_invoice_details', 'created_at')) {
            $query->orderByDesc('created_at')->orderByDesc('id');
            return;
        }

        if (Schema::hasColumn('fuel_invoices', 'created_at')) {
            $query->orderByDesc(
                FuelInvoice::query()
                    ->select('created_at')
                    ->whereColumn('fuel_invoices.id', 'fuel_invoice_details.fuel_invoice_id')
                    ->limit(1)
            )->orderByDesc('id');
            return;
        }

        $query->orderByDesc('id');
    }

    private function applyFuelLogDateFilters($query, ?string $dateFrom, ?string $dateTo): void
    {
        $dateFrom = $dateFrom ? trim($dateFrom) : null;
        $dateTo = $dateTo ? trim($dateTo) : null;

        if ($dateFrom === null && $dateTo === null) {
            return;
        }

        if (Schema::hasColumn('fuel_invoices', 'fecha_emision')) {
            $query->whereHas('invoice', function ($invoiceQuery) use ($dateFrom, $dateTo) {
                if ($dateFrom) {
                    $invoiceQuery->whereDate('fecha_emision', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $invoiceQuery->whereDate('fecha_emision', '<=', $dateTo);
                }
            });
            return;
        }

        if (Schema::hasColumn('fuel_invoice_details', 'created_at')) {
            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }
            return;
        }

        if (Schema::hasColumn('fuel_invoices', 'created_at')) {
            $query->whereHas('invoice', function ($invoiceQuery) use ($dateFrom, $dateTo) {
                if ($dateFrom) {
                    $invoiceQuery->whereDate('created_at', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $invoiceQuery->whereDate('created_at', '<=', $dateTo);
                }
            });
        }
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

    private function resolveFuelVerificationStatus(?string $qrPayload, ?string $explicitStatus = null): string
    {
        if ($this->isSiatQrPayload($qrPayload)) {
            return 'Verificado';
        }

        $normalizedExplicit = trim((string) $explicitStatus);
        if ($normalizedExplicit === 'Verificado') {
            return 'Verificado';
        }

        return 'Falta verificar';
    }

    private function isSiatQrPayload(?string $payload): bool
    {
        $value = trim((string) $payload);
        if ($value === '') {
            return false;
        }

        $normalized = strtolower($value);
        if (!str_contains($normalized, 'siat.impuestos.gob.bo/consulta')) {
            return false;
        }

        $parts = parse_url($value);
        if (!is_array($parts)) {
            return str_contains($normalized, 'siat.impuestos.gob.bo/consulta');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = strtolower((string) ($parts['path'] ?? ''));

        return $host === 'siat.impuestos.gob.bo' && str_starts_with($path, '/consulta');
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

        $objectData = is_array($rawData['objeto'] ?? null) ? $rawData['objeto'] : [];
        $header = is_array($rawData['cabecera'] ?? null)
            ? $rawData['cabecera']
            : (is_array($objectData['cabecera'] ?? null) ? $objectData['cabecera'] : []);

        $details = $rawData['details']
            ?? $rawData['detalles']
            ?? $rawData['listaDetalle']
            ?? $objectData['details']
            ?? $objectData['listaDetalle']
            ?? null;
        $firstDetail = is_array($details) && isset($details[0]) && is_array($details[0])
            ? $details[0]
            : [];

        $gasStation = is_array($rawData['gas_station'] ?? null)
            ? $rawData['gas_station']
            : (is_array($rawData['gasStation'] ?? null) ? $rawData['gasStation'] : []);

        $station = trim((string) (
            $gasStation['razon_social']
            ?? $rawData['razon_social_emisor']
            ?? $rawData['razonSocialEmisor']
            ?? $objectData['razonSocialEmisor']
            ?? $header['razonSocialEmisor']
            ?? ''
        ));
        $stationNit = trim((string) (
            $gasStation['nit_emisor']
            ?? $rawData['nit_emisor']
            ?? $rawData['nitEmisor']
            ?? $objectData['nitEmisor']
            ?? $header['nitEmisor']
            ?? ''
        ));
        $stationAddress = trim((string) (
            $gasStation['direccion']
            ?? $rawData['direccion_emisor']
            ?? $rawData['direccion']
            ?? $objectData['direccion']
            ?? $header['direccion']
            ?? ''
        ));
        $liters = $rawData['cantidad']
            ?? $rawData['cantidad_combustible']
            ?? $rawData['galones']
            ?? $rawData['litros']
            ?? $objectData['cantidad']
            ?? $objectData['litros']
            ?? ($firstDetail['cantidad'] ?? null)
            ?? ($firstDetail['cantidadProducto'] ?? null)
            ?? ($firstDetail['litros'] ?? null)
            ?? ($firstDetail['galones'] ?? null)
            ?? null;
        $totalAmount = $rawData['monto_total']
            ?? $rawData['total_calculado']
            ?? $rawData['total']
            ?? $rawData['subtotal']
            ?? $objectData['montoTotal']
            ?? $header['montoTotal']
            ?? ($firstDetail['subtotal'] ?? null)
            ?? ($firstDetail['subTotal'] ?? null)
            ?? null;
        $unitPrice = $rawData['precio_unitario']
            ?? $rawData['precio_galon']
            ?? $rawData['precio']
            ?? $objectData['precio_unitario']
            ?? ($firstDetail['precio_unitario'] ?? null)
            ?? ($firstDetail['precioUnitario'] ?? null)
            ?? ($firstDetail['precio'] ?? null)
            ?? null;
        $dateTime = (string) ($rawData['fecha_emision'] ?? $rawData['fechaEmision'] ?? $objectData['fechaEmision'] ?? $header['fechaEmision'] ?? '');
        $invoiceNumber = (string) ($rawData['numero_factura'] ?? $rawData['numeroFactura'] ?? $objectData['numeroFactura'] ?? $header['numeroFactura'] ?? '');
        $customerName = (string) ($rawData['nombre_cliente'] ?? $rawData['nombreCliente'] ?? $rawData['nombreRazonSocialReceptor'] ?? $objectData['nombreRazonSocial'] ?? $objectData['nombreCliente'] ?? $header['nombreRazonSocial'] ?? '');

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

    private function registerDuplicateInvoiceAlert(
        Request $request,
        string $invoiceNumber,
        FuelLog $duplicateLog,
        ?int $incomingVehicleId,
        ?int $incomingDriverId
    ): void {
        try {
            ActivityLog::create(ActivityLog::prepareAttributes([
                'user_id' => (int) ($request->user()?->id ?? 0) ?: null,
                'action' => 'FUEL_INVOICE_DUPLICATE_ALERT',
                'model' => 'FuelInvoice',
                'module' => 'combustible',
                'record_id' => (int) ($duplicateLog->fuel_invoice_id ?? 0) ?: null,
                'changes_json' => [
                    'invoice_number' => $invoiceNumber,
                    'existing_fuel_log_id' => (int) $duplicateLog->id,
                    'existing_vehicle_id' => (int) ($duplicateLog->vehicleLog?->vehicles_id ?? 0) ?: null,
                    'existing_vehicle_plate' => (string) ($duplicateLog->vehicleLog?->vehicle?->placa ?? ''),
                    'existing_driver_id' => (int) ($duplicateLog->vehicleLog?->drivers_id ?? 0) ?: null,
                    'existing_driver_name' => (string) ($duplicateLog->vehicleLog?->driver?->nombre ?? ''),
                    'incoming_vehicle_id' => $incomingVehicleId,
                    'incoming_driver_id' => $incomingDriverId,
                    'source' => 'mobile_api',
                ],
                'details' => 'Intento de registro duplicado de factura de combustible.',
                'ip_address' => (string) $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'fecha' => now(),
            ]));
        } catch (\Throwable) {
            // No bloquear el flujo por error de auditoria.
        }
    }

    private function registerCapacityExceededAlert(
        Request $request,
        Vehicle $vehicle,
        float $liters,
        float $capacity,
        ?int $incomingDriverId
    ): void {
        try {
            ActivityLog::create(ActivityLog::prepareAttributes([
                'user_id' => (int) ($request->user()?->id ?? 0) ?: null,
                'action' => 'FUEL_CAPACITY_EXCEEDED_ALERT',
                'model' => 'FuelInvoice',
                'module' => 'combustible',
                'record_id' => (int) $vehicle->id ?: null,
                'changes_json' => [
                    'vehicle_id' => (int) $vehicle->id,
                    'vehicle_plate' => (string) ($vehicle->placa ?? ''),
                    'incoming_driver_id' => $incomingDriverId,
                    'liters_attempted' => round($liters, 3),
                    'tank_capacity' => round($capacity, 3),
                    'source' => 'mobile_api',
                ],
                'details' => 'Intento de carga que excede la capacidad del tanque del vehiculo.',
                'ip_address' => (string) $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'fecha' => now(),
            ]));
        } catch (\Throwable) {
            // No bloquear el flujo por error de auditoria.
        }
    }
}
