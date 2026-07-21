<?php

namespace App\Livewire;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\Workshop;
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
        $user = $this->currentUser();

        abort_unless(
            in_array($user?->role, ['admin', 'recepcion'], true)
                || (method_exists($user, 'can') && $user->can('livewire.vehicle-assignments')),
            403
        );
        $this->fecha_inicio = $this->formatForDateTimeInput(now());
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
            'reportVehicles' => Vehicle::query()->with('vehicleClass')->orderBy('placa')->get(),
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
                if (Carbon::parse((string) $this->fecha_fin)->lt(now())) {
                    $this->addError('fecha_fin', 'La fecha fin de una asignacion temporal no puede ser anterior a la hora actual.');
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
        $this->fecha_inicio = $this->formatForDateTimeInput($assignment->fecha_inicio);
        $this->fecha_fin = $this->formatForDateTimeInput($assignment->fecha_fin);
        $this->activo = (bool) $assignment->activo;
        $this->showForm = true;
    }

    public function delete(VehicleAssignment $assignment): void
    {
        if (!$this->isAdministrator()) {
            session()->flash('error', 'Solo los administradores pueden eliminar asignaciones.');
            return;
        }

        $vehicleId = $assignment->vehicle_id ? (int) $assignment->vehicle_id : null;
        if ($vehicleId && $this->hasOpenWorkshopForVehicle($vehicleId)) {
            session()->flash('error', 'No se puede inactivar la asignacion mientras el vehiculo tenga una orden de taller abierta.');
            return;
        }

        $assignment->update([
            'activo' => false,
            'fecha_fin' => $assignment->fecha_fin?->toDateTimeString() ?: now()->toDateTimeString(),
        ]);
        if ($vehicleId) {
            $this->syncOpenWorkshopsResponsibleDriverForVehicle($vehicleId);
        }

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
        $this->fecha_inicio = $this->formatForDateTimeInput(now());
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
        $vehicleId = $assignment->vehicle_id ? (int) $assignment->vehicle_id : null;
        if ($vehicleId && $this->hasOpenWorkshopForVehicle($vehicleId)) {
            session()->flash('error', "No se puede desasignar {$vehiclePlate} mientras tenga una orden de taller abierta.");
            return;
        }

        $assignment->update([
            'activo' => false,
            'fecha_fin' => now()->toDateTimeString(),
        ]);
        if ($vehicleId) {
            $this->syncOpenWorkshopsResponsibleDriverForVehicle($vehicleId);
        }

        session()->flash('message', "{$driverName} quedo sin vehiculo asignado. Se cerro logicamente la asignacion de {$vehiclePlate}.");
    }

    public function resetForm(): void
    {
        $this->driver_id = 0;
        $this->vehicle_id = 0;
        $this->tipo_asignacion = 'Fijo';
        $this->fecha_inicio = $this->formatForDateTimeInput(now());
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
        'fecha_inicio' => $this->normalizeDateTimeForStorage($this->fecha_inicio),
        'fecha_fin' => $this->normalizeDateTimeForStorage($this->fecha_fin),
        'activo' => $this->activo,
    ];

    $aborted = false;

    try {
        DB::transaction(function () use ($payload, $successMessage, &$aborted) {
            if ($this->activo) {
                $conflictQuery = VehicleAssignment::query()
                    ->where('activo', true)
                    ->whereNotNull('vehicle_id')
                    ->whereNotNull('driver_id')
                    ->where(function ($query) {
                        $query->where('vehicle_id', $this->vehicle_id)
                            ->orWhere('driver_id', $this->driver_id);
                    })
                    ->when(
                        $this->editingId,
                        fn ($query) => $query->where('id', '!=', $this->editingId)
                    );

                $this->applyAssignmentOverlapFilter(
                    $conflictQuery,
                    $this->fecha_inicio,
                    $this->fecha_fin
                );

                $conflict = $conflictQuery
                    ->lockForUpdate()
                    ->orderByDesc('fecha_inicio')
                    ->orderByDesc('id')
                    ->first();

                if ($conflict) {
                    if ((int) $conflict->vehicle_id === (int) $this->vehicle_id) {
                        $this->addError(
                            'vehicle_id',
                            'El vehículo ya tiene una asignación activa.'
                        );

                        session()->flash(
                            'error',
                            'No se pudo guardar: el vehículo ya fue asignado a otro conductor.'
                        );
                    } else {
                        $this->addError(
                            'driver_id',
                            'El conductor ya tiene una asignación activa.'
                        );

                        session()->flash(
                            'error',
                            'No se pudo guardar: el conductor ya fue asignado a otro vehículo.'
                        );
                    }

                    $aborted = true;

                    return;
                }
            }

            if ($this->isEdit && $this->editingId) {
                $assignment = VehicleAssignment::query()
                    ->lockForUpdate()
                    ->find($this->editingId);

                if (!$assignment) {
                    session()->flash(
                        'error',
                        'No se encontró la asignación que intenta actualizar.'
                    );

                    $aborted = true;

                    return;
                }

                $previousVehicleId = $assignment->vehicle_id
                    ? (int) $assignment->vehicle_id
                    : null;

                $identityChanged =
                    (int) ($assignment->driver_id ?? 0) !== (int) $payload['driver_id']
                    || (int) ($assignment->vehicle_id ?? 0) !== (int) $payload['vehicle_id'];

                if (
                    $payload['activo']
                    && ($identityChanged || $this->reactivatingAssignment)
                ) {
                    if (!$this->reactivatingAssignment) {
                        $assignment->update([
                            'activo' => false,
                            'fecha_fin' => $payload['fecha_inicio']
                                ?: now()->toDateTimeString(),
                        ]);
                    }

                    $newAssignment = VehicleAssignment::create($payload);

                    $affectedVehicleIds = collect([
                        $previousVehicleId,
                        $newAssignment->vehicle_id
                            ? (int) $newAssignment->vehicle_id
                            : null,
                    ])
                        ->filter()
                        ->unique()
                        ->values();

                    foreach ($affectedVehicleIds as $vehicleId) {
                        $this->syncOpenWorkshopsResponsibleDriverForVehicle(
                            (int) $vehicleId
                        );
                    }

                    session()->flash(
                        'message',
                        $successMessage
                            ?: 'Asignación histórica cerrada y nueva asignación creada correctamente.'
                    );

                    return;
                }

                $assignment->update($payload);

                $affectedVehicleIds = collect([
                    $previousVehicleId,
                    $assignment->vehicle_id
                        ? (int) $assignment->vehicle_id
                        : null,
                ])
                    ->filter()
                    ->unique()
                    ->values();

                foreach ($affectedVehicleIds as $vehicleId) {
                    $this->syncOpenWorkshopsResponsibleDriverForVehicle(
                        (int) $vehicleId
                    );
                }

                session()->flash(
                    'message',
                    $successMessage ?: 'Asignación actualizada correctamente.'
                );

                return;
            }

            $assignment = VehicleAssignment::create($payload);

            if ($assignment->vehicle_id) {
                $this->syncOpenWorkshopsResponsibleDriverForVehicle(
                    (int) $assignment->vehicle_id
                );
            }

            session()->flash(
                'message',
                $successMessage ?: 'Asignación creada correctamente.'
            );
        });
    } catch (\Throwable $exception) {
        report($exception);

        session()->flash(
            'error',
            'No se pudo guardar la asignación. Intente nuevamente.'
        );

        return;
    }

    if ($aborted) {
        return;
    }

    $this->resetForm();
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

        $assignmentDateTime = $this->resolveAssignmentDateTime();

        return VehicleAssignment::query()
            ->with(['driver', 'vehicle'])
            ->where('activo', true)
            ->whereNotNull('vehicle_id')
            ->whereNotNull('driver_id')
            ->when($this->editingId, fn($q) => $q->where('id', '!=', $this->editingId))
            ->where(function ($q) use ($assignmentDateTime) {
                $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $assignmentDateTime);
            })
            ->where(function ($q) use ($assignmentDateTime) {
                $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $assignmentDateTime);
            })
            ->get()
            ->keyBy($column);
    }

    private function applyAssignmentOverlapFilter($query, ?string $startDate, ?string $endDate): void
    {
        $newStart = $this->normalizeDateTimeForQuery($startDate) ?? now()->toDateTimeString();
        $newEnd = $this->normalizeDateTimeForQuery($endDate);

        if ($newEnd) {
            $query->where(function ($q) use ($newEnd) {
                $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $newEnd);
            });
        }

        $query->where(function ($q) use ($newStart) {
            $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $newStart);
        });
    }

    private function normalizeDateTimeForQuery(?string $date): ?string
    {
        if (!filled($date)) {
            return null;
        }

        try {
            return Carbon::parse((string) $date)->toDateTimeString();
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

    private function resolveAssignmentDateTime(): string
    {
        try {
            return filled($this->fecha_inicio)
                ? Carbon::parse($this->fecha_inicio)->toDateTimeString()
                : now()->toDateTimeString();
        } catch (\Throwable) {
            return now()->toDateTimeString();
        }
    }

    private function formatForDateTimeInput($value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return '';
        }
    }

    private function normalizeDateTimeForStorage(?string $value): ?string
    {
        if (!filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
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
        $expired = VehicleAssignment::query()
            ->where('activo', true)
            ->where('tipo_asignacion', 'Temporal')
            ->whereNotNull('fecha_fin')
            ->where('fecha_fin', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            return;
        }

        $expiredWithoutOpenWorkshop = $expired
            ->reject(function (VehicleAssignment $assignment) {
                $vehicleId = $assignment->vehicle_id ? (int) $assignment->vehicle_id : 0;
                return $vehicleId > 0 && $this->hasOpenWorkshopForVehicle($vehicleId);
            })
            ->values();

        if ($expiredWithoutOpenWorkshop->isEmpty()) {
            return;
        }

        $vehicleIds = $expiredWithoutOpenWorkshop
            ->map(fn (VehicleAssignment $assignment) => $assignment->vehicle_id ? (int) $assignment->vehicle_id : null)
            ->filter()
            ->unique()
            ->values();

        VehicleAssignment::query()
            ->whereIn('id', $expiredWithoutOpenWorkshop->pluck('id')->all())
            ->update(['activo' => false]);

        foreach ($vehicleIds as $vehicleId) {
            $this->syncOpenWorkshopsResponsibleDriverForVehicle((int) $vehicleId);
        }
    }

    private function hasOpenWorkshopForVehicle(int $vehicleId): bool
    {
        if ($vehicleId <= 0) {
            return false;
        }

        return Workshop::query()
            ->where('vehicle_id', $vehicleId)
            ->where('activo', true)
            ->whereIn('estado', [
                Workshop::STATUS_PENDING,
                Workshop::STATUS_DISPATCHED,
                Workshop::STATUS_DIAGNOSIS,
                Workshop::STATUS_APPROVED,
                Workshop::STATUS_REPAIR,
                Workshop::STATUS_READY,
            ])
            ->exists();
    }

    private function resolveCurrentAssignmentForVehicle(int $vehicleId): ?VehicleAssignment
    {
        if ($vehicleId <= 0) {
            return null;
        }

        return VehicleAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where(function ($query) {
                $query->where('activo', true)->orWhereNull('activo');
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get()
            ->first(function (VehicleAssignment $assignment) {
                $now = now()->toDateTimeString();
                $starts = $assignment->fecha_inicio?->toDateTimeString();
                $ends = $assignment->fecha_fin?->toDateTimeString();

                if ($starts && $starts > $now) {
                    return false;
                }

                if ($ends && $ends < $now) {
                    return false;
                }

                return true;
            });
    }

    private function syncOpenWorkshopsResponsibleDriverForVehicle(int $vehicleId): void
    {
        if ($vehicleId <= 0) {
            return;
        }

        $currentAssignment = $this->resolveCurrentAssignmentForVehicle($vehicleId);
        $driverId = $currentAssignment?->driver_id ? (int) $currentAssignment->driver_id : null;

        Workshop::query()
            ->where('vehicle_id', $vehicleId)
            ->where('activo', true)
            ->whereIn('estado', [
                Workshop::STATUS_PENDING,
                Workshop::STATUS_DISPATCHED,
                Workshop::STATUS_DIAGNOSIS,
                Workshop::STATUS_APPROVED,
                Workshop::STATUS_REPAIR,
                Workshop::STATUS_READY,
            ])
            ->update([
                'driver_id' => $driverId,
            ]);
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

    public function isAdministrator(): bool
    {
        $user = $this->currentUser();

        return $user !== null && (
            mb_strtolower(trim((string) ($user->role ?? ''))) === 'admin'
            || (method_exists($user, 'hasRole') && $user->hasRole('admin'))
        );
    }
}
