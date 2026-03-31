<?php

namespace App\Livewire;

use App\Models\Driver;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceAppointment;
use App\Models\MaintenanceLog;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Models\WorkshopCatalog;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class WorkshopManager extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public bool $showForm = false;
    public bool $isEdit = false;
    public ?int $editingId = null;
    public ?int $vehicle_id = null;
    public ?int $driver_id = null;
    public ?int $maintenance_appointment_id = null;
    public ?int $maintenance_log_id = null;
    public ?int $maintenance_alert_id = null;
    public ?int $workshop_catalog_id = null;
    public string $order_number = '';
    public string $nombre_taller = '';
    public ?string $fecha_ingreso = null;
    public ?string $fecha_prometida_entrega = null;
    public ?string $fecha_listo = null;
    public ?string $fecha_salida = null;
    public string $estado = Workshop::STATUS_PENDING;
    public string $workflow_kind = Workshop::FLOW_LIGHT;
    public bool $approval_required = false;
    public ?string $fixed_catalog_cost = null;
    public ?string $labor_cost = null;
    public ?string $additional_cost = null;
    public string $rejection_reason = '';
    public string $cancellation_reason = '';
    public string $pre_entrada_estado = '';
    public string $observaciones_tecnicas = '';
    public string $diagnostico = '';
    public string $observaciones = '';
    public array $partChanges = [];
    public $reception_photo_file = null;
    public $damage_photo_file = null;
    public $invoice_file = null;
    public $receipt_file = null;
    public ?string $reception_photo_path = null;
    public ?string $damage_photo_path = null;
    public ?string $invoice_file_path = null;
    public ?string $receipt_file_path = null;
    public string $catalog_name = '';
    public string $catalog_type = 'Interno';

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'recepcion']), 403);
        $this->ensurePartRow();

        $fromAlertId = (int) request()->query('from_alert_id', 0);
        $editWorkshopId = (int) request()->query('edit_workshop_id', 0);

        if ($editWorkshopId > 0) {
            $workshop = Workshop::query()->find($editWorkshopId);
            if ($workshop) {
                $this->edit($workshop);
                return;
            }
        }

        if ($fromAlertId > 0) {
            $this->prefillFromAlert($fromAlertId);
        }
    }

    public function render()
    {
        $query = Workshop::query()
            ->with([
                'vehicle.brand',
                'driver',
                'maintenanceAppointment.tipoMantenimiento',
                'maintenanceLog',
                'maintenanceAlert.maintenanceType',
                'workshopCatalog',
                'reassignedFromWorkshopCatalog',
                'partChanges',
            ])
            ->orderByRaw("CASE WHEN estado IN ('" . implode("','", $this->openStatuses()) . "') THEN 0 ELSE 1 END")
            ->orderByDesc('fecha_ingreso')
            ->orderByDesc('id');

        if ($this->statusFilter !== '') {
            $query->where('estado', $this->statusFilter);
        }

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('nombre_taller', 'like', "%{$search}%")
                    ->orWhere('estado', 'like', "%{$search}%")
                    ->orWhere('pre_entrada_estado', 'like', "%{$search}%")
                    ->orWhere('observaciones_tecnicas', 'like', "%{$search}%")
                    ->orWhere('diagnostico', 'like', "%{$search}%")
                    ->orWhere('observaciones', 'like', "%{$search}%")
                    ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('placa', 'like', "%{$search}%"))
                    ->orWhereHas('driver', fn ($driverQuery) => $driverQuery->where('nombre', 'like', "%{$search}%"))
                    ->orWhereHas('workshopCatalog', fn ($catalogQuery) => $catalogQuery->where('nombre', 'like', "%{$search}%"))
                    ->orWhereHas('partChanges', function ($partQuery) use ($search) {
                        $partQuery->where('codigo_pieza_nueva', 'like', "%{$search}%")
                            ->orWhere('codigo_pieza_antigua', 'like', "%{$search}%")
                            ->orWhere('descripcion', 'like', "%{$search}%");
                    });
            });
        }

        $vehicles = $this->availableVehicles();
        $drivers = Driver::query()->orderBy('nombre')->get(['id', 'nombre']);
        $appointments = MaintenanceAppointment::query()
            ->with(['vehicle:id,placa', 'tipoMantenimiento:id,nombre'])
            ->whereNotIn('estado', [MaintenanceAppointment::STATUS_COMPLETED, MaintenanceAppointment::STATUS_CANCELLED, MaintenanceAppointment::STATUS_REJECTED])
            ->orderByDesc('fecha_programada')
            ->limit(100)
            ->get();
        $maintenanceLogs = MaintenanceLog::query()->with('vehicle:id,placa')->orderByDesc('fecha')->limit(100)->get();
        $workshopCatalogs = WorkshopCatalog::query()->orderByDesc('activo')->orderBy('tipo')->orderBy('nombre')->get();

        return view('livewire.workshop-manager', [
            'workshops' => $query->paginate(10),
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'appointments' => $appointments,
            'maintenanceLogs' => $maintenanceLogs,
            'workshopCatalogs' => $workshopCatalogs,
            'activeWorkshops' => Workshop::query()
                ->with(['vehicle:id,placa', 'workshopCatalog:id,nombre', 'maintenanceAlert.maintenanceType:id,nombre'])
                ->whereIn('estado', $this->openStatuses())
                ->orderBy('fecha_prometida_entrega')
                ->orderByDesc('fecha_ingreso')
                ->limit(8)
                ->get(),
            'sourceAlert' => $this->maintenance_alert_id
                ? MaintenanceAlert::query()->with(['vehicle:id,placa', 'maintenanceType:id,nombre', 'maintenanceAppointment:id'])->find($this->maintenance_alert_id)
                : null,
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedMaintenanceAppointmentId($value): void
    {
        if (!$value) {
            return;
        }

        $appointment = MaintenanceAppointment::query()->with('vehicle')->find((int) $value);
        if (!$appointment) {
            return;
        }

        $this->vehicle_id = $appointment->vehicle_id ? (int) $appointment->vehicle_id : null;
        $this->driver_id = $appointment->driver_id ? (int) $appointment->driver_id : null;
        if (!$this->maintenance_alert_id) {
            $this->pre_entrada_estado = trim($this->pre_entrada_estado) !== ''
                ? $this->pre_entrada_estado
                : 'Ingreso por solicitud/cita de mantenimiento.';
        }
    }

    public function updatedWorkshopCatalogId($value): void
    {
        $catalog = $value ? WorkshopCatalog::query()->find((int) $value) : null;
        $this->nombre_taller = (string) ($catalog?->nombre ?? '');
    }

    public function updatedWorkflowKind($value): void
    {
        if ($value === Workshop::FLOW_LIGHT) {
            $this->approval_required = false;
        }
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function createCatalog(): void
    {
        $validated = $this->validate([
            'catalog_name' => 'required|string|max:150',
            'catalog_type' => 'required|string|in:Interno,Externo',
        ]);

        $catalog = WorkshopCatalog::query()->create([
            'nombre' => trim((string) $validated['catalog_name']),
            'tipo' => $validated['catalog_type'],
            'activo' => true,
        ]);

        $this->catalog_name = '';
        $this->catalog_type = 'Interno';
        $this->workshop_catalog_id = (int) $catalog->id;
        $this->nombre_taller = (string) $catalog->nombre;

        session()->flash('message', 'Taller del catalogo creado correctamente.');
    }

    public function toggleCatalog(int $catalogId): void
    {
        $catalog = WorkshopCatalog::query()->find($catalogId);
        if (!$catalog) {
            return;
        }

        $catalog->update([
            'activo' => !$catalog->activo,
        ]);

        session()->flash('message', 'Estado del taller actualizado correctamente.');
    }

    public function addPartRow(): void
    {
        $this->partChanges[] = [
            'codigo_pieza_antigua' => '',
            'codigo_pieza_nueva' => '',
            'descripcion' => '',
            'costo' => '',
        ];
    }

    public function removePartRow(int $index): void
    {
        unset($this->partChanges[$index]);
        $this->partChanges = array_values($this->partChanges);
        $this->ensurePartRow();
    }

    public function save(): void
    {
        $this->validate([
            'reception_photo_file' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,webp,pdf',
            'damage_photo_file' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,webp,pdf',
            'invoice_file' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,webp,pdf',
            'receipt_file' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,webp,pdf',
        ]);

        $validated = $this->validate([
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'driver_id' => 'nullable|integer|exists:drivers,id',
            'maintenance_appointment_id' => 'nullable|integer|exists:maintenance_appointments,id',
            'maintenance_log_id' => 'nullable|integer|exists:maintenance_logs,id',
            'maintenance_alert_id' => 'nullable|integer|exists:maintenance_alerts,id',
            'workshop_catalog_id' => 'required|integer|exists:workshop_catalogs,id',
            'fecha_ingreso' => 'required|date',
            'fecha_prometida_entrega' => 'nullable|date|after_or_equal:fecha_ingreso',
            'fecha_listo' => 'nullable|date|after_or_equal:fecha_ingreso',
            'fecha_salida' => 'nullable|date|after_or_equal:fecha_ingreso',
            'estado' => 'required|string|in:' . implode(',', [
                Workshop::STATUS_PENDING,
                Workshop::STATUS_DISPATCHED,
                Workshop::STATUS_DIAGNOSIS,
                Workshop::STATUS_APPROVED,
                Workshop::STATUS_REPAIR,
                Workshop::STATUS_READY,
                Workshop::STATUS_DELIVERED,
                Workshop::STATUS_CLOSED,
                Workshop::STATUS_REJECTED,
                Workshop::STATUS_CANCELLED,
            ]),
            'workflow_kind' => 'required|string|in:' . Workshop::FLOW_LIGHT . ',' . Workshop::FLOW_HEAVY,
            'approval_required' => 'boolean',
            'fixed_catalog_cost' => 'nullable|numeric|min:0',
            'labor_cost' => 'nullable|numeric|min:0',
            'additional_cost' => 'nullable|numeric|min:0',
            'rejection_reason' => 'nullable|string',
            'cancellation_reason' => 'nullable|string',
            'pre_entrada_estado' => 'required|string',
            'observaciones_tecnicas' => 'nullable|string',
            'diagnostico' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'partChanges.*.codigo_pieza_antigua' => 'nullable|string|max:120',
            'partChanges.*.codigo_pieza_nueva' => 'nullable|string|max:120',
            'partChanges.*.descripcion' => 'nullable|string|max:255',
            'partChanges.*.costo' => 'nullable|numeric|min:0',
        ]);

        $catalog = WorkshopCatalog::query()->findOrFail((int) $validated['workshop_catalog_id']);
        $vehicle = Vehicle::query()->findOrFail((int) $validated['vehicle_id']);

        if (
            $validated['workflow_kind'] === Workshop::FLOW_HEAVY &&
            (bool) ($validated['approval_required'] ?? false) &&
            $validated['estado'] === Workshop::STATUS_REPAIR &&
            trim((string) ($validated['diagnostico'] ?? '')) === ''
        ) {
            $this->addError('diagnostico', 'Debe registrar el diagnostico antes de pasar un mantenimiento grave a reparacion.');
            return;
        }

        if ($validated['estado'] === Workshop::STATUS_REJECTED && trim((string) ($validated['rejection_reason'] ?? '')) === '') {
            $this->addError('rejection_reason', 'Debe indicar el motivo del rechazo.');
            return;
        }

        if ($validated['estado'] === Workshop::STATUS_CANCELLED && trim((string) ($validated['cancellation_reason'] ?? '')) === '') {
            $this->addError('cancellation_reason', 'Debe indicar el motivo de la cancelacion.');
            return;
        }

        $existingOpenWorkshop = Workshop::query()
            ->where('vehicle_id', (int) $vehicle->id)
            ->whereIn('estado', $this->openStatuses())
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->first();

        if ($existingOpenWorkshop) {
            $this->addError('vehicle_id', 'Este vehiculo ya tiene una orden de taller abierta.');
            return;
        }

        $existingWorkshop = $this->isEdit && $this->editingId
            ? Workshop::query()->find($this->editingId)
            : null;
        $previousCatalogId = $existingWorkshop?->workshop_catalog_id ? (int) $existingWorkshop->workshop_catalog_id : null;

        $fixedCatalogCost = $validated['fixed_catalog_cost'] !== null ? (float) $validated['fixed_catalog_cost'] : 0.0;
        $laborCost = $validated['labor_cost'] !== null ? (float) $validated['labor_cost'] : 0.0;
        $additionalCost = $validated['additional_cost'] !== null ? (float) $validated['additional_cost'] : 0.0;
        $partsCost = collect($this->partChanges)->sum(function (array $row) {
            return (float) (($row['costo'] ?? '') !== '' && ($row['costo'] ?? null) !== null ? $row['costo'] : 0);
        });
        $totalCost = round($fixedCatalogCost + $laborCost + $additionalCost + $partsCost, 2);

        $receptionPhotoPath = $this->storeWorkshopFile($this->reception_photo_file, 'workshop/reception') ?? $this->reception_photo_path;
        $damagePhotoPath = $this->storeWorkshopFile($this->damage_photo_file, 'workshop/damage') ?? $this->damage_photo_path;
        $invoiceFilePath = $this->storeWorkshopFile($this->invoice_file, 'workshop/invoice') ?? $this->invoice_file_path;
        $receiptFilePath = $this->storeWorkshopFile($this->receipt_file, 'workshop/receipt') ?? $this->receipt_file_path;

        $payload = [
            'vehicle_id' => (int) $validated['vehicle_id'],
            'driver_id' => $validated['driver_id'] ?? null,
            'maintenance_appointment_id' => $validated['maintenance_appointment_id'] ?? null,
            'maintenance_log_id' => $validated['maintenance_log_id'] ?? null,
            'maintenance_alert_id' => $validated['maintenance_alert_id'] ?? null,
            'workshop_catalog_id' => (int) $catalog->id,
            'nombre_taller' => (string) $catalog->nombre,
            'fecha_ingreso' => $validated['fecha_ingreso'],
            'fecha_prometida_entrega' => $validated['fecha_prometida_entrega'] ?? null,
            'fecha_listo' => $validated['fecha_listo'] ?? null,
            'fecha_salida' => $validated['fecha_salida'] ?? null,
            'estado' => $validated['estado'],
            'workflow_kind' => $validated['workflow_kind'],
            'approval_required' => (bool) ($validated['approval_required'] ?? false),
            'fixed_catalog_cost' => $validated['fixed_catalog_cost'] !== null ? $fixedCatalogCost : null,
            'labor_cost' => $validated['labor_cost'] !== null ? $laborCost : null,
            'additional_cost' => $validated['additional_cost'] !== null ? $additionalCost : null,
            'total_cost' => $totalCost,
            'reassigned_from_workshop_catalog_id' => $previousCatalogId && $previousCatalogId !== (int) $catalog->id
                ? $previousCatalogId
                : ($existingWorkshop?->reassigned_from_workshop_catalog_id),
            'rejection_reason' => trim((string) ($validated['rejection_reason'] ?? '')),
            'cancellation_reason' => trim((string) ($validated['cancellation_reason'] ?? '')),
            'reception_photo_path' => $receptionPhotoPath,
            'damage_photo_path' => $damagePhotoPath,
            'invoice_file_path' => $invoiceFilePath,
            'receipt_file_path' => $receiptFilePath,
            'pre_entrada_estado' => trim((string) $validated['pre_entrada_estado']),
            'observaciones_tecnicas' => trim((string) ($validated['observaciones_tecnicas'] ?? '')),
            'diagnostico' => trim((string) ($validated['diagnostico'] ?? '')),
            'observaciones' => trim((string) ($validated['observaciones'] ?? '')),
        ];

        if ($this->isEdit && $this->editingId) {
            $workshop = Workshop::query()->findOrFail($this->editingId);
            $workshop->update($payload);
        } else {
            $workshop = Workshop::query()->create($payload);
        }

        if (!$workshop->order_number) {
            $workshop->update([
                'order_number' => $this->buildOrderNumber($workshop),
            ]);
        }

        $changes = collect($this->partChanges)
            ->map(fn (array $row) => [
                'codigo_pieza_antigua' => trim((string) ($row['codigo_pieza_antigua'] ?? '')),
                'codigo_pieza_nueva' => trim((string) ($row['codigo_pieza_nueva'] ?? '')),
                'descripcion' => trim((string) ($row['descripcion'] ?? '')),
                'costo' => $row['costo'] !== '' && $row['costo'] !== null ? (float) $row['costo'] : null,
            ])
            ->filter(fn (array $row) => $row['codigo_pieza_antigua'] !== '' || $row['codigo_pieza_nueva'] !== '' || $row['descripcion'] !== '' || $row['costo'] !== null)
            ->values();

        $workshop->partChanges()->delete();
        foreach ($changes as $change) {
            $workshop->partChanges()->create($change);
        }

        $this->applyWorkshopStateEffects($workshop->fresh(['maintenanceAlert', 'maintenanceAppointment']));

        session()->flash('message', $this->isEdit
            ? 'Orden de taller actualizada correctamente.'
            : 'Orden de taller creada correctamente y el vehiculo fue enviado a mantenimiento.');

        $this->resetForm();
    }

    public function edit(Workshop $workshop): void
    {
        $this->showForm = true;
        $this->isEdit = true;
        $this->editingId = $workshop->id;
        $this->vehicle_id = $workshop->vehicle_id ? (int) $workshop->vehicle_id : null;
        $this->driver_id = $workshop->driver_id ? (int) $workshop->driver_id : null;
        $this->maintenance_appointment_id = $workshop->maintenance_appointment_id ? (int) $workshop->maintenance_appointment_id : null;
        $this->maintenance_log_id = $workshop->maintenance_log_id ? (int) $workshop->maintenance_log_id : null;
        $this->maintenance_alert_id = $workshop->maintenance_alert_id ? (int) $workshop->maintenance_alert_id : null;
        $this->workshop_catalog_id = $workshop->workshop_catalog_id ? (int) $workshop->workshop_catalog_id : null;
        $this->order_number = (string) ($workshop->order_number ?? '');
        $this->nombre_taller = (string) $workshop->nombre_taller;
        $this->fecha_ingreso = optional($workshop->fecha_ingreso)->format('Y-m-d');
        $this->fecha_prometida_entrega = optional($workshop->fecha_prometida_entrega)->format('Y-m-d');
        $this->fecha_listo = optional($workshop->fecha_listo)->format('Y-m-d');
        $this->fecha_salida = optional($workshop->fecha_salida)->format('Y-m-d');
        $this->estado = (string) $workshop->estado;
        $this->workflow_kind = (string) ($workshop->workflow_kind ?: Workshop::FLOW_LIGHT);
        $this->approval_required = (bool) $workshop->approval_required;
        $this->fixed_catalog_cost = $workshop->fixed_catalog_cost !== null ? (string) $workshop->fixed_catalog_cost : null;
        $this->labor_cost = $workshop->labor_cost !== null ? (string) $workshop->labor_cost : null;
        $this->additional_cost = $workshop->additional_cost !== null ? (string) $workshop->additional_cost : null;
        $this->rejection_reason = (string) ($workshop->rejection_reason ?? '');
        $this->cancellation_reason = (string) ($workshop->cancellation_reason ?? '');
        $this->pre_entrada_estado = (string) $workshop->pre_entrada_estado;
        $this->observaciones_tecnicas = (string) ($workshop->observaciones_tecnicas ?? '');
        $this->diagnostico = (string) ($workshop->diagnostico ?? '');
        $this->observaciones = (string) ($workshop->observaciones ?? '');
        $this->reception_photo_file = null;
        $this->damage_photo_file = null;
        $this->invoice_file = null;
        $this->receipt_file = null;
        $this->reception_photo_path = (string) ($workshop->reception_photo_path ?? '') ?: null;
        $this->damage_photo_path = (string) ($workshop->damage_photo_path ?? '') ?: null;
        $this->invoice_file_path = (string) ($workshop->invoice_file_path ?? '') ?: null;
        $this->receipt_file_path = (string) ($workshop->receipt_file_path ?? '') ?: null;
        $this->partChanges = $workshop->partChanges->map(fn ($row) => [
            'codigo_pieza_antigua' => (string) ($row->codigo_pieza_antigua ?? ''),
            'codigo_pieza_nueva' => (string) ($row->codigo_pieza_nueva ?? ''),
            'descripcion' => (string) ($row->descripcion ?? ''),
            'costo' => $row->costo !== null ? (string) $row->costo : '',
        ])->values()->all();
        $this->ensurePartRow();
    }

    public function delete(Workshop $workshop): void
    {
        $vehicleId = (int) $workshop->vehicle_id;
        $workshop->delete();
        $this->syncVehicleOperationalStatus($vehicleId);
        session()->flash('message', 'Orden de taller eliminada correctamente.');
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset([
            'vehicle_id',
            'driver_id',
            'maintenance_appointment_id',
            'maintenance_log_id',
            'maintenance_alert_id',
            'workshop_catalog_id',
            'order_number',
            'nombre_taller',
            'fecha_ingreso',
            'fecha_prometida_entrega',
            'fecha_listo',
            'fecha_salida',
            'estado',
            'workflow_kind',
            'approval_required',
            'fixed_catalog_cost',
            'labor_cost',
            'additional_cost',
            'rejection_reason',
            'cancellation_reason',
            'pre_entrada_estado',
            'observaciones_tecnicas',
            'diagnostico',
            'observaciones',
            'reception_photo_file',
            'damage_photo_file',
            'invoice_file',
            'receipt_file',
            'reception_photo_path',
            'damage_photo_path',
            'invoice_file_path',
            'receipt_file_path',
            'isEdit',
            'editingId',
        ]);
        $this->estado = Workshop::STATUS_DISPATCHED;
        $this->workflow_kind = Workshop::FLOW_LIGHT;
        $this->approval_required = false;
        $this->showForm = false;
        $this->partChanges = [];
        $this->ensurePartRow();
        $this->resetPage();
    }

    private function ensurePartRow(): void
    {
        if ($this->partChanges === []) {
            $this->addPartRow();
        }
    }

    private function prefillFromAlert(int $alertId): void
    {
        $alert = MaintenanceAlert::query()
            ->with(['vehicle', 'maintenanceAppointment', 'maintenanceType'])
            ->find($alertId);

        if (!$alert) {
            return;
        }

        $existing = Workshop::query()
            ->where('maintenance_alert_id', $alertId)
            ->whereIn('estado', $this->openStatuses())
            ->first();

        if ($existing) {
            $this->edit($existing);
            session()->flash('message', 'La alerta ya tenia una orden de taller abierta. Se cargo para editar.');
            return;
        }

        $defaultCatalog = WorkshopCatalog::query()->where('activo', true)->orderBy('tipo')->orderBy('nombre')->first();

        $this->resetForm();
        $this->showForm = true;
        $this->maintenance_alert_id = (int) $alert->id;
        $this->maintenance_appointment_id = $alert->maintenance_appointment_id ? (int) $alert->maintenance_appointment_id : null;
        $this->vehicle_id = $alert->vehicle_id ? (int) $alert->vehicle_id : null;
        $this->driver_id = $alert->maintenanceAppointment?->driver_id ? (int) $alert->maintenanceAppointment->driver_id : null;
        $this->workshop_catalog_id = $defaultCatalog?->id ? (int) $defaultCatalog->id : null;
        $this->nombre_taller = (string) ($defaultCatalog?->nombre ?? '');
        $this->fecha_ingreso = now()->toDateString();
        $requestDiagnosis = request()->boolean('request_diagnosis');
        $this->estado = $requestDiagnosis ? Workshop::STATUS_PENDING : Workshop::STATUS_DISPATCHED;
        $this->workflow_kind = $requestDiagnosis ? Workshop::FLOW_HEAVY : Workshop::FLOW_LIGHT;
        $this->approval_required = $requestDiagnosis;
        $typeName = (string) ($alert->maintenanceType?->nombre ?? $alert->tipo);
        $this->pre_entrada_estado = $requestDiagnosis
            ? "Ingreso para diagnostico desde alerta activa de mantenimiento {$typeName}."
            : "Ingreso desde alerta activa de mantenimiento {$typeName}.";
        $this->observaciones = trim((string) $alert->mensaje);
    }

    private function availableVehicles(): Collection
    {
        $query = Vehicle::query()
            ->where('activo', true)
            ->operationallyAvailable()
            ->orderBy('placa');

        $vehicles = $query->get(['id', 'placa', 'operational_status']);

        if ($this->vehicle_id && !$vehicles->contains('id', $this->vehicle_id)) {
            $selected = Vehicle::query()->find($this->vehicle_id, ['id', 'placa', 'operational_status']);
            if ($selected) {
                $vehicles->push($selected);
            }
        }

        return $vehicles;
    }

    private function openStatuses(): array
    {
        return [
            Workshop::STATUS_PENDING,
            Workshop::STATUS_DISPATCHED,
            Workshop::STATUS_DIAGNOSIS,
            Workshop::STATUS_APPROVED,
            Workshop::STATUS_REPAIR,
            Workshop::STATUS_READY,
        ];
    }

    private function buildOrderNumber(Workshop $workshop): string
    {
        $date = optional($workshop->fecha_ingreso)->format('Ymd') ?? now()->format('Ymd');

        return sprintf('OT-%s-%04d', $date, (int) $workshop->id);
    }

    private function applyWorkshopStateEffects(Workshop $workshop): void
    {
        $updates = [];
        $userId = auth()->id();

        if (in_array($workshop->estado, [Workshop::STATUS_PENDING, Workshop::STATUS_DIAGNOSIS], true) && !$workshop->diagnosis_requested_at) {
            $updates['diagnosis_requested_at'] = now();
        }

        if ($workshop->estado === Workshop::STATUS_DISPATCHED && !$workshop->dispatched_by_user_id) {
            $updates['dispatched_by_user_id'] = $userId;
        }

        if ($workshop->estado === Workshop::STATUS_DIAGNOSIS && !$workshop->diagnosed_by_user_id) {
            $updates['diagnosed_by_user_id'] = $userId;
        }

        if ($workshop->estado === Workshop::STATUS_APPROVED) {
            if (!$workshop->approved_by_user_id) {
                $updates['approved_by_user_id'] = $userId;
            }
            if (!$workshop->fecha_aprobacion) {
                $updates['fecha_aprobacion'] = now();
            }
        }

        if ($workshop->estado === Workshop::STATUS_READY && !$workshop->fecha_listo) {
            $updates['fecha_listo'] = now()->toDateString();
        }

        if (in_array($workshop->estado, [Workshop::STATUS_DELIVERED, Workshop::STATUS_CLOSED], true)) {
            if (!$workshop->fecha_listo) {
                $updates['fecha_listo'] = now()->toDateString();
            }
            if (!$workshop->fecha_salida) {
                $updates['fecha_salida'] = now()->toDateString();
            }
            if (!$workshop->closed_by_user_id) {
                $updates['closed_by_user_id'] = $userId;
            }
            if (!$workshop->fecha_cierre) {
                $updates['fecha_cierre'] = now();
            }
        }

        if (in_array($workshop->estado, [Workshop::STATUS_REJECTED, Workshop::STATUS_CANCELLED], true) && !$workshop->fecha_cierre) {
            $updates['fecha_cierre'] = now();
        }

        if ($updates !== []) {
            $workshop->update($updates);
            $workshop->refresh();
        }

        if ($workshop->maintenanceAlert && $workshop->maintenanceAlert->status === MaintenanceAlert::STATUS_ACTIVE) {
            if ($workshop->isClosedState()) {
                $workshop->maintenanceAlert->update([
                    'status' => MaintenanceAlert::STATUS_RESOLVED,
                    'leida' => true,
                    'fecha_resolucion' => now(),
                    'usuario_id' => $userId,
                ]);
            } elseif (in_array($workshop->estado, [Workshop::STATUS_REJECTED, Workshop::STATUS_CANCELLED], true)) {
                $reason = $workshop->estado === Workshop::STATUS_REJECTED
                    ? trim((string) $workshop->rejection_reason)
                    : trim((string) $workshop->cancellation_reason);

                $workshop->maintenanceAlert->update([
                    'status' => MaintenanceAlert::STATUS_ACTIVE,
                    'leida' => false,
                    'fecha_resolucion' => null,
                    'usuario_id' => null,
                    'mensaje' => trim(sprintf(
                        '%s. %s en taller %s%s',
                        $workshop->maintenanceAlert->mensaje,
                        $workshop->estado,
                        $workshop->workshopCatalog?->nombre ?? $workshop->nombre_taller,
                        $reason !== '' ? ': ' . $reason . '. Reasignar a otro taller.' : '. Reasignar a otro taller.'
                    )),
                ]);
            } else {
                $workshop->maintenanceAlert->update([
                    'leida' => true,
                ]);
            }
        }

        if ($workshop->maintenanceAppointment) {
            if ($workshop->isClosedState()) {
                $workshop->maintenanceAppointment->update([
                    'estado' => MaintenanceAppointment::STATUS_COMPLETED,
                ]);
            } elseif ($workshop->estado === Workshop::STATUS_REJECTED) {
                $workshop->maintenanceAppointment->update([
                    'estado' => MaintenanceAppointment::STATUS_REJECTED,
                ]);
            } elseif ($workshop->estado === Workshop::STATUS_CANCELLED) {
                $workshop->maintenanceAppointment->update([
                    'estado' => MaintenanceAppointment::STATUS_CANCELLED,
                ]);
            } elseif ($workshop->maintenanceAppointment->estado === MaintenanceAppointment::STATUS_PENDING) {
                $workshop->maintenanceAppointment->update([
                    'estado' => MaintenanceAppointment::STATUS_APPROVED,
                ]);
            }
        }

        $this->syncVehicleOperationalStatus((int) $workshop->vehicle_id);
    }

    private function syncVehicleOperationalStatus(int $vehicleId): void
    {
        $vehicle = Vehicle::query()->find($vehicleId);
        if (!$vehicle) {
            return;
        }

        $hasOpenWorkshop = Workshop::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('estado', $this->openStatuses())
            ->exists();

        $vehicle->update([
            'operational_status' => $hasOpenWorkshop
                ? Vehicle::OPERATIONAL_STATUS_IN_MAINTENANCE
                : Vehicle::OPERATIONAL_STATUS_AVAILABLE,
        ]);
    }

    private function storeWorkshopFile($file, string $folder): ?string
    {
        if (!$file) {
            return null;
        }

        return (string) $file->store($folder, 'public');
    }
}
