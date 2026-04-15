<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceAppointment;
use App\Models\MaintenanceType;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaintenanceRequestApiController extends Controller
{
    public function index(Request $request)
    {
        $authUser = $request->user();
        $vehicleId = (int) $request->query('vehicle_id', 0);

        $query = MaintenanceAppointment::query()
            ->with(['vehicle', 'driver', 'tipoMantenimiento', 'requestedBy'])
            ->orderByDesc('solicitud_fecha')
            ->orderByDesc('id');

        if ($authUser) {
            $driver = Driver::query()->where('user_id', $authUser->id)->first();
            if ($driver) {
                $query->where(function ($inner) use ($authUser, $driver) {
                    $inner->where('requested_by_user_id', $authUser->id)
                        ->orWhere('driver_id', (int) $driver->id);
                });
            } else {
                $query->where('requested_by_user_id', $authUser->id);
            }
        } elseif ($vehicleId > 0) {
            $query->where('vehicle_id', $vehicleId);
        } else {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($request->filled('status')) {
            $query->where('estado', (string) $request->query('status'));
        }

        $appointments = $query->get()->map(fn (MaintenanceAppointment $appointment) => $this->mapAppointment($appointment));

        return response()->json([
            'data' => $appointments->values(),
        ]);
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'maintenance_type_id' => 'nullable|integer|exists:maintenance_types,id',
            'maintenance_type_name' => 'nullable|string|max:255',
            'fecha_programada' => 'nullable|date|after_or_equal:today',
            'es_accidente' => 'nullable|boolean',
            'photo_base64' => 'nullable|string',
        ]);
        Log::info('maintenance_request.store.received', [
            'auth_user_id' => $request->user()?->id,
            'vehicle_id' => $payload['vehicle_id'] ?? null,
            'maintenance_type_id' => $payload['maintenance_type_id'] ?? null,
            'maintenance_type_name' => $payload['maintenance_type_name'] ?? null,
            'fecha_programada' => $payload['fecha_programada'] ?? null,
            'es_accidente' => (bool) ($payload['es_accidente'] ?? false),
            'has_photo' => !empty($payload['photo_base64']),
        ]);

        $authUser = $request->user();
        $vehicle = $this->resolveVehicle($authUser?->id, $payload['vehicle_id'] ?? null);
        if (!$vehicle) {
            Log::warning('maintenance_request.store.vehicle_not_resolved', [
                'auth_user_id' => $authUser?->id,
                'requested_vehicle_id' => $payload['vehicle_id'] ?? null,
            ]);
            throw ValidationException::withMessages([
                'vehicle_id' => 'No se pudo resolver un vehiculo asignado para la solicitud de mantenimiento.',
            ]);
        }

        $driver = $this->resolveDriver($authUser?->id, (int) $vehicle->id);
        $type = $this->resolveMaintenanceType(
            $vehicle,
            $payload['maintenance_type_id'] ?? null,
            $payload['maintenance_type_name'] ?? null
        );

        if (!$type) {
            Log::warning('maintenance_request.store.type_not_resolved', [
                'auth_user_id' => $authUser?->id,
                'vehicle_id' => $vehicle->id,
                'maintenance_type_id' => $payload['maintenance_type_id'] ?? null,
                'maintenance_type_name' => $payload['maintenance_type_name'] ?? null,
            ]);
            throw ValidationException::withMessages([
                'maintenance_type_id' => 'No se pudo resolver un tipo de mantenimiento valido para el vehiculo.',
            ]);
        }

        $scheduledAt = !empty($payload['fecha_programada'])
            ? \Carbon\Carbon::parse((string) $payload['fecha_programada'])
            : now();
        $evidencePath = $this->storeEvidenceImage($payload['photo_base64'] ?? null, (int) $vehicle->id);

        $existingPendingAppointment = MaintenanceAppointment::query()
            ->where('vehicle_id', (int) $vehicle->id)
            ->where('tipo_mantenimiento_id', (int) $type->id)
            ->where('estado', 'Pendiente')
            ->when($driver?->id, fn ($query) => $query->where('driver_id', (int) $driver->id))
            ->when($authUser?->id, fn ($query) => $query->where('requested_by_user_id', (int) $authUser->id))
            ->orderByDesc('id')
            ->first();

        if ($existingPendingAppointment) {
            $existingPendingAppointment->fecha_programada = $scheduledAt;
            $existingPendingAppointment->solicitud_fecha = now();
            $existingPendingAppointment->es_accidente = (bool) ($payload['es_accidente'] ?? false);
            if (!empty($evidencePath)) {
                $existingPendingAppointment->evidencia_path = $evidencePath;
            }
            $existingPendingAppointment->save();
            $existingPendingAppointment->load(['vehicle', 'driver', 'tipoMantenimiento', 'requestedBy']);
            Log::info('maintenance_request.store.updated_existing_pending', [
                'appointment_id' => $existingPendingAppointment->id,
                'vehicle_id' => $existingPendingAppointment->vehicle_id,
                'driver_id' => $existingPendingAppointment->driver_id,
                'tipo_mantenimiento_id' => $existingPendingAppointment->tipo_mantenimiento_id,
                'fecha_programada' => optional($existingPendingAppointment->fecha_programada)->toIso8601String(),
            ]);

            return response()->json([
                'message' => 'Ya existia una solicitud pendiente. Se actualizo con la nueva informacion enviada desde el movil.',
                'data' => $this->mapAppointment($existingPendingAppointment),
                'deduplicated' => true,
            ], 200);
        }

        $appointment = DB::transaction(function () use ($authUser, $driver, $type, $vehicle, $payload, $evidencePath, $scheduledAt) {
            $appointment = MaintenanceAppointment::create([
                'vehicle_id' => (int) $vehicle->id,
                'driver_id' => $driver?->id,
                'requested_by_user_id' => $authUser?->id,
                'tipo_mantenimiento_id' => (int) $type->id,
                'fecha_programada' => $scheduledAt,
                'solicitud_fecha' => now(),
                'origen_solicitud' => 'mobile_driver',
                'es_accidente' => (bool) ($payload['es_accidente'] ?? false),
                'evidencia_path' => $evidencePath,
                'estado' => 'Pendiente',
            ]);

            if (Schema::hasTable('maintenance_alerts')) {
                $plate = (string) ($vehicle->placa ?? 'N/A');
                $typeName = (string) ($type->nombre ?? 'mantenimiento');
                $driverName = (string) ($driver?->nombre ?? ($authUser?->name ?? 'Conductor'));
                $message = "Solicitud movil de {$typeName} para vehiculo {$plate} por {$driverName}.";
                $currentKm = $this->resolveVehicleCurrentKilometraje($vehicle);
                $requestedSnapshot = $this->buildRequestedAlertKilometrageSnapshot($vehicle, $type);

                MaintenanceAlert::create([
                    'vehicle_id' => (int) $vehicle->id,
                    'maintenance_type_id' => (int) $type->id,
                    'maintenance_appointment_id' => (int) $appointment->id,
                    'tipo' => 'Solicitud',
                    'mensaje' => $message,
                    'leida' => false,
                    'status' => MaintenanceAlert::STATUS_REQUESTED,
                    'fecha_resolucion' => null,
                    'usuario_id' => null,
                    'kilometraje_actual' => $currentKm,
                    'kilometraje_objetivo' => $requestedSnapshot['target_km'],
                    'faltante_km' => $requestedSnapshot['remaining_km'],
                ]);
            }

            return $appointment;
        });

        $appointment->load(['vehicle', 'driver', 'tipoMantenimiento', 'requestedBy']);
        Log::info('maintenance_request.store.created', [
            'appointment_id' => $appointment->id,
            'vehicle_id' => $appointment->vehicle_id,
            'driver_id' => $appointment->driver_id,
            'tipo_mantenimiento_id' => $appointment->tipo_mantenimiento_id,
            'fecha_programada' => optional($appointment->fecha_programada)->toIso8601String(),
        ]);

        return response()->json([
            'message' => 'Solicitud de mantenimiento registrada correctamente.',
            'data' => $this->mapAppointment($appointment),
        ], 201);
    }

    private function mapAppointment(MaintenanceAppointment $appointment): array
    {
        return [
            'id' => (int) $appointment->id,
            'vehicle_id' => (int) $appointment->vehicle_id,
            'vehicle_plate' => (string) ($appointment->vehicle?->placa ?? ''),
            'driver_id' => $appointment->driver_id ? (int) $appointment->driver_id : null,
            'maintenance_type_id' => $appointment->tipo_mantenimiento_id ? (int) $appointment->tipo_mantenimiento_id : null,
            'maintenance_type_name' => (string) ($appointment->tipoMantenimiento?->nombre ?? ''),
            'fecha_programada' => optional($appointment->fecha_programada)->toIso8601String(),
            'solicitud_fecha' => optional($appointment->solicitud_fecha)->toIso8601String(),
            'estado' => (string) $appointment->estado,
            'es_accidente' => (bool) $appointment->es_accidente,
            'evidencia_path' => $appointment->evidencia_path ? route('maintenance-appointments.evidence', $appointment) : null,
            'request_document_url' => $appointment->evidencia_path ? route('maintenance-appointments.evidence', $appointment) : null,
            'formulario_documento_path' => $appointment->formulario_documento_path ? route('maintenance-appointments.form', $appointment) : null,
            'form_document_url' => $appointment->formulario_documento_path ? route('maintenance-appointments.form', $appointment) : null,
            'origen_solicitud' => (string) ($appointment->origen_solicitud ?? 'mobile_driver'),
        ];
    }

    private function resolveVehicle(?int $authUserId, mixed $vehicleId): ?Vehicle
    {
        $candidateId = (int) ($vehicleId ?? 0);
        if (($authUserId ?? 0) <= 0) {
            return $candidateId > 0 ? Vehicle::query()->find($candidateId) : null;
        }

        $driver = Driver::query()->where('user_id', $authUserId)->first();
        if (!$driver) {
            return $candidateId > 0 ? Vehicle::query()->find($candidateId) : null;
        }

        $assignment = VehicleAssignment::query()
            ->where('driver_id', (int) $driver->id)
            ->where(function ($q) {
                $q->where('activo', true)->orWhereNull('activo');
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        $assignedVehicle = $assignment ? Vehicle::query()->find((int) $assignment->vehicle_id) : null;
        if ($assignedVehicle) {
            return $assignedVehicle;
        }

        return $candidateId > 0 ? Vehicle::query()->find($candidateId) : null;
    }

    private function resolveDriver(?int $authUserId, int $vehicleId): ?Driver
    {
        if (($authUserId ?? 0) > 0) {
            $driver = Driver::query()->where('user_id', $authUserId)->first();
            if ($driver) {
                return $driver;
            }
        }

        if ($vehicleId <= 0) {
            return null;
        }

        $assignment = VehicleAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where(function ($q) {
                $q->where('activo', true)->orWhereNull('activo');
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        return $assignment ? Driver::query()->find((int) $assignment->driver_id) : null;
    }

    private function resolveMaintenanceType(Vehicle $vehicle, mixed $typeId, mixed $typeName): ?MaintenanceType
    {
        $query = MaintenanceType::query()->applicableToVehicle($vehicle);

        $candidateId = (int) ($typeId ?? 0);
        if ($candidateId > 0) {
            return (clone $query)->whereKey($candidateId)->first();
        }

        $candidateName = trim((string) ($typeName ?? ''));
        if ($candidateName === '') {
            return null;
        }

        return (clone $query)
            ->where(function ($q) use ($candidateName) {
                $lowered = Str::lower($candidateName);
                $q->whereRaw('LOWER(nombre) = ?', [$lowered])
                    ->orWhereRaw('LOWER(nombre) like ?', ['%' . $lowered . '%']);
            })
            ->orderBy('nombre')
            ->first();
    }

    private function storeEvidenceImage(?string $photoBase64, int $vehicleId): ?string
    {
        $encoded = trim((string) ($photoBase64 ?? ''));
        if ($encoded === '') {
            return null;
        }

        if (Str::startsWith($encoded, 'data:')) {
            $encoded = explode(',', $encoded, 2)[1] ?? '';
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false) {
            throw ValidationException::withMessages([
                'photo_base64' => 'La imagen de evidencia no tiene un formato base64 valido.',
            ]);
        }

        $path = sprintf(
            'maintenance-appointments/%d/request-%s.jpg',
            $vehicleId,
            now()->format('YmdHis') . '-' . Str::lower(Str::random(8))
        );

        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function resolveVehicleCurrentKilometraje(Vehicle $vehicle): ?float
    {
        $current = $vehicle->kilometraje_actual ?? $vehicle->kilometraje_inicial ?? $vehicle->kilometraje;
        return is_numeric($current) ? (float) $current : null;
    }

    /**
     * @return array{target_km: ?float, remaining_km: ?float}
     */
    private function buildRequestedAlertKilometrageSnapshot(Vehicle $vehicle, MaintenanceType $type): array
    {
        $currentKm = $this->resolveVehicleCurrentKilometraje($vehicle);
        if ($currentKm === null) {
            return ['target_km' => null, 'remaining_km' => null];
        }

        $interval = null;
        if ($type->cada_km !== null) {
            $interval = (float) $type->cada_km;
        } elseif ($type->intervalo_km_init !== null) {
            $interval = (float) $type->intervalo_km_init;
        } elseif ($type->intervalo_km !== null) {
            $interval = (float) $type->intervalo_km;
        } elseif ($type->intervalo_km_fh !== null) {
            $interval = (float) $type->intervalo_km_fh;
        }

        if ($interval !== null && $interval > 0) {
            return [
                'target_km' => $currentKm + $interval,
                'remaining_km' => $interval,
            ];
        }

        return [
            'target_km' => $currentKm,
            'remaining_km' => 0.0,
        ];
    }
}
