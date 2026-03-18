<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\FuelInvoice;
use App\Models\FuelInvoiceDetail;
use App\Models\FuelLog;
use App\Models\GasStation;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceAppointment;
use App\Models\MaintenanceLog;
use App\Models\MaintenanceType;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleBrand;
use App\Models\VehicleClass;
use App\Models\VehicleLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class MobileCrudApiController extends Controller
{
    /**
     * @return array<string, class-string<Model>>
     */
    private function resourceMap(): array
    {
        return [
            'users' => User::class,
            'roles' => Role::class,
            'drivers' => Driver::class,
            'vehicles' => Vehicle::class,
            'vehicle_brands' => VehicleBrand::class,
            'vehicle_classes' => VehicleClass::class,
            'vehicle_assignments' => VehicleAssignment::class,
            'vehicle_logs' => VehicleLog::class,
            'fuel_invoices' => FuelInvoice::class,
            'fuel_logs' => FuelLog::class,
            'fuel_invoice_details' => FuelInvoiceDetail::class,
            'gas_stations' => GasStation::class,
            'maintenance_types' => MaintenanceType::class,
            'maintenance_logs' => MaintenanceLog::class,
            'maintenance_appointments' => MaintenanceAppointment::class,
            'maintenance_alerts' => MaintenanceAlert::class,
            'activity_logs' => ActivityLog::class,
        ];
    }

    public function resources()
    {
        $items = collect($this->resourceMap())
            ->map(function (string $modelClass, string $resource) {
                $model = new $modelClass();

                return [
                    'resource' => $resource,
                    'model' => class_basename($modelClass),
                    'table' => $model->getTable(),
                    'fillable' => array_values($model->getFillable()),
                ];
            })
            ->values();

        return response()->json([
            'count' => $items->count(),
            'data' => $items,
        ]);
    }

    public function index(Request $request, string $resource)
    {
        $modelClass = $this->resolveModelClass($resource);
        if (!$modelClass) {
            return $this->resourceNotFound($resource);
        }

        /** @var Model $model */
        $model = new $modelClass();
        $query = $modelClass::query();

        $with = $this->parseWith($request, $model);
        if (!empty($with)) {
            $query->with($with);
        }

        $perPage = (int) max(1, min(100, (int) $request->integer('per_page', 20)));
        $items = $query->paginate($perPage);

        return response()->json($items);
    }

    public function show(Request $request, string $resource, int $id)
    {
        $modelClass = $this->resolveModelClass($resource);
        if (!$modelClass) {
            return $this->resourceNotFound($resource);
        }

        /** @var Model $model */
        $model = $modelClass::query()->find($id);
        if (!$model) {
            return response()->json(['message' => 'Registro no encontrado.'], 404);
        }

        $with = $this->parseWith($request, $model);
        if (!empty($with)) {
            $model->load($with);
        }

        return response()->json($model);
    }

    public function store(Request $request, string $resource)
    {
        $modelClass = $this->resolveModelClass($resource);
        if (!$modelClass) {
            return $this->resourceNotFound($resource);
        }

        /** @var Model $model */
        $model = new $modelClass();
        $data = $this->extractAllowedPayload($request->all(), $model);

        if (empty($data)) {
            return response()->json([
                'message' => 'No se enviaron campos permitidos para guardar.',
                'fillable' => array_values($model->getFillable()),
            ], 422);
        }

        try {
            $record = $modelClass::query()->create($data);
            return response()->json($record, 201);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'No se pudo crear el registro.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, string $resource, int $id)
    {
        $modelClass = $this->resolveModelClass($resource);
        if (!$modelClass) {
            return $this->resourceNotFound($resource);
        }

        /** @var Model $model */
        $model = $modelClass::query()->find($id);
        if (!$model) {
            return response()->json(['message' => 'Registro no encontrado.'], 404);
        }

        $data = $this->extractAllowedPayload($request->all(), $model);
        if (empty($data)) {
            return response()->json([
                'message' => 'No se enviaron campos permitidos para actualizar.',
                'fillable' => array_values($model->getFillable()),
            ], 422);
        }

        try {
            $model->fill($data);
            $model->save();

            return response()->json($model->fresh());
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'No se pudo actualizar el registro.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(string $resource, int $id)
    {
        $modelClass = $this->resolveModelClass($resource);
        if (!$modelClass) {
            return $this->resourceNotFound($resource);
        }

        /** @var Model $model */
        $model = $modelClass::query()->find($id);
        if (!$model) {
            return response()->json(['message' => 'Registro no encontrado.'], 404);
        }

        $model->delete();

        return response()->json([
            'message' => 'Registro eliminado.',
            'id' => $id,
            'resource' => $resource,
        ]);
    }

    /**
     * @return class-string<Model>|null
     */
    private function resolveModelClass(string $resource): ?string
    {
        $resource = trim(mb_strtolower($resource));
        $map = $this->resourceMap();

        return $map[$resource] ?? null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function extractAllowedPayload(array $payload, Model $model): array
    {
        $allowed = array_fill_keys($model->getFillable(), true);
        $data = [];

        foreach ($payload as $key => $value) {
            if (isset($allowed[$key])) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function parseWith(Request $request, Model $model): array
    {
        $raw = (string) $request->query('with', '');
        if ($raw === '') {
            return [];
        }

        $candidates = array_filter(array_map('trim', explode(',', $raw)));
        $allowed = [];

        foreach ($candidates as $relation) {
            if (method_exists($model, $relation)) {
                $allowed[] = $relation;
            }
        }

        return array_values(array_unique($allowed));
    }

    private function resourceNotFound(string $resource)
    {
        return response()->json([
            'message' => "Recurso '{$resource}' no soportado.",
            'resources' => array_keys($this->resourceMap()),
        ], 404);
    }
}
