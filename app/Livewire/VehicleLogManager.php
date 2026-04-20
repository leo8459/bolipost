<?php

namespace App\Livewire;

use App\Models\Driver;
use App\Models\Estado;
use App\Models\FuelLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleLog;
use App\Models\VehicleOperationAlert;
use App\Services\MaintenanceAlertService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;

class VehicleLogManager extends Component
{
    use WithFileUploads;
    use WithPagination;
    use WithoutUrlPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Validate('required|integer|exists:vehicles,id')]
    public ?int $vehicles_id = null;

    public ?int $drivers_id = null;
    public ?int $fuel_log_id = null;

    #[Validate('required|date')]
    public ?string $fecha = null;

    #[Validate('required|numeric|min:0')]
    public ?float $kilometraje_salida = null;

    #[Validate('required|numeric|min:0')]
    public ?float $kilometraje_recorrido = null;

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
    public $odometro_photo = null;
    public ?string $currentOdometroPhotoPath = null;
    public ?string $currentOdometroPhotoUrl = null;

    public bool $isEdit = false;
    public ?int $editingLogId = null;
    public bool $showForm = false;
    public string $search = '';
    public string $table_view = 'logs';
    public ?string $fecha_desde = null;
    public ?string $fecha_hasta = null;
    public ?int $vehicle_filter_id = null;
    public ?int $driver_filter_id = null;

    public function mount(): void
    {
        abort_unless(in_array($this->currentUser()?->role, ['admin', 'recepcion', 'conductor']), 403);
        $today = now()->toDateString();
        $this->fecha_desde = $today;
        $this->fecha_hasta = $today;
    }

    public function render()
    {
        $query = VehicleLog::query()
            ->active()
            ->with(['vehicle.brand', 'vehicle.vehicleClass', 'driver', 'fuelLog'])
            ->orderByDesc('fecha')
            ->orderByDesc('id');
        $operationAlertsQuery = VehicleOperationAlert::query()
            ->with(['vehicle', 'session.driver'])
            ->where('status', VehicleOperationAlert::STATUS_ACTIVE)
            ->orderByDesc('detected_at')
            ->orderByDesc('id');

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('recorrido_inicio', 'like', "%{$search}%")
                    ->orWhere('recorrido_destino', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(fecha AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(kilometraje_salida AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(kilometraje_llegada AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(kilometraje_recorrido AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('placa', 'like', "%{$search}%"))
                    ->orWhereHas('driver', fn ($driverQuery) => $driverQuery->where('nombre', 'like', "%{$search}%"));
            });

            $operationAlertsQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('current_stage', 'like', "%{$search}%")
                    ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('placa', 'like', "%{$search}%"))
                    ->orWhereHas('session.driver', fn ($driverQuery) => $driverQuery->where('nombre', 'like', "%{$search}%"));
            });
        }

        if ($this->currentUser()?->role === 'conductor') {
            $driverId = (int) ($this->currentUser()?->resolvedDriver()?->id ?? 0);
            if ($driverId <= 0) {
                $query->whereRaw('1=0');
                $operationAlertsQuery->whereRaw('1=0');
            } else {
                $query->where('drivers_id', $driverId);
                $operationAlertsQuery->whereHas('session', fn ($sessionQuery) => $sessionQuery
                    ->where('current_driver_id', $driverId)
                    ->orWhere('responsible_driver_id', $driverId));
                $this->driver_filter_id = $driverId;
            }
        }

        [$fechaDesde, $fechaHasta] = $this->resolveOrderedFilterDateRange();

        if ($fechaDesde) {
            $query->whereDate('fecha', '>=', $fechaDesde);
            $operationAlertsQuery->whereDate('detected_at', '>=', $fechaDesde);
        }

        if ($fechaHasta) {
            $query->whereDate('fecha', '<=', $fechaHasta);
            $operationAlertsQuery->whereDate('detected_at', '<=', $fechaHasta);
        }

        if ($this->vehicle_filter_id) {
            $query->where('vehicles_id', (int) $this->vehicle_filter_id);
            $operationAlertsQuery->where('vehicle_id', (int) $this->vehicle_filter_id);
        }

        if ($this->driver_filter_id) {
            $query->where('drivers_id', (int) $this->driver_filter_id);
            $operationAlertsQuery->whereHas('session', fn ($sessionQuery) => $sessionQuery
                ->where('current_driver_id', (int) $this->driver_filter_id)
                ->orWhere('responsible_driver_id', (int) $this->driver_filter_id));
        }

        $logs = $this->paginateWithinBounds($query, 10);
        $operationAlerts = $this->paginateWithinBounds($operationAlertsQuery, 10, 'alertsPage');
        $crossSummary = $this->buildOperationalSummary($logs);

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
                ->operationallyAvailable()
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
                ->operationallyAvailable()
                ->whereIn('id', $vehicleIds)
                ->get(['id', 'placa', 'kilometraje_actual', 'kilometraje_inicial', 'kilometraje']);

            $drivers = Driver::query()
                ->where('activo', true)
                ->whereIn('id', $driverIds)
                ->get(['id', 'nombre']);
        }

        if ($this->isEdit && $this->vehicles_id && !$vehicles->contains('id', $this->vehicles_id)) {
            $selectedVehicle = Vehicle::withTrashed()->find($this->vehicles_id);
            if ($selectedVehicle) {
                $vehicles->push($selectedVehicle);
            }
        }

        if ($this->isEdit && $this->drivers_id && !$drivers->contains('id', $this->drivers_id)) {
            $selectedDriver = Driver::withTrashed()->find($this->drivers_id);
            if ($selectedDriver) {
                $drivers->push($selectedDriver);
            }
        }

        $fuelLogs = FuelLog::query()
            ->active()
            ->leftJoin('fuel_invoices as fi', 'fuel_invoice_details.fuel_invoice_id', '=', 'fi.id')
            ->select([
                'fuel_invoice_details.id',
                'fi.fecha_emision as fecha',
                'fuel_invoice_details.cantidad as galones',
            ])
            ->orderByDesc('fuel_invoice_details.id')
            ->get();

        return view('livewire.vehicle-log-manager', [
            'logs' => $logs,
            'operationAlerts' => $operationAlerts,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'fuelLogs' => $fuelLogs,
            'crossSummary' => $crossSummary,
        ]);
    }

    public function showLogsTable(): void
    {
        $this->table_view = 'logs';
    }

    public function showOperationalAlertsTable(): void
    {
        $this->table_view = 'alerts';
    }

    public function markOperationalAlertReviewed(int $alertId): void
    {
        $alert = VehicleOperationAlert::query()->find($alertId);
        if (!$alert) {
            session()->flash('error', 'La alerta operativa ya no existe.');
            return;
        }

        $alert->update([
            'status' => VehicleOperationAlert::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);

        session()->flash('message', 'Alerta operativa marcada como revisada.');
    }

    public function save(): void
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
        }

        $odometroPhotoRules = ['image', 'max:5120'];
        if (!$this->isEdit || empty($this->currentOdometroPhotoPath)) {
            array_unshift($odometroPhotoRules, 'required');
        } else {
            array_unshift($odometroPhotoRules, 'nullable');
        }

        $this->validate(
            [
                'vehicles_id' => ['required', 'integer', 'min:1', 'exists:vehicles,id'],
                'drivers_id' => ['required', 'integer', 'min:1', 'exists:drivers,id'],
                'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'kilometraje_salida' => ['required', 'numeric', 'min:0'],
                'kilometraje_recorrido' => ['required', 'numeric', 'min:0'],
                'recorrido_inicio' => ['required', 'string', 'max:255'],
                'recorrido_destino' => ['required', 'string', 'max:255', 'different:recorrido_inicio'],
                'odometro_photo' => $odometroPhotoRules,
            ],
            [
                'vehicles_id.required' => 'Debe seleccionar un vehiculo.',
                'vehicles_id.integer' => 'El vehiculo seleccionado no es valido.',
                'vehicles_id.min' => 'Debe seleccionar un vehiculo valido.',
                'vehicles_id.exists' => 'El vehiculo seleccionado no existe.',
                'drivers_id.required' => 'Debe seleccionar un conductor.',
                'drivers_id.integer' => 'El conductor seleccionado no es valido.',
                'drivers_id.min' => 'Debe seleccionar un conductor valido.',
                'drivers_id.exists' => 'El conductor seleccionado no existe.',
                'fecha.required' => 'La fecha es obligatoria.',
                'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
                'fecha.before_or_equal' => 'La fecha no puede ser mayor a hoy.',
                'kilometraje_salida.required' => 'El kilometraje de salida es obligatorio.',
                'kilometraje_salida.numeric' => 'El kilometraje de salida debe ser numerico.',
                'kilometraje_salida.min' => 'El kilometraje de salida no puede ser negativo.',
                'kilometraje_recorrido.required' => 'El kilometraje recorrido es obligatorio.',
                'kilometraje_recorrido.numeric' => 'El kilometraje recorrido debe ser numerico.',
                'kilometraje_recorrido.min' => 'El kilometraje recorrido no puede ser negativo.',
                'recorrido_inicio.required' => 'El recorrido de inicio es obligatorio.',
                'recorrido_inicio.max' => 'El recorrido de inicio no puede superar los 255 caracteres.',
                'recorrido_destino.required' => 'El recorrido de destino es obligatorio.',
                'recorrido_destino.max' => 'El recorrido de destino no puede superar los 255 caracteres.',
                'recorrido_destino.different' => 'El destino debe ser diferente del inicio.',
                'odometro_photo.required' => 'La foto de odometro es obligatoria.',
                'odometro_photo.image' => 'La foto de odometro debe ser una imagen valida.',
                'odometro_photo.max' => 'La foto de odometro no puede superar 5 MB.',
            ]
        );

        if (!$this->ensureVehicleHasAssignedDriverForDate()) {
            return;
        }

        $vehicle = Vehicle::query()->find($this->vehicles_id);
        $blockReason = MaintenanceAlertService::resolveVehicleLogBlockReason($vehicle);
        if ($blockReason !== null) {
            $this->addError('vehicles_id', $blockReason);
            return;
        }

        $this->recorrido_inicio = trim($this->recorrido_inicio);
        $this->recorrido_destino = trim($this->recorrido_destino);
        $this->kilometraje_salida = $this->resolveReadonlyKilometrajeSalida($this->vehicles_id);
        $this->kilometraje_llegada = $this->calculateKilometrajeLlegada();

        if (!$this->validateTripAndKmRules()) {
            return;
        }

        $locationPayload = $this->resolveLocationPayload();
        $photoPath = $this->storeOdometroPhoto();

        $data = [
            ...$locationPayload,
            'vehicles_id' => $this->vehicles_id,
            'drivers_id' => $this->drivers_id,
            'fuel_log_id' => $this->fuel_log_id,
            'fecha' => $this->fecha,
            'kilometraje_salida' => $this->kilometraje_salida,
            'kilometraje_recorrido' => $this->kilometraje_recorrido,
            'kilometraje_llegada' => $this->kilometraje_llegada,
            'recorrido_inicio' => $this->recorrido_inicio,
            'recorrido_destino' => $this->recorrido_destino,
            'abastecimiento_combustible' => $this->resolveAbastecimientoCombustible(),
            'firma_digital' => $this->firma_digital,
            'odometro_photo_path' => $photoPath,
            'ruta_json' => $this->buildRoutePoints($locationPayload),
        ];

        if ($this->isEdit && $this->editingLogId) {
            $log = VehicleLog::find($this->editingLogId);
            if ($log) {
                $log->update($data);
                $kmUpdateStatus = $this->updateVehicleKilometraje(
                    $this->vehicles_id,
                    $this->kilometraje_llegada,
                    $this->kilometraje_salida
                );
                session()->flash('message', 'Registro de bitacora actualizado correctamente.' . ($kmUpdateStatus === 'same' ? ' El kilometraje se mantuvo igual al anterior.' : ''));
            }
        } else {
            VehicleLog::create($data);
            $kmUpdateStatus = $this->updateVehicleKilometraje(
                $this->vehicles_id,
                $this->kilometraje_llegada,
                $this->kilometraje_salida
            );
            session()->flash('message', 'Registro de bitacora creado correctamente.' . ($kmUpdateStatus === 'same' ? ' El kilometraje se mantuvo igual al anterior.' : ''));
        }

        $this->resetForm();
    }

    public function edit(VehicleLog $log): void
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
        $this->kilometraje_recorrido = $log->kilometraje_recorrido !== null
            ? (float) $log->kilometraje_recorrido
            : (($this->kilometraje_llegada !== null && $this->kilometraje_salida !== null)
                ? max(0, $this->kilometraje_llegada - $this->kilometraje_salida)
                : null);
        $this->recorrido_inicio = (string) $log->recorrido_inicio;
        $this->recorrido_destino = (string) $log->recorrido_destino;
        $this->latitud_inicio = $log->latitud_inicio !== null ? (float) $log->latitud_inicio : null;
        $this->logitud_inicio = $log->logitud_inicio !== null ? (float) $log->logitud_inicio : null;
        $this->latitud_destino = $log->latitud_destino !== null ? (float) $log->latitud_destino : null;
        $this->logitud_destino = $log->logitud_destino !== null ? (float) $log->logitud_destino : null;
        $this->abastecimiento_combustible = $this->resolveFuelLinkedFlag($log);
        $this->firma_digital = $log->firma_digital;
        $this->odometro_photo = null;
        $this->currentOdometroPhotoPath = $log->odometro_photo_path;
        $this->currentOdometroPhotoUrl = $log->odometro_photo_path
            ? asset('storage/' . ltrim($log->odometro_photo_path, '/'))
            : null;
    }

    public function delete(VehicleLog $log): void
    {
        if ($this->currentUser()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para eliminar registros de bitacora.');
            return;
        }

        $log->update(['activo' => false]);
        session()->flash('message', 'Registro de bitacora inactivado correctamente.');
    }

    public function resetForm(): void
    {
        $this->vehicles_id = null;
        $this->drivers_id = null;
        $this->fuel_log_id = null;
        $this->fecha = null;
        $this->kilometraje_salida = null;
        $this->kilometraje_recorrido = null;
        $this->kilometraje_llegada = null;
        $this->recorrido_inicio = '';
        $this->recorrido_destino = '';
        $this->latitud_inicio = null;
        $this->logitud_inicio = null;
        $this->latitud_destino = null;
        $this->logitud_destino = null;
        $this->abastecimiento_combustible = false;
        $this->firma_digital = null;
        $this->odometro_photo = null;
        $this->currentOdometroPhotoPath = null;
        $this->currentOdometroPhotoUrl = null;
        $this->isEdit = false;
        $this->editingLogId = null;
        $this->showForm = false;
        $this->resetListPages();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetListPages();
    }

    public function updatedFechaDesde(): void
    {
        $this->validateFilterDateRange();
        $this->resetListPages();
    }

    public function updatedFechaHasta(): void
    {
        $this->validateFilterDateRange();
        $this->resetListPages();
    }

    public function updatedVehicleFilterId(): void
    {
        $this->resetListPages();
    }

    public function updatedDriverFilterId(): void
    {
        $this->resetListPages();
    }

    public function searchLogs(): void
    {
        $this->search = trim((string) $this->search);
        $this->resetListPages();
    }

    public function limpiarFiltrosListado(): void
    {
        $today = now()->toDateString();
        $this->fecha_desde = $today;
        $this->fecha_hasta = $today;
        $this->resetValidation(['fecha_desde', 'fecha_hasta']);
        $this->vehicle_filter_id = null;
        $this->driver_filter_id = $this->currentUser()?->role === 'conductor'
            ? (int) ($this->currentUser()?->resolvedDriver()?->id ?? 0) ?: null
            : null;
        $this->resetListPages();
    }

    public function updatedVehiclesId($value): void
    {
        $vehicleId = $value !== null && $value !== '' ? (int) $value : null;
        $this->syncKmSalidaFromVehicle($vehicleId);
        $this->syncDriverFromVehicle($vehicleId);
    }

    public function updatedKilometrajeRecorrido($value): void
    {
        $this->kilometraje_recorrido = $value !== null && $value !== '' ? (float) $value : null;
        $this->kilometraje_llegada = $this->calculateKilometrajeLlegada();
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

    private function updateVehicleKilometraje(?int $vehicleId, ?float $kmActual, ?float $kmInicial): string
    {
        if (!$vehicleId) {
            return 'skipped';
        }

        $vehicle = Vehicle::find($vehicleId);
        if (!$vehicle) {
            return 'skipped';
        }

        if ((bool) ($vehicle->tacometro_danado ?? false)) {
            return 'skipped';
        }

        $updates = [];
        $status = 'skipped';
        if ($kmInicial !== null && $vehicle->kilometraje_inicial === null) {
            $updates['kilometraje_inicial'] = $kmInicial;
        }

        if ($kmActual !== null) {
            $prev = $vehicle->kilometraje_actual !== null ? (float) $vehicle->kilometraje_actual : null;
            if ($prev === null || $kmActual >= $prev) {
                $updates['kilometraje_actual'] = $kmActual;
                $updates['kilometraje'] = $kmActual;
                $status = $prev !== null && abs($kmActual - $prev) < 0.000001 ? 'same' : 'updated';
            }
        }

        if (!empty($updates)) {
            $vehicle->update($updates);
        }

        MaintenanceAlertService::evaluateVehicleByKilometraje((int) $vehicleId);
        return $status;
    }

    private function syncKmSalidaFromVehicle(?int $vehicleId): void
    {
        if (!$vehicleId) {
            $this->kilometraje_salida = null;
            $this->kilometraje_llegada = null;
            return;
        }

        $vehicle = Vehicle::query()
            ->select(['id', 'kilometraje_actual', 'kilometraje_inicial', 'kilometraje'])
            ->find($vehicleId);

        if (!$vehicle) {
            $this->kilometraje_salida = null;
            $this->kilometraje_llegada = null;
            return;
        }

        $km = $vehicle->kilometraje_actual ?? $vehicle->kilometraje_inicial ?? $vehicle->kilometraje;
        $this->kilometraje_salida = $km !== null ? (float) $km : null;
        $this->kilometraje_llegada = $this->calculateKilometrajeLlegada();
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

    private function ensureVehicleHasAssignedDriverForDate(): bool
    {
        $vehicleId = (int) ($this->vehicles_id ?? 0);
        $driverId = (int) ($this->drivers_id ?? 0);

        if ($vehicleId <= 0 || $driverId <= 0) {
            return true;
        }

        $assignmentDate = $this->resolveAssignmentDate();

        $vehicleHasAssignedDriver = VehicleAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->whereNotNull('driver_id')
            ->where('activo', true)
            ->where(function ($q) use ($assignmentDate) {
                $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $assignmentDate);
            })
            ->where(function ($q) use ($assignmentDate) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $assignmentDate);
            })
            ->exists();

        if (!$vehicleHasAssignedDriver) {
            $this->addError('vehicles_id', 'El vehiculo seleccionado no tiene un conductor asignado para la fecha indicada.');
            return false;
        }

        $pairAssignmentExists = VehicleAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where('driver_id', $driverId)
            ->where('activo', true)
            ->where(function ($q) use ($assignmentDate) {
                $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $assignmentDate);
            })
            ->where(function ($q) use ($assignmentDate) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $assignmentDate);
            })
            ->exists();

        if (!$pairAssignmentExists) {
            if ($this->currentUser()?->role === 'conductor') {
                $this->addError('vehicles_id', 'Solo puede registrar bitacora para su vehiculo asignado.');
                return false;
            }

            $this->addError('drivers_id', 'El conductor seleccionado no tiene asignado este vehiculo en la fecha indicada.');
            return false;
        }

        return true;
    }

    private function validateFilterDateRange(): void
    {
        $desde = $this->toDateStringOrNull($this->fecha_desde);
        $hasta = $this->toDateStringOrNull($this->fecha_hasta);

        if (!$desde || !$hasta) {
            $this->resetValidation('fecha_hasta');
            return;
        }

        if ($desde > $hasta) {
            $this->addError('fecha_hasta', 'La fecha hasta no puede ser menor que la fecha desde.');
            return;
        }

        $this->resetValidation('fecha_hasta');
    }

    private function resolveOrderedFilterDateRange(): array
    {
        $desde = $this->toDateStringOrNull($this->fecha_desde);
        $hasta = $this->toDateStringOrNull($this->fecha_hasta);

        if ($desde && $hasta && $desde > $hasta) {
            return [$hasta, $desde];
        }

        return [$desde, $hasta];
    }

    private function toDateStringOrNull(?string $value): ?string
    {
        if (!filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
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
        $kmRecorrido = $this->kilometraje_recorrido !== null ? (float) $this->kilometraje_recorrido : null;
        $kmLlegada = $this->kilometraje_llegada !== null ? (float) $this->kilometraje_llegada : null;

        if ($kmSalida === null || $kmSalida <= 0) {
            $this->addError('kilometraje_salida', 'El kilometraje de salida debe ser mayor a 0.');
            return false;
        }

        if ($kmRecorrido === null || $kmRecorrido < 0) {
            $this->addError('kilometraje_recorrido', 'El kilometraje recorrido debe ser 0 o mayor.');
            return false;
        }

        if ($kmLlegada === null || $kmLlegada < $kmSalida) {
            $this->addError('kilometraje_recorrido', 'El kilometraje final no puede ser menor al kilometraje de salida.');
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

    private function calculateKilometrajeLlegada(): ?float
    {
        if ($this->kilometraje_salida === null || $this->kilometraje_recorrido === null) {
            return null;
        }

        return round((float) $this->kilometraje_salida + (float) $this->kilometraje_recorrido, 2);
    }

    private function resolveReadonlyKilometrajeSalida(?int $vehicleId): ?float
    {
        if ($this->isEdit && $this->editingLogId && $this->kilometraje_salida !== null) {
            return (float) $this->kilometraje_salida;
        }

        if (!$vehicleId) {
            return $this->kilometraje_salida;
        }

        $vehicle = Vehicle::query()
            ->select(['id', 'kilometraje_actual', 'kilometraje_inicial', 'kilometraje'])
            ->find($vehicleId);

        if (!$vehicle) {
            return $this->kilometraje_salida;
        }

        $km = $vehicle->kilometraje_actual ?? $vehicle->kilometraje_inicial ?? $vehicle->kilometraje;

        return $km !== null ? (float) $km : $this->kilometraje_salida;
    }

    private function storeOdometroPhoto(): ?string
    {
        if ($this->odometro_photo) {
            if ($this->currentOdometroPhotoPath && Storage::disk('public')->exists($this->currentOdometroPhotoPath)) {
                Storage::disk('public')->delete($this->currentOdometroPhotoPath);
            }

            $path = (string) $this->odometro_photo->store('vehicle-log/odometro', 'public');
            $this->currentOdometroPhotoPath = $path;
            $this->currentOdometroPhotoUrl = asset('storage/' . $path);

            return $path;
        }

        return $this->currentOdometroPhotoPath;
    }

    private function resolveAbastecimientoCombustible(): bool
    {
        if ($this->fuel_log_id) {
            return true;
        }

        return (bool) $this->abastecimiento_combustible;
    }

    private function resolveFuelLinkedFlag(VehicleLog $log): bool
    {
        if ($log->fuel_log_id) {
            return true;
        }

        return (bool) $log->abastecimiento_combustible;
    }

    private function buildOperationalSummary($logs): array
    {
        $items = collect($logs->items());
        $default = [
            'plate_label' => 'Todas las placas',
            'month_label' => 'Sin rango',
            'date_label' => 'Sin fechas',
            'fuel_total' => 0.0,
            'package_total' => 0,
            'guides_label' => 'Guias entregadas',
        ];

        if ($items->isEmpty()) {
            return $default;
        }

        $driverUserIds = $items
            ->map(fn ($log) => (int) ($log->driver?->user_id ?? 0))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $dates = $items
            ->map(fn ($log) => optional($log->fecha)->format('Y-m-d'))
            ->filter()
            ->unique()
            ->values();

        $deliveryCounts = collect();
        $deliveredStateIds = Estado::query()
            ->whereRaw('UPPER(nombre_estado) LIKE ?', ['%ENTREGADO%'])
            ->pluck('id');

        if ($driverUserIds->isNotEmpty() && $dates->isNotEmpty() && $deliveredStateIds->isNotEmpty()) {
            $minDate = $dates->min();
            $maxDate = $dates->max();

            $deliveryCounts = DB::table('cartero')
                ->selectRaw('id_user, DATE(COALESCE(updated_at, created_at)) as event_date, COUNT(*) as delivered_count')
                ->whereIn('id_user', $driverUserIds->all())
                ->whereIn('id_estados', $deliveredStateIds->all())
                ->whereBetween(DB::raw('DATE(COALESCE(updated_at, created_at))'), [$minDate, $maxDate])
                ->groupBy('id_user', DB::raw('DATE(COALESCE(updated_at, created_at))'))
                ->get()
                ->mapWithKeys(fn ($row) => [
                    ((int) $row->id_user) . '|' . (string) $row->event_date => (int) $row->delivered_count,
                ]);
        }

        $items->transform(function ($log) use ($deliveryCounts) {
            $userId = (int) ($log->driver?->user_id ?? 0);
            $date = optional($log->fecha)->format('Y-m-d');
            $log->package_count = (int) ($deliveryCounts[$userId . '|' . $date] ?? 0);

            return $log;
        });

        $logs->setCollection($items);

        $selectedVehicle = $this->vehicle_filter_id
            ? Vehicle::query()->find((int) $this->vehicle_filter_id)
            : null;

        $plateLabel = $selectedVehicle?->placa
            ?: (($items->pluck('vehicle.placa')->filter()->unique()->count() === 1)
                ? (string) $items->pluck('vehicle.placa')->filter()->first()
                : 'Todas las placas');

        $monthLabel = 'Sin rango';
        if ($this->fecha_desde && $this->fecha_hasta) {
            $from = Carbon::parse($this->fecha_desde);
            $to = Carbon::parse($this->fecha_hasta);
            $monthLabel = ucfirst($from->translatedFormat('F Y'));
            if ($from->format('Y-m') !== $to->format('Y-m')) {
                $monthLabel .= ' - ' . ucfirst($to->translatedFormat('F Y'));
            }
        } elseif ($this->fecha_desde) {
            $monthLabel = ucfirst(Carbon::parse($this->fecha_desde)->translatedFormat('F Y'));
        }

        $dateLabel = 'Sin fechas';
        if ($this->fecha_desde && $this->fecha_hasta) {
            $dateLabel = Carbon::parse($this->fecha_desde)->format('d/m/Y') . ' - ' . Carbon::parse($this->fecha_hasta)->format('d/m/Y');
        } elseif ($this->fecha_desde) {
            $dateLabel = Carbon::parse($this->fecha_desde)->format('d/m/Y');
        }

        return [
            'plate_label' => $plateLabel,
            'month_label' => $monthLabel,
            'date_label' => $dateLabel,
            'fuel_total' => round((float) $items->sum(fn ($log) => (float) ($log->fuelLog?->cantidad ?? 0)), 2),
            'package_total' => (int) $items->sum(fn ($log) => (int) ($log->package_count ?? 0)),
            'guides_label' => 'Guias entregadas',
        ];
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private function resetListPages(): void
    {
        $this->resetPage();
        $this->resetPage('logsPage');
        $this->resetPage('alertsPage');
    }

    private function paginateWithinBounds($query, int $perPage, string $pageName = 'page')
    {
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = max(1, (int) $this->getPage($pageName));

        if ($currentPage > $lastPage) {
            $this->setPage($lastPage, $pageName);
        }

        return $query->paginate($perPage, ['*'], $pageName);
    }
}
