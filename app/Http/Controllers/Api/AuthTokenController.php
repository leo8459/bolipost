<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthTokenController extends Controller
{
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

        $deviceName = trim((string) ($credentials['device_name'] ?? 'mobile-app'));
        $token = $user->createToken($deviceName)->plainTextToken;

        $driver = $user->resolvedDriver();
        $roleId = $this->resolveRoleId($user);
        $roleName = $user->role;

        return response()->json([
            'success' => true,
            'token_type' => 'Bearer',
            'access_token' => $token,
            'accessToken' => $token,
            'token' => $token,
            'acceses_token' => $token,
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token,
                'accessToken' => $token,
                'token' => $token,
                'acceses_token' => $token,
            ],
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
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
                'role' => $roleName,
                'role_id' => $roleId,
                'driver_id' => $driver?->id,
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
        $request->user()?->currentAccessToken()?->delete();

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

        $vehicleLogs = collect();
        $vales = collect();
        $fuelInvoices = collect();
        $gasStations = collect();
        $resolvedDriverId = $driver ? (int) $driver->id : null;
        $resolvedVehicleId = $vehicle ? (int) $vehicle->id : null;

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

            // Listado de vales robusto: parte desde vehicle_log y hace LEFT JOIN a detalle/factura.
            // Asi seguimos mostrando vales aunque existan enlaces historicos incompletos.
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
            // Formato compacto (nuevo)
            'user' => $usersPayload[0] ?? null,
            'driver' => $driversPayload[0] ?? null,
            'assignment' => $assignmentsPayload[0] ?? null,
            'vehicle' => $vehiclesPayload[0] ?? null,
            'resolved_driver_id' => $resolvedDriverId,
            'resolved_vehicle_id' => $resolvedVehicleId,
            // Formato legacy que espera la app movil
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
