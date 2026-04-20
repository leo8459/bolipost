<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceAlertUserRead;
use App\Models\MaintenanceAppointment;
use App\Models\MaintenanceLog;
use App\Models\MaintenanceType;
use App\Models\Role;
use App\Models\User;
use App\Services\DriverIncentiveService;
use App\Services\MaintenanceAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthTokenController extends Controller
{
    public function __construct(
        private readonly DriverIncentiveService $driverIncentiveService
    ) {
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'nullable|string|max:255',
            'login' => 'nullable|string|max:255',
            'alias' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:120',
            'device_id' => 'nullable|string|max:190',
        ]);

        $identifier = $this->resolveLoginIdentifier($credentials);
        if ($identifier === '') {
            return response()->json([
                'message' => 'Usuario/email requerido.',
            ], 422);
        }

        $user = $this->resolveUserForMobileLogin($identifier);
        if (!$user || !Hash::check($credentials['password'], (string) $user->password)) {
            return response()->json([
                'message' => 'Credenciales invalidas.',
            ], 422);
        }

        $currentDeviceId = $this->resolveMobileDeviceId($request, $credentials);
        $previousMobileSession = $this->getActiveMobileSession($user->id);
        if ($this->hasAnotherActiveMobileSession($request, $previousMobileSession, $currentDeviceId)) {
            return response()->json([
                'message' => 'Esta cuenta ya tiene una sesion activa en otro dispositivo movil.',
                'session_conflict' => true,
            ], 409);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $this->rememberActiveMobileSession(
            $user->id,
            $request->session()->getId(),
            $currentDeviceId,
            is_array($previousMobileSession) ? ($previousMobileSession['session_id'] ?? null) : null
        );

        $driver = $user->resolvedDriver();
        $roleId = $this->resolveRoleId($user);
        $roleName = $user->role;

        return response()->json([
            'success' => true,
            'auth_mode' => 'session',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $roleName,
                'role_id' => $roleId,
                'driver_id' => $driver?->id,
                'driver_license' => $driver?->licencia,
                'phone' => $driver?->telefono,
                'status' => $driver ? ($driver->activo ? 'Activo' : 'Inactivo') : null,
            ],
            'bootstrap' => $this->buildBootstrapPayload($user),
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $driver = $user?->resolvedDriver();
        $roleId = $this->resolveRoleId($user);
        $roleName = $user?->role;
        $incentive = $this->resolveDriverIncentivePayload($driver);

        return response()->json([
            'id' => $user?->id,
            'name' => $user?->name,
            'fullName' => $user?->name,
            'email' => $user?->email,
            'role' => $roleName,
            'role_id' => $roleId,
            'driver_id' => $driver?->id,
            'driver_license' => $driver?->licencia,
            'phone' => $driver?->telefono,
            'status' => $driver ? ($driver->activo ? 'Activo' : 'Inactivo') : null,
            'incentive_stars' => $incentive['stars'],
            'incentive_max_stars' => $incentive['max_stars'],
            'incentive_period_label' => $incentive['period_label'],
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
                'role' => $roleName,
                'role_id' => $roleId,
                'driver_id' => $driver?->id,
                'incentive_stars' => $incentive['stars'],
                'incentive_max_stars' => $incentive['max_stars'],
                'incentive_period_label' => $incentive['period_label'],
            ],
            'bootstrap' => $this->buildBootstrapPayload($user),
        ]);
    }

    public function bootstrap(Request $request)
    {
        return response()->json($this->buildBootstrapPayload($request->user()));
    }

    public function logout(Request $request)
    {
        $userId = (int) ($request->user()?->id ?? 0);
        $currentSessionId = $request->session()->getId();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $this->forgetActiveMobileSession($userId, $currentSessionId);

        return response()->json([
            'message' => 'Sesion cerrada correctamente.',
        ]);
    }

    private function buildBootstrapPayload($user): array
    {
        $driver = $user?->resolvedDriver();
        $roleId = $this->resolveRoleId($user);

        $activeAssignment = null;
        $latestAssignment = null;
        $vehicle = null;

        if ($driver) {
            $latestAssignment = \App\Models\VehicleAssignment::query()
                ->with(['vehicle.brand', 'vehicle.vehicleClass'])
                ->where('driver_id', (int) $driver->id)
                ->orderByDesc('fecha_inicio')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();

            $today = now()->startOfDay();
            $latestIsCurrent = $latestAssignment
                && (bool) ($latestAssignment->activo ?? false)
                && (!$latestAssignment->fecha_inicio || $latestAssignment->fecha_inicio->copy()->startOfDay()->lte($today))
                && (!$latestAssignment->fecha_fin || $latestAssignment->fecha_fin->copy()->startOfDay()->gte($today))
                && (int) ($latestAssignment->vehicle_id ?? 0) > 0;

            $activeAssignment = $latestIsCurrent ? $latestAssignment : null;
            $vehicle = $activeAssignment?->vehicle;
        }

        if ($vehicle) {
            MaintenanceAlertService::evaluateVehicleByKilometraje((int) $vehicle->id);
            $vehicle->refresh();
        }

        $vehicleLogs = collect();
        $vales = collect();
        $fuelInvoices = collect();
        $gasStations = collect();
        $maintenanceAlerts = collect();
        $maintenanceCalendar = collect();
        $maintenancePlan = collect();
        $resolvedDriverId = $driver ? (int) $driver->id : null;
        $resolvedVehicleId = $vehicle ? (int) $vehicle->id : null;
        $incentive = $this->resolveDriverIncentivePayload($driver);

        if ($driver) {
            $vehicleLogsQuery = \App\Models\VehicleLog::query()
                ->active()
                ->where('drivers_id', (int) $driver->id)
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->limit(250);

            if ($resolvedVehicleId) {
                $vehicleLogsQuery->where('vehicles_id', $resolvedVehicleId);
            }

            $vehicleLogs = $vehicleLogsQuery->get();

            $invoiceHasGasStationId = Schema::hasColumn('fuel_invoices', 'gas_station_id');
            $detailHasGasStationId = Schema::hasColumn('fuel_invoice_details', 'gas_station_id');
            $stationHasRazonSocial = Schema::hasColumn('gas_stations', 'razon_social');
            $stationHasNombre = Schema::hasColumn('gas_stations', 'nombre');
            $stationHasDireccion = Schema::hasColumn('gas_stations', 'direccion');
            $vehicleLogHasActivo = Schema::hasColumn('vehicle_log', 'activo');
            $detailHasActivo = Schema::hasColumn('fuel_invoice_details', 'activo');
            $invoiceHasActivo = Schema::hasColumn('fuel_invoices', 'activo');

            $valesQuery = DB::table('vehicle_log as vl')
                ->leftJoin('fuel_invoice_details as fid', 'fid.id', '=', 'vl.fuel_log_id')
                ->leftJoin('fuel_invoices as fi', 'fi.id', '=', 'fid.fuel_invoice_id')
                ->where('vl.drivers_id', (int) $driver->id)
                ->where(function ($q) {
                    $q->whereNotNull('vl.fuel_log_id')
                        ->orWhere('vl.abastecimiento_combustible', true);
                })
                ->orderByDesc('vl.fecha')
                ->orderByDesc('vl.id')
                ->limit(250);

            if ($vehicleLogHasActivo) {
                $valesQuery->where('vl.activo', true);
            }

            if ($detailHasActivo) {
                $valesQuery->where(function ($q) {
                    $q->whereNull('fid.activo')->orWhere('fid.activo', true);
                });
            }

            if ($invoiceHasActivo) {
                $valesQuery->where(function ($q) {
                    $q->whereNull('fi.activo')->orWhere('fi.activo', true);
                });
            }

            if ($resolvedVehicleId) {
                $valesQuery->where('vl.vehicles_id', $resolvedVehicleId);
            }

            if ($invoiceHasGasStationId) {
                $valesQuery->leftJoin('gas_stations as gs', 'gs.id', '=', 'fi.gas_station_id');
            } elseif ($detailHasGasStationId) {
                $valesQuery->leftJoin('gas_stations as gs', 'gs.id', '=', 'fid.gas_station_id');
            }

            $selectColumns = [
                'vl.id as vehicle_log_id',
                'vl.fuel_log_id',
                'vl.fecha',
                'vl.drivers_id as driver_id',
                'vl.vehicles_id as vehicle_id',
                'vl.kilometraje_salida',
                'vl.kilometraje_llegada',
                'vl.recorrido_inicio',
                'vl.recorrido_destino',
                'fid.id as fuel_detail_id',
                'fid.fuel_invoice_id',
                'fid.cantidad',
                'fid.precio_unitario',
                'fid.subtotal',
                'fi.numero_factura',
                'fi.fecha_emision',
                'fi.nombre_cliente',
                'fi.monto_total',
            ];

            if ($invoiceHasGasStationId) {
                $selectColumns[] = 'fi.gas_station_id';
                if ($stationHasRazonSocial && $stationHasNombre) {
                    $selectColumns[] = DB::raw('COALESCE(gs.razon_social, gs.nombre) as station_name');
                } elseif ($stationHasRazonSocial) {
                    $selectColumns[] = DB::raw('gs.razon_social as station_name');
                } elseif ($stationHasNombre) {
                    $selectColumns[] = DB::raw('gs.nombre as station_name');
                } else {
                    $selectColumns[] = DB::raw('NULL as station_name');
                }
                if ($stationHasDireccion) {
                    $selectColumns[] = 'gs.direccion as station_address';
                } else {
                    $selectColumns[] = DB::raw('NULL as station_address');
                }
            } elseif ($detailHasGasStationId) {
                $selectColumns[] = 'fid.gas_station_id';
                if ($stationHasRazonSocial && $stationHasNombre) {
                    $selectColumns[] = DB::raw('COALESCE(gs.razon_social, gs.nombre) as station_name');
                } elseif ($stationHasRazonSocial) {
                    $selectColumns[] = DB::raw('gs.razon_social as station_name');
                } elseif ($stationHasNombre) {
                    $selectColumns[] = DB::raw('gs.nombre as station_name');
                } else {
                    $selectColumns[] = DB::raw('NULL as station_name');
                }
                if ($stationHasDireccion) {
                    $selectColumns[] = 'gs.direccion as station_address';
                } else {
                    $selectColumns[] = DB::raw('NULL as station_address');
                }
            } else {
                $selectColumns[] = DB::raw('NULL as gas_station_id');
                $selectColumns[] = DB::raw('NULL as station_name');
                $selectColumns[] = DB::raw('NULL as station_address');
            }

            $vales = $valesQuery
                ->get($selectColumns)
                ->map(function ($row) {
                    return [
                        'id' => (int) ($row->fuel_detail_id ?: $row->fuel_log_id ?: $row->vehicle_log_id),
                        'vehicle_log_id' => (int) $row->vehicle_log_id,
                        'fuel_log_id' => $row->fuel_log_id ? (int) $row->fuel_log_id : null,
                        'fuel_invoice_id' => $row->fuel_invoice_id ? (int) $row->fuel_invoice_id : null,
                        'gas_station_id' => $row->gas_station_id ? (int) $row->gas_station_id : null,
                        'driver_id' => $row->driver_id ? (int) $row->driver_id : null,
                        'vehicle_id' => $row->vehicle_id ? (int) $row->vehicle_id : null,
                        'fecha' => $row->fecha,
                        'fecha_emision' => $row->fecha_emision,
                        'numero_factura' => $row->numero_factura,
                        'nombre_cliente' => $row->nombre_cliente,
                        'cantidad' => $row->cantidad !== null ? (float) $row->cantidad : null,
                        'precio_unitario' => $row->precio_unitario !== null ? (float) $row->precio_unitario : null,
                        'subtotal' => $row->subtotal !== null ? (float) $row->subtotal : null,
                        'monto_total' => $row->monto_total !== null ? (float) $row->monto_total : null,
                        'station_name' => $row->station_name,
                        'station_address' => $row->station_address,
                        'kilometraje_salida' => $row->kilometraje_salida !== null ? (float) $row->kilometraje_salida : null,
                        'kilometraje_llegada' => $row->kilometraje_llegada !== null ? (float) $row->kilometraje_llegada : null,
                        'recorrido_inicio' => $row->recorrido_inicio,
                        'recorrido_destino' => $row->recorrido_destino,
                    ];
                })
                ->values();

            $fuelInvoices = $vales
                ->filter(fn ($v) => !empty($v['fuel_invoice_id']))
                ->unique('fuel_invoice_id')
                ->map(function ($v) {
                    return [
                        'id' => (int) $v['fuel_invoice_id'],
                        'numero_factura' => $v['numero_factura'],
                        'fecha_emision' => $v['fecha_emision'],
                        'gas_station_id' => $v['gas_station_id'] ?? null,
                        'nombre_cliente' => $v['nombre_cliente'],
                        'monto_total' => $v['monto_total'],
                    ];
                })
                ->values();

            $gasStations = $vales
                ->filter(fn ($v) => !empty($v['station_name']))
                ->unique('station_name')
                ->map(function ($v) {
                    return [
                        'id' => $v['gas_station_id'] ?? null,
                        'nombre' => $v['station_name'],
                        'razon_social' => $v['station_name'],
                        'direccion' => $v['station_address'],
                    ];
                })
                ->values();

            $maintenanceAlertsCollection = MaintenanceAlert::query()
                ->with(['maintenanceType:id,nombre', 'vehicle:id,placa'])
                ->where('status', MaintenanceAlert::STATUS_ACTIVE)
                ->when($resolvedVehicleId, fn ($query) => $query->where('vehicle_id', $resolvedVehicleId))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            $alertReadIds = [];
            if (Schema::hasTable('maintenance_alert_user_reads') && $maintenanceAlertsCollection->isNotEmpty()) {
                $alertReadIds = MaintenanceAlertUserRead::query()
                    ->where('user_id', (int) ($user?->id ?? 0))
                    ->whereIn('maintenance_alert_id', $maintenanceAlertsCollection->pluck('id')->all())
                    ->pluck('maintenance_alert_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            $maintenanceAlerts = $maintenanceAlertsCollection
                ->map(function (MaintenanceAlert $alert) use ($alertReadIds) {
                    $read = in_array((int) $alert->id, $alertReadIds, true);
                    return [
                        'id' => (int) $alert->id,
                        'tipo' => (string) $alert->tipo,
                        'type' => (string) $alert->tipo,
                        'status' => (string) $alert->status,
                        'titulo' => (string) ($alert->maintenanceType?->nombre ?: $alert->tipo),
                        'title' => (string) ($alert->maintenanceType?->nombre ?: $alert->tipo),
                        'mensaje' => (string) $alert->mensaje,
                        'vehicle_id' => $alert->vehicle_id ? (int) $alert->vehicle_id : null,
                        'vehicle_plate' => (string) ($alert->vehicle?->placa ?? ''),
                        'placa' => (string) ($alert->vehicle?->placa ?? ''),
                        'maintenance_type_id' => $alert->maintenance_type_id ? (int) $alert->maintenance_type_id : null,
                        'maintenance_type_name' => (string) ($alert->maintenanceType?->nombre ?? ''),
                        'kilometraje_actual' => $alert->kilometraje_actual !== null ? (float) $alert->kilometraje_actual : null,
                        'kilometraje_objetivo' => $alert->kilometraje_objetivo !== null ? (float) $alert->kilometraje_objetivo : null,
                        'faltante_km' => $alert->faltante_km !== null ? (float) $alert->faltante_km : null,
                        'leida' => $read,
                        'read' => $read,
                        'due_date' => optional($alert->created_at)->toDateString(),
                        'created_at' => optional($alert->created_at)?->toIso8601String(),
                    ];
                })
                ->values();

            if ($vehicle) {
                $maintenancePlan = $this->buildVehicleMaintenancePlan($vehicle);
                $maintenanceCalendar = MaintenanceAppointment::query()
                    ->with(['vehicle:id,placa', 'driver:id,nombre', 'tipoMantenimiento:id,nombre'])
                    ->active()
                    ->where('vehicle_id', (int) $vehicle->id)
                    ->whereIn('estado', [
                        MaintenanceAppointment::STATUS_PENDING,
                        MaintenanceAppointment::STATUS_APPROVED,
                    ])
                    ->whereDate('fecha_programada', '>=', now()->subDay()->toDateString())
                    ->orderBy('fecha_programada')
                    ->limit(30)
                    ->get()
                    ->map(function (MaintenanceAppointment $appointment) {
                        return [
                            'id' => (int) $appointment->id,
                            'vehicle_id' => $appointment->vehicle_id ? (int) $appointment->vehicle_id : null,
                            'vehicle_plate' => (string) ($appointment->vehicle?->placa ?? ''),
                            'driver_id' => $appointment->driver_id ? (int) $appointment->driver_id : null,
                            'driver_name' => (string) ($appointment->driver?->nombre ?? ''),
                            'maintenance_type_id' => $appointment->tipo_mantenimiento_id ? (int) $appointment->tipo_mantenimiento_id : null,
                            'maintenance_type_name' => (string) ($appointment->tipoMantenimiento?->nombre ?? 'Mantenimiento'),
                            'title' => (string) ($appointment->tipoMantenimiento?->nombre ?? 'Mantenimiento programado'),
                            'description' => (string) (($appointment->estado ?? '') . ' - ' . ($appointment->tipoMantenimiento?->nombre ?? 'Mantenimiento')),
                            'status' => (string) $appointment->estado,
                            'date' => optional($appointment->fecha_programada)->toDateString(),
                            'scheduled_at' => optional($appointment->fecha_programada)?->toIso8601String(),
                            'requested_at' => optional($appointment->solicitud_fecha)?->toIso8601String(),
                            'source' => (string) ($appointment->origen_solicitud ?? ''),
                            'is_accident' => (bool) $appointment->es_accidente,
                        ];
                    })
                    ->values();
            }
        }

        $usersPayload = $user ? [[
            'id' => (int) $user->id,
            'name' => $user->name,
            'fullName' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'role_id' => $roleId,
            'driver_id' => $driver?->id,
            'driver_license' => $driver?->licencia,
            'phone' => $driver?->telefono,
            'status' => $driver ? ($driver->activo ? 'Activo' : 'Inactivo') : null,
            'incentive_stars' => $incentive['stars'],
            'incentive_max_stars' => $incentive['max_stars'],
            'incentive_period_label' => $incentive['period_label'],
        ]] : [];

        $rolesPayload = $user?->role ? [[
            'id' => (int) ($roleId ?? 0),
            'nombre' => (string) $user->role,
        ]] : [];

        $driversPayload = $driver ? [[
            'id' => (int) $driver->id,
            'user_id' => (int) ($driver->user_id ?? 0),
            'nombre' => $driver->nombre,
            'licencia' => $driver->licencia,
            'tipo_licencia' => $driver->tipo_licencia,
            'telefono' => $driver->telefono,
            'email' => $driver->email,
            'activo' => (bool) $driver->activo,
            'fecha_vencimiento_licencia' => optional($driver->fecha_vencimiento_licencia)->toDateString(),
        ]] : [];

        $assignmentsPayload = $latestAssignment ? [[
            'id' => (int) $latestAssignment->id,
            'driver_id' => (int) ($latestAssignment->driver_id ?? 0),
            'vehicle_id' => (int) ($latestAssignment->vehicle_id ?? 0),
            'tipo_asignacion' => $latestAssignment->tipo_asignacion,
            'fecha_inicio' => optional($latestAssignment->fecha_inicio)->toDateString(),
            'fecha_fin' => optional($latestAssignment->fecha_fin)->toDateString(),
            'activo' => (bool) $latestAssignment->activo,
        ]] : [];

        $vehiclesPayload = $vehicle ? [[
            'id' => (int) $vehicle->id,
            'placa' => $vehicle->placa,
            'marca' => $vehicle->marca,
            'modelo' => $vehicle->modelo,
            'tipo_combustible' => $vehicle->tipo_combustible,
            'anio' => $vehicle->anio,
            'capacidad_tanque' => $vehicle->capacidad_tanque,
            'kilometraje' => $vehicle->kilometraje,
            'activo' => (bool) $vehicle->activo,
        ]] : [];

        return [
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'user' => $usersPayload[0] ?? null,
            'driver' => $driversPayload[0] ?? null,
            'assignment' => $assignmentsPayload[0] ?? null,
            'vehicle' => $vehiclesPayload[0] ?? null,
            'resolved_driver_id' => $resolvedDriverId,
            'resolved_vehicle_id' => $resolvedVehicleId,
            'users' => $usersPayload,
            'roles' => $rolesPayload,
            'drivers' => $driversPayload,
            'vehicle_assignments' => $assignmentsPayload,
            'vehicles' => $vehiclesPayload,
            'vehicle_brands' => [],
            'vehicle_logs' => $vehicleLogs
                ->map(function ($log) {
                    $row = $log->toArray();
                    $row['driver_id'] = $log->drivers_id;
                    $row['vehicle_id'] = $log->vehicles_id;
                    return $row;
                })
                ->values()
                ->all(),
            'fuel_invoices' => $fuelInvoices->values()->all(),
            'fuel_invoice_details' => $vales->values()->all(),
            'fuel_logs' => $vales->values()->all(),
            'gas_stations' => $gasStations->values()->all(),
            'vales' => $vales->values()->all(),
            'maintenance_alerts' => $maintenanceAlerts->values()->all(),
            'maintenance_calendar' => $maintenanceCalendar->values()->all(),
            'maintenance_plan' => $maintenancePlan->values()->all(),
            'incentive' => $incentive,
        ];
    }

    private function buildVehicleMaintenancePlan(\App\Models\Vehicle $vehicle)
    {
        $types = MaintenanceType::query()
            ->applicableToVehicle($vehicle)
            ->orderBy('id')
            ->get([
                'id',
                'nombre',
                'es_preventivo',
                'cada_km',
                'intervalo_km',
                'intervalo_km_init',
                'intervalo_km_fh',
                'km_alerta_previa',
            ]);

        if ($types->isEmpty()) {
            return collect();
        }

        $latestLogsByType = MaintenanceLog::query()
            ->active()
            ->where('vehicle_id', (int) $vehicle->id)
            ->whereNotNull('maintenance_type_id')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->unique('maintenance_type_id')
            ->keyBy('maintenance_type_id');

        $currentKm = $vehicle->kilometraje_actual ?? $vehicle->kilometraje_inicial ?? $vehicle->kilometraje;
        $currentKm = $currentKm !== null ? (float) $currentKm : null;

        return $types
            ->map(function (MaintenanceType $type) use ($vehicle, $latestLogsByType, $currentKm) {
                $intervalKm = $type->cada_km
                    ?? $type->intervalo_km_init
                    ?? $type->intervalo_km
                    ?? $type->intervalo_km_fh;

                if ($intervalKm === null || (float) $intervalKm <= 0) {
                    return null;
                }

                /** @var MaintenanceLog|null $latestLog */
                $latestLog = $latestLogsByType->get((int) $type->id);

                $targetKm = null;
                if ($latestLog?->proximo_kilometraje !== null) {
                    $targetKm = (float) $latestLog->proximo_kilometraje;
                } elseif ($latestLog?->kilometraje !== null) {
                    $targetKm = (float) $latestLog->kilometraje + (float) $intervalKm;
                } else {
                    $targetKm = (float) $intervalKm;
                }

                $remainingKm = $currentKm !== null ? ($targetKm - $currentKm) : null;
                $alertLeadKm = $type->km_alerta_previa !== null ? (int) $type->km_alerta_previa : 15;

                return [
                    'maintenance_type_id' => (int) $type->id,
                    'maintenance_type_name' => (string) $type->nombre,
                    'vehicle_id' => (int) $vehicle->id,
                    'vehicle_plate' => (string) ($vehicle->placa ?? ''),
                    'is_preventive' => (bool) ($type->es_preventivo ?? true),
                    'current_km' => $currentKm,
                    'target_km' => $targetKm,
                    'remaining_km' => $remainingKm,
                    'alert_lead_km' => $alertLeadKm,
                    'interval_km' => (float) $intervalKm,
                    'source' => $latestLog ? 'maintenance_log' : 'maintenance_type',
                    'last_maintenance_log_id' => $latestLog?->id ? (int) $latestLog->id : null,
                    'due_now' => $remainingKm !== null ? $remainingKm <= 0 : false,
                ];
            })
            ->filter()
            ->values();
    }

    private function resolveDriverIncentivePayload(?Driver $driver): array
    {
        $period = $this->driverIncentiveService->latestClosedMonth();

        if (!$driver) {
            return [
                'stars' => DriverIncentiveService::MAX_STARS,
                'max_stars' => DriverIncentiveService::MAX_STARS,
                'period_label' => $period->translatedFormat('F Y'),
            ];
        }

        $report = $this->driverIncentiveService
            ->reportsForMonth($period)
            ->firstWhere('driver_id', (int) $driver->id);

        return [
            'stars' => (int) ($report->stars_end ?? DriverIncentiveService::MAX_STARS),
            'max_stars' => DriverIncentiveService::MAX_STARS,
            'period_label' => $period->translatedFormat('F Y'),
        ];
    }

    private function resolveRoleId($user): ?int
    {
        if (!$user) {
            return null;
        }

        $explicitRoleId = (int) ($user->role_id ?? 0);
        if ($explicitRoleId > 0) {
            return $explicitRoleId;
        }

        $roleName = (string) ($user->getRoleNames()->first() ?? '');
        if ($roleName === '') {
            return null;
        }

        return Role::query()->where('name', $roleName)->value('id');
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function resolveLoginIdentifier(array $credentials): string
    {
        foreach (['login', 'alias', 'username', 'email'] as $key) {
            $value = trim((string) ($credentials[$key] ?? ''));
            if ($value !== '') {
                return mb_strtolower($value);
            }
        }

        return '';
    }

    private function resolveUserForMobileLogin(string $identifier): ?User
    {
        $normalized = mb_strtolower(trim($identifier));
        if ($normalized === '') {
            return null;
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->orWhereRaw('LOWER(alias) = ?', [$normalized])
            ->first();

        if ($user) {
            return $user;
        }

        $driver = Driver::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->first();

        if ($driver?->user_id) {
            return User::query()->find((int) $driver->user_id);
        }

        return null;
    }

    private function getActiveMobileSession(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $cacheKey = $this->mobileSessionCacheKey($userId);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            $sessionId = trim((string) ($cached['session_id'] ?? ''));
            $deviceId = trim((string) ($cached['device_id'] ?? ''));

            if ($sessionId !== '' && !$this->sessionRecordExists($sessionId)) {
                Cache::forget($cacheKey);
                return null;
            }

            return $sessionId !== ''
                ? [
                    'session_id' => $sessionId,
                    'device_id' => $deviceId !== '' ? $deviceId : null,
                ]
                : null;
        }

        $sessionId = is_string($cached) ? trim($cached) : '';
        if ($sessionId !== '' && !$this->sessionRecordExists($sessionId)) {
            Cache::forget($cacheKey);
            return null;
        }

        return $sessionId !== ''
            ? [
                'session_id' => $sessionId,
                'device_id' => null,
            ]
            : null;
    }

    private function hasAnotherActiveMobileSession(Request $request, ?array $activeSession, ?string $currentDeviceId): bool
    {
        $resolvedActiveSessionId = trim((string) ($activeSession['session_id'] ?? ''));
        if ($resolvedActiveSessionId === '') {
            return false;
        }

        $resolvedActiveDeviceId = trim((string) ($activeSession['device_id'] ?? ''));
        $resolvedCurrentDeviceId = trim((string) ($currentDeviceId ?? ''));
        if (
            $resolvedCurrentDeviceId !== ''
            && $resolvedActiveDeviceId !== ''
            && hash_equals($resolvedActiveDeviceId, $resolvedCurrentDeviceId)
        ) {
            return false;
        }

        if ($resolvedCurrentDeviceId !== '' && $resolvedActiveDeviceId === '') {
            return false;
        }

        $currentSessionId = trim((string) $request->session()->getId());
        if ($currentSessionId !== '' && hash_equals($resolvedActiveSessionId, $currentSessionId)) {
            return false;
        }

        if (!$this->sessionRecordExists($resolvedActiveSessionId)) {
            return false;
        }

        return true;
    }

    private function rememberActiveMobileSession(int $userId, ?string $sessionId, ?string $deviceId = null, ?string $previousSessionId = null): void
    {
        $resolvedSessionId = is_string($sessionId) ? trim($sessionId) : '';
        if ($userId <= 0 || $resolvedSessionId === '') {
            return;
        }

        $expiresAt = now()->addMinutes($this->mobileSessionWindowMinutes());
        Cache::put($this->mobileSessionCacheKey($userId), [
            'session_id' => $resolvedSessionId,
            'device_id' => $deviceId ? trim($deviceId) : null,
        ], $expiresAt);

        if ($previousSessionId && $previousSessionId !== $resolvedSessionId) {
            $this->deleteSessionRecord($previousSessionId);
        }
    }

    private function forgetActiveMobileSession(int $userId, ?string $sessionId = null): void
    {
        if ($userId <= 0) {
            return;
        }

        $cacheKey = $this->mobileSessionCacheKey($userId);
        $activeSession = $this->getActiveMobileSession($userId);
        $resolvedCurrentSessionId = is_string($sessionId) ? trim($sessionId) : '';

        $resolvedActiveSessionId = trim((string) ($activeSession['session_id'] ?? ''));
        if (
            $resolvedCurrentSessionId !== ''
            && $resolvedActiveSessionId !== ''
            && !hash_equals($resolvedActiveSessionId, $resolvedCurrentSessionId)
        ) {
            return;
        }

        Cache::forget($cacheKey);
    }

    private function deleteSessionRecord(?string $sessionId): void
    {
        $resolvedSessionId = is_string($sessionId) ? trim($sessionId) : '';
        if ($resolvedSessionId === '' || config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('id', $resolvedSessionId)
            ->delete();
    }

    private function sessionRecordExists(string $sessionId): bool
    {
        $resolvedSessionId = trim($sessionId);
        if ($resolvedSessionId === '') {
            return false;
        }

        if (config('session.driver') === 'file') {
            $path = storage_path('framework/sessions/' . $resolvedSessionId);
            if (!is_file($path)) {
                return false;
            }

            $lifetimeSeconds = max(60, ((int) config('session.lifetime', 120)) * 60);
            $lastTouchedAt = @filemtime($path);
            if (!is_int($lastTouchedAt) || $lastTouchedAt <= 0) {
                return true;
            }

            return ($lastTouchedAt + $lifetimeSeconds) >= time();
        }

        if (config('session.driver') !== 'database') {
            return false;
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('id', $resolvedSessionId)
            ->exists();
    }

    private function mobileSessionCacheKey(int $userId): string
    {
        return "mobile_auth:active_session:user:{$userId}";
    }

    private function mobileSessionWindowMinutes(): int
    {
        return 5;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function resolveMobileDeviceId(Request $request, array $credentials): ?string
    {
        $candidates = [
            $credentials['device_id'] ?? null,
            $request->header('X-Mobile-Device-Id'),
            $request->header('X-Device-Id'),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
