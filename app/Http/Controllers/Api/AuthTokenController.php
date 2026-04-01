<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceLog;
use App\Models\MaintenanceType;
use App\Models\Role;
use App\Models\User;
use App\Services\DriverIncentiveService;
use App\Services\MaintenanceAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

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
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Sesion cerrada correctamente.',
        ]);
    }

    private function buildBootstrapPayload($user): array
    {
        $driver = $user?->resolvedDriver();
        $roleId = $this->resolveRoleId($user);

        $activeAssignment = null;
        $vehicle = null;

        if ($driver) {
            $driver->loadMissing(['assignments.vehicle']);

            $activeAssignment = $driver->assignments
                ->first(function ($assignment) {
                    $isActive = (bool) ($assignment->activo ?? false);
                    if (!$isActive) {
                        return false;
                    }

                    $endDate = $assignment->fecha_fin;
                    if (!$endDate) {
                        return true;
                    }

                    return $endDate >= now()->startOfDay();
                });

            $vehicle = $activeAssignment?->vehicle ?? $driver->currentVehicle();
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
        $maintenancePlan = collect();
        $resolvedDriverId = $driver ? (int) $driver->id : null;
        $resolvedVehicleId = $vehicle ? (int) $vehicle->id : null;
        $incentive = $this->resolveDriverIncentivePayload($driver);

        if ($driver) {
            $vehicleLogsQuery = \App\Models\VehicleLog::query()
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

            $maintenanceAlerts = MaintenanceAlert::query()
                ->with(['maintenanceType:id,nombre', 'vehicle:id,placa'])
                ->where('status', MaintenanceAlert::STATUS_ACTIVE)
                ->when($resolvedVehicleId, fn ($query) => $query->where('vehicle_id', $resolvedVehicleId))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(function (MaintenanceAlert $alert) {
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
                        'leida' => (bool) $alert->leida,
                        'read' => (bool) $alert->leida,
                        'due_date' => optional($alert->created_at)->toDateString(),
                        'created_at' => optional($alert->created_at)?->toIso8601String(),
                    ];
                })
                ->values();

            if ($vehicle) {
                $maintenancePlan = $this->buildVehicleMaintenancePlan($vehicle);
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

        $assignmentsPayload = $activeAssignment ? [[
            'id' => (int) $activeAssignment->id,
            'driver_id' => (int) ($activeAssignment->driver_id ?? 0),
            'vehicle_id' => (int) ($activeAssignment->vehicle_id ?? 0),
            'tipo_asignacion' => $activeAssignment->tipo_asignacion,
            'fecha_inicio' => optional($activeAssignment->fecha_inicio)->toDateString(),
            'fecha_fin' => optional($activeAssignment->fecha_fin)->toDateString(),
            'activo' => (bool) $activeAssignment->activo,
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
}
