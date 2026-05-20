<?php

namespace App\Livewire;

use App\Models\MaintenanceAppointment;
use App\Models\MaintenanceAlert;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\MaintenanceType;
use App\Models\VehicleAssignment;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;
use Livewire\Attributes\Validate;

class MaintenanceAppointmentManager extends Component
{
    use WithPagination;
    use WithFileUploads;
    use WithoutUrlPagination;

    protected string $paginationTheme = 'bootstrap';

    public bool $showForm = false; // Control de vista
    public string $search = '';
    public string $statusFilter = '';
    public string $maintenance_form_type = 'vehiculo';
    public ?string $fecha_desde = null;
    public ?string $fecha_hasta = null;

    

    #[Validate('required|integer')]
    public int $vehicle_id = 0;

    #[Validate('nullable|integer')]
    public ?int $driver_id = null;

    #[Validate('nullable|integer')]
    public ?int $tipo_mantenimiento_id = null;

    #[Validate('required|date_format:Y-m-d\TH:i')]
    public string $fecha_programada = '';

    #[Validate('boolean')]
    public bool $es_accidente = false;

    public $formulario_documento_file = null;
    public ?string $formulario_documento_path = null;

    #[Validate('required|string|in:Pendiente,Aprobado,Realizado,Rechazado,Cancelado')]
    public string $estado = MaintenanceAppointment::STATUS_PENDING;

    public bool $isEdit = false;
    public ?int $editingId = null;
    public ?string $editingEvidenceUrl = null;
    public ?string $editingFormUrl = null;
    public ?string $editingOriginLabel = null;
    public bool $editingRequiresAgencyForm = false;

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'recepcion']), 403);
        $this->fecha_desde = now()->startOfMonth()->toDateString();
        $this->fecha_hasta = now()->toDateString();
    }

    public function openForm()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function render()
    {
        $query = MaintenanceAppointment::query()
            ->active()
            ->with(['vehicle.brand', 'vehicle.vehicleClass', 'driver', 'tipoMantenimiento', 'requestedBy', 'approvedBy'])
            ->orderByRaw("CASE estado WHEN 'Pendiente' THEN 1 WHEN 'Aprobado' THEN 2 WHEN 'Realizado' THEN 3 WHEN 'Rechazado' THEN 4 WHEN 'Cancelado' THEN 5 ELSE 9 END")
            ->orderBy('fecha_programada', 'desc');

        if ($this->statusFilter !== '') {
            $query->where('estado', $this->statusFilter);
        }

        [$fechaDesde, $fechaHasta] = $this->resolveOrderedFilterDateRange();
        if ($fechaDesde) {
            $query->whereDate('fecha_programada', '>=', $fechaDesde);
        }

        if ($fechaHasta) {
            $query->whereDate('fecha_programada', '<=', $fechaHasta);
        }

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('estado', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(fecha_programada AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(es_accidente AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('placa', 'like', "%{$search}%"))
                    ->orWhereHas('driver', fn ($driverQuery) => $driverQuery->where('nombre', 'like', "%{$search}%"))
                    ->orWhereHas('tipoMantenimiento', fn ($typeQuery) => $typeQuery->where('nombre', 'like', "%{$search}%"));
            });
        }

        $approvedAppointmentsQuery = MaintenanceAppointment::query()
            ->active()
            ->with(['vehicle.brand', 'driver', 'tipoMantenimiento'])
            ->where('estado', MaintenanceAppointment::STATUS_APPROVED)
            ->orderBy('fecha_programada', 'desc');

        if ($fechaDesde) {
            $approvedAppointmentsQuery->whereDate('fecha_programada', '>=', $fechaDesde);
        }

        if ($fechaHasta) {
            $approvedAppointmentsQuery->whereDate('fecha_programada', '<=', $fechaHasta);
        }

        if ($search !== '') {
            $approvedAppointmentsQuery->where(function ($q) use ($search) {
                $q->whereRaw('CAST(fecha_programada AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('placa', 'like', "%{$search}%"))
                    ->orWhereHas('driver', fn ($driverQuery) => $driverQuery->where('nombre', 'like', "%{$search}%"))
                    ->orWhereHas('tipoMantenimiento', fn ($typeQuery) => $typeQuery->where('nombre', 'like', "%{$search}%"));
            });
        }

        $approvedAppointments = $approvedAppointmentsQuery
            ->limit(12)
            ->get();

        $appointments = $this->paginateWithinBounds($query, 10);
        $vehicles = Vehicle::with(['brand', 'vehicleClass'])
            ->where('activo', true)
            ->operationallyAvailable()
            ->where(function ($query) {
                $query->where('maintenance_form_type', $this->maintenance_form_type)
                    ->orWhereHas('vehicleClass', function ($vehicleClassQuery) {
                        $vehicleClassQuery->where('maintenance_form_type', $this->maintenance_form_type);
                    });
            })
            ->orderBy('placa')
            ->get();

        if ($this->vehicle_id > 0 && !$vehicles->contains('id', $this->vehicle_id)) {
            $selectedVehicle = Vehicle::with(['brand', 'vehicleClass'])->find($this->vehicle_id);
            if ($selectedVehicle) {
                $vehicles->push($selectedVehicle);
            }
        }
        $drivers = Driver::where('activo', true)->orderBy('nombre')->get();
        $selectedVehicle = $this->vehicle_id > 0
            ? Vehicle::with('vehicleClass')->find($this->vehicle_id)
            : null;
        $typesQuery = MaintenanceType::query()->active()->orderBy('nombre');

        if ($selectedVehicle) {
            $typesQuery->applicableToVehicle($selectedVehicle);
        } else {
            $typesQuery->where(function ($query) {
                $query->whereNull('maintenance_form_type')
                    ->orWhere('maintenance_form_type', $this->maintenance_form_type);
            });
        }

        $types = $typesQuery->get();
        
        return view('livewire.maintenance-appointment-manager', [
            'appointments' => $appointments,
            'approvedAppointments' => $approvedAppointments,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'types' => $types,
            'reportVehicles' => Vehicle::with('brand')->orderBy('placa')->get(),
            'reportDrivers' => Driver::orderBy('nombre')->get(),
            'approvedCount' => MaintenanceAppointment::query()->active()->where('estado', MaintenanceAppointment::STATUS_APPROVED)->count(),
            'pendingCount' => MaintenanceAppointment::query()->active()->where('estado', MaintenanceAppointment::STATUS_PENDING)->count(),
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetAppointmentsPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetAppointmentsPage();
    }

    public function updatedFechaDesde(): void
    {
        $this->resetAppointmentsPage();
    }

    public function updatedFechaHasta(): void
    {
        $this->resetAppointmentsPage();
    }

    public function save()
    {
        $this->validate();
        $this->validate([
            'formulario_documento_file' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
        ]);

        if (!$this->isEdit) {
            $scheduledAt = Carbon::createFromFormat('Y-m-d\TH:i', $this->fecha_programada, config('app.timezone'));
            if ($scheduledAt->lte(now())) {
                $this->addError('fecha_programada', 'La cita de mantenimiento solo puede programarse hacia adelante.');
                return;
            }
        }

        if (!$this->isEdit) {
            $this->estado = MaintenanceAppointment::STATUS_PENDING;
        }

        $vehicle = Vehicle::with('vehicleClass')->find($this->vehicle_id);
        if (!$vehicle) {
            $this->addError('vehicle_id', 'El vehiculo seleccionado no existe.');
            return;
        }

        if ($vehicle->isInMaintenance()) {
            $this->addError('vehicle_id', 'El vehiculo esta en mantenimiento y no puede recibir nuevas citas por ahora.');
            return;
        }

        if ($this->tipo_mantenimiento_id) {
            $allowedType = MaintenanceType::query()
                ->applicableToVehicle($vehicle)
                ->whereKey((int) $this->tipo_mantenimiento_id)
                ->first();

            if (!$allowedType) {
                $this->addError('tipo_mantenimiento_id', 'El tipo de mantenimiento no corresponde al vehiculo seleccionado.');
                return;
            }
        }

        $storedFormPath = $this->formulario_documento_path;
        if ($this->formulario_documento_file) {
            $storedFormPath = (string) $this->formulario_documento_file->store('maintenance-appointment-forms', 'public');
        }

        if ($this->isEdit && $this->editingId) {
            $appointment = MaintenanceAppointment::find($this->editingId);
            if ($appointment) {
                $approvalFields = $this->resolveApprovalFieldsForSave($appointment, $this->estado);
                $appointment->update([
                    'vehicle_id' => $this->vehicle_id,
                    'driver_id' => $this->driver_id,
                    'tipo_mantenimiento_id' => $this->tipo_mantenimiento_id,
                    'fecha_programada' => $this->fecha_programada,
                    'es_accidente' => $this->es_accidente,
                    'formulario_documento_path' => $storedFormPath,
                    'estado' => $this->estado,
                ] + $approvalFields);
                $this->syncRequestAlert($appointment);
                $this->syncProgrammedAlert($appointment);
                session()->flash('message', 'Cita de mantenimiento actualizada correctamente.');
            }
        } else {
            $appointment = MaintenanceAppointment::create([
                'vehicle_id' => $this->vehicle_id,
                'driver_id' => $this->driver_id,
                'tipo_mantenimiento_id' => $this->tipo_mantenimiento_id,
                'fecha_programada' => $this->fecha_programada,
                'solicitud_fecha' => now(),
                'requested_by_user_id' => auth()->id(),
                'origen_solicitud' => 'web_agencia',
                'es_accidente' => $this->es_accidente,
                'formulario_documento_path' => $storedFormPath,
                'estado' => MaintenanceAppointment::STATUS_PENDING,
                'activo' => true,
            ]);
            $this->syncRequestAlert($appointment);
            $this->syncProgrammedAlert($appointment);
            session()->flash('message', 'Cita de mantenimiento creada correctamente.');
        }

        $this->resetForm();
    }

    public function edit(MaintenanceAppointment $appointment)
    {
        $this->isEdit = true;
        $this->editingId = $appointment->id;
        $this->vehicle_id = $appointment->vehicle_id ?? 0;
        $this->driver_id = $appointment->driver_id;
        $this->tipo_mantenimiento_id = $appointment->tipo_mantenimiento_id;
        $this->maintenance_form_type = (string) ($appointment->vehicle?->maintenance_form_type
            ?: $appointment->vehicle?->vehicleClass?->maintenance_form_type
            ?: 'vehiculo');
        $this->fecha_programada = $appointment->fecha_programada->format('Y-m-d\TH:i');
        $this->es_accidente = $appointment->es_accidente;
        $this->estado = $appointment->estado;
        $this->formulario_documento_path = $appointment->formulario_documento_path;
        $this->formulario_documento_file = null;
        $this->editingEvidenceUrl = $appointment->evidencia_path
            ? route('maintenance-appointments.evidence', $appointment)
            : null;
        $this->editingFormUrl = $appointment->formulario_documento_path
            ? route('maintenance-appointments.form', $appointment)
            : null;
        $this->editingOriginLabel = $this->resolveOriginLabel($appointment);
        $this->editingRequiresAgencyForm = !empty($appointment->evidencia_path);
        
        $this->showForm = true; // Mostrar formulario al editar
    }

    public function delete(MaintenanceAppointment $appointment)
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('maintenance_alerts')) {
            MaintenanceAlert::query()
                ->where('maintenance_appointment_id', $appointment->id)
                ->where('tipo', 'Programado')
                ->where('status', MaintenanceAlert::STATUS_ACTIVE)
                ->update([
                    'status' => MaintenanceAlert::STATUS_OMITTED,
                    'leida' => true,
                    'fecha_resolucion' => now(),
                    'usuario_id' => auth()->id(),
                ]);
            MaintenanceAlert::query()
                ->where('maintenance_appointment_id', $appointment->id)
                ->where('tipo', 'Solicitud')
                ->where('status', MaintenanceAlert::STATUS_ACTIVE)
                ->update([
                    'status' => MaintenanceAlert::STATUS_OMITTED,
                    'leida' => true,
                    'fecha_resolucion' => now(),
                    'usuario_id' => auth()->id(),
                ]);
        }

        $appointment->update(['activo' => false]);
        session()->flash('message', 'Cita de mantenimiento inactivada correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'vehicle_id', 'driver_id', 'tipo_mantenimiento_id', 
            'fecha_programada', 'es_accidente',
            'estado', 'isEdit', 'editingId', 'editingEvidenceUrl', 'editingFormUrl', 'editingOriginLabel',
            'editingRequiresAgencyForm', 'formulario_documento_file', 'formulario_documento_path',
            'maintenance_form_type'
        ]);
        $this->vehicle_id = 0;
        $this->maintenance_form_type = 'vehiculo';
        $this->estado = MaintenanceAppointment::STATUS_PENDING;
        $this->showForm = false; // Volver a la tabla
        $this->resetAppointmentsPage();
    }

    public function updatedMaintenanceFormType(): void
    {
        $this->vehicle_id = 0;
        $this->driver_id = null;
        $this->tipo_mantenimiento_id = null;
    }

    public function updatedVehicleId(): void
    {
        if (!$this->vehicle_id) {
            if (!$this->driver_id) {
                $this->driver_id = null;
            }
            $this->tipo_mantenimiento_id = null;
            return;
        }

        $vehicle = Vehicle::with(['vehicleClass', 'assignments.driver'])->find($this->vehicle_id);
        if (!$vehicle) {
            $this->driver_id = null;
            $this->tipo_mantenimiento_id = null;
            return;
        }

        $this->maintenance_form_type = (string) ($vehicle->maintenance_form_type
            ?: $vehicle->vehicleClass?->maintenance_form_type
            ?: $this->maintenance_form_type
            ?: 'vehiculo');

        $currentAssignment = $vehicle->assignments
            ->filter(function ($assignment) {
                if (!(bool) ($assignment->activo ?? true)) {
                    return false;
                }

                $starts = $assignment->fecha_inicio;
                $ends = $assignment->fecha_fin;

                if ($starts && now()->lt($starts)) {
                    return false;
                }

                if ($ends && now()->gt($ends)) {
                    return false;
                }

                return true;
            })
            ->sortByDesc('fecha_inicio')
            ->first();

        $this->driver_id = $currentAssignment?->driver_id ? (int) $currentAssignment->driver_id : null;

        if (!$this->vehicle_id || !$this->tipo_mantenimiento_id) {
            return;
        }

        $allowed = MaintenanceType::query()
            ->applicableToVehicle($vehicle)
            ->whereKey((int) $this->tipo_mantenimiento_id)
            ->exists();

        if (!$allowed) {
            $this->tipo_mantenimiento_id = null;
        }
    }

    public function updatedDriverId(): void
    {
        if (!$this->driver_id) {
            return;
        }

        $assignment = VehicleAssignment::query()
            ->with(['vehicle.vehicleClass'])
            ->where('driver_id', (int) $this->driver_id)
            ->where(function ($query) {
                $query->where('activo', true)->orWhereNull('activo');
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get()
            ->first(function (VehicleAssignment $assignment) {
                $starts = $assignment->fecha_inicio;
                $ends = $assignment->fecha_fin;

                if ($starts && now()->lt($starts)) {
                    return false;
                }

                if ($ends && now()->gt($ends)) {
                    return false;
                }

                return true;
            });

        if (!$assignment?->vehicle_id) {
            return;
        }

        $vehicle = $assignment->vehicle;
        if (!$vehicle) {
            return;
        }

        $this->vehicle_id = (int) $assignment->vehicle_id;
        $this->maintenance_form_type = (string) ($vehicle->maintenance_form_type
            ?: $vehicle->vehicleClass?->maintenance_form_type
            ?: $this->maintenance_form_type
            ?: 'vehiculo');

        if ($this->tipo_mantenimiento_id) {
            $allowed = MaintenanceType::query()
                ->applicableToVehicle($vehicle)
                ->whereKey((int) $this->tipo_mantenimiento_id)
                ->exists();

            if (!$allowed) {
                $this->tipo_mantenimiento_id = null;
            }
        }
    }

    public function resolveOriginLabel(MaintenanceAppointment $appointment): string
    {
        if (!empty($appointment->evidencia_path)) {
            return 'Documento';
        }

        return match ((string) ($appointment->origen_solicitud ?? '')) {
            'mobile_driver' => 'Movil',
            'web_agencia' => 'Web',
            default => 'Web',
        };
    }

    public function uploadedFormIsPdf(): bool
    {
        if (!$this->formulario_documento_file instanceof UploadedFile) {
            return false;
        }

        return $this->uploadedFileIsPdf($this->formulario_documento_file);
    }

    public function currentFormIsPdf(): bool
    {
        return $this->pathLooksLikePdf($this->formulario_documento_path);
    }

    public function evidenceIsPdf(): bool
    {
        return $this->pathLooksLikePdfFromUrl($this->editingEvidenceUrl);
    }

    private function uploadedFileIsPdf(UploadedFile $file): bool
    {
        $mime = strtolower((string) $file->getMimeType());
        if ($mime === 'application/pdf') {
            return true;
        }

        return strtolower((string) $file->getClientOriginalExtension()) === 'pdf';
    }

    private function pathLooksLikePdf(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    private function pathLooksLikePdfFromUrl(?string $url): bool
    {
        if (!$url) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || trim($path) === '') {
            return false;
        }

        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function approve(int $appointmentId): void
    {
        $appointment = MaintenanceAppointment::query()->find($appointmentId);
        if (!$appointment) {
            return;
        }

        $appointment->update([
            'estado' => MaintenanceAppointment::STATUS_APPROVED,
            'approved_at' => $appointment->approved_at ?? now(),
            'approved_by_user_id' => $appointment->approved_by_user_id ?? auth()->id(),
        ]);

        $this->syncRequestAlert($appointment);
        $this->syncProgrammedAlert($appointment);
        session()->flash('message', 'Solicitud de mantenimiento aprobada correctamente.');
    }

    public function reject(int $appointmentId): void
    {
        $appointment = MaintenanceAppointment::query()->find($appointmentId);
        if (!$appointment) {
            return;
        }

        $appointment->update([
            'estado' => MaintenanceAppointment::STATUS_REJECTED,
        ]);

        $this->syncRequestAlert($appointment);
        $this->syncProgrammedAlert($appointment);
        session()->flash('message', 'Solicitud de mantenimiento rechazada correctamente.');
    }

    private function syncRequestAlert(MaintenanceAppointment $appointment): void
    {
        if (!Schema::hasTable('maintenance_alerts')) {
            return;
        }

        $typeName = (string) ($appointment->tipoMantenimiento?->nombre ?? 'mantenimiento');
        $plate = (string) ($appointment->vehicle?->placa ?? 'N/A');
        $driverName = (string) ($appointment->driver?->nombre ?? $appointment->requestedBy?->name ?? 'Conductor');
        $message = "Solicitud de {$typeName} para vehiculo {$plate} por {$driverName}.";

        if ($appointment->estado === MaintenanceAppointment::STATUS_PENDING) {
            MaintenanceAlert::query()->updateOrCreate(
                [
                    'maintenance_appointment_id' => $appointment->id,
                    'tipo' => 'Solicitud',
                ],
                [
                    'vehicle_id' => (int) $appointment->vehicle_id,
                    'maintenance_type_id' => $appointment->tipo_mantenimiento_id,
                    'mensaje' => $message,
                    'leida' => false,
                    'status' => MaintenanceAlert::STATUS_REQUESTED,
                    'fecha_resolucion' => null,
                    'usuario_id' => null,
                ]
            );
            return;
        }

        $resolutionStatus = in_array($appointment->estado, [
            MaintenanceAppointment::STATUS_APPROVED,
            MaintenanceAppointment::STATUS_COMPLETED,
        ], true)
            ? MaintenanceAlert::STATUS_RESOLVED
            : MaintenanceAlert::STATUS_OMITTED;

        MaintenanceAlert::query()
            ->where('maintenance_appointment_id', $appointment->id)
            ->where('tipo', 'Solicitud')
            ->whereIn('status', MaintenanceAlert::openStatuses())
            ->update([
                'status' => $resolutionStatus,
                'leida' => true,
                'fecha_resolucion' => now(),
                'usuario_id' => auth()->id(),
            ]);
    }

    private function syncProgrammedAlert(MaintenanceAppointment $appointment): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('maintenance_alerts')) {
            return;
        }

        if (!$appointment->es_accidente || $appointment->estado !== MaintenanceAppointment::STATUS_APPROVED) {
            MaintenanceAlert::query()
                ->where('maintenance_appointment_id', $appointment->id)
                ->where('tipo', 'Programado')
                ->where('status', MaintenanceAlert::STATUS_ACTIVE)
                ->update([
                    'status' => MaintenanceAlert::STATUS_OMITTED,
                    'leida' => true,
                    'fecha_resolucion' => now(),
                    'usuario_id' => auth()->id(),
                ]);
            return;
        }

        $placa = (string) ($appointment->vehicle?->placa ?? 'N/A');
        $fecha = optional($appointment->fecha_programada)->format('d/m/Y H:i') ?? '-';

        MaintenanceAlert::query()->updateOrCreate(
            [
                'maintenance_appointment_id' => $appointment->id,
                'tipo' => 'Programado',
                'status' => MaintenanceAlert::STATUS_ACTIVE,
            ],
            [
                'vehicle_id' => (int) $appointment->vehicle_id,
                'maintenance_type_id' => $appointment->tipo_mantenimiento_id,
                'mensaje' => "Revision programada por accidente para vehiculo {$placa} el {$fecha}.",
                'leida' => false,
                'fecha_resolucion' => null,
                'usuario_id' => null,
            ]
        );
    }

    private function resolveOrderedFilterDateRange(): array
    {
        $desde = $this->normalizeDate($this->fecha_desde);
        $hasta = $this->normalizeDate($this->fecha_hasta);

        if ($desde && $hasta && $desde > $hasta) {
            return [$hasta, $desde];
        }

        return [$desde, $hasta];
    }

    private function resolveApprovalFieldsForSave(MaintenanceAppointment $appointment, string $nextStatus): array
    {
        $wasApproved = (string) $appointment->estado === MaintenanceAppointment::STATUS_APPROVED;
        $willBeApproved = $nextStatus === MaintenanceAppointment::STATUS_APPROVED;

        if (!$willBeApproved) {
            return [
                'approved_at' => null,
                'approved_by_user_id' => null,
            ];
        }

        if ($wasApproved && $appointment->approved_at) {
            return [];
        }

        return [
            'approved_at' => now(),
            'approved_by_user_id' => auth()->id(),
        ];
    }

    private function normalizeDate(?string $value): ?string
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

    private function resetAppointmentsPage(): void
    {
        $this->resetPage();
        $this->resetPage('appointmentsPage');
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
