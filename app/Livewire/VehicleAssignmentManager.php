<?php

namespace App\Livewire;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class VehicleAssignmentManager extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public bool $showForm = false;
    public string $search = '';
    public string $plateFilter = '';
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

    public function mount(): void
    {
        abort_unless(in_array($this->currentUser()?->role, ['admin', 'recepcion']), 403);
        $this->fecha_inicio = now()->toDateString();
    }

    public function render()
    {
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

        $assignments = $query->paginate(10);
        $assignedDriverIds = $this->visibleAssignmentsQuery()
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

        $vehicles = Vehicle::query()
            ->where('activo', true)
            ->operationallyAvailable()
            ->orderBy('placa')
            ->get();

        if ($this->vehicle_id > 0 && !$vehicles->contains('id', $this->vehicle_id)) {
            $selectedVehicle = Vehicle::query()->find($this->vehicle_id);
            if ($selectedVehicle) {
                $vehicles->push($selectedVehicle);
            }
        }

        $unassignedDrivers = Driver::query()
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

    public function updatedShowUnassignedDrivers(): void
    {
        $this->resetPage();
    }

    public function openForm(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $vehicle = Vehicle::query()->find($this->vehicle_id);
        if (!$vehicle || $vehicle->isInMaintenance()) {
            $this->addError('vehicle_id', 'El vehiculo esta en mantenimiento y no puede asignarse en este momento.');
            return;
        }

        // Si ya confirmamos (skip es true), guardamos directo.
        if ($this->skipNextReassignCheck) {
            $this->skipNextReassignCheck = false; // Resetear para la próxima
            $this->persistAssignment();
            return;
        }

        // Si está activo, revisamos si hay conflictos
        if ($this->activo && $this->prepareReassignConfirmation()) {
            // Si hay conflicto, el método prepareReassignConfirmation ya disparó el modal
            return;
        }

        // Si no hay conflictos, guardamos normal
        $this->persistAssignment();
    }

    public function confirmReassignment(): void
    {
        $replacementEndDate = $this->resolveAssignmentDate();

        // 1. Desactivamos los conflictos existentes
        if ($this->conflictVehicleAssignmentId) {
            $assignment = VehicleAssignment::find($this->conflictVehicleAssignmentId);
            if ($assignment) {
                $assignment->update([
                    'activo' => false,
                    'fecha_fin' => $replacementEndDate,
                ]);
            }
        }

        if ($this->conflictDriverAssignmentId && $this->conflictDriverAssignmentId !== $this->conflictVehicleAssignmentId) {
            $assignment = VehicleAssignment::find($this->conflictDriverAssignmentId);
            if ($assignment) {
                $assignment->update([
                    'activo' => false,
                    'fecha_fin' => $replacementEndDate,
                ]);
            }
        }

        // 2. IMPORTANTE: En lugar de llamar a save(), llamamos directamente a persistAssignment()
        // Así evitamos que pase por la validación de conflictos OTRA VEZ.
        $this->closeReassignConfirm();
        $this->persistAssignment();
    }

    public function cancelReassignment(): void
    {
        session()->flash('message', $this->buildReassignmentCancelledMessage());
        $this->closeReassignConfirm();
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
        $assignment->delete();
        session()->flash('message', 'Asignacion eliminada correctamente.');
    }

    public function unassign(VehicleAssignment $assignment): void
    {
        if (!$assignment->activo) {
            session()->flash('message', 'La asignacion ya estaba inactiva.');
            return;
        }

        $assignment->update([
            'activo' => false,
            'fecha_fin' => now()->toDateString(),
        ]);

        session()->flash('message', 'Vehiculo desasignado del conductor correctamente.');
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
        $this->closeReassignConfirm();
        $this->showForm = false;
        $this->resetPage();
    }

    private function persistAssignment(): void
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
                session()->flash('message', 'Asignacion actualizada correctamente.');
            }
        } else {
            VehicleAssignment::create($payload);
            session()->flash('message', 'Asignacion creada correctamente.');
        }

        $this->resetForm();
    }

    private function prepareReassignConfirmation(): bool
    {
        $assignmentDate = $this->resolveAssignmentDate();

        $activeAssignments = VehicleAssignment::query()
            ->with(['driver', 'vehicle'])
            ->where('activo', true)
            ->whereNotNull('vehicle_id')
            ->whereNotNull('driver_id')
            ->where(function ($q) use ($assignmentDate) {
                $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $assignmentDate);
            })
            ->where(function ($q) use ($assignmentDate) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $assignmentDate);
            });

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

    private function visibleAssignmentsQuery()
    {
        $today = now()->toDateString();

        return VehicleAssignment::query()
            ->with(['driver', 'vehicle'])
            ->where('activo', true)
            ->whereNotNull('vehicle_id')
            ->whereNotNull('driver_id')
            ->where(function ($q) use ($today) {
                $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $today);
            });
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

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
