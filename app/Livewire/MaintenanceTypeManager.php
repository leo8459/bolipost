<?php

namespace App\Livewire;

use App\Models\MaintenanceType;
use App\Models\VehicleClass;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Validate;

class MaintenanceTypeManager extends Component
{
    use WithPagination;
    public string $search = '';

    //public bool $isEdit = false;
    //public ?int $editingId = null;
    
    // 1. AÑADE ESTA VARIABLE
    public bool $showForm = false;

    #[Validate('required|string|max:255')]
    public string $nombre = '';
    public ?int $vehicle_class_id = null;

    #[Validate('nullable|integer|min:1')]
    public ?int $cada_km = null;

    #[Validate('nullable|integer|min:0')]
    public ?int $intervalo_km_init = null;

    #[Validate('nullable|integer|min:0')]
    public ?int $intervalo_km_fh = null;

    #[Validate('nullable|integer|min:0')]
    public ?int $km_alerta_previa = 15;

    #[Validate('nullable|string|max:500')]
    public ?string $descripcion = null;

    public bool $isEdit = false;
    public ?int $editingId = null;

    public function render()
    {
        $query = MaintenanceType::with('vehicleClass')->orderBy('nombre');

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('vehicleClass', fn ($classQuery) => $classQuery->where('nombre', 'like', "%{$search}%"));

                if (Schema::hasColumn('maintenance_types', 'cada_km')) {
                    $q->orWhereRaw('CAST(cada_km AS TEXT) ILIKE ?', ["%{$search}%"]);
                }
                if (Schema::hasColumn('maintenance_types', 'intervalo_km')) {
                    $q->orWhereRaw('CAST(intervalo_km AS TEXT) ILIKE ?', ["%{$search}%"]);
                }
                if (Schema::hasColumn('maintenance_types', 'intervalo_km_init')) {
                    $q->orWhereRaw('CAST(intervalo_km_init AS TEXT) ILIKE ?', ["%{$search}%"]);
                }
                if (Schema::hasColumn('maintenance_types', 'intervalo_km_fh')) {
                    $q->orWhereRaw('CAST(intervalo_km_fh AS TEXT) ILIKE ?', ["%{$search}%"]);
                }
                if (Schema::hasColumn('maintenance_types', 'km_alerta_previa')) {
                    $q->orWhereRaw('CAST(km_alerta_previa AS TEXT) ILIKE ?', ["%{$search}%"]);
                }
            });
        }

        $types = $query->paginate(10);
        $classes = VehicleClass::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);

        return view('livewire.maintenance-type-manager', [
            'types' => $types,
            'classes' => $classes,
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function save()
    {
        $this->validate();
        if ($this->vehicle_class_id !== null && $this->vehicle_class_id > 0) {
            $exists = VehicleClass::query()->whereKey((int) $this->vehicle_class_id)->exists();
            if (!$exists) {
                $this->addError('vehicle_class_id', 'La clase de vehiculo seleccionada no existe.');
                return;
            }
        } else {
            $this->vehicle_class_id = null;
        }

        if ($this->isEdit && $this->editingId) {
            $type = MaintenanceType::find($this->editingId);
            if ($type) {
                $type->update([
                    'nombre' => $this->nombre,
                    ...$this->resolveClassPayload(),
                    ...$this->resolveIntervalPayload(),
                    ...$this->resolveAlertPayload(),
                    'descripcion' => $this->descripcion,
                ]);
                session()->flash('message', 'Tipo de mantenimiento actualizado correctamente.');
            }
        } else {
            MaintenanceType::create([
                'nombre' => $this->nombre,
                ...$this->resolveClassPayload(),
                ...$this->resolveIntervalPayload(),
                ...$this->resolveAlertPayload(),
                'descripcion' => $this->descripcion,
            ]);
            session()->flash('message', 'Tipo de mantenimiento creado correctamente.');
        }

        $this->resetForm();
    }

    // 2. MODIFICA EL MÉTODO EDIT
    public function edit(MaintenanceType $type)
    {
        $this->showForm = true; // Mostramos el formulario al editar
        $this->isEdit = true;
        $this->editingId = $type->id;
        $this->nombre = $type->nombre;
        $this->vehicle_class_id = $type->vehicle_class_id ? (int) $type->vehicle_class_id : null;
        $this->cada_km = $type->cada_km !== null ? (int) $type->cada_km : null;
        $this->intervalo_km_init = $type->intervalo_km_init ?? $type->intervalo_km;
        $this->intervalo_km_fh = $type->intervalo_km_fh ?? $type->intervalo_km;
        $this->km_alerta_previa = $type->km_alerta_previa ?? 15;
        $this->descripcion = $type->descripcion;
    }

    // 3. AÑADE ESTA FUNCIÓN PARA EL BOTÓN "NUEVO"
    public function openForm()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    // 4. MODIFICA resetForm PARA CERRAR EL FORMULARIO
    public function resetForm()
    {
        $this->nombre = '';
        $this->vehicle_class_id = null;
        $this->cada_km = null;
        $this->intervalo_km_init = null;
        $this->intervalo_km_fh = null;
        $this->km_alerta_previa = 15;
        $this->descripcion = null;
        $this->isEdit = false;
        $this->editingId = null;
        $this->showForm = false; // Ocultamos el formulario
    }

    public function delete(MaintenanceType $type)
    {
        $type->delete();
        session()->flash('message', 'Tipo de mantenimiento eliminado correctamente.');
    }

    private function resolveIntervalPayload(): array
    {
        $cadaKm = $this->cada_km;
        if ($cadaKm === null || $cadaKm <= 0) {
            $cadaKm = $this->intervalo_km_init ?? $this->intervalo_km_fh;
        }

        $payload = [];
        if (Schema::hasColumn('maintenance_types', 'cada_km')) {
            $payload['cada_km'] = $cadaKm;
        }

        if (Schema::hasColumn('maintenance_types', 'intervalo_km_init')) {
            $payload['intervalo_km_init'] = $this->intervalo_km_init ?? $cadaKm;
        }
        if (Schema::hasColumn('maintenance_types', 'intervalo_km_fh')) {
            $payload['intervalo_km_fh'] = $this->intervalo_km_fh ?? $cadaKm;
        }
        if (Schema::hasColumn('maintenance_types', 'intervalo_km')) {
            $payload['intervalo_km'] = $cadaKm;
        }

        return $payload;
    }

    private function resolveClassPayload(): array
    {
        if (!Schema::hasColumn('maintenance_types', 'vehicle_class_id')) {
            return [];
        }

        return [
            'vehicle_class_id' => $this->vehicle_class_id,
        ];
    }

    private function resolveAlertPayload(): array
    {
        if (!Schema::hasColumn('maintenance_types', 'km_alerta_previa')) {
            return [];
        }

        return [
            'km_alerta_previa' => $this->km_alerta_previa ?? 15,
        ];
    }
}
