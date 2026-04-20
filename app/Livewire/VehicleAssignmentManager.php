<?php

namespace App\Livewire;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;

class VehicleAssignmentManager extends Component
{
    use WithPagination;
    use WithoutUrlPagination;

    protected string $paginationTheme = 'bootstrap';

    public bool $showForm = false;
    public string $search = '';
    public string $plateFilter = '';
    public string $assignmentStatusFilter = 'active';
    public bool $showUnassignedDrivers = false;

    #[Validate('required|integer|min:1|exists:drivers,id')]
    public int $driver_id = 0;

    #[Validate('required|integer|min:1|exists:vehicles,id')]
    public int $vehicle_id = 0;

    #[Validate('nullable|string|max:100')]
    public ?string $tipo_asignacion = 'Fijo';

    #[Validate('required|date')]
    public string $fecha_inicio = '';

    #[Validate('nullable|date')]
    public ?string $fecha_fin = null;

    #[Validate('boolean')]
    public bool $activo = true;

    public bool $isEdit = false;
    public ?int $editingId = null;
    public bool $showReassignConfirm = false;
    public bool $skipNextReassignCheck = false;
    public ?int $conflictVehicleAssignmentId = null;
    public ?string $conflictVehicleDriverName = null;
    public ?string $conflictVehiclePlate = null;
    public ?int $conflictDriverAssignmentId = null;
    public ?string $conflictDriverName = null;
    public ?string $conflictDriverVehiclePlate = null;
    public bool $reactivatingAssignment = false;

    public function mount(): void
    {
        abort_unless(in_array($this->currentUser()?->role, ['admin', 'recepcion']), 403);
        $this->fecha_inicio = now()->toDateString();
    }

    public function render()
    {
        $this->syncExpiredAssignments();

        $query = $this->visibleAssignmentsQuery()->orderByDesc('fecha_inicio');

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('tipo_asignacion', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(fecha_inicio AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(fecha_fin AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('driver', fn($driverQuery) => $driverQuery->where('nombre', 'like', "%{$search}%"))
                    ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                        $vehicleQuery->where('placa', 'like', "%{$search}%")
                            ->orWhere('modelo', 'like', "%{$search}%");
                    });
            });
        }

        $plateFilter = mb_strtoupper(trim($this->plateFilter));
        if ($plateFilter !== '') {
            $query->whereHas('vehicle', function ($vehicleQuery) use ($plateFilter) {
                $vehicleQuery->whereRaw('UPPER(placa) LIKE ?', ['%' . $plateFilter . '%']);
            });
        }

        $assignments = $this->paginateWithinBounds($query, 10);
        $assignedDriverIds = $this->activeAssignmentsQuery()
            ->pluck('driver_id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $drivers = Driver::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $assignmentDate = $this->resolveAssignmentDate();
        $editingId = $this->editingId;

        $vehiclesQuery = Vehicle::query()
            ->where('activo', true)
            ->operationallyAvailable();

        $vehicles = $vehiclesQuery
            ->orderBy('placa')
            ->get();

        if ($this->isEdit && $this->vehicle_id > 0 && !$vehicles->contains('id', $this->vehicle_id)) {
            $selectedVehicle = Vehicle::query()->find($this->vehicle_id);
            if ($selectedVehicle) {
                $vehicles->push($selectedVehicle);
            }
        }

        $unassignedDrivers = Driver::query()
            ->where('activo', true)
            ->when(
                !empty($assignedDriverIds),
                fn($driverQuery) => $driverQuery->whereNotIn('id', $assignedDriverIds)
            )
            ->orderBy('nombre')
            ->get();

        return view('livewire.vehicle-assignment-manager', [
            'assignments' => $assignments,
            'drivers' => $drivers,
            'vehicles' => $vehicles,
            'unassignedDrivers' => $unassignedDrivers,
            'activeAssignmentsByDriver' => $this->activeAssignmentsIndexedBy('driver_id'),
            'activeAssignmentsByVehicle' => $this->activeAssignmentsIndexedBy('vehicle_id'),
            'reportDrivers' => Driver::query()->orderBy('nombre')->get(),
            'reportVehicles' => Vehicle::query()->orderBy('placa')->get(),
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPlateFilter(): void
    {
        $this->plateFilter = mb_strtoupper(trim($this->plateFilter));
        $this->resetPage();
    }

    public function updatedAssignmentStatusFilter(): void
    {
        if (!in_array($this->assignmentStatusFilter, ['active', 'inactive', 'all'], true)) {
            $this->assignmentStatusFilter = 'active';
        }

        $this->resetPage();
    }

    public function updatedShowUnassignedDrivers(): void
    {
        $this->resetPage();
    }

    public function updatedDriverId(): void
    {
        $this->skipNextReassignCheck = false;
        $this->closeReassignConfirm();
    }

    public function updatedVehicleId(): void
    {
        $this->skipNextReassignCheck = false;
        $this->closeReassignConfirm();
    }

    public function openForm(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->tipo_asignacion = in_array((string) $this->tipo_asignacion, ['Fijo', 'Temporal'], true)
            ? (string) $this->tipo_asignacion
            : 'Fijo';

        if ($this->tipo_asignacion !== 'Temporal') {
            $this->fecha_fin = null;
            $this->resetValidation('fecha_fin');
        }

        $fechaInicioRules = ['required', 'date'];
        if (!$this->isEdit) {
            $fechaInicioRules[] = 'after_or_equal:today';
        }

        $this->validate(
            [
                'driver_id' => ['required', 'integer', 'min:1', 'exists:drivers,id'],
                'vehicle_id' => ['required', 'integer', 'min:1', 'exists:vehicles,id'],
                'tipo_asignacion' => ['required', 'string', Rule::in(['Fijo', 'Temporal'])],
                'fecha_inicio' => $fechaInicioRules,
                'fecha_fin' => [
                    'nullable',
                    'date',
                    'after_or_equal:fecha_inicio',
                    'required_if:tipo_asignacion,Temporal',
                ],
                'activo' => ['required', 'boolean'],
            ],
            [
                'driver_id.required' => 'Debe seleccionar un conductor.',
                'driver_id.integer' => 'El conductor seleccionado no es valido.',
                'driver_id.min' => 'Debe seleccionar un conductor valido.',
                'driver_id.exists' => 'El conductor seleccionado no existe.',
                'vehicle_id.required' => 'Debe seleccionar un vehiculo.',
                'vehicle_id.integer' => 'El vehiculo seleccionado no es valido.',
                'vehicle_id.min' => 'Debe seleccionar un vehiculo valido.',
                'vehicle_id.exists' => 'El vehiculo seleccionado no existe.',
                'tipo_asignacion.required' => 'Debe seleccionar el tipo de asignacion.',
                'tipo_asignacion.in' => 'El tipo de asignacion seleccionado no es valido.',
                'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
                'fecha_inicio.date' => 'La fecha de inicio no es valida.',
                'fecha_inicio.after_or_equal' => 'La fecha de inicio no puede ser anterior a hoy.',
                'fecha_fin.required_if' => 'Debe indicar hasta cuando sera la asignacion temporal.',
                'fecha_fin.date' => 'La fecha fin no es valida.',
                'fecha_fin.after_or_equal' => 'La fecha fin no puede ser anterior a la fecha de inicio.',
                'activo.required' => 'Debe indicar si la asignacion estara activa.',
                'activo.boolean' => 'El estado de la asignacion no es valido.',
            ]
        );

        if (!$this->ensureValidAssignmentSelection()) {
            return;
        }

        if ($this->tipo_asignacion === 'Temporal' && filled($this->fecha_fin)) {
            try {
                if (Carbon::parse((string) $this->fecha_fin)->lt(now()->startOfDay())) {
                    $this->addError('fecha_fin', 'La fecha fin de una asignacion temporal no puede ser anterior a hoy.');
                    return;
                }
            } catch (\Throwable) {
                $this->addError('fecha_fin', 'La fecha fin no es valida.');
                return;
            }
        }

        if (!$this->skipNextReassignCheck && $this->prepareReassignConfirmation()) {
            return;
        }

        if (!$this->ensureNoActiveAssignmentConflicts()) {
            return;
        }

        $this->persistAssignment();
    }
    public function confirmReassignment(): void
    {
        $this->skipNextReassignCheck = true;
        $this->resolveConflictingAssignments();
        $this->closeReassignConfirm();

        if (!$this->ensureNoActiveAssignmentConflicts()) {
            $this->skipNextReassignCheck = false;
            return;
        }

        if ($this->reactivatingAssignment) {
            $this->isEdit = true;
            $this->activo = true;
        }

        $this->persistAssignment();
    }
    public function cancelReassignment(): void
    {
        $wasReactivating = $this->reactivatingAssignment;
        $message = $this->buildReassignmentCancelledMessage();
        $this->closeReassignConfirm();
        $this->reactivatingAssignment = false;
        $this->skipNextReassignCheck = false;

        if ($wasReactivating) {
            $this->resetForm();
        }

        session()->flash('message', $message);
    }

    public function closeReassignConfirm(): void
    {
        $this->showReassignConfirm = false;
        $this->conflictVehicleAssignmentId = null;
        $this->conflictVehicleDriverName = null;
        $this->conflictVehiclePlate = null;
        $this->conflictDriverAssignmentId = null;
        $this->conflictDriverName = null;
        $this->conflictDriverVehiclePlate = null;
        $this->dispatch('closeVehicleReassignModal');
    }

    public function edit(VehicleAssignment $assignment): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->skipNextReassignCheck = false;
        $this->reactivatingAssignment = false;
        $this->closeReassignConfirm();

        $this->isEdit = true;
        $this->editingId = $assignment->id;
        $this->driver_id = $assignment->driver_id ?? 0;
        $this->vehicle_id = $assignment->vehicle_id ?? 0;
        $this->tipo_asignacion = $assignment->tipo_asignacion;
        $this->fecha_inicio = $assignment->fecha_inicio?->toDateString() ?? now()->toDateString();
        $this->fecha_fin = $assignment->fecha_fin?->toDateString();
        $this->activo = (bool) $assignment->activo;
        $this->showForm = true;
    }

    public function delete(VehicleAssignment $assignment): void
    {
        $assignment->update([
            'activo' => false,
            'fecha_fin' => $assignment->fecha_fin?->toDateString() ?: now()->toDateString(),
        ]);

        session()->flash('message', 'Asignacion inactivada correctamente.');
    }

    public function reactivate(VehicleAssignment $assignment): void
    {
        if (!$assignment->driver_id || !$assignment->vehicle_id) {
            session()->flash('error', 'No se puede reactivar una asignacion sin conductor o vehiculo historico.');
            return;
        }

        $this->driver_id = (int) $assignment->driver_id;
        $this->vehicle_id = (int) $assignment->vehicle_id;
        $this->fecha_inicio = now()->toDateString();
        $this->fecha_fin = null;
        $this->tipo_asignacion = (string) ($assignment->tipo_asignacion ?: 'Fijo');
        $this->activo = true;
        $this->isEdit = true;
        $this->editingId = (int) $assignment->id;
        $this->reactivatingAssignment = true;

        if (!$this->ensureValidAssignmentSelection()) {
            $this->resetForm();
            return;
        }

        if (!$this->skipNextReassignCheck && $this->prepareReassignConfirmation()) {
            return;
        }

        if (!$this->ensureNoActiveAssignmentConflicts()) {
            $this->resetForm();
            return;
        }

        $this->persistAssignment('Asignacion reactivada correctamente.');
    }

    public function unassign(VehicleAssignment $assignment): void
    {
        $driverName = (string) ($assignment->driver?->nombre ?? 'El conductor');
        $vehiclePlate = (string) ($assignment->vehicle?->placa ?? 'el vehiculo');

        $assignment->update([
            'activo' => false,
            'fecha_fin' => now()->toDateString(),
        ]);

        session()->flash('message', "{$driverName} quedo sin vehiculo asignado. Se cerro logicamente la asignacion de {$vehiclePlate}.");
    }

    public function resetForm(): void
    {
        $this->driver_id = 0;
        $this->vehicle_id = 0;
        $this->tipo_asignacion = 'Fijo';
        $this->fecha_inicio = now()->toDateString();
        $this->fecha_fin = null;
        $this->activo = true;
        $this->isEdit = false;
        $this->editingId = null;
        $this->skipNextReassignCheck = false;
        $this->reactivatingAssignment = false;
        $this->closeReassignConfirm();
        $this->showForm = false;
        $this->resetPage();
    }

    public function updatedTipoAsignacion($value): void
    {
        if ($value !== 'Temporal') {
            $this->fecha_fin = null;
            $this->resetValidation('fecha_fin');
        }
    }

    private function persistAssignment(?string $successMessage = null): void
    {
        $payload = [
            'driver_id' => $this->driver_id,
            'vehicle_id' => $this->vehicle_id,
            'tipo_asignacion' => $this->tipo_asignacion,
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin' => $this->fecha_fin,
            'activo' => $this->activo,
        ];

        if ($this->isEdit && $this->editingId) {
            $assignment = VehicleAssignment::find($this->editingId);
            if ($assignment) {
                $assignment->update($payload);
                session()->flash('message', $successMessage ?: 'Asignacion actualizada correctamente.');
            }
        } else {
            VehicleAssignment::create($payload);
            session()->flash('message', $successMessage ?: 'Asignacion creada correctamente.');
        }

        $this->resetForm();
    }

    private function resolveConflictingAssignments(): void
    {
        $assignmentIds = collect([
            $this->conflictVehicleAssignmentId,
            $this->conflictDriverAssignmentId,
        ])
            ->filter(fn($id) => filled($id))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($assignmentIds->isEmpty()) {
            return;
        }

        $effectiveEndDate = $this->resolveAssignmentDate();

        VehicleAssignment::query()
            ->whereIn('id', $assignmentIds->all())
            ->get()
            ->each(function (VehicleAssignment $assignment) use ($effectiveEndDate) {
                $assignment->update([
                    // QUITAMOS la linea de vehicle_id => null para mantener el historial
                    'activo' => false,
                    'fecha_fin' => $effectiveEndDate,
                ]);
            });
    }

    private function ensureValidAssignmentSelection(): bool
    {
        $driverId = (int) ($this->driver_id ?? 0);
        $vehicleId = (int) ($this->vehicle_id ?? 0);

        $driver = $driverId > 0 ? Driver::query()->find($driverId) : null;
        $vehicle = $vehicleId > 0 ? Vehicle::query()->find($vehicleId) : null;

        if (!$driver) {
            $this->addError('driver_id', 'Debe seleccionar un conductor valido.');
        }

        if (!$vehicle) {
            $this->addError('vehicle_id', 'Debe seleccionar un vehiculo valido.');
        }

        if (!$driver || !$vehicle) {
            return false;
        }

        if ($vehicle->isInMaintenance()) {
            $this->addError('vehicle_id', 'El vehiculo esta en mantenimiento y no puede asignarse en este momento.');
            return false;
        }

        return true;
    }

    private function ensureNoActiveAssignmentConflicts(): bool
    {
        if (!$this->activo) {
            return true;
        }

        $activeAssignments = VehicleAssignment::query()
            ->with(['driver', 'vehicle'])
            ->where('activo', true)
            ->whereNotNull('vehicle_id')
            ->whereNotNull('driver_id')
            ->when($this->editingId, fn($q) => $q->where('id', '!=', $this->editingId));

        $this->applyAssignmentOverlapFilter($activeAssignments, $this->fecha_inicio, $this->fecha_fin);

        $vehicleConflict = (clone $activeAssignments)
            ->where('vehicle_id', $this->vehicle_id)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        if ($vehicleConflict) {
            $plate = (string) ($vehicleConflict->vehicle?->placa ?? 'sin placa');
            $driverName = (string) ($vehicleConflict->driver?->nombre ?? 'otro conductor');

            $this->addError('vehicle_id', "El vehiculo {$plate} ya tiene una asignacion activa con {$driverName}.");
            session()->flash('error', 'No se puede registrar: el vehiculo ya esta asignado de forma activa.');
            $this->closeReassignConfirm();
            return false;
        }

        $driverConflict = (clone $activeAssignments)
            ->where('driver_id', $this->driver_id)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        if ($driverConflict) {
            $driverName = (string) ($driverConflict->driver?->nombre ?? 'conductor');
            $plate = (string) ($driverConflict->vehicle?->placa ?? 'sin placa');

            $this->addError('driver_id', "El conductor {$driverName} ya tiene una asignacion activa con el vehiculo {$plate}.");
            session()->flash('error', 'No se puede registrar: el conductor ya tiene un vehiculo activo asignado.');
            $this->closeReassignConfirm();
            return false;
        }

        return true;
    }

    private function prepareReassignConfirmation(): bool
    {
        $activeAssignments = VehicleAssignment::query()
            ->with(['driver', 'vehicle'])
            ->where('activo', true)
            ->whereNotNull('vehicle_id')
            ->whereNotNull('driver_id');

        $this->applyAssignmentOverlapFilter($activeAssignments, $this->fecha_inicio, $this->fecha_fin);

        $vehicleConflict = (clone $activeAssignments)
            ->where('vehicle_id', $this->vehicle_id)
            ->when($this->editingId, fn($q) => $q->where('id', '!=', $this->editingId))
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        $driverConflict = (clone $activeAssignments)
            ->where('driver_id', $this->driver_id)
            ->when($this->editingId, fn($q) => $q->where('id', '!=', $this->editingId))
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        if (!$vehicleConflict && !$driverConflict) {
            return false;
        }

        $this->showReassignConfirm = true;
        $this->conflictVehicleAssignmentId = $vehicleConflict?->id;
        $this->conflictVehicleDriverName = $vehicleConflict?->driver?->nombre;
        $this->conflictVehiclePlate = $vehicleConflict?->vehicle?->placa;
        $this->conflictDriverAssignmentId = $driverConflict?->id;
        $this->conflictDriverName = $driverConflict?->driver?->nombre;
        $this->conflictDriverVehiclePlate = $driverConflict?->vehicle?->placa;
        $this->dispatch('openVehicleReassignModal');

        return true;
    }

    private function activeAssignmentsIndexedBy(string $column)
    {
        if (!in_array($column, ['driver_id', 'vehicle_id'], true)) {
            return collect();
        }

        $assignmentDate = $this->resolveAssignmentDate();

        return VehicleAssignment::query()
            ->with(['driver', 'vehicle'])
            ->where('activo', true)
            ->whereNotNull('vehicle_id')
            ->whereNotNull('driver_id')
            ->when($this->editingId, fn($q) => $q->where('id', '!=', $this->editingId))
            ->where(function ($q) use ($assignmentDate) {
                $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $assignmentDate);
            })
            ->where(function ($q) use ($assignmentDate) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $assignmentDate);
            })
            ->get()
            ->keyBy($column);
    }

    private function applyAssignmentOverlapFilter($query, ?string $startDate, ?string $endDate): void
    {
        $newStart = $this->normalizeDateForQuery($startDate) ?? now()->toDateString();
        $newEnd = $this->normalizeDateForQuery($endDate);

        if ($newEnd) {
            $query->where(function ($q) use ($newEnd) {
                $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $newEnd);
            });
        }

        $query->where(function ($q) use ($newStart) {
            $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $newStart);
        });
    }

    private function normalizeDateForQuery(?string $date): ?string
    {
        if (!filled($date)) {
            return null;
        }

        try {
            return Carbon::parse((string) $date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function visibleAssignmentsQuery()
    {
        if (in_array($this->assignmentStatusFilter, ['inactive', 'all'], true)) {
            $query = VehicleAssignment::query()
                ->with(['driver', 'vehicle'])
                ->whereNotNull('vehicle_id')
                ->whereNotNull('driver_id');

            if ($this->assignmentStatusFilter === 'inactive') {
                $query->where('activo', false);
            }

            return $query;
        }

        return VehicleAssignment::query()
            ->with(['driver', 'vehicle'])
            ->where('activo', true) // Solo lo que esté marcado como activo
            ->whereNotNull('vehicle_id')
            ->whereNotNull('driver_id');
    }

    private function activeAssignmentsQuery()
    {
        return VehicleAssignment::query()
            ->with(['driver', 'vehicle'])
            ->where('activo', true)
            ->whereNotNull('vehicle_id')
            ->whereNotNull('driver_id');
    }

    private function resolveAssignmentDate(): string
    {
        try {
            return filled($this->fecha_inicio)
                ? Carbon::parse($this->fecha_inicio)->toDateString()
                : now()->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function buildReassignmentCancelledMessage(): string
    {
        $selectedDriver = Driver::find($this->driver_id);
        $selectedVehicle = Vehicle::find($this->vehicle_id);

        $driverName = trim((string) ($selectedDriver?->nombre ?? 'la persona seleccionada'));
        $vehiclePlate = trim((string) ($selectedVehicle?->placa ?? $this->conflictVehiclePlate ?? 'sin placa'));

        if ($this->conflictVehicleAssignmentId) {
            $currentDriverName = trim((string) ($this->conflictVehicleDriverName ?? 'otra persona'));

            return "No se asigno este vehiculo a {$driverName} porque el vehiculo {$vehiclePlate} ya estaba asignado a {$currentDriverName}.";
        }

        if ($this->conflictDriverAssignmentId) {
            $currentVehiclePlate = trim((string) ($this->conflictDriverVehiclePlate ?? 'otro vehiculo'));

            return "No se asigno este vehiculo a {$driverName} porque ya tiene asignado el vehiculo {$currentVehiclePlate}.";
        }

        return 'No se realizo la asignacion del vehiculo.';
    }

    private function syncExpiredAssignments(): void
    {
        VehicleAssignment::query()
            ->where('activo', true)
            ->where('tipo_asignacion', 'Temporal')
            ->whereNotNull('fecha_fin')
            ->whereDate('fecha_fin', '<', now()->toDateString())
            ->update(['activo' => false]);
    }

    private function paginateWithinBounds($query, int $perPage, string $pageName = 'page')
    {
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = (int) ($this->getPage($pageName) ?: 1);

        if ($currentPage > $lastPage) {
            $this->setPage($lastPage, $pageName);
        }

        return $query->paginate($perPage, ['*'], $pageName);
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
