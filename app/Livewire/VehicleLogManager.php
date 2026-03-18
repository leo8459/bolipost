<?php

namespace App\Livewire;

use App\Models\Driver;
use App\Models\FuelLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleLog;
use App\Services\MaintenanceAlertService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class VehicleLogManager extends Component
{
    use WithPagination;

    #[Validate('required|integer|exists:vehicles,id')]
    public ?int $vehicles_id = null;

    public ?int $drivers_id = null;
    public ?int $fuel_log_id = null;

    #[Validate('required|date')]
    public ?string $fecha = null;

    #[Validate('required|numeric|min:0')]
    public ?float $kilometraje_salida = null;

    public ?float $kilometraje_llegada = null;

    #[Validate('required|string|max:255')]
    public string $recorrido_inicio = '';
    public ?float $latitud_inicio = null;
    public ?float $logitud_inicio = null;

    #[Validate('required|string|max:255')]
    public string $recorrido_destino = '';
    public ?float $latitud_destino = null;
    public ?float $logitud_destino = null;

    public bool $abastecimiento_combustible = false;
    public ?string $firma_digital = null;

    public bool $isEdit = false;
    public ?int $editingLogId = null;
    public bool $showForm = false;
    public string $search = '';

    public function mount(): void
    {
        abort_unless(in_array($this->currentUser()?->role, ['admin', 'recepcion', 'conductor']), 403);
    }

    public function render()
    {
        $query = VehicleLog::with(['vehicle', 'driver', 'fuelLog'])
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('recorrido_inicio', 'like', "%{$search}%")
                    ->orWhere('recorrido_destino', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(fecha AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(kilometraje_salida AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(kilometraje_llegada AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(abastecimiento_combustible AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('placa', 'like', "%{$search}%"))
                    ->orWhereHas('driver', fn ($driverQuery) => $driverQuery->where('nombre', 'like', "%{$search}%"));
            });
        }

        if ($this->currentUser()?->role === 'conductor') {
            $driverId = (int) ($this->currentUser()?->resolvedDriver()?->id ?? 0);
            if ($driverId <= 0) {
                $query->whereRaw('1=0');
            } else {
                $query->where('drivers_id', $driverId);
            }
        }

        $logs = $query->paginate(10);

        if ($this->currentUser()?->role === 'conductor') {
            $driverId = (int) ($this->currentUser()?->resolvedDriver()?->id ?? 0);
            $assignmentDate = $this->resolveAssignmentDate();

            $vehicleIds = VehicleAssignment::query()
                ->where('driver_id', $driverId)
                ->where('activo', true)
                ->where(function ($q) use ($assignmentDate) {
                    $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $assignmentDate);
                })
                ->where(function ($q) use ($assignmentDate) {
                    $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $assignmentDate);
                })
                ->pluck('vehicle_id')
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $vehicles = Vehicle::query()
                ->where('activo', true)
                ->whereIn('id', $vehicleIds)
                ->get(['id', 'placa', 'kilometraje_actual', 'kilometraje_inicial', 'kilometraje']);

            $drivers = Driver::query()
                ->where('id', $driverId)
                ->get(['id', 'nombre']);

            $this->drivers_id = $driverId ?: null;
        } else {
            $assignmentDate = $this->resolveAssignmentDate();

            $activeAssignments = VehicleAssignment::query()
                ->where('activo', true)
                ->whereNotNull('vehicle_id')
                ->whereNotNull('driver_id')
                ->where(function ($q) use ($assignmentDate) {
                    $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $assignmentDate);
                })
                ->where(function ($q) use ($assignmentDate) {
                    $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $assignmentDate);
                });

            $vehicleIds = (clone $activeAssignments)
                ->pluck('vehicle_id')
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $driverIds = (clone $activeAssignments)
                ->pluck('driver_id')
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $vehicles = Vehicle::query()
                ->where('activo', true)
                ->whereIn('id', $vehicleIds)
                ->get(['id', 'placa', 'kilometraje_actual', 'kilometraje_inicial', 'kilometraje']);

            $drivers = Driver::query()
                ->where('activo', true)
                ->whereIn('id', $driverIds)
                ->get(['id', 'nombre']);
        }

        if ($this->vehicles_id && !$vehicles->contains('id', $this->vehicles_id)) {
            $selectedVehicle = Vehicle::withTrashed()->find($this->vehicles_id);
            if ($selectedVehicle) {
                $vehicles->push($selectedVehicle);
            }
        }

        if ($this->drivers_id && !$drivers->contains('id', $this->drivers_id)) {
            $selectedDriver = Driver::withTrashed()->find($this->drivers_id);
            if ($selectedDriver) {
                $drivers->push($selectedDriver);
            }
        }

        $fuelLogs = FuelLog::query()
            ->select([
                'id',
                'fecha_emision as fecha',
                'cantidad as galones',
            ])
            ->orderByDesc('id')
            ->get();

        return view('livewire.vehicle-log-manager', [
            'logs' => $logs,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'fuelLogs' => $fuelLogs,
        ]);
    }

    public function save()
    {
        if ($this->currentUser()?->role === 'conductor' && $this->isEdit) {
            session()->flash('error', 'No tiene permiso para editar registros de bitacora.');
            return;
        }

        if ($this->currentUser()?->role === 'conductor') {
            $driverId = (int) ($this->currentUser()?->resolvedDriver()?->id ?? 0);
            if ($driverId <= 0) {
                $this->addError('drivers_id', 'El usuario conductor no tiene perfil de conductor asociado.');
                return;
            }

            $this->drivers_id = $driverId;
            $assignmentDate = $this->resolveAssignmentDate();

            $isAssigned = VehicleAssignment::query()
                ->where('driver_id', $driverId)
                ->where('vehicle_id', $this->vehicles_id)
                ->where('activo', true)
                ->where(function ($q) use ($assignmentDate) {
                    $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $assignmentDate);
                })
                ->where(function ($q) use ($assignmentDate) {
                    $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $assignmentDate);
                })
                ->exists();

            if (!$isAssigned) {
                $this->addError('vehicles_id', 'Solo puede registrar bitacora para su vehiculo asignado.');
                return;
            }
        }

        $this->validate();

        $this->recorrido_inicio = trim($this->recorrido_inicio);
        $this->recorrido_destino = trim($this->recorrido_destino);

        if (!$this->validateTripAndKmRules()) {
            return;
        }

        $locationPayload = $this->resolveLocationPayload();

        $data = [
            ...$locationPayload,
            'vehicles_id' => $this->vehicles_id,
            'drivers_id' => $this->drivers_id,
            'fuel_log_id' => $this->fuel_log_id,
            'fecha' => $this->fecha,
            'kilometraje_salida' => $this->kilometraje_salida,
            'kilometraje_llegada' => $this->kilometraje_llegada,
            'recorrido_inicio' => $this->recorrido_inicio,
            'recorrido_destino' => $this->recorrido_destino,
            'abastecimiento_combustible' => $this->abastecimiento_combustible,
            'firma_digital' => $this->firma_digital,
            'ruta_json' => $this->buildRoutePoints($locationPayload),
        ];

        if ($this->isEdit && $this->editingLogId) {
            $log = VehicleLog::find($this->editingLogId);
            if ($log) {
                $log->update($data);
                $this->updateVehicleKilometraje(
                    $this->vehicles_id,
                    $this->kilometraje_llegada ?? $this->kilometraje_salida,
                    $this->kilometraje_salida
                );
                session()->flash('message', 'Registro de bitacora actualizado correctamente.');
            }
        } else {
            VehicleLog::create($data);
            $this->updateVehicleKilometraje(
                $this->vehicles_id,
                $this->kilometraje_llegada ?? $this->kilometraje_salida,
                $this->kilometraje_salida
            );
            session()->flash('message', 'Registro de bitacora creado correctamente.');
        }

        $this->resetForm();
    }

    public function edit(VehicleLog $log)
    {
        if ($this->currentUser()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para editar registros de bitacora.');
            return;
        }

        $this->showForm = true;
        $this->isEdit = true;
        $this->editingLogId = $log->id;
        $this->vehicles_id = $log->vehicles_id;
        $this->drivers_id = $log->drivers_id;
        $this->fuel_log_id = $log->fuel_log_id;
        $this->fecha = optional($log->fecha)->format('Y-m-d');
        $this->kilometraje_salida = $log->kilometraje_salida !== null ? (float) $log->kilometraje_salida : null;
        $this->kilometraje_llegada = $log->kilometraje_llegada !== null ? (float) $log->kilometraje_llegada : null;
        $this->recorrido_inicio = (string) $log->recorrido_inicio;
        $this->recorrido_destino = (string) $log->recorrido_destino;
        $this->latitud_inicio = $log->latitud_inicio !== null ? (float) $log->latitud_inicio : null;
        $this->logitud_inicio = $log->logitud_inicio !== null ? (float) $log->logitud_inicio : null;
        $this->latitud_destino = $log->latitud_destino !== null ? (float) $log->latitud_destino : null;
        $this->logitud_destino = $log->logitud_destino !== null ? (float) $log->logitud_destino : null;
        $this->abastecimiento_combustible = (bool) $log->abastecimiento_combustible;
        $this->firma_digital = $log->firma_digital;
    }

    public function delete(VehicleLog $log)
    {
        if ($this->currentUser()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para eliminar registros de bitacora.');
            return;
        }

        $log->delete();
        session()->flash('message', 'Registro de bitacora eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->vehicles_id = null;
        $this->drivers_id = null;
        $this->fuel_log_id = null;
        $this->fecha = null;
        $this->kilometraje_salida = null;
        $this->kilometraje_llegada = null;
        $this->recorrido_inicio = '';
        $this->recorrido_destino = '';
        $this->latitud_inicio = null;
        $this->logitud_inicio = null;
        $this->latitud_destino = null;
        $this->logitud_destino = null;
        $this->abastecimiento_combustible = false;
        $this->firma_digital = null;
        $this->isEdit = false;
        $this->editingLogId = null;
        $this->showForm = false;
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function cancelForm()
    {
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function searchLogs(): void
    {
        $this->search = trim((string) $this->search);
        $this->resetPage();
    }

    public function updatedVehiclesId($value): void
    {
        $vehicleId = $value !== null && $value !== '' ? (int) $value : null;
        $this->syncKmSalidaFromVehicle($vehicleId);
        $this->syncDriverFromVehicle($vehicleId);
    }

    public function onVehicleChanged($value): void
    {
        $this->vehicles_id = $value !== null && $value !== '' ? (int) $value : null;
        $this->syncKmSalidaFromVehicle($this->vehicles_id);
        $this->syncDriverFromVehicle($this->vehicles_id);
    }

    private function resolveLocationPayload(): array
    {
        [$latInicio, $lngInicio] = $this->resolveCoordinates($this->latitud_inicio, $this->logitud_inicio, $this->recorrido_inicio);
        [$latDestino, $lngDestino] = $this->resolveCoordinates($this->latitud_destino, $this->logitud_destino, $this->recorrido_destino);

        $this->latitud_inicio = $latInicio;
        $this->logitud_inicio = $lngInicio;
        $this->latitud_destino = $latDestino;
        $this->logitud_destino = $lngDestino;

        return [
            'latitud_inicio' => $latInicio,
            'logitud_inicio' => $lngInicio,
            'latitud_destino' => $latDestino,
            'logitud_destino' => $lngDestino,
        ];
    }

    private function resolveCoordinates(?float $lat, ?float $lng, ?string $sourceText): array
    {
        if ($lat !== null && $lng !== null) {
            $safeLat = $this->normalizeLatitude((float) $lat);
            $safeLng = $this->normalizeLongitude((float) $lng);
            if ($safeLat !== null && $safeLng !== null) {
                return [$safeLat, $safeLng];
            }
        }

        if ($sourceText && preg_match('/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/', $sourceText, $m)) {
            $safeLat = $this->normalizeLatitude((float) $m[1]);
            $safeLng = $this->normalizeLongitude((float) $m[2]);
            if ($safeLat !== null && $safeLng !== null) {
                return [$safeLat, $safeLng];
            }
        }

        return [null, null];
    }

    private function normalizeLatitude(float $value): ?float
    {
        if ($value < -90 || $value > 90) {
            return null;
        }

        return round($value, 8);
    }

    private function normalizeLongitude(float $value): ?float
    {
        if ($value < -180 || $value > 180) {
            return null;
        }

        return round($value, 8);
    }

    private function updateVehicleKilometraje(?int $vehicleId, ?float $kmActual, ?float $kmInicial): void
    {
        if (!$vehicleId) {
            return;
        }

        $vehicle = Vehicle::find($vehicleId);
        if (!$vehicle) {
            return;
        }

        $updates = [];
        if ($kmInicial !== null && $vehicle->kilometraje_inicial === null) {
            $updates['kilometraje_inicial'] = $kmInicial;
        }

        if ($kmActual !== null) {
            $prev = $vehicle->kilometraje_actual !== null ? (float) $vehicle->kilometraje_actual : null;
            if ($prev === null || $kmActual >= $prev) {
                $updates['kilometraje_actual'] = $kmActual;
                $updates['kilometraje'] = $kmActual;
            }
        }

        if (!empty($updates)) {
            $vehicle->update($updates);
        }

        MaintenanceAlertService::evaluateVehicleByKilometraje((int) $vehicleId);
    }

    private function syncKmSalidaFromVehicle(?int $vehicleId): void
    {
        if (!$vehicleId) {
            $this->kilometraje_salida = null;
            return;
        }

        $vehicle = Vehicle::query()
            ->select(['id', 'kilometraje_actual', 'kilometraje_inicial', 'kilometraje'])
            ->find($vehicleId);

        if (!$vehicle) {
            $this->kilometraje_salida = null;
            return;
        }

        $km = $vehicle->kilometraje_actual ?? $vehicle->kilometraje_inicial ?? $vehicle->kilometraje;
        $this->kilometraje_salida = $km !== null ? (float) $km : null;
    }

    private function resolveAssignmentDate(): string
    {
        if (!$this->fecha) {
            return now()->toDateString();
        }

        try {
            return Carbon::parse($this->fecha)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function syncDriverFromVehicle(?int $vehicleId): void
    {
        if (!$vehicleId || $this->currentUser()?->role === 'conductor') {
            return;
        }

        $assignmentDate = $this->resolveAssignmentDate();
        $assignment = VehicleAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->whereNotNull('driver_id')
            ->where('activo', true)
            ->where(function ($q) use ($assignmentDate) {
                $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $assignmentDate);
            })
            ->where(function ($q) use ($assignmentDate) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $assignmentDate);
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        if ($assignment?->driver_id) {
            $this->drivers_id = (int) $assignment->driver_id;
        }
    }

    private function validateTripAndKmRules(): bool
    {
        $kmSalida = $this->kilometraje_salida !== null ? (float) $this->kilometraje_salida : null;
        $kmLlegada = $this->kilometraje_llegada !== null ? (float) $this->kilometraje_llegada : null;

        if ($kmSalida !== null && $kmLlegada !== null && $kmLlegada < $kmSalida) {
            $this->addError('kilometraje_llegada', 'El kilometraje de llegada no puede ser menor al kilometraje de salida.');
            return false;
        }

        [$latInicio, $lngInicio] = $this->resolveCoordinates($this->latitud_inicio, $this->logitud_inicio, $this->recorrido_inicio);
        [$latDestino, $lngDestino] = $this->resolveCoordinates($this->latitud_destino, $this->logitud_destino, $this->recorrido_destino);

        if ($latInicio === null || $lngInicio === null) {
            $this->addError('recorrido_inicio', 'Seleccione un punto de inicio valido en el mapa.');
            return false;
        }

        if ($latDestino === null || $lngDestino === null) {
            $this->addError('recorrido_destino', 'Seleccione un punto de destino valido en el mapa.');
            return false;
        }

        if (abs($latInicio - $latDestino) < 0.0000001 && abs($lngInicio - $lngDestino) < 0.0000001) {
            $this->addError('recorrido_destino', 'El destino debe ser diferente del inicio.');
            return false;
        }

        return true;
    }

    private function buildRoutePoints(array $locationPayload): array
    {
        $route = [];
        $timestamp = $this->fecha
            ? Carbon::parse($this->fecha)->startOfDay()->toIso8601String()
            : now()->toIso8601String();

        $startLat = $locationPayload['latitud_inicio'] ?? null;
        $startLng = $locationPayload['logitud_inicio'] ?? null;
        $endLat = $locationPayload['latitud_destino'] ?? null;
        $endLng = $locationPayload['logitud_destino'] ?? null;

        if ($startLat !== null && $startLng !== null) {
            $route[] = [
                'lat' => (float) $startLat,
                'lng' => (float) $startLng,
                't' => $timestamp,
                'address' => $this->recorrido_inicio,
                'label' => 'Inicio',
                'is_marked' => true,
                'index' => 0,
            ];
        }

        if (
            $endLat !== null && $endLng !== null &&
            (
                empty($route) ||
                abs((float) $endLat - (float) ($startLat ?? 0)) > 0.0000001 ||
                abs((float) $endLng - (float) ($startLng ?? 0)) > 0.0000001
            )
        ) {
            $route[] = [
                'lat' => (float) $endLat,
                'lng' => (float) $endLng,
                't' => $timestamp,
                'address' => $this->recorrido_destino,
                'label' => 'Destino',
                'is_marked' => true,
                'index' => count($route),
            ];
        }

        return $route;
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
