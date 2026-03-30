<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Services\MaintenanceRequestFormService;
use Illuminate\Http\Request;

class MaintenanceRequestFormController extends Controller
{
    public function __construct(private readonly MaintenanceRequestFormService $service)
    {
    }

    public function download(Request $request)
    {
        $vehicle = $this->resolveVehicle($request);
        abort_unless($vehicle, 404, 'No se encontro un vehiculo para generar el formulario.');

        return $this->service->buildDownloadResponse($vehicle, $this->resolveDriver($request, $vehicle));
    }

    private function resolveVehicle(Request $request): ?Vehicle
    {
        $vehicleId = (int) $request->query('vehicle_id', 0);
        if ($vehicleId > 0) {
            return Vehicle::query()->with(['brand', 'vehicleClass'])->find($vehicleId);
        }

        $authUser = $request->user();
        if (!$authUser) {
            return null;
        }

        $driver = Driver::query()->where('user_id', (int) $authUser->id)->first();
        if (!$driver) {
            return null;
        }

        $assignment = VehicleAssignment::query()
            ->where('driver_id', (int) $driver->id)
            ->where(function ($q) {
                $q->where('activo', true)->orWhereNull('activo');
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        return $assignment
            ? Vehicle::query()->with(['brand', 'vehicleClass'])->find((int) $assignment->vehicle_id)
            : null;
    }

    private function resolveDriver(Request $request, Vehicle $vehicle): ?Driver
    {
        $authUser = $request->user();
        if ($authUser) {
            $driver = Driver::query()->where('user_id', (int) $authUser->id)->first();
            if ($driver) {
                return $driver;
            }
        }

        $assignment = VehicleAssignment::query()
            ->where('vehicle_id', (int) $vehicle->id)
            ->where(function ($q) {
                $q->where('activo', true)->orWhereNull('activo');
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        return $assignment ? Driver::query()->find((int) $assignment->driver_id) : null;
    }
}
