<?php

namespace App\Livewire;

use App\Models\VehicleAssignment;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Validate;

class VehicleAssignmentManager extends Component
{
    use WithPagination;
    public bool $showForm = false;
    public string $search = '';

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

    public function mount(): void
    {
        abort_unless(in_array($this->currentUser()?->role, ['admin', 'recepcion']), 403);
        $this->fecha_inicio = now()->toDateString();
    }

    public function render()
    {
        $query = VehicleAssignment::with(['driver', 'vehicle'])
            ->orderBy('fecha_inicio', 'desc');

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('tipo_asignacion', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(fecha_inicio AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(fecha_fin AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(activo AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('driver', fn ($driverQuery) => $driverQuery->where('nombre', 'like', "%{$search}%"))
                    ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                        $vehicleQuery->where('placa', 'like', "%{$search}%")
                            ->orWhere('modelo', 'like', "%{$search}%");
                    });
            });
        }

        $assignments = $query->paginate(10);

        $activeAssignments = VehicleAssignment::query()
            ->where('activo', true)
            ->where(function ($q) {
                $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now()->toDateString());
            });

        $activeDriverIds = (clone $activeAssignments)->pluck('driver_id')->filter()->values();
        $activeVehicleIds = (clone $activeAssignments)->pluck('vehicle_id')->filter()->values();

        if ($this->isEdit && $this->editingId) {
            $currentAssignment = VehicleAssignment::find($this->editingId);
            if ($currentAssignment) {
                $activeDriverIds = $activeDriverIds->reject(fn ($id) => (int) $id === (int) $currentAssignment->driver_id)->values();
                $activeVehicleIds = $activeVehicleIds->reject(fn ($id) => (int) $id === (int) $currentAssignment->vehicle_id)->values();
            }
        }

        $drivers = Driver::where('activo', true)
            ->whereNotIn('id', $activeDriverIds->all())
            ->orderBy('nombre')
            ->get();

        $vehicles = Vehicle::where('activo', true)
            ->whereNotIn('id', $activeVehicleIds->all())
            ->orderBy('placa')
            ->get();
        
        return view('livewire.vehicle-assignment-manager', [
            'assignments' => $assignments,
            'drivers' => $drivers,
            'vehicles' => $vehicles,
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openForm()
    {
        $this->resetForm(); // Limpia datos previos
        $this->showForm = true;
    }

    public function save()
    {
        $this->validate();

        if ($this->activo) {
            $vehicleConflict = VehicleAssignment::query()
                ->where('vehicle_id', $this->vehicle_id)
                ->where('activo', true)
                ->where(function ($q) {
                    $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now()->toDateString());
                })
                ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                ->exists();

            if ($vehicleConflict) {
                $this->addError('vehicle_id', 'Este vehiculo ya tiene una asignacion activa.');
            }

            $driverConflict = VehicleAssignment::query()
                ->where('driver_id', $this->driver_id)
                ->where('activo', true)
                ->where(function ($q) {
                    $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now()->toDateString());
                })
                ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                ->exists();

            if ($driverConflict) {
                $this->addError('driver_id', 'Este conductor ya tiene una asignacion activa.');
            }

            if ($vehicleConflict || $driverConflict) {
                return;
            }
        }

        if ($this->isEdit && $this->editingId) {
            $assignment = VehicleAssignment::find($this->editingId);
            if ($assignment) {
                $assignment->update([
                    'driver_id' => $this->driver_id,
                    'vehicle_id' => $this->vehicle_id,
                    'tipo_asignacion' => $this->tipo_asignacion,
                    'fecha_inicio' => $this->fecha_inicio,
                    'fecha_fin' => $this->fecha_fin,
                    'activo' => $this->activo,
                ]);
                session()->flash('message', 'Asignación actualizada correctamente.');
            }
        } else {
            VehicleAssignment::create([
                'driver_id' => $this->driver_id,
                'vehicle_id' => $this->vehicle_id,
                'tipo_asignacion' => $this->tipo_asignacion,
                'fecha_inicio' => $this->fecha_inicio,
                'fecha_fin' => $this->fecha_fin,
                'activo' => $this->activo,
            ]);
            session()->flash('message', 'Asignación creada correctamente.');
        }

        $this->resetForm();
    }

    public function edit(VehicleAssignment $assignment)
    {
        $this->isEdit = true;
        $this->editingId = $assignment->id;
        $this->driver_id = $assignment->driver_id ?? 0;
        $this->vehicle_id = $assignment->vehicle_id ?? 0;
        $this->tipo_asignacion = $assignment->tipo_asignacion;
        $this->fecha_inicio = $assignment->fecha_inicio->toDateString();
        $this->fecha_fin = $assignment->fecha_fin?->toDateString();
        $this->activo = $assignment->activo;
        
        $this->showForm = true; // Mostramos el formulario al editar
    }

    public function delete(VehicleAssignment $assignment)
    {
        $assignment->delete();
        session()->flash('message', 'Asignación eliminada correctamente.');
    }

    public function resetForm()
    {
        $this->driver_id = 0;
        $this->vehicle_id = 0;
        $this->tipo_asignacion = 'Fijo';
        $this->fecha_inicio = now()->toDateString();
        $this->fecha_fin = null;
        $this->activo = true;
        $this->isEdit = false;
        $this->editingId = null;
        $this->showForm = false; // Ocultamos el formulario
        $this->resetPage();
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
