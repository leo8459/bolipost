<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleLog;
use App\Models\VehicleLogSession;
use App\Models\VehicleLogStageEvent;
use App\Services\MaintenanceAlertService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VehicleLogApiController extends Controller
{
    public function index(Request $request)
    {
        $query = VehicleLog::query()
            ->active()
            ->with(['vehicle', 'driver', 'fuelLog'])
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($request->filled('vehicle_id')) {
            $query->where('vehicles_id', (int) $request->integer('vehicle_id'));
        }

        if ($request->filled('driver_id')) {
            $query->where('drivers_id', (int) $request->integer('driver_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('fecha', '>=', (string) $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('fecha', '<=', (string) $request->input('date_to'));
        }

        return response()->json($query->paginate(20));
    }

    public function show(VehicleLog $vehicleLog)
    {
        if (isset($vehicleLog->activo) && !$vehicleLog->activo) {
            abort(404);
        }

        $vehicleLog->load(['vehicle', 'driver', 'fuelLog']);

        return response()->json($vehicleLog);
    }

    public function store(Request $request)
    {
        $input = $request->all();

        $candidateVehicleId = (int) ($input['vehicles_id'] ?? $input['vehicle_id'] ?? 0);
        $resolvedDriverId = $this->resolveDriverId(
            $request,
            $input['drivers_id'] ?? $input['driver_id'] ?? null,
            $candidateVehicleId
        );
        $resolvedVehicleId = $this->resolveVehicleId($candidateVehicleId, $resolvedDriverId);

        $vehicle = ($resolvedVehicleId ?? 0) > 0 ? Vehicle::query()->find($resolvedVehicleId) : null;
        $blockReason = MaintenanceAlertService::resolveVehicleLogBlockReason($vehicle);
        if ($blockReason !== null) {
            throw ValidationException::withMessages([
                'vehicles_id' => $blockReason,
            ]);
        }

        $kilometrajeSalida = $this->asFloat(
            $input['kilometraje_salida']
                ?? $input['odometer_start']
                ?? $input['km_start']
                ?? ($vehicle?->kilometraje_actual ?? $vehicle?->kilometraje_inicial ?? $vehicle?->kilometraje ?? null)
        );

        $distance = $this->asFloat($input['distance_km'] ?? $input['distancia'] ?? null);
        $kilometrajeLlegada = $this->asFloat(
            $input['kilometraje_llegada']
                ?? $input['odometer_end']
                ?? $input['km_end']
                ?? (!is_null($distance) ? $kilometrajeSalida + $distance : null)
        );

        $ruta = $this->normalizeRoutePoints(
            $input['ruta_json']
                ?? $input['points_json']
                ?? $input['route_points']
                ?? $input['points']
                ?? null
        );

        $payload = [
            'drivers_id' => $resolvedDriverId,
            'vehicles_id' => $resolvedVehicleId,
            'fecha' => $input['fecha'] ?? $input['date'] ?? $input['date_time'] ?? now()->toDateString(),
            'kilometraje_salida' => $kilometrajeSalida,
            'kilometraje_llegada' => $kilometrajeLlegada,
            'recorrido_inicio' => (string) ($input['recorrido_inicio'] ?? $input['origen'] ?? $input['origin'] ?? ''),
            'recorrido_destino' => (string) ($input['recorrido_destino'] ?? $input['destino'] ?? $input['destination'] ?? ''),
            'abastecimiento_combustible' => (bool) ($input['abastecimiento_combustible'] ?? $input['has_fuel_load'] ?? false),
        ];
        if (Schema::hasColumn('vehicle_log', 'activo')) {
            $payload['activo'] = true;
        }
        if (Schema::hasColumn('vehicle_log', 'firma_digital')) {
            $payload['firma_digital'] = $input['firma_digital'] ?? $input['signature'] ?? null;
        }

        $latInicio = $this->asFloat(
            $input['latitud_inicio']
                ?? $input['start_latitude']
                ?? $input['start_lat']
                ?? ($input['start']['latitude'] ?? null)
                ?? ($input['start']['lat'] ?? null)
        );
        $lngInicio = $this->asFloat(
            $input['logitud_inicio']
                ?? $input['start_longitude']
                ?? $input['start_lng']
                ?? ($input['start']['longitude'] ?? null)
                ?? ($input['start']['lng'] ?? null)
        );
        $latDestino = $this->asFloat(
            $input['latitud_destino']
                ?? $input['end_latitude']
                ?? $input['end_lat']
                ?? ($input['end']['latitude'] ?? null)
                ?? ($input['end']['lat'] ?? null)
        );
        $lngDestino = $this->asFloat(
            $input['logitud_destino']
                ?? $input['end_longitude']
                ?? $input['end_lng']
                ?? ($input['end']['longitude'] ?? null)
                ?? ($input['end']['lng'] ?? null)
        );

        if (Schema::hasColumn('vehicle_log', 'latitud_inicio') && !is_null($latInicio)) {
            $payload['latitud_inicio'] = $latInicio;
        }
        if (Schema::hasColumn('vehicle_log', 'logitud_inicio') && !is_null($lngInicio)) {
            $payload['logitud_inicio'] = $lngInicio;
        }
        if (Schema::hasColumn('vehicle_log', 'latitud_destino') && !is_null($latDestino)) {
            $payload['latitud_destino'] = $latDestino;
        }
        if (Schema::hasColumn('vehicle_log', 'logitud_destino') && !is_null($lngDestino)) {
            $payload['logitud_destino'] = $lngDestino;
        }
        if (Schema::hasColumn('vehicle_log', 'session_reference')) {
            $payload['session_reference'] = $this->resolveSessionReference($input);
        }
        if (Schema::hasColumn('vehicle_log', 'responsible_driver_id')) {
            $payload['responsible_driver_id'] = $this->resolveResponsibleDriverId($input, $resolvedDriverId);
        }
        if (Schema::hasColumn('vehicle_log', 'current_driver_id')) {
            $payload['current_driver_id'] = $this->resolveCurrentDriverId($input, $resolvedDriverId);
        }

        $startRouteLabel = $this->sanitizeRouteLabel((string) ($payload['recorrido_inicio'] ?? ''));
        $endRouteLabel = $this->sanitizeRouteLabel((string) ($payload['recorrido_destino'] ?? ''));
        if (empty($ruta)) {
            $fallbackRoute = [];
            $baseTs = (string) ($input['date_time'] ?? $input['sent_at'] ?? now()->toIso8601String());
            if (!is_null($latInicio) && !is_null($lngInicio)) {
                $fallbackRoute[] = [
                    'lat' => $latInicio,
                    'lng' => $lngInicio,
                    't' => $baseTs,
                    'address' => $startRouteLabel ?? '',
                    'label' => $startRouteLabel ?? 'Inicio',
                    'is_marked' => true,
                    'index' => 0,
                ];
            }
            if (!is_null($latDestino) && !is_null($lngDestino)) {
                $isDifferentEnd = is_null($latInicio) || is_null($lngInicio)
                    || abs($latDestino - $latInicio) > 0.000001
                    || abs($lngDestino - $lngInicio) > 0.000001;
                if ($isDifferentEnd || empty($fallbackRoute)) {
                    $fallbackRoute[] = [
                        'lat' => $latDestino,
                        'lng' => $lngDestino,
                        't' => $baseTs,
                        'address' => $endRouteLabel ?? '',
                        'label' => $endRouteLabel ?? 'Destino',
                        'is_marked' => true,
                        'index' => count($fallbackRoute),
                    ];
                }
            }
            $ruta = $fallbackRoute;
        }

        if (!empty($ruta)) {
            $payload['ruta_json'] = $ruta;
            $firstAddress = (string) ($ruta[0]['address'] ?? '');
            $lastAddress = (string) ($ruta[array_key_last($ruta)]['address'] ?? '');
            if (is_null($startRouteLabel) && $firstAddress !== '') {
                $payload['recorrido_inicio'] = Str::limit($firstAddress, 255, '');
            }
            if (is_null($endRouteLabel) && $lastAddress !== '') {
                $payload['recorrido_destino'] = Str::limit($lastAddress, 255, '');
            }
        }

        $rules = [
            'drivers_id' => 'nullable|integer|min:1',
            'vehicles_id' => 'required|integer|exists:vehicles,id',
            'fecha' => 'required|date',
            'kilometraje_salida' => 'required|numeric|min:0.01',
            'kilometraje_llegada' => 'nullable|numeric|gte:kilometraje_salida',
            'recorrido_inicio' => 'required|string|max:255',
            'recorrido_destino' => 'required|string|max:255',
            'abastecimiento_combustible' => 'nullable|boolean',
            'ruta_json' => 'nullable|array',
        ];
        if (Schema::hasColumn('vehicle_log', 'firma_digital')) {
            $rules['firma_digital'] = 'nullable|string';
        }
        if (Schema::hasColumn('vehicle_log', 'latitud_inicio')) {
            $rules['latitud_inicio'] = 'required|numeric|between:-90,90';
        }
        if (Schema::hasColumn('vehicle_log', 'logitud_inicio')) {
            $rules['logitud_inicio'] = 'required|numeric|between:-180,180';
        }
        if (Schema::hasColumn('vehicle_log', 'latitud_destino')) {
            $rules['latitud_destino'] = 'required|numeric|between:-90,90';
        }
        if (Schema::hasColumn('vehicle_log', 'logitud_destino')) {
            $rules['logitud_destino'] = 'required|numeric|between:-180,180';
        }
        if (Schema::hasColumn('vehicle_log', 'session_reference')) {
            $rules['session_reference'] = 'nullable|string|max:120';
        }
        if (Schema::hasColumn('vehicle_log', 'responsible_driver_id')) {
            $rules['responsible_driver_id'] = 'nullable|integer|min:1';
        }
        if (Schema::hasColumn('vehicle_log', 'current_driver_id')) {
            $rules['current_driver_id'] = 'nullable|integer|min:1';
        }

        $payload = validator($payload, $rules)->validate();

        if ((float) $payload['kilometraje_salida'] <= 0) {
            throw ValidationException::withMessages([
                'kilometraje_salida' => 'El kilometraje de salida debe ser mayor a 0.',
            ]);
        }

        if (!is_null($payload['kilometraje_llegada'] ?? null) && (float) $payload['kilometraje_llegada'] <= 0) {
            throw ValidationException::withMessages([
                'kilometraje_llegada' => 'El kilometraje de llegada debe ser mayor a 0.',
            ]);
        }

        $log = VehicleLog::create($payload);
        $this->upsertSharedSession(
            $payload['session_reference'] ?? null,
            (int) $resolvedVehicleId,
            $this->asInt($payload['responsible_driver_id'] ?? null) ?? $resolvedDriverId,
            $this->asInt($payload['current_driver_id'] ?? null) ?? $resolvedDriverId,
            $payload['fecha'] ?? null,
            null,
            (int) $log->id
        );

        $manualDistanceKm = null;
        if (!is_null($kilometrajeSalida) && !is_null($kilometrajeLlegada)) {
            $manualDistanceKm = max(0, round($kilometrajeLlegada - $kilometrajeSalida, 3));
        }

        $discrepancyKm = null;
        if (!is_null($distance) && !is_null($manualDistanceKm)) {
            $discrepancyKm = round(abs($distance - $manualDistanceKm), 3);
            if ($discrepancyKm >= 1.0) {
                Log::warning('Discrepancia detectada entre distance_km y odometro manual.', [
                    'vehicle_log_id' => $log->id,
                    'vehicle_id' => $resolvedVehicleId,
                    'driver_id' => $resolvedDriverId,
                    'distance_km_app' => $distance,
                    'distance_km_manual' => $manualDistanceKm,
                    'discrepancy_km' => $discrepancyKm,
                ]);
            }
        }

        $log->load(['vehicle', 'driver']);
        $response = $log->toArray();
        $response['distance_km_app'] = $distance;
        $response['distance_km_manual'] = $manualDistanceKm;
        $response['distance_km_discrepancy'] = $discrepancyKm;

        return response()->json($response, 201);
    }

    public function pointToPoint(Request $request)
    {
        $input = $request->all();
        $providedSegments = $this->normalizePointToPointSegments(
            $input['point_to_point_segments'] ?? $input['segments'] ?? null
        );
        $timeline = $this->normalizeTimelinePoints($input['timeline'] ?? $input['points'] ?? []);
        if (empty($providedSegments) && count($timeline) > 2) {
            $timeline = [$timeline[0], $timeline[count($timeline) - 1]];
        }

        if (empty($providedSegments) && count($timeline) < 2) {
            return response()->json([
                'message' => 'Se requieren al menos 2 puntos para generar bitacora punto a punto.',
            ], 422);
        }

        $candidateVehicleId = (int) ($input['vehicle_id'] ?? $input['vehicles_id'] ?? 0);
        $resolvedDriverId = $this->resolveDriverId(
            $request,
            $input['driver_id'] ?? $input['drivers_id'] ?? null,
            $candidateVehicleId
        );
        $resolvedVehicleId = $this->resolveVehicleId($candidateVehicleId, $resolvedDriverId);

        if (($resolvedVehicleId ?? 0) <= 0) {
            return response()->json([
                'message' => 'No se pudo resolver el vehiculo para generar bitacoras punto a punto.',
            ], 422);
        }

        $sentAt = $this->parseTimelineDate($input['sent_at'] ?? $input['fecha'] ?? null) ?? now();
        $sessionId = (int) ($input['session_id'] ?? 0);
        $sessionReference = $this->resolveSessionReference($input);
        $distanceKm = $this->asFloat($input['distance_km'] ?? $input['distancia'] ?? null);
        $responsibleDriverId = $this->resolveResponsibleDriverId($input, $resolvedDriverId);
        $currentDriverId = $this->resolveCurrentDriverId($input, $resolvedDriverId);

        $vehicle = Vehicle::query()->find($resolvedVehicleId);
        $kmStart = $this->asFloat(
            $input['kilometraje_salida']
                ?? $input['km_start']
                ?? $input['odometer_start']
                ?? ($vehicle?->kilometraje_actual ?? $vehicle?->kilometraje_inicial ?? $vehicle?->kilometraje ?? null)
        ) ?? 0.0;

        $segments = !empty($providedSegments)
            ? $this->buildProvidedPointToPointSegments($providedSegments, $distanceKm)
            : $this->buildTimelineSegments($timeline, $distanceKm);
        $created = [];
        $cursorKm = $kmStart;

        foreach ($segments as $idx => $segment) {
            $start = $segment['start'];
            $end = $segment['end'];
            $segmentKm = $segment['distance_km'];
            $segmentSignature = $this->buildPointToPointSignature($sessionId, $start, $end);

            $existing = $this->findExistingPointToPointLog(
                (int) ($resolvedDriverId ?? 0),
                (int) $resolvedVehicleId,
                $sentAt->toDateString(),
                $segmentSignature,
                (float) $start['lat'],
                (float) $start['lng'],
                (float) $end['lat'],
                (float) $end['lng']
            );
            if ($existing) {
                $created[] = $existing;
                $cursorKm = $this->asFloat($existing->kilometraje_llegada) ?? $cursorKm;
                continue;
            }

            $payload = [
                'drivers_id' => $resolvedDriverId,
                'vehicles_id' => $resolvedVehicleId,
                'fecha' => $sentAt->toDateString(),
                'kilometraje_salida' => $cursorKm,
                'kilometraje_llegada' => $cursorKm + ($segmentKm ?? 0),
                'recorrido_inicio' => Str::limit((string) (($start['address'] ?? '') ?: ($start['label'] ?? 'Punto A')), 255, ''),
                'recorrido_destino' => Str::limit((string) (($end['address'] ?? '') ?: ($end['label'] ?? 'Punto B')), 255, ''),
                'abastecimiento_combustible' => false,
                'ruta_json' => array_map(
                    fn (array $point) => array_merge($point, [
                        'segment_signature' => $segmentSignature,
                        'session_id' => $sessionId,
                    ]),
                    $segment['route_points'] ?? $this->buildSegmentRoutePoints($start, $end)
                ),
            ];
            if (Schema::hasColumn('vehicle_log', 'activo')) {
                $payload['activo'] = true;
            }
            if (Schema::hasColumn('vehicle_log', 'session_reference')) {
                $payload['session_reference'] = $sessionReference;
            }
            if (Schema::hasColumn('vehicle_log', 'responsible_driver_id')) {
                $payload['responsible_driver_id'] = $responsibleDriverId;
            }
            if (Schema::hasColumn('vehicle_log', 'current_driver_id')) {
                $payload['current_driver_id'] = $currentDriverId;
            }

            if (Schema::hasColumn('vehicle_log', 'latitud_inicio')) {
                $payload['latitud_inicio'] = $start['lat'];
            }
            if (Schema::hasColumn('vehicle_log', 'logitud_inicio')) {
                $payload['logitud_inicio'] = $start['lng'];
            }
            if (Schema::hasColumn('vehicle_log', 'latitud_destino')) {
                $payload['latitud_destino'] = $end['lat'];
            }
            if (Schema::hasColumn('vehicle_log', 'logitud_destino')) {
                $payload['logitud_destino'] = $end['lng'];
            }

            $log = VehicleLog::query()->create($payload);
            $created[] = $log;
            $cursorKm = $this->asFloat($log->kilometraje_llegada) ?? $cursorKm;
        }

        $this->upsertSharedSession(
            $sessionReference,
            (int) $resolvedVehicleId,
            $responsibleDriverId,
            $currentDriverId,
            $sentAt->toIso8601String(),
            null,
            !empty($created) ? (int) ($created[0]->id ?? 0) : null
        );

        if ($vehicle && Schema::hasColumn('vehicles', 'kilometraje_actual')) {
            $arrival = $cursorKm;
            $current = $this->asFloat($vehicle->kilometraje_actual ?? null);
            if (is_null($current) || $arrival > $current) {
                $vehicle->kilometraje_actual = $arrival;
                if (Schema::hasColumn('vehicles', 'kilometraje')) {
                    $vehicle->kilometraje = $arrival;
                }
                $vehicle->save();
            }
        }

        return response()->json([
            'message' => 'Bitacoras punto a punto registradas.',
            'session_id' => $sessionId,
            'session_reference' => $sessionReference,
            'created_count' => count($created),
            'vehicle_log_ids' => collect($created)->pluck('id')->values(),
        ], 201);
    }

    public function storeStageEvent(Request $request)
    {
        $payload = $request->validate([
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'driver_id' => 'nullable|integer|min:1',
            'responsible_driver_id' => 'nullable|integer|min:1',
            'current_driver_id' => 'nullable|integer|min:1',
            'vehicle_log_id' => 'nullable|integer|min:1',
            'session_reference' => 'nullable|string|max:120',
            'stage_name' => 'required|string|max:60',
            'event_kind' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'event_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'photo_base64' => 'required|string',
            'payload_json' => 'nullable|array',
        ]);

        $actingDriverId = $this->resolveDriverId(
            $request,
            $payload['driver_id'] ?? null,
            (int) $payload['vehicle_id']
        );
        $responsibleDriverId = $this->resolveResponsibleDriverId($payload, $actingDriverId);
        $currentDriverId = $this->resolveCurrentDriverId($payload, $actingDriverId);
        $sessionReference = $this->resolveSessionReference($payload);
        $session = $this->upsertSharedSession(
            $sessionReference,
            (int) $payload['vehicle_id'],
            $responsibleDriverId,
            $currentDriverId,
            $payload['event_at'] ?? now()->toIso8601String(),
            null,
            $this->asInt($payload['vehicle_log_id'] ?? null)
        );

        $photoPath = $this->storeBase64Image(
            (string) $payload['photo_base64'],
            'vehicle-log-stages'
        );

        $event = VehicleLogStageEvent::query()->create([
            'vehicle_log_session_id' => $session?->id,
            'vehicle_log_id' => $payload['vehicle_log_id'] ?? null,
            'session_reference' => $sessionReference,
            'vehicle_id' => (int) $payload['vehicle_id'],
            'responsible_driver_id' => $responsibleDriverId,
            'acting_driver_id' => $actingDriverId,
            'stage_name' => mb_strtoupper((string) $payload['stage_name']),
            'event_kind' => (string) ($payload['event_kind'] ?? 'stage'),
            'address' => $payload['address'] ?? null,
            'latitude' => $this->asFloat($payload['latitude'] ?? null),
            'longitude' => $this->asFloat($payload['longitude'] ?? null),
            'event_at' => $payload['event_at'] ?? now(),
            'photo_path' => $photoPath,
            'notes' => $payload['notes'] ?? null,
            'payload_json' => $payload['payload_json'] ?? null,
        ]);

        if (mb_strtoupper((string) $payload['stage_name']) === 'FINALIZAR' && $session) {
            $session->update([
                'ended_at' => $payload['event_at'] ?? now(),
                'status' => 'Finalizada',
            ]);
        }

        return response()->json([
            'message' => 'Evento de bitacora registrado.',
            'event_id' => $event->id,
            'session_reference' => $sessionReference,
            'photo_url' => Storage::disk('public')->url($photoPath),
        ], 201);
    }

    public function createReassignmentQr(Request $request)
    {
        $payload = $request->validate([
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'driver_id' => 'nullable|integer|min:1',
            'responsible_driver_id' => 'nullable|integer|min:1',
            'current_driver_id' => 'nullable|integer|min:1',
            'session_reference' => 'required|string|max:120',
            'vehicle_snapshot' => 'nullable|array',
            'session_snapshot' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
        ]);

        $actingDriverId = $this->resolveDriverId(
            $request,
            $payload['driver_id'] ?? null,
            (int) $payload['vehicle_id']
        );
        $responsibleDriverId = $this->resolveResponsibleDriverId($payload, $actingDriverId);
        $currentDriverId = $this->resolveCurrentDriverId($payload, $actingDriverId);
        $sessionReference = (string) $payload['session_reference'];

        $session = $this->upsertSharedSession(
            $sessionReference,
            (int) $payload['vehicle_id'],
            $responsibleDriverId,
            $currentDriverId,
            now()->toIso8601String()
        );

        $qrPayload = [
            'type' => 'vehicle_log_reassignment',
            'session_reference' => $sessionReference,
            'vehicle_id' => (int) $payload['vehicle_id'],
            'responsible_driver_id' => $responsibleDriverId,
            'current_driver_id' => $currentDriverId,
            'handover_driver_id' => $actingDriverId,
            'issued_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes(20)->toIso8601String(),
        ];
        $qrText = 'BOLIPOST-REASSIGN|' . base64_encode(json_encode($qrPayload, JSON_UNESCAPED_UNICODE));
        $qrBase64 = \DNS2D::getBarcodePNG($qrText, 'QRCODE', 8, 8);

        VehicleLogStageEvent::query()->create([
            'vehicle_log_session_id' => $session?->id,
            'session_reference' => $sessionReference,
            'vehicle_id' => (int) $payload['vehicle_id'],
            'responsible_driver_id' => $responsibleDriverId,
            'acting_driver_id' => $actingDriverId,
            'stage_name' => 'REASIGNAR',
            'event_kind' => 'handover_requested',
            'event_at' => now(),
            'notes' => $payload['notes'] ?? null,
            'payload_json' => $qrPayload,
        ]);

        return response()->json([
            'message' => 'QR de reasignacion generado.',
            'session_reference' => $sessionReference,
            'qr_text' => $qrText,
            'qr_image_base64' => $qrBase64,
            'responsible_driver_id' => $responsibleDriverId,
            'current_driver_id' => $currentDriverId,
            'vehicle_snapshot' => $payload['vehicle_snapshot'] ?? null,
            'session_snapshot' => $payload['session_snapshot'] ?? null,
        ]);
    }

    public function acceptReassignment(Request $request)
    {
        $payload = $request->validate([
            'qr_text' => 'required|string',
            'driver_id' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        $qrPayload = $this->decodeReassignmentPayload((string) $payload['qr_text']);
        if (!$qrPayload) {
            return response()->json([
                'message' => 'El QR de reasignacion no es valido.',
            ], 422);
        }

        $expiresAt = $this->parseTimelineDate($qrPayload['expires_at'] ?? null);
        if ($expiresAt && $expiresAt->isPast()) {
            return response()->json([
                'message' => 'El QR de reasignacion ya expiro.',
            ], 422);
        }

        $vehicleId = (int) ($qrPayload['vehicle_id'] ?? 0);
        $newCurrentDriverId = $this->resolveAuthenticatedDriverIdForReassignment(
            $request,
            $payload['driver_id'] ?? null
        );
        $responsibleDriverId = $this->asInt($qrPayload['responsible_driver_id'] ?? null);
        $sessionReference = (string) ($qrPayload['session_reference'] ?? '');
        $previousCurrentDriverId = $this->asInt($qrPayload['current_driver_id'] ?? null);

        if (($newCurrentDriverId ?? 0) <= 0) {
            return response()->json([
                'message' => 'No se pudo identificar al conductor autenticado que recibe el vehiculo. Vuelve a iniciar sesion en el segundo movil e intenta nuevamente.',
            ], 422);
        }

        if (($previousCurrentDriverId ?? 0) > 0 && $previousCurrentDriverId === $newCurrentDriverId) {
            return response()->json([
                'message' => 'La reasignacion debe ser aceptada por un conductor diferente al actual.',
            ], 422);
        }

        $session = $this->upsertSharedSession(
            $sessionReference,
            $vehicleId,
            $responsibleDriverId,
            $newCurrentDriverId,
            $qrPayload['issued_at'] ?? now()->toIso8601String()
        );

        if ($session) {
            $session->update([
                'current_driver_id' => $newCurrentDriverId,
                'last_reassigned_at' => now(),
                'status' => 'Reasignada',
            ]);
        }

        VehicleLogStageEvent::query()->create([
            'vehicle_log_session_id' => $session?->id,
            'session_reference' => $sessionReference,
            'vehicle_id' => $vehicleId,
            'responsible_driver_id' => $responsibleDriverId,
            'acting_driver_id' => $newCurrentDriverId,
            'stage_name' => 'REASIGNAR',
            'event_kind' => 'handover_accepted',
            'event_at' => now(),
            'notes' => $payload['notes'] ?? null,
            'payload_json' => [
                'previous_current_driver_id' => $previousCurrentDriverId,
                'new_current_driver_id' => $newCurrentDriverId,
                'raw_qr' => $payload['qr_text'],
            ],
        ]);

        VehicleLog::query()
            ->where('session_reference', $sessionReference)
            ->update([
                'current_driver_id' => $newCurrentDriverId,
                'responsible_driver_id' => $responsibleDriverId,
            ]);

        $this->syncVehicleAssignmentAfterReassignment(
            $vehicleId,
            $newCurrentDriverId,
            $previousCurrentDriverId
        );

        return response()->json([
            'message' => 'Bitacora reasignada correctamente.',
            'session_reference' => $sessionReference,
            'vehicle_id' => $vehicleId,
            'responsible_driver_id' => $responsibleDriverId,
            'current_driver_id' => $newCurrentDriverId,
            'previous_current_driver_id' => $previousCurrentDriverId,
            'vehicle_snapshot' => $qrPayload['vehicle_snapshot'] ?? null,
            'session_snapshot' => $qrPayload['session_snapshot'] ?? null,
        ]);
    }

    private function syncVehicleAssignmentAfterReassignment(
        int $vehicleId,
        ?int $newCurrentDriverId,
        ?int $previousCurrentDriverId
    ): void {
        if ($vehicleId <= 0 || ($newCurrentDriverId ?? 0) <= 0) {
            return;
        }

        $today = now()->toDateString();
        $now = now();

        VehicleAssignment::query()
            ->where('activo', true)
            ->where('driver_id', $newCurrentDriverId)
            ->where('vehicle_id', '!=', $vehicleId)
            ->get()
            ->each(function (VehicleAssignment $assignment) use ($today, $now): void {
                $assignment->update([
                    'fecha_fin' => $assignment->fecha_fin ?? $today,
                    'activo' => false,
                    'updated_at' => $now,
                ]);
            });

        $activeAssignmentForVehicle = VehicleAssignment::query()
            ->where('activo', true)
            ->where('vehicle_id', $vehicleId)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        if ($activeAssignmentForVehicle) {
            $activeAssignmentForVehicle->update([
                'driver_id' => $newCurrentDriverId,
                'fecha_inicio' => $today,
                'fecha_fin' => null,
                'tipo_asignacion' => $activeAssignmentForVehicle->tipo_asignacion ?: 'Fijo',
                'activo' => true,
                'updated_at' => $now,
            ]);

            VehicleAssignment::query()
                ->where('activo', true)
                ->where('vehicle_id', $vehicleId)
                ->where('id', '!=', $activeAssignmentForVehicle->id)
                ->get()
                ->each(function (VehicleAssignment $assignment) use ($today, $now): void {
                    $assignment->update([
                        'fecha_fin' => $assignment->fecha_fin ?? $today,
                        'activo' => false,
                        'updated_at' => $now,
                    ]);
                });

            $this->createVehicleUnassignmentMarker($previousCurrentDriverId, $today);
            return;
        }

        VehicleAssignment::query()->create([
            'driver_id' => $newCurrentDriverId,
            'vehicle_id' => $vehicleId,
            'tipo_asignacion' => 'Fijo',
            'fecha_inicio' => $today,
            'fecha_fin' => null,
            'activo' => true,
        ]);

        $this->createVehicleUnassignmentMarker($previousCurrentDriverId, $today);
    }

    private function createVehicleUnassignmentMarker(?int $driverId, string $effectiveDate): void
    {
        if (($driverId ?? 0) <= 0) {
            return;
        }

        VehicleAssignment::query()->create([
            'driver_id' => $driverId,
            'vehicle_id' => null,
            'tipo_asignacion' => 'Desasignado',
            'fecha_inicio' => $effectiveDate,
            'fecha_fin' => $effectiveDate,
            'activo' => false,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $segments
     * @return array<int, array{start: array<string, mixed>, end: array<string, mixed>, route_points: array<int, array<string, mixed>>, distance_km: float|null}>
     */
    private function buildProvidedPointToPointSegments(array $segments, ?float $distanceKm): array
    {
        $normalized = [];
        $rawLengths = [];

        foreach ($segments as $segment) {
            $startRaw = $segment['from'] ?? null;
            $endRaw = $segment['to'] ?? null;
            if (!is_array($startRaw) || !is_array($endRaw)) {
                continue;
            }

            $start = $this->normalizePointToPointPoint($startRaw);
            $end = $this->normalizePointToPointPoint($endRaw);
            if (!$start || !$end) {
                continue;
            }

            $len = $this->haversineKm(
                (float) $start['lat'],
                (float) $start['lng'],
                (float) $end['lat'],
                (float) $end['lng']
            );
            $intermediatePoints = collect($segment['intermediate_points'] ?? [])
                ->map(fn ($point) => $this->normalizePointToPointPoint($point))
                ->filter()
                ->values()
                ->all();
            $rawLengths[] = $len;
            $normalized[] = [
                'start' => $start,
                'end' => $end,
                'route_points' => $this->buildSegmentRoutePoints($start, $end, $intermediatePoints),
                'distance_km' => null,
            ];
        }

        if (empty($normalized)) {
            return [];
        }

        $totalRaw = array_sum($rawLengths);
        foreach ($normalized as $i => $item) {
            $piece = null;
            if (!is_null($distanceKm) && $distanceKm >= 0) {
                if ($totalRaw > 0) {
                    $piece = $distanceKm * (($rawLengths[$i] ?? 0) / $totalRaw);
                } else {
                    $piece = count($normalized) > 0 ? ($distanceKm / count($normalized)) : 0;
                }
            }
            $normalized[$i]['distance_km'] = $piece;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $start
     * @param array<string, mixed> $end
     * @param array<int, array<string, mixed>> $intermediatePoints
     * @return array<int, array<string, mixed>>
     */
    private function buildSegmentRoutePoints(array $start, array $end, array $intermediatePoints = []): array
    {
        $points = collect([$start])
            ->merge($intermediatePoints)
            ->push($end)
            ->values();

        return $points
            ->map(fn ($point) => $this->normalizePointToPointPoint($point))
            ->filter()
            ->values()
            ->map(function (array $point, int $index) {
                $point['index'] = $index;
                return $point;
            })
            ->all();
    }

    private function resolveDriverId(Request $request, mixed $candidateDriverId, int $vehicleId): ?int
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

        $assignment = VehicleAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where(function ($q) {
                $q->where('activo', true)->orWhereNull('activo');
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        if ($assignment && (int) $assignment->driver_id > 0) {
            return (int) $assignment->driver_id;
        }

        return null;
    }

    private function resolveVehicleId(int $candidateVehicleId, ?int $driverId): ?int
    {
        if ($candidateVehicleId > 0 && Vehicle::query()->whereKey($candidateVehicleId)->exists()) {
            return $candidateVehicleId;
        }

        if (($driverId ?? 0) > 0) {
            $assignment = VehicleAssignment::query()
                ->where('driver_id', (int) $driverId)
                ->where(function ($q) {
                    $q->where('activo', true)->orWhereNull('activo');
                })
                ->orderByDesc('fecha_inicio')
                ->orderByDesc('id')
                ->first();

            if ($assignment && (int) $assignment->vehicle_id > 0) {
                return (int) $assignment->vehicle_id;
            }
        }

        return null;
    }

    private function resolveAuthenticatedDriverIdForReassignment(Request $request, mixed $candidateDriverId): ?int
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

        return null;
    }

    private function asFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function normalizeRoutePoints(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $normalized = [];

        foreach ($raw as $point) {
            if (!is_array($point)) {
                continue;
            }

            $lat = $this->asFloat($point['latitude'] ?? $point['lat'] ?? null);
            $lng = $this->asFloat($point['longitude'] ?? $point['lng'] ?? null);

            if (is_null($lat) || is_null($lng)) {
                continue;
            }

            $timestamp = $point['timestamp'] ?? $point['t'] ?? Carbon::now()->toIso8601String();
            $normalized[] = [
                'lat' => $lat,
                'lng' => $lng,
                't' => is_scalar($timestamp) ? (string) $timestamp : Carbon::now()->toIso8601String(),
                'address' => (string) ($point['address'] ?? ''),
                'label' => (string) ($point['label'] ?? $point['point_label'] ?? ''),
                'is_marked' => !empty($point['isMarked']) || !empty($point['is_marked']) || !empty($point['marked']),
                'index' => is_numeric($point['index'] ?? null) ? (int) $point['index'] : null,
            ];
        }

        return $normalized;
    }

    private function normalizeTimelinePoints(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $point) {
            if (!is_array($point)) {
                continue;
            }

            $lat = $this->asFloat($point['latitude'] ?? $point['lat'] ?? null);
            $lng = $this->asFloat($point['longitude'] ?? $point['lng'] ?? null);
            if (is_null($lat) || is_null($lng)) {
                continue;
            }

            $timestamp = $point['timestamp'] ?? $point['t'] ?? Carbon::now()->toIso8601String();
            $normalized[] = [
                'lat' => $lat,
                'lng' => $lng,
                't' => is_scalar($timestamp) ? (string) $timestamp : Carbon::now()->toIso8601String(),
                'address' => (string) ($point['address'] ?? ''),
                'label' => (string) ($point['label'] ?? ''),
                'is_marked' => !empty($point['isMarked']) || !empty($point['is_marked']) || !empty($point['marked']),
                'index' => is_numeric($point['index'] ?? null) ? (int) $point['index'] : null,
            ];
        }

        usort($normalized, function (array $a, array $b) {
            return strcmp((string) ($a['t'] ?? ''), (string) ($b['t'] ?? ''));
        });

        return $normalized;
    }

    private function normalizePointToPointPoint(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $lat = $this->asFloat($raw['latitude'] ?? $raw['lat'] ?? null);
        $lng = $this->asFloat($raw['longitude'] ?? $raw['lng'] ?? null);
        if (is_null($lat) || is_null($lng)) {
            return null;
        }

        $timestamp = $raw['timestamp'] ?? $raw['t'] ?? Carbon::now()->toIso8601String();

        return [
            'lat' => $lat,
            'lng' => $lng,
            't' => is_scalar($timestamp) ? (string) $timestamp : Carbon::now()->toIso8601String(),
            'address' => (string) ($raw['address'] ?? ''),
            'label' => (string) ($raw['label'] ?? ''),
            'is_marked' => !empty($raw['isMarked']) || !empty($raw['is_marked']) || !empty($raw['marked']),
            'index' => is_numeric($raw['index'] ?? null) ? (int) $raw['index'] : null,
        ];
    }

    private function normalizePointToPointSegments(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $segment) {
            if (!is_array($segment)) {
                continue;
            }

            $from = $this->normalizePointToPointPoint($segment['from'] ?? null);
            $to = $this->normalizePointToPointPoint($segment['to'] ?? null);
            if (!$from || !$to) {
                continue;
            }

            $normalized[] = [
                'from' => $from,
                'to' => $to,
                'intermediate_points' => collect($segment['intermediate_points'] ?? [])
                    ->map(fn ($point) => $this->normalizePointToPointPoint($point))
                    ->filter()
                    ->values()
                    ->all(),
            ];
        }

        usort($normalized, function (array $a, array $b) {
            return strcmp((string) ($a['from']['t'] ?? ''), (string) ($b['from']['t'] ?? ''));
        });

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $timeline
     * @return array<int, array{start: array<string, mixed>, end: array<string, mixed>, distance_km: float|null}>
     */
    private function buildTimelineSegments(array $timeline, ?float $distanceKm): array
    {
        $segments = [];
        $rawLengths = [];

        for ($i = 0; $i < count($timeline) - 1; $i++) {
            $start = $timeline[$i];
            $end = $timeline[$i + 1];
            $len = $this->haversineKm(
                (float) $start['lat'],
                (float) $start['lng'],
                (float) $end['lat'],
                (float) $end['lng']
            );
            $rawLengths[] = $len;
            $segments[] = [
                'start' => $start,
                'end' => $end,
                'distance_km' => null,
            ];
        }

        $totalRaw = array_sum($rawLengths);
        foreach ($segments as $i => $segment) {
            $piece = null;
            if (!is_null($distanceKm) && $distanceKm >= 0) {
                if ($totalRaw > 0) {
                    $piece = $distanceKm * (($rawLengths[$i] ?? 0) / $totalRaw);
                } else {
                    $piece = count($segments) > 0 ? ($distanceKm / count($segments)) : 0;
                }
            }
            $segments[$i]['distance_km'] = $piece;
        }

        return $segments;
    }

    private function parseTimelineDate(mixed $raw): ?Carbon
    {
        if (!$raw) {
            return null;
        }

        if (is_numeric($raw)) {
            $numeric = (float) $raw;
            return $numeric > 1000000000000
                ? Carbon::createFromTimestampMs((int) $numeric)
                : Carbon::createFromTimestamp((int) $numeric);
        }

        if (is_string($raw)) {
            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function resolveSessionReference(array $input): string
    {
        $raw = trim((string) ($input['session_reference'] ?? $input['sessionRef'] ?? ''));
        if ($raw !== '') {
            return Str::limit($raw, 120, '');
        }

        return 'bitacora-' . now()->format('YmdHis') . '-' . Str::lower(Str::random(8));
    }

    private function resolveResponsibleDriverId(array $input, ?int $fallback): ?int
    {
        return $this->asInt(
            $input['responsible_driver_id']
                ?? $input['responsibleDriverId']
                ?? $input['owner_driver_id']
                ?? $fallback
        );
    }

    private function resolveCurrentDriverId(array $input, ?int $fallback): ?int
    {
        return $this->asInt(
            $input['current_driver_id']
                ?? $input['currentDriverId']
                ?? $input['acting_driver_id']
                ?? $input['driver_id']
                ?? $fallback
        );
    }

    private function asInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : null;
    }

    private function upsertSharedSession(
        ?string $sessionReference,
        int $vehicleId,
        ?int $responsibleDriverId,
        ?int $currentDriverId,
        mixed $startedAt = null,
        mixed $endedAt = null,
        ?int $originVehicleLogId = null
    ): ?VehicleLogSession {
        if (!Schema::hasTable('vehicle_log_sessions') || !$sessionReference) {
            return null;
        }

        $session = VehicleLogSession::query()->firstOrNew([
            'session_reference' => $sessionReference,
        ]);

        if (!$session->exists) {
            $session->started_at = $this->parseTimelineDate($startedAt ?? now()) ?? now();
        }

        $session->vehicle_id = $vehicleId > 0 ? $vehicleId : $session->vehicle_id;
        $session->responsible_driver_id = $responsibleDriverId ?? $session->responsible_driver_id;
        $session->current_driver_id = $currentDriverId ?? $session->current_driver_id;
        $session->origin_vehicle_log_id = $originVehicleLogId ?: $session->origin_vehicle_log_id;
        if ($endedAt) {
            $session->ended_at = $this->parseTimelineDate($endedAt);
            $session->status = 'Finalizada';
        } else {
            $session->status = $session->status ?: 'Activa';
        }

        $session->save();

        return $session;
    }

    private function storeBase64Image(string $rawBase64, string $folder): string
    {
        $normalized = preg_replace('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', '', trim($rawBase64)) ?? '';
        $binary = base64_decode($normalized, true);
        if ($binary === false) {
            throw ValidationException::withMessages([
                'photo_base64' => 'La imagen enviada no tiene un formato valido.',
            ]);
        }

        $folder = trim($folder, '/');
        $path = $folder . '/' . now()->format('Y/m') . '/' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function decodeReassignmentPayload(string $raw): ?array
    {
        $trimmed = trim($raw);
        if (str_starts_with($trimmed, 'PACKGO-REASSIGN:')) {
            $encoded = substr($trimmed, strlen('PACKGO-REASSIGN:'));
            $decoded = json_decode(urldecode($encoded), true);
            if (!is_array($decoded)) {
                return null;
            }

            return [
                'type' => $decoded['type'] ?? 'vehicle_log_reassignment',
                'issued_at' => $decoded['issued_at'] ?? $decoded['issuedAt'] ?? null,
                'expires_at' => $decoded['expires_at'] ?? $decoded['expiresAt'] ?? null,
                'vehicle_id' => $decoded['vehicle_id'] ?? $decoded['vehicleId'] ?? null,
                'session_reference' => $decoded['session_reference'] ?? $decoded['sessionReference'] ?? null,
                'responsible_driver_id' => $decoded['responsible_driver_id'] ?? $decoded['responsibleDriverId'] ?? null,
                'current_driver_id' => $decoded['current_driver_id'] ?? $decoded['currentDriverId'] ?? null,
                'handover_driver_id' => $decoded['handover_driver_id'] ?? $decoded['handoverDriverId'] ?? $decoded['driver_id'] ?? $decoded['driverId'] ?? null,
                'vehicle_snapshot' => $decoded['vehicle_snapshot'] ?? $decoded['vehicleSnapshot'] ?? null,
                'vehicle_label' => $decoded['vehicle_label'] ?? $decoded['vehicleLabel'] ?? null,
                'notes' => $decoded['notes'] ?? null,
            ];
        }

        if (!str_starts_with($trimmed, 'BOLIPOST-REASSIGN|')) {
            return null;
        }

        $encoded = substr($trimmed, strlen('BOLIPOST-REASSIGN|'));
        if ($encoded === false || $encoded === '') {
            return null;
        }

        $json = base64_decode($encoded, true);
        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $start
     * @param array<string, mixed> $end
     */
    private function buildPointToPointSignature(int $sessionId, array $start, array $end): string
    {
        return sha1(implode('|', [
            (string) $sessionId,
            (string) ($start['lat'] ?? ''),
            (string) ($start['lng'] ?? ''),
            (string) ($start['t'] ?? ''),
            (string) ($end['lat'] ?? ''),
            (string) ($end['lng'] ?? ''),
            (string) ($end['t'] ?? ''),
        ]));
    }

    private function findExistingPointToPointLog(
        int $driverId,
        int $vehicleId,
        string $date,
        string $signature,
        float $startLat,
        float $startLng,
        float $endLat,
        float $endLng
    ): ?VehicleLog
    {
        // 1) Dedupe duro por coordenadas/fecha/vehiculo/conductor.
        $byCoords = VehicleLog::query()
            ->active()
            ->where('drivers_id', $driverId)
            ->where('vehicles_id', $vehicleId)
            ->whereDate('fecha', $date)
            ->whereBetween('latitud_inicio', [$startLat - 0.00001, $startLat + 0.00001])
            ->whereBetween('logitud_inicio', [$startLng - 0.00001, $startLng + 0.00001])
            ->whereBetween('latitud_destino', [$endLat - 0.00001, $endLat + 0.00001])
            ->whereBetween('logitud_destino', [$endLng - 0.00001, $endLng + 0.00001])
            ->latest('id')
            ->first();
        if ($byCoords) {
            return $byCoords;
        }

        // 2) Fallback por firma en JSON (cuando el motor DB soporte consultas JSON).
        return VehicleLog::query()
            ->active()
            ->where('drivers_id', $driverId)
            ->where('vehicles_id', $vehicleId)
            ->whereDate('fecha', $date)
            ->whereJsonContains('ruta_json', ['segment_signature' => $signature])
            ->latest('id')
            ->first();
    }

    private function sanitizeRouteLabel(string $raw): ?string
    {
        $label = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);
        if ($label === '') {
            return null;
        }

        $lower = mb_strtolower($label);
        if ($this->looksLikeCoordinateLabel($label) || $this->looksLikeDateTimeLabel($label)) {
            return null;
        }

        if (in_array($lower, [
            'sincronizacion app',
            'en ruta',
            'no definido',
            'offline snapshot',
            'heartbeat',
            'punto a',
            'punto b',
            'punto de salida',
            'punto de llegada',
            'ruta iniciada',
            'ubicacion marcada',
            'ubicación marcada',
        ], true)) {
            return null;
        }

        return Str::limit($label, 255, '');
    }

    private function looksLikeCoordinateLabel(string $value): bool
    {
        return preg_match('/^\s*-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?\s*$/', $value) === 1;
    }

    private function looksLikeDateTimeLabel(string $value): bool
    {
        return preg_match('/^\d{1,2}\/\d{1,2}\/\d{4},\s*\d{1,2}:\d{2}/', $value) === 1
            || preg_match('/^\d{4}-\d{2}-\d{2}t\d{2}:\d{2}/i', $value) === 1;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($a)));
    }
}
