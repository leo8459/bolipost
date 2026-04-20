<?php

namespace App\Livewire;

use App\Models\MaintenanceLog;
use App\Models\MaintenanceType;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceAppointment;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Services\MaintenanceAlertService;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;
use Livewire\Attributes\Validate;

class MaintenanceLogManager extends Component
{
    use WithPagination;
    use WithFileUploads;
    use WithoutUrlPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $search = '';
    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?int $history_vehicle_filter_id = null;

    #[Validate('required|integer|exists:vehicles,id')]
    public ?int $vehicle_id = null;

    #[Validate('required|string|max:100')]
    public string $tipo = '';
    public ?int $maintenance_type_id = null;
    public ?int $cada_km = null;
    public ?int $intervalo_km_init = null;
    public ?int $intervalo_km_fh = null;
    public ?int $km_alerta_previa = 15;

    #[Validate('required|date')]
    public ?string $fecha = null;

    #[Validate('required|numeric|min:0')]
    public ?float $costo = null;

    #[Validate('required|numeric|min:0')]
    public ?float $kilometraje = null;
    public ?float $kilometraje_actual_vehiculo = null;
    public bool $tacometro_danado_vehiculo = false;

    public string $taller = '';
    public string $descripcion = '';
    public string $comprobante = '';
    public $comprobante_file = null;
    public string $observaciones = '';

    public bool $isEdit = false;
    public ?int $editingMaintenanceId = null;
    public bool $showForm = false;
    public ?int $from_alert_id = null;
    public ?int $from_workshop_id = null;
    public bool $manual_proximo_km = false;
    public string $tableView = 'pending';

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'recepcion']), 403);
        $this->date_from = now()->startOfMonth()->toDateString();
        $this->date_to = now()->toDateString();

        $fromAlertId = request()->integer('from_alert_id');
        if ($fromAlertId > 0) {
            $this->from_alert_id = $fromAlertId;
            $this->prefillFromAlert($fromAlertId);
        }

        $fromWorkshopId = request()->integer('from_workshop_id');
        if ($fromWorkshopId > 0) {
            $this->from_workshop_id = $fromWorkshopId;
            $this->prefillFromWorkshop($fromWorkshopId);
        }
    }

    public function render()
    {
        $query = MaintenanceLog::query()
            ->active()
            ->with(['vehicle.brand', 'vehicle.vehicleClass', 'maintenanceType'])
            ->orderBy('fecha', 'desc');

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('tipo', 'like', "%{$search}%")
                    ->orWhere('taller', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%")
                    ->orWhere('observaciones', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(fecha AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(costo AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(kilometraje AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('placa', 'like', "%{$search}%"))
                    ->orWhereHas('maintenanceType', fn ($typeQuery) => $typeQuery->where('nombre', 'like', "%{$search}%"));
            });
        }

        [$fromDate, $toDate] = $this->resolveOrderedDateRange();
        if ($fromDate) {
            $query->whereDate('fecha', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('fecha', '<=', $toDate);
        }
        if ($this->history_vehicle_filter_id) {
            $query->where('vehicle_id', (int) $this->history_vehicle_filter_id);
        }

        $maintenanceLogs = $this->paginateWithinBounds($query, 10);
        
        $vehicles = $this->loadAlertVehicles();
        $maintenanceTypes = $this->loadMaintenanceTypes();
        $pendingWorkshopRecords = Workshop::query()
            ->with(['vehicle.brand', 'workshopCatalog', 'maintenanceAlert.maintenanceType', 'maintenanceAppointment.tipoMantenimiento', 'maintenanceLog'])
            ->where('estado', Workshop::STATUS_DELIVERED)
            ->orderByDesc('fecha_salida')
            ->orderByDesc('fecha_listo')
            ->orderByDesc('id')
            ->get();

        return view('livewire.maintenance-log-manager', [
            'maintenanceLogs' => $maintenanceLogs,
            'vehicles' => $vehicles,
            'maintenanceTypes' => $maintenanceTypes,
            'selectedVehicle' => $this->vehicle_id
                ? Vehicle::query()->with(['brand', 'vehicleClass'])->find((int) $this->vehicle_id)
                : null,
            'pendingWorkshopRecords' => $pendingWorkshopRecords,
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedHistoryVehicleFilterId(): void
    {
        $this->resetPage();
    }

    public function setTableView(string $view): void
    {
        if (!in_array($view, ['pending', 'history'], true)) {
            return;
        }

        $this->tableView = $view;
        $this->resetPage();
    }

    public function save()
    {
        $this->validate([
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'maintenance_type_id' => 'required|integer|exists:maintenance_types,id',
            'fecha' => 'required|date',
            'costo' => 'required|numeric|gt:0',
            'kilometraje' => 'required|numeric|min:0',
            'intervalo_km_fh' => 'nullable|numeric|min:0',
            'comprobante_file' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,webp',
        ], [
            'costo.gt' => 'Debe registrar un costo mayor a 0 para guardar el mantenimiento.',
        ]);

        $selectedType = MaintenanceType::find($this->maintenance_type_id);
        if (!$selectedType) {
            $this->addError('maintenance_type_id', 'Tipo de mantenimiento no valido.');
            return;
        }

        $vehicle = Vehicle::with('vehicleClass')->find((int) $this->vehicle_id);
        if (!$vehicle) {
            $this->addError('vehicle_id', 'El vehiculo seleccionado no existe.');
            return;
        }

        $typeAllowedForVehicle = MaintenanceType::query()
            ->applicableToVehicle($vehicle)
            ->whereKey((int) $this->maintenance_type_id)
            ->exists();

        if (!$typeAllowedForVehicle) {
            $this->addError('maintenance_type_id', 'El tipo de mantenimiento no corresponde al vehiculo seleccionado.');
            return;
        }

        $currentKm = $vehicle?->kilometraje_actual ?? $vehicle?->kilometraje_inicial ?? $vehicle?->kilometraje;
        if ($currentKm !== null && $this->kilometraje !== null) {
            $isFromAlert = (int) ($this->from_alert_id ?? 0) > 0;
            $isInvalid = $isFromAlert
                ? ((float) $this->kilometraje < (float) $currentKm)
                : ($this->tacometro_danado_vehiculo
                    ? ((float) $this->kilometraje < (float) $currentKm)
                    : ((float) $this->kilometraje <= (float) $currentKm));

            if ($isInvalid) {
                $this->addError(
                    'kilometraje',
                    $isFromAlert
                        ? ('El kilometraje no puede ser menor al actual del vehiculo (' . number_format((float) $currentKm, 2) . ').')
                        : ('Debe registrar un nuevo kilometraje mayor al actual del vehiculo (' . number_format((float) $currentKm, 2) . ').')
                );
                return;
            }
        }

        $canUseExistingRecordFlow = $this->isEdit && (int) ($this->editingMaintenanceId ?? 0) > 0;

        if (!$canUseExistingRecordFlow && !$this->hasAlertLinkedType() && !$this->hasWorkshopLinkedType()) {
            $this->addError('maintenance_type_id', 'El mantenimiento debe existir como alerta para el vehiculo seleccionado.');
            return;
        }

        $this->tipo = (string) $selectedType->nombre;
        if (trim($this->descripcion) === '') {
            $this->descripcion = (string) ($selectedType->descripcion ?? '');
        }

        if ($this->comprobante_file) {
            $this->comprobante = (string) $this->comprobante_file->store('comprobantes-mantenimiento', 'public');
        }

        $data = [
            'vehicle_id' => $this->vehicle_id,
            'tipo' => $this->tipo,
            'fecha' => $this->fecha,
            'proxima_fecha' => null,
            'costo' => $this->costo,
            'kilometraje' => $this->kilometraje,
            'taller' => $this->taller,
            'descripcion' => $this->descripcion,
            'comprobante' => $this->comprobante,
            'observaciones' => $this->observaciones,
        ];

        if (Schema::hasColumn('maintenance_logs', 'maintenance_type_id')) {
            $data['maintenance_type_id'] = $this->maintenance_type_id;
        }
        if (Schema::hasColumn('maintenance_logs', 'proximo_kilometraje')) {
            $intervalo = $selectedType->cada_km
                ?? $selectedType->intervalo_km_init
                ?? $selectedType->intervalo_km_fh
                ?? $selectedType->intervalo_km;

            $targetByInterval = $intervalo ? ((float) $this->kilometraje + (float) $intervalo) : null;
            $manualFinalKm = $this->intervalo_km_fh !== null ? (float) $this->intervalo_km_fh : null;
            $targetKm = ($this->manual_proximo_km && $manualFinalKm !== null) ? $manualFinalKm : $targetByInterval;

            if ($targetKm !== null && (float) $targetKm <= (float) $this->kilometraje) {
                $this->addError('intervalo_km_fh', 'El kilometraje final debe ser mayor al kilometraje registrado.');
                return;
            }

            $data['proximo_kilometraje'] = $targetKm;
        }

        $savedMaintenance = null;

        if ($this->isEdit && $this->editingMaintenanceId) {
            $maintenance = MaintenanceLog::find($this->editingMaintenanceId);
            if ($maintenance) {
                $maintenance->update($data);
                $savedMaintenance = $maintenance->fresh();
                $this->markResolvedAlertsAsRead();
                $vehicle?->update(['tacometro_danado' => $this->tacometro_danado_vehiculo]);
                $kmUpdateStatus = $this->updateVehicleKilometraje($this->vehicle_id, $this->kilometraje);
                session()->flash('message', 'Registro de mantenimiento actualizado correctamente.' . ($kmUpdateStatus === 'same' ? ' El kilometraje se mantuvo igual al anterior.' : ''));
            }
        } else {
            $savedMaintenance = MaintenanceLog::create($data);
            $this->markResolvedAlertsAsRead();
            $vehicle?->update(['tacometro_danado' => $this->tacometro_danado_vehiculo]);
            $kmUpdateStatus = $this->updateVehicleKilometraje($this->vehicle_id, $this->kilometraje);
            session()->flash('message', 'Registro de mantenimiento creado correctamente.' . ($kmUpdateStatus === 'same' ? ' El kilometraje se mantuvo igual al anterior.' : ''));
        }

        if ($savedMaintenance && (int) ($this->from_workshop_id ?? 0) > 0) {
            $this->closeWorkshopAfterMaintenanceRegistration((int) $this->from_workshop_id, (int) $savedMaintenance->id);
        }

        $this->resetForm();
    }

    public function edit(MaintenanceLog $maintenance)
    {
        $this->showForm = true;
        $this->isEdit = true;
        $this->editingMaintenanceId = $maintenance->id;
        $this->vehicle_id = $maintenance->vehicle_id;
        $this->maintenance_type_id = $maintenance->maintenance_type_id ? (int) $maintenance->maintenance_type_id : null;
        $this->tipo = $maintenance->tipo;
        $this->fecha = optional($maintenance->fecha)->format('Y-m-d');
        $this->costo = $maintenance->costo;
        $this->kilometraje = $maintenance->kilometraje;
        $this->taller = $maintenance->taller;
        $this->descripcion = $maintenance->descripcion;
        $this->comprobante = (string) ($maintenance->comprobante ?? '');
        $this->comprobante_file = null;
        $this->observaciones = $maintenance->observaciones;
        $this->syncVehicleKilometrajeActual($this->vehicle_id);
        $this->tacometro_danado_vehiculo = (bool) ($maintenance->vehicle?->tacometro_danado ?? false);
        $this->intervalo_km_fh = $maintenance->proximo_kilometraje !== null ? (int) $maintenance->proximo_kilometraje : null;
        $this->manual_proximo_km = $maintenance->proximo_kilometraje !== null;

        $type = $maintenance->maintenance_type_id
            ? MaintenanceType::find((int) $maintenance->maintenance_type_id)
            : MaintenanceType::query()->where('nombre', $maintenance->tipo)->orderByDesc('id')->first();
        if ($type) {
            $this->maintenance_type_id = (int) $type->id;
            $this->syncTypeIntervals($type);
        } else {
            $this->maintenance_type_id = null;
            $this->intervalo_km_init = null;
            $this->intervalo_km_fh = null;
            $this->cada_km = null;
        }
    }

    public function delete(MaintenanceLog $maintenance)
    {
        $vehicleId = $maintenance->vehicle_id ? (int) $maintenance->vehicle_id : null;
        $maintenance->update(['activo' => false]);
        if ($vehicleId) {
            MaintenanceAlertService::evaluateVehicleByKilometraje($vehicleId);
        }
        session()->flash('message', 'Registro de mantenimiento inactivado correctamente.');
    }

    public function resetForm()
    {
        $this->vehicle_id = null;
        $this->tipo = '';
        $this->maintenance_type_id = null;
        $this->from_alert_id = null;
        $this->from_workshop_id = null;
        $this->manual_proximo_km = false;
        $this->cada_km = null;
        $this->intervalo_km_init = null;
        $this->intervalo_km_fh = null;
        $this->km_alerta_previa = 15;
        $this->fecha = null;
        $this->costo = null;
        $this->kilometraje = null;
        $this->kilometraje_actual_vehiculo = null;
        $this->tacometro_danado_vehiculo = false;
        $this->taller = '';
        $this->descripcion = '';
        $this->comprobante = '';
        $this->comprobante_file = null;
        $this->observaciones = '';
        $this->isEdit = false;
        $this->editingMaintenanceId = null;
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

    public function updatedVehicleId($value): void
    {
        if (!$value) {
            $this->kilometraje = null;
            $this->kilometraje_actual_vehiculo = null;
            $this->maintenance_type_id = null;
            return;
        }

        $vehicle = Vehicle::find((int) $value);
        if (!$vehicle) {
            return;
        }

        $this->tacometro_danado_vehiculo = (bool) ($vehicle->tacometro_danado ?? false);
        $km = $vehicle->kilometraje_actual ?? $vehicle->kilometraje_inicial ?? $vehicle->kilometraje;
        $this->kilometraje_actual_vehiculo = $km !== null ? (float) $km : null;
        if ($km !== null) {
            $this->kilometraje = (float) $km;
        }

        if ($this->maintenance_type_id) {
            $allowedType = $this->loadMaintenanceTypes()->firstWhere('id', (int) $this->maintenance_type_id);
            if (!$allowedType) {
                $this->maintenance_type_id = null;
                $this->tipo = '';
                $this->cada_km = null;
                $this->intervalo_km_init = null;
                $this->intervalo_km_fh = null;
            }
        }
    }

    public function updatedKilometraje($value): void
    {
        if ($value === null || $value === '') {
            return;
        }
    }

    public function updatedIntervaloKmFh($value): void
    {
        if ($value === null || $value === '') {
            $this->manual_proximo_km = false;
            return;
        }

        $this->manual_proximo_km = true;
    }

    public function updatedMaintenanceTypeId($value): void
    {
        if (!$value) {
            $this->tipo = '';
            $this->cada_km = null;
            $this->intervalo_km_init = null;
            $this->intervalo_km_fh = null;
            $this->km_alerta_previa = 15;
            return;
        }

        $type = MaintenanceType::find((int) $value);
        if (!$type) {
            return;
        }

        $this->tipo = (string) $type->nombre;
        $this->syncTypeIntervals($type);
        $this->descripcion = (string) ($type->descripcion ?? '');
        $this->km_alerta_previa = $type->km_alerta_previa !== null ? (int) $type->km_alerta_previa : 15;
    }

    private function loadMaintenanceTypes()
    {
        if (!$this->vehicle_id) {
            return collect();
        }

        $select = ['id', 'nombre', 'descripcion'];

        if (Schema::hasColumn('maintenance_types', 'intervalo_km_init')) {
            $select[] = 'intervalo_km_init';
        }
        if (Schema::hasColumn('maintenance_types', 'intervalo_km_fh')) {
            $select[] = 'intervalo_km_fh';
        }
        if (Schema::hasColumn('maintenance_types', 'cada_km')) {
            $select[] = 'cada_km';
        }
        if (Schema::hasColumn('maintenance_types', 'intervalo_km')) {
            $select[] = 'intervalo_km';
        }
        if (Schema::hasColumn('maintenance_types', 'km_alerta_previa')) {
            $select[] = 'km_alerta_previa';
        }

        $query = MaintenanceType::query();
        $alertTypeIds = MaintenanceAlert::query()
            ->where('vehicle_id', (int) $this->vehicle_id)
            ->where('status', MaintenanceAlert::STATUS_ACTIVE)
            ->whereNotNull('maintenance_type_id')
            ->pluck('maintenance_type_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->toArray();
        $appointmentTypeIds = MaintenanceAlert::query()
            ->where('vehicle_id', (int) $this->vehicle_id)
            ->where('status', MaintenanceAlert::STATUS_ACTIVE)
            ->whereNull('maintenance_type_id')
            ->whereNotNull('maintenance_appointment_id')
            ->with(['maintenanceAppointment:id,tipo_mantenimiento_id'])
            ->get()
            ->pluck('maintenanceAppointment.tipo_mantenimiento_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();
        $allowedTypeIds = collect(array_merge($alertTypeIds, $appointmentTypeIds))
            ->unique()
            ->values()
            ->all();
        if (!empty($allowedTypeIds)) {
            $query->whereIn('id', $allowedTypeIds);
        } elseif (($this->isEdit || $this->from_alert_id || $this->from_workshop_id) && $this->maintenance_type_id) {
            $query->whereKey((int) $this->maintenance_type_id);
        } else {
            return collect();
        }

        $vehicle = Vehicle::with('vehicleClass')->find((int) $this->vehicle_id);
        $query->applicableToVehicle($vehicle);

        return $query->orderBy('nombre')->get($select);
    }

    private function loadAlertVehicles()
    {
        $vehicleIds = MaintenanceAlert::query()
            ->where('status', MaintenanceAlert::STATUS_ACTIVE)
            ->whereNotNull('vehicle_id')
            ->pluck('vehicle_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->toArray();

        if ($this->vehicle_id && !in_array((int) $this->vehicle_id, $vehicleIds, true)) {
            $vehicleIds[] = (int) $this->vehicle_id;
        }

        $workshopVehicleIds = Workshop::query()
            ->where('estado', Workshop::STATUS_DELIVERED)
            ->pluck('vehicle_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        $vehicleIds = collect(array_merge($vehicleIds, $workshopVehicleIds))
            ->unique()
            ->values()
            ->all();

        if (empty($vehicleIds)) {
            return collect();
        }

        return Vehicle::query()
            ->where('activo', true)
            ->whereIn('id', $vehicleIds)
            ->orderBy('placa')
            ->get(['id', 'placa']);
    }

    private function hasAlertLinkedType(): bool
    {
        if (!$this->vehicle_id || !$this->maintenance_type_id) {
            return false;
        }

        return MaintenanceAlert::query()
            ->where('vehicle_id', (int) $this->vehicle_id)
            ->when(!$this->from_alert_id, fn ($q) => $q->where('status', MaintenanceAlert::STATUS_ACTIVE))
            ->when($this->from_alert_id, fn ($q) => $q->whereKey((int) $this->from_alert_id))
            ->where(function ($q) {
                $q->where('maintenance_type_id', (int) $this->maintenance_type_id)
                    ->orWhereHas('maintenanceAppointment', function ($qa) {
                        $qa->where('tipo_mantenimiento_id', (int) $this->maintenance_type_id);
                    });
            })
            ->exists();
    }

    private function hasWorkshopLinkedType(): bool
    {
        if (!(int) ($this->from_workshop_id ?? 0) || !$this->vehicle_id || !$this->maintenance_type_id) {
            return false;
        }

        return Workshop::query()
            ->whereKey((int) $this->from_workshop_id)
            ->where('vehicle_id', (int) $this->vehicle_id)
            ->where('estado', Workshop::STATUS_DELIVERED)
            ->where(function ($q) {
                $q->whereHas('maintenanceAlert', function ($qa) {
                    $qa->where('maintenance_type_id', (int) $this->maintenance_type_id);
                })->orWhereHas('maintenanceAppointment', function ($qa) {
                    $qa->where('tipo_mantenimiento_id', (int) $this->maintenance_type_id);
                });
            })
            ->exists();
    }

    private function markResolvedAlertsAsRead(): void
    {
        if (!$this->vehicle_id) {
            return;
        }

        $resolutionPayload = [
            'status' => MaintenanceAlert::STATUS_RESOLVED,
            'leida' => true,
            'fecha_resolucion' => now(),
            'usuario_id' => auth()->id(),
        ];

        $resolvedAlertIds = collect();

        if ((int) ($this->from_workshop_id ?? 0) > 0) {
            $workshop = Workshop::query()
                ->with(['maintenanceAlert', 'maintenanceAppointment'])
                ->find((int) $this->from_workshop_id);

            if ($workshop?->maintenance_alert_id) {
                MaintenanceAlert::query()
                    ->whereKey((int) $workshop->maintenance_alert_id)
                    ->where('vehicle_id', (int) $this->vehicle_id)
                    ->update($resolutionPayload);

                $resolvedAlertIds->push((int) $workshop->maintenance_alert_id);
            }

            if ($workshop?->maintenance_appointment_id) {
                MaintenanceAppointment::query()
                    ->whereKey((int) $workshop->maintenance_appointment_id)
                    ->update(['estado' => 'Realizado']);
            }
        }

        if ($this->maintenance_type_id) {
            $query = MaintenanceAlert::query()
                ->where('vehicle_id', (int) $this->vehicle_id)
                ->whereIn('status', MaintenanceAlert::openStatuses())
                ->where(function ($q) {
                    $q->where('maintenance_type_id', (int) $this->maintenance_type_id)
                        ->orWhereHas('maintenanceAppointment', function ($qa) {
                            $qa->where('tipo_mantenimiento_id', (int) $this->maintenance_type_id);
                        });
                });

            if ($resolvedAlertIds->isNotEmpty()) {
                $query->whereNotIn('id', $resolvedAlertIds->all());
            }

            $query->update($resolutionPayload);
        }

        if ((int) ($this->from_alert_id ?? 0) > 0) {
            $appointmentId = MaintenanceAlert::query()
                ->whereKey((int) $this->from_alert_id)
                ->value('maintenance_appointment_id');

            if ($appointmentId) {
                MaintenanceAppointment::query()
                    ->whereKey((int) $appointmentId)
                    ->update(['estado' => 'Realizado']);
            }
        }
    }

    private function syncTypeIntervals(MaintenanceType $type): void
    {
        $init = null;
        $fin = null;
        $cadaKm = null;

        if (isset($type->cada_km) && $type->cada_km !== null) {
            $cadaKm = (int) $type->cada_km;
        }

        if (isset($type->intervalo_km_init)) {
            $init = $type->intervalo_km_init !== null ? (int) $type->intervalo_km_init : null;
        }
        if (isset($type->intervalo_km_fh)) {
            $fin = $type->intervalo_km_fh !== null ? (int) $type->intervalo_km_fh : null;
        }

        if ($init === null && $fin === null && isset($type->intervalo_km) && $type->intervalo_km !== null) {
            $init = (int) $type->intervalo_km;
            $fin = (int) $type->intervalo_km;
        }

        $this->cada_km = $cadaKm ?? $init ?? $fin;
        $this->intervalo_km_init = $init ?? $this->cada_km;
        $this->intervalo_km_fh = $fin ?? $this->cada_km;
        $this->manual_proximo_km = false;
        $this->km_alerta_previa = $type->km_alerta_previa !== null ? (int) $type->km_alerta_previa : 15;
    }

    private function updateVehicleKilometraje(?int $vehicleId, ?float $kmActual): string
    {
        if (!$vehicleId || $kmActual === null) {
            return 'skipped';
        }

        $vehicle = Vehicle::find($vehicleId);
        if (!$vehicle) {
            return 'skipped';
        }

        if ((bool) ($vehicle->tacometro_danado ?? false)) {
            return 'skipped';
        }

        $hasInicial = Schema::hasColumn('vehicles', 'kilometraje_inicial');
        $hasActual = Schema::hasColumn('vehicles', 'kilometraje_actual');
        $hasLegacy = Schema::hasColumn('vehicles', 'kilometraje');

        $updates = [];
        $status = 'skipped';

        if ($hasInicial && $vehicle->kilometraje_inicial === null) {
            $updates['kilometraje_inicial'] = $kmActual;
        }

        if ($hasActual) {
            $prev = $vehicle->kilometraje_actual !== null ? (float) $vehicle->kilometraje_actual : null;
            if ($prev === null || $kmActual >= $prev) {
                $updates['kilometraje_actual'] = $kmActual;
                $status = $prev !== null && abs($kmActual - $prev) < 0.000001 ? 'same' : 'updated';
            }
        }

        if ($hasLegacy) {
            $prevLegacy = $vehicle->kilometraje !== null ? (float) $vehicle->kilometraje : null;
            if ($prevLegacy === null || $kmActual >= $prevLegacy) {
                $updates['kilometraje'] = $kmActual;
                if ($status === 'skipped') {
                    $status = $prevLegacy !== null && abs($kmActual - $prevLegacy) < 0.000001 ? 'same' : 'updated';
                }
            }
        }

        if (!empty($updates)) {
            $vehicle->update($updates);
        }

        MaintenanceAlertService::evaluateVehicleByKilometraje((int) $vehicleId);
        return $status;
    }

    private function prefillFromAlert(int $alertId): void
    {
        $alert = MaintenanceAlert::query()
            ->with(['vehicle', 'maintenanceType', 'maintenanceAppointment'])
            ->find($alertId);

        if (!$alert) {
            return;
        }

        $this->resetForm();
        $this->showForm = true;
        $this->from_alert_id = (int) $alert->id;

        $this->vehicle_id = $alert->vehicle_id ? (int) $alert->vehicle_id : null;
        $this->syncVehicleKilometrajeActual($this->vehicle_id);

        $maintenanceTypeId = $alert->maintenance_type_id
            ?? $alert->maintenanceAppointment?->tipo_mantenimiento_id;
        $this->maintenance_type_id = $maintenanceTypeId ? (int) $maintenanceTypeId : null;

        if ($this->maintenance_type_id) {
            $type = MaintenanceType::find($this->maintenance_type_id);
            if ($type) {
                $this->tipo = (string) $type->nombre;
                $this->syncTypeIntervals($type);
                $this->descripcion = (string) ($type->descripcion ?? '');
            }
        }

        if (trim($this->tipo) === '') {
            $this->tipo = (string) ($alert->tipo ?? '');
        }

        $this->fecha = now()->toDateString();
        $km = $alert->kilometraje_actual !== null
            ? (float) $alert->kilometraje_actual
            : $this->kilometraje_actual_vehiculo;
        $this->kilometraje = $km;

        $this->costo = 0;
        $this->descripcion = trim($this->descripcion) !== '' ? $this->descripcion : (string) ($alert->mensaje ?? '');
        $this->observaciones = 'Generado desde alerta #' . $alert->id . ' (' . optional($alert->created_at)->format('d/m/Y H:i') . ').';
    }

    public function registerFromWorkshop(int $workshopId): void
    {
        $workshop = Workshop::query()->find($workshopId);
        if (!$workshop) {
            return;
        }

        if ($workshop->maintenance_log_id) {
            $maintenance = MaintenanceLog::query()->find((int) $workshop->maintenance_log_id);
            if ($maintenance) {
                $this->edit($maintenance);
                return;
            }
        }

        $this->prefillFromWorkshop($workshopId);
    }

    private function prefillFromWorkshop(int $workshopId): void
    {
        $workshop = Workshop::query()
            ->with(['vehicle', 'maintenanceAlert.maintenanceType', 'maintenanceAppointment.tipoMantenimiento', 'workshopCatalog', 'partChanges'])
            ->find($workshopId);

        if (!$workshop || $workshop->estado !== Workshop::STATUS_DELIVERED) {
            return;
        }

        $this->resetForm();
        $this->showForm = true;
        $this->from_workshop_id = (int) $workshop->id;
        $this->vehicle_id = $workshop->vehicle_id ? (int) $workshop->vehicle_id : null;
        $this->syncVehicleKilometrajeActual($this->vehicle_id);

        $type = $workshop->maintenanceAlert?->maintenanceType ?? $workshop->maintenanceAppointment?->tipoMantenimiento;
        if ($type) {
            $this->maintenance_type_id = (int) $type->id;
            $this->tipo = (string) $type->nombre;
            $this->syncTypeIntervals($type);
            $this->descripcion = (string) ($type->descripcion ?? '');
        }

        $this->fecha = optional($workshop->fecha_salida)->format('Y-m-d')
            ?: optional($workshop->fecha_listo)->format('Y-m-d')
            ?: now()->toDateString();
        $this->costo = (float) ($workshop->total_cost ?? 0);
        $this->kilometraje = $this->kilometraje_actual_vehiculo;
        $this->taller = (string) ($workshop->workshopCatalog?->nombre ?? $workshop->nombre_taller ?? '');
        $this->comprobante = (string) ($workshop->invoice_file_path ?? $workshop->receipt_file_path ?? '');

        $partsText = $workshop->partChanges
            ->map(function ($row) {
                $descripcion = trim((string) ($row->descripcion ?? ''));
                $nueva = trim((string) ($row->codigo_pieza_nueva ?? ''));
                $antigua = trim((string) ($row->codigo_pieza_antigua ?? ''));

                if ($descripcion !== '') {
                    return $descripcion;
                }

                if ($nueva !== '' || $antigua !== '') {
                    return trim(sprintf('Nueva: %s / Antigua: %s', $nueva !== '' ? $nueva : 'N/A', $antigua !== '' ? $antigua : 'N/A'));
                }

                return null;
            })
            ->filter()
            ->implode(' | ');

        $this->descripcion = collect([
            trim((string) $this->descripcion),
            trim((string) $workshop->diagnostico),
            trim((string) $workshop->observaciones_tecnicas),
            $partsText !== '' ? 'Repuestos: ' . $partsText : '',
        ])->filter(fn ($value) => $value !== '')->implode(' || ');

        $this->observaciones = collect([
            'Generado desde orden de taller ' . ($workshop->order_number ?: ('#' . $workshop->id)) . '.',
            trim((string) $workshop->observaciones),
        ])->filter(fn ($value) => $value !== '')->implode(' ');
    }

    private function closeWorkshopAfterMaintenanceRegistration(int $workshopId, int $maintenanceLogId): void
    {
        $workshop = Workshop::query()
            ->with(['maintenanceAlert', 'maintenanceAppointment'])
            ->find($workshopId);

        if (!$workshop) {
            return;
        }

        $updates = [
            'maintenance_log_id' => $maintenanceLogId,
            'estado' => Workshop::STATUS_CLOSED,
        ];

        if (!$workshop->fecha_listo) {
            $updates['fecha_listo'] = now()->toDateString();
        }

        if (!$workshop->fecha_salida) {
            $updates['fecha_salida'] = now()->toDateString();
        }

        if (!$workshop->fecha_cierre) {
            $updates['fecha_cierre'] = now();
        }

        if (!$workshop->closed_by_user_id) {
            $updates['closed_by_user_id'] = auth()->id();
        }

        $workshop->update($updates);

        if ($workshop->maintenanceAppointment) {
            $workshop->maintenanceAppointment->update([
                'estado' => MaintenanceAppointment::STATUS_COMPLETED,
            ]);
        }
    }

    private function syncVehicleKilometrajeActual(?int $vehicleId): void
    {
        if (!$vehicleId) {
            $this->kilometraje_actual_vehiculo = null;
            return;
        }

        $vehicle = Vehicle::find((int) $vehicleId);
        if (!$vehicle) {
            $this->kilometraje_actual_vehiculo = null;
            return;
        }

        $km = $vehicle->kilometraje_actual ?? $vehicle->kilometraje_inicial ?? $vehicle->kilometraje;
        $this->kilometraje_actual_vehiculo = $km !== null ? (float) $km : null;
        $this->tacometro_danado_vehiculo = (bool) ($vehicle->tacometro_danado ?? false);
    }

    private function resolveOrderedDateRange(): array
    {
        $from = $this->normalizeDate($this->date_from);
        $to = $this->normalizeDate($this->date_to);

        if ($from && $to && $from > $to) {
            return [$to, $from];
        }

        return [$from, $to];
    }

    private function normalizeDate(?string $value): ?string
    {
        if (!filled($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
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
