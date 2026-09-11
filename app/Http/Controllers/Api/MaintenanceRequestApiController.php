<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceAppointment;
use App\Models\MaintenanceType;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaintenanceRequestApiController extends Controller
{
    public function externalVehicles()
    {
        $today = now()->toDateString();
        $relations = ['assignments' => function ($query) use ($today): void {
            $query
                ->with('driver')
                ->where('activo', true)
                ->where(fn ($dates) => $dates->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $today))
                ->where(fn ($dates) => $dates->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $today))
                ->latest('fecha_inicio')
                ->latest('id');
        }];
        if (Schema::hasTable('vehicle_brands')) {
            $relations[] = 'brand';
        }
        if (Schema::hasTable('vehicle_classes')) {
            $relations[] = 'vehicleClass';
        }

        $vehicles = Vehicle::query()
            ->where('activo', true)
            ->with($relations)
            ->orderBy('placa')
            ->get()
            ->map(function (Vehicle $vehicle): array {
                $assignment = $vehicle->assignments->first();
                $rawBrand = trim((string) $vehicle->getRawOriginal('marca'));

                return [
                    'id' => (int) $vehicle->id,
                    'placa' => (string) $vehicle->placa,
                    'marca' => $vehicle->relationLoaded('brand') ? ($vehicle->brand?->nombre ?: ($rawBrand ?: null)) : ($rawBrand ?: null),
                    'modelo' => $vehicle->modelo,
                    'anio' => $vehicle->anio !== null ? (int) $vehicle->anio : null,
                    'color' => $vehicle->color,
                    'tipo_combustible' => $vehicle->tipo_combustible,
                    'maintenance_form_type' => $vehicle->maintenance_form_type,
                    'vehicle_class_id' => $vehicle->vehicle_class_id ? (int) $vehicle->vehicle_class_id : null,
                    'vehicle_class' => $vehicle->relationLoaded('vehicleClass') ? $vehicle->vehicleClass?->nombre : null,
                    'kilometraje_actual' => $this->resolveVehicleCurrentKilometraje($vehicle),
                    'operational_status' => $vehicle->operational_status,
                    'activo' => (bool) $vehicle->activo,
                    'disponible_para_mantenimiento' => ! $vehicle->isInMaintenance(),
                    'tiene_asignacion_activa' => $assignment !== null,
                    'asignacion_activa' => $assignment ? [
                        'id' => (int) $assignment->id,
                        'driver_id' => $assignment->driver_id ? (int) $assignment->driver_id : null,
                        'conductor' => $assignment->driver?->nombre,
                        'fecha_inicio' => $assignment->fecha_inicio?->toIso8601String(),
                        'fecha_fin' => $assignment->fecha_fin?->toIso8601String(),
                    ] : null,
                ];
            })
            ->values();

        return response()->json(['count' => $vehicles->count(), 'data' => $vehicles]);
    }

    public function externalDrivers()
    {
        $today = now()->toDateString();
        $drivers = Driver::query()
            ->where('activo', true)
            ->with(['assignments' => function ($query) use ($today): void {
                $query
                    ->with('vehicle')
                    ->where('activo', true)
                    ->where(fn ($dates) => $dates->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $today))
                    ->where(fn ($dates) => $dates->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $today))
                    ->latest('fecha_inicio')
                    ->latest('id');
            }])
            ->orderBy('nombre')
            ->get()
            ->map(function (Driver $driver): array {
                $assignment = $driver->assignments->first();

                return [
                    'id' => (int) $driver->id,
                    'nombre' => (string) $driver->nombre,
                    'licencia' => $driver->licencia,
                    'tipo_licencia' => $driver->tipo_licencia,
                    'fecha_vencimiento_licencia' => $driver->fecha_vencimiento_licencia?->toDateString(),
                    'telefono' => $driver->telefono,
                    'email' => $driver->email,
                    'activo' => (bool) $driver->activo,
                    'licencia_vencida' => $driver->isLicenseExpired(),
                    'tiene_asignacion_activa' => $assignment !== null,
                    'asignacion_activa' => $assignment ? [
                        'id' => (int) $assignment->id,
                        'vehicle_id' => $assignment->vehicle_id ? (int) $assignment->vehicle_id : null,
                        'placa' => $assignment->vehicle?->placa,
                        'fecha_inicio' => $assignment->fecha_inicio?->toIso8601String(),
                        'fecha_fin' => $assignment->fecha_fin?->toIso8601String(),
                    ] : null,
                ];
            })
            ->values();

        return response()->json(['count' => $drivers->count(), 'data' => $drivers]);
    }

    public function externalTypes(Request $request)
    {
        $validated = $request->validate([
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
        ]);
        $vehicle = ! empty($validated['vehicle_id'])
            ? Vehicle::query()->with('vehicleClass')->find((int) $validated['vehicle_id'])
            : null;
        $types = MaintenanceType::query()
            ->active()
            ->when($vehicle, fn ($query) => $query->applicableToVehicle($vehicle))
            ->orderBy('nombre')
            ->get()
            ->map(fn (MaintenanceType $type): array => [
                'id' => (int) $type->id,
                'nombre' => (string) $type->nombre,
                'descripcion' => $type->descripcion,
                'categoria' => $type->categoria,
                'categoria_label' => $type->categoria_label,
                'es_preventivo' => (bool) $type->es_preventivo,
                'cada_km' => $type->cada_km !== null ? (int) $type->cada_km : null,
                'intervalo_km' => $type->intervalo_km !== null ? (int) $type->intervalo_km : null,
                'km_alerta_previa' => $type->km_alerta_previa !== null ? (int) $type->km_alerta_previa : null,
                'maintenance_form_type' => $type->maintenance_form_type,
                'vehicle_class_id' => $type->vehicle_class_id ? (int) $type->vehicle_class_id : null,
                'activo' => (bool) ($type->activo ?? true),
            ])
            ->values();

        return response()->json([
            'vehicle_id' => $vehicle?->id,
            'count' => $types->count(),
            'data' => $types,
        ]);
    }

    public function externalIndex(Request $request)
    {
        $validated = $request->validate([
            'vehicle_id' => ['nullable', 'integer', 'min:1'],
            'driver_id' => ['nullable', 'integer', 'min:1'],
            'maintenance_type_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:Pendiente,Aprobado,Realizado,Rechazado,Cancelado'],
            'es_accidente' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'scheduled_from' => ['nullable', 'date'],
            'scheduled_to' => ['nullable', 'date', 'after_or_equal:scheduled_from'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = MaintenanceAppointment::query()
            ->active()
            ->with(['vehicle.brand', 'vehicle.vehicleClass', 'driver', 'tipoMantenimiento', 'requestedBy', 'approvedBy'])
            ->orderByDesc('solicitud_fecha')
            ->orderByDesc('id');

        foreach (['vehicle_id', 'driver_id'] as $field) {
            if (! empty($validated[$field])) {
                $query->where($field, (int) $validated[$field]);
            }
        }
        if (! empty($validated['maintenance_type_id'])) {
            $query->where('tipo_mantenimiento_id', (int) $validated['maintenance_type_id']);
        }
        if (! empty($validated['status'])) {
            $query->where('estado', $validated['status']);
        }
        if (array_key_exists('es_accidente', $validated)) {
            $query->where('es_accidente', (bool) $validated['es_accidente']);
        }
        if (! empty($validated['date_from'])) {
            $query->whereDate(DB::raw('COALESCE(solicitud_fecha, created_at)'), '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->whereDate(DB::raw('COALESCE(solicitud_fecha, created_at)'), '<=', $validated['date_to']);
        }
        if (! empty($validated['scheduled_from'])) {
            $query->whereDate('fecha_programada', '>=', $validated['scheduled_from']);
        }
        if (! empty($validated['scheduled_to'])) {
            $query->whereDate('fecha_programada', '<=', $validated['scheduled_to']);
        }
        if (! empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($builder) use ($search): void {
                $builder->where('estado', 'like', '%'.$search.'%')
                    ->orWhereHas('vehicle', fn ($vehicles) => $vehicles->where('placa', 'like', '%'.$search.'%'))
                    ->orWhereHas('driver', fn ($drivers) => $drivers->where('nombre', 'like', '%'.$search.'%'))
                    ->orWhereHas('tipoMantenimiento', fn ($types) => $types->where('nombre', 'like', '%'.$search.'%'));
            });
        }

        return response()->json(
            $query->paginate((int) ($validated['per_page'] ?? 20))
                ->through(fn (MaintenanceAppointment $appointment): array => $this->mapExternalAppointment($appointment))
        );
    }

    public function externalStore(Request $request)
    {
        $payload = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'maintenance_type_id' => ['required', 'integer', 'exists:maintenance_types,id'],
            'fecha_programada' => ['required', 'date', 'after:now'],
            'es_accidente' => ['nullable', 'boolean'],
            'evidencia' => ['nullable', 'image', 'max:5120'],
            'photo_base64' => ['nullable', 'string'],
            'formulario_documento' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $vehicle = Vehicle::query()->with('vehicleClass')->findOrFail((int) $payload['vehicle_id']);
        if (! (bool) $vehicle->activo) {
            throw ValidationException::withMessages(['vehicle_id' => 'El vehiculo seleccionado esta inactivo.']);
        }
        if ($vehicle->isInMaintenance()) {
            throw ValidationException::withMessages(['vehicle_id' => 'El vehiculo ya se encuentra en mantenimiento.']);
        }

        $type = MaintenanceType::query()
            ->active()
            ->applicableToVehicle($vehicle)
            ->whereKey((int) $payload['maintenance_type_id'])
            ->first();
        if (! $type) {
            throw ValidationException::withMessages([
                'maintenance_type_id' => 'El tipo de mantenimiento no corresponde al vehiculo seleccionado.',
            ]);
        }

        $driver = ! empty($payload['driver_id'])
            ? Driver::query()->where('activo', true)->find((int) $payload['driver_id'])
            : $this->resolveDriver(null, (int) $vehicle->id);
        if (! empty($payload['driver_id']) && ! $driver) {
            throw ValidationException::withMessages(['driver_id' => 'El conductor seleccionado esta inactivo.']);
        }

        $evidencePath = $request->hasFile('evidencia')
            ? $request->file('evidencia')->store('maintenance-appointments/'.(int) $vehicle->id, 'public')
            : $this->storeEvidenceImage($payload['photo_base64'] ?? null, (int) $vehicle->id);
        $formPath = $request->hasFile('formulario_documento')
            ? $request->file('formulario_documento')->store('maintenance-appointment-forms', 'public')
            : null;
        $oldPaths = [];

        try {
            $appointment = DB::transaction(function () use ($request, $payload, $vehicle, $driver, $type, $evidencePath, $formPath, &$oldPaths): MaintenanceAppointment {
                $appointment = MaintenanceAppointment::query()
                    ->where('vehicle_id', (int) $vehicle->id)
                    ->where('tipo_mantenimiento_id', (int) $type->id)
                    ->where('estado', MaintenanceAppointment::STATUS_PENDING)
                    ->orderByDesc('id')
                    ->first();

                if (! $appointment) {
                    $appointment = new MaintenanceAppointment;
                } else {
                    $oldPaths = array_filter([
                        $evidencePath ? $appointment->evidencia_path : null,
                        $formPath ? $appointment->formulario_documento_path : null,
                    ]);
                }

                $externalToken = $request->attributes->get('external_api_token');
                $appointment->fill([
                    'vehicle_id' => (int) $vehicle->id,
                    'driver_id' => $driver?->id,
                    'requested_by_user_id' => $externalToken?->user_id,
                    'tipo_mantenimiento_id' => (int) $type->id,
                    'fecha_programada' => $payload['fecha_programada'],
                    'solicitud_fecha' => now(),
                    'origen_solicitud' => 'external_api',
                    'es_accidente' => (bool) ($payload['es_accidente'] ?? false),
                    'evidencia_path' => $evidencePath ?: $appointment->evidencia_path,
                    'formulario_documento_path' => $formPath ?: $appointment->formulario_documento_path,
                    'estado' => MaintenanceAppointment::STATUS_PENDING,
                    'activo' => true,
                ])->save();

                $this->syncExternalRequestAlert($appointment, $vehicle, $driver, $type);

                return $appointment;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete(array_filter([$evidencePath, $formPath]));
            throw $exception;
        }

        Storage::disk('public')->delete($oldPaths);
        $appointment->load(['vehicle.brand', 'vehicle.vehicleClass', 'driver', 'tipoMantenimiento', 'requestedBy', 'approvedBy']);

        return response()->json([
            'message' => $appointment->wasRecentlyCreated
                ? 'Solicitud de mantenimiento registrada correctamente.'
                : 'La solicitud pendiente existente fue actualizada correctamente.',
            'deduplicated' => ! $appointment->wasRecentlyCreated,
            'data' => $this->mapExternalAppointment($appointment),
        ], $appointment->wasRecentlyCreated ? 201 : 200);
    }

    public function externalEvidence(MaintenanceAppointment $maintenanceAppointment)
    {
        abort_if(Schema::hasColumn('maintenance_appointments', 'activo') && ! $maintenanceAppointment->activo, 404);

        return $this->serveExternalFile((string) $maintenanceAppointment->evidencia_path, 'Evidencia de solicitud no encontrada.');
    }

    public function externalForm(MaintenanceAppointment $maintenanceAppointment)
    {
        abort_if(Schema::hasColumn('maintenance_appointments', 'activo') && ! $maintenanceAppointment->activo, 404);

        return $this->serveExternalFile((string) $maintenanceAppointment->formulario_documento_path, 'Documento de formulario no encontrado.');
    }

    public function index(Request $request)
    {
        return $this->indexInternal($request, false);
    }

    public function indexMobile(Request $request)
    {
        return $this->indexInternal($request, true);
    }

    private function indexInternal(Request $request, bool $mobileScoped = false)
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

        $appointments = $query->get()->map(fn (MaintenanceAppointment $appointment) => $this->mapAppointment($appointment, $mobileScoped));

        return response()->json([
            'data' => $appointments->values(),
        ]);
    }

    public function store(Request $request)
    {
        return $this->storeInternal($request, false);
    }

    public function storeMobile(Request $request)
    {
        return $this->storeInternal($request, true);
    }

    private function storeInternal(Request $request, bool $mobileScoped = false)
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
            'has_photo' => ! empty($payload['photo_base64']),
        ]);

        $authUser = $request->user();
        $vehicle = $this->resolveVehicle($authUser?->id, $payload['vehicle_id'] ?? null);
        if (! $vehicle) {
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
            $payload['maintenance_type_name'] ?? null,
            $mobileScoped,
        );

        if (! $type) {
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

        $scheduledAt = ! empty($payload['fecha_programada'])
            ? Carbon::parse((string) $payload['fecha_programada'])
            : now();
        $evidencePath = $this->storeEvidenceImage($payload['photo_base64'] ?? null, (int) $vehicle->id);

        $existingPendingAppointment = MaintenanceAppointment::query()
            ->where('vehicle_id', (int) $vehicle->id)
            ->where('tipo_mantenimiento_id', (int) $type->id)
            ->where('estado', MaintenanceAppointment::STATUS_PENDING)
            ->orderByDesc('id')
            ->first();

        if ($existingPendingAppointment) {
            $existingPendingAppointment->fecha_programada = $scheduledAt;
            $existingPendingAppointment->solicitud_fecha = now();
            $existingPendingAppointment->es_accidente = (bool) ($payload['es_accidente'] ?? false);
            if (Schema::hasColumn('maintenance_appointments', 'activo')) {
                $existingPendingAppointment->activo = true;
            }
            if (! empty($evidencePath)) {
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
                'data' => $this->mapAppointment($existingPendingAppointment, $mobileScoped),
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
                'estado' => MaintenanceAppointment::STATUS_PENDING,
                'activo' => true,
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
            'data' => $this->mapAppointment($appointment, $mobileScoped),
        ], 201);
    }

    private function mapExternalAppointment(MaintenanceAppointment $appointment): array
    {
        $vehicle = $appointment->vehicle;
        $driver = $appointment->driver;
        $type = $appointment->tipoMantenimiento;

        return [
            'id' => (int) $appointment->id,
            'vehicle_id' => $appointment->vehicle_id ? (int) $appointment->vehicle_id : null,
            'vehicle' => $vehicle ? [
                'id' => (int) $vehicle->id,
                'placa' => (string) $vehicle->placa,
                'marca' => $vehicle->brand?->nombre ?: ($vehicle->marca ?: null),
                'modelo' => $vehicle->modelo,
                'anio' => $vehicle->anio !== null ? (int) $vehicle->anio : null,
                'vehicle_class' => $vehicle->vehicleClass?->nombre,
                'maintenance_form_type' => $vehicle->maintenance_form_type,
                'kilometraje_actual' => $this->resolveVehicleCurrentKilometraje($vehicle),
                'operational_status' => $vehicle->operational_status,
            ] : null,
            'driver_id' => $appointment->driver_id ? (int) $appointment->driver_id : null,
            'driver' => $driver ? [
                'id' => (int) $driver->id,
                'nombre' => (string) $driver->nombre,
                'licencia' => $driver->licencia,
                'tipo_licencia' => $driver->tipo_licencia,
                'telefono' => $driver->telefono,
                'email' => $driver->email,
            ] : null,
            'maintenance_type_id' => $appointment->tipo_mantenimiento_id ? (int) $appointment->tipo_mantenimiento_id : null,
            'maintenance_type' => $type ? [
                'id' => (int) $type->id,
                'nombre' => (string) $type->nombre,
                'descripcion' => $type->descripcion,
                'categoria' => $type->categoria,
                'categoria_label' => $type->categoria_label,
                'es_preventivo' => (bool) $type->es_preventivo,
                'cada_km' => $type->cada_km !== null ? (int) $type->cada_km : null,
            ] : null,
            'fecha_programada' => $appointment->fecha_programada?->toIso8601String(),
            'solicitud_fecha' => $appointment->solicitud_fecha?->toIso8601String(),
            'estado' => (string) $appointment->estado,
            'es_accidente' => (bool) $appointment->es_accidente,
            'origen_solicitud' => (string) $appointment->origen_solicitud,
            'evidencia_url' => $appointment->evidencia_path
                ? route('api.mantenimientos.evidence', $appointment)
                : null,
            'formulario_documento_url' => $appointment->formulario_documento_path
                ? route('api.mantenimientos.form', $appointment)
                : null,
            'requested_by' => $appointment->requestedBy ? [
                'id' => (int) $appointment->requestedBy->id,
                'name' => (string) $appointment->requestedBy->name,
            ] : null,
            'approved_at' => $appointment->approved_at?->toIso8601String(),
            'approved_by' => $appointment->approvedBy ? [
                'id' => (int) $appointment->approvedBy->id,
                'name' => (string) $appointment->approvedBy->name,
            ] : null,
            'created_at' => $appointment->created_at?->toIso8601String(),
            'updated_at' => $appointment->updated_at?->toIso8601String(),
        ];
    }

    private function syncExternalRequestAlert(
        MaintenanceAppointment $appointment,
        Vehicle $vehicle,
        ?Driver $driver,
        MaintenanceType $type
    ): void {
        if (! Schema::hasTable('maintenance_alerts')) {
            return;
        }

        $currentKm = $this->resolveVehicleCurrentKilometraje($vehicle);
        $snapshot = $this->buildRequestedAlertKilometrageSnapshot($vehicle, $type);
        MaintenanceAlert::query()->updateOrCreate(
            [
                'maintenance_appointment_id' => (int) $appointment->id,
                'tipo' => 'Solicitud',
            ],
            [
                'vehicle_id' => (int) $vehicle->id,
                'maintenance_type_id' => (int) $type->id,
                'mensaje' => sprintf(
                    'Solicitud API de %s para vehiculo %s por %s.',
                    (string) $type->nombre,
                    (string) $vehicle->placa,
                    (string) ($driver?->nombre ?? 'Sin conductor')
                ),
                'leida' => false,
                'status' => MaintenanceAlert::STATUS_REQUESTED,
                'fecha_resolucion' => null,
                'usuario_id' => null,
                'kilometraje_actual' => $currentKm,
                'kilometraje_objetivo' => $snapshot['target_km'],
                'faltante_km' => $snapshot['remaining_km'],
            ]
        );
    }

    private function serveExternalFile(string $storedPath, string $notFoundMessage)
    {
        $storedPath = ltrim(trim(str_replace('\\', '/', $storedPath)), '/');
        abort_if($storedPath === '', 404, $notFoundMessage);

        if (Str::startsWith($storedPath, 'public/')) {
            $storedPath = Str::after($storedPath, 'public/');
        }

        $disk = Storage::disk('public');
        abort_unless($disk->exists($storedPath), 404, $notFoundMessage);

        return response()->file($disk->path($storedPath), [
            'Content-Type' => $disk->mimeType($storedPath) ?: 'application/octet-stream',
        ]);
    }

    private function mapAppointment(MaintenanceAppointment $appointment, bool $mobileScoped = false): array
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
            'origen_solicitud' => (string) ($appointment->origen_solicitud ?? ($mobileScoped ? 'mobile_driver' : 'web')),
        ];
    }

    private function resolveVehicle(?int $authUserId, mixed $vehicleId): ?Vehicle
    {
        $candidateId = (int) ($vehicleId ?? 0);
        if (($authUserId ?? 0) <= 0) {
            return $candidateId > 0 ? Vehicle::query()->find($candidateId) : null;
        }

        $driver = Driver::query()->where('user_id', $authUserId)->first();
        if (! $driver) {
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

    private function resolveMaintenanceType(
        Vehicle $vehicle,
        mixed $typeId,
        mixed $typeName,
        bool $mobileScoped = false
    ): ?MaintenanceType {
        $query = $mobileScoped
            ? MaintenanceType::query()->active()->applicableToVehicleForMobile($vehicle)
            : MaintenanceType::query()->applicableToVehicle($vehicle);

        $candidateId = (int) ($typeId ?? 0);
        if ($candidateId > 0) {
            $byId = (clone $query)->whereKey($candidateId)->first();
            if ($byId) {
                return $byId;
            }
        }

        $candidateName = trim((string) ($typeName ?? ''));
        if ($candidateName === '') {
            return null;
        }

        $matched = (clone $query)
            ->where(function ($q) use ($candidateName) {
                $lowered = Str::lower($candidateName);
                $q->whereRaw('LOWER(nombre) = ?', [$lowered])
                    ->orWhereRaw('LOWER(nombre) like ?', ['%'.$lowered.'%']);
            })
            ->orderBy('nombre')
            ->first();

        if ($matched) {
            return $matched;
        }

        if (! $mobileScoped) {
            return null;
        }

        // En modo movil, si no existe una coincidencia valida, creamos/reciclamos un tipo
        // exclusivo del canal movil para no contaminar el catalogo web.
        $normalizedCandidate = $this->normalizeMaintenanceTypeName($candidateName);
        if ($normalizedCandidate === '') {
            return null;
        }

        $mobileType = MaintenanceType::query()
            ->when(
                Schema::hasColumn('maintenance_types', 'maintenance_form_type'),
                fn ($mobileQuery) => $mobileQuery->where('maintenance_form_type', 'mobile_driver'),
            )
            ->whereRaw('LOWER(nombre) = ?', [Str::lower($normalizedCandidate)])
            ->first();

        if (! $mobileType) {
            $attributes = [
                'nombre' => $normalizedCandidate,
            ];
            if (Schema::hasColumn('maintenance_types', 'maintenance_form_type')) {
                $attributes['maintenance_form_type'] = 'mobile_driver';
            }
            if (Schema::hasColumn('maintenance_types', 'activo')) {
                $attributes['activo'] = true;
            }
            if (Schema::hasColumn('maintenance_types', 'descripcion')) {
                $attributes['descripcion'] = 'Tipo generado automaticamente para solicitudes del canal movil.';
            }

            $mobileType = MaintenanceType::query()->create($attributes);
        }

        if (Schema::hasTable('maintenance_type_vehicle')) {
            $mobileType->vehicles()->syncWithoutDetaching([(int) $vehicle->id]);
        }

        return $mobileType;
    }

    private function normalizeMaintenanceTypeName(string $candidateName): string
    {
        $name = trim($candidateName);
        if ($name === '') {
            return '';
        }

        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return Str::title(Str::lower($name));
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
            now()->format('YmdHis').'-'.Str::lower(Str::random(8))
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
