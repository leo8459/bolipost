<?php

namespace App\Livewire;

use App\Models\MaintenanceType;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Validate;

class MaintenanceTypeManager extends Component
{
    use WithPagination;
    public string $search = '';

    private const MAINTENANCE_FORM_TYPES = [
        'vehiculo',
        'moto',
    ];

    //public bool $isEdit = false;
    //public ?int $editingId = null;
    
    // 1. AÑADE ESTA VARIABLE
    public bool $showForm = false;

    #[Validate('required|string|max:255')]
    public string $nombre = '';
    public array $selected_vehicle_ids = [];
    public ?string $vehicle_to_add = null;
    public string $maintenance_form_type = 'vehiculo';
    public bool $es_preventivo = false;

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
        $query = MaintenanceType::with(['vehicles:id,placa'])->orderBy('nombre');

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('vehicles', fn ($vehicleQuery) => $vehicleQuery->where('placa', 'like', "%{$search}%"));

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
        $vehicles = $this->filteredVehicles();
        $selectedVehicles = Vehicle::query()
            ->whereIn('id', collect($this->selected_vehicle_ids)->map(fn ($id) => (int) $id)->all())
            ->orderBy('placa')
            ->get(['id', 'placa']);

        return view('livewire.maintenance-type-manager', [
            'types' => $types,
            'vehicles' => $vehicles,
            'selectedVehicles' => $selectedVehicles,
            'maintenanceFormTypes' => self::MAINTENANCE_FORM_TYPES,
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMaintenanceFormType(): void
    {
        $allowedVehicleIds = $this->filteredVehicles()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $this->selected_vehicle_ids = collect($this->selected_vehicle_ids)
            ->filter(fn ($id) => in_array((string) $id, $allowedVehicleIds, true))
            ->values()
            ->all();

        $this->vehicle_to_add = null;
    }

    public function addSelectedVehicle(): void
    {
        $vehicleId = (int) ($this->vehicle_to_add ?? 0);
        if ($vehicleId <= 0) {
            return;
        }

        $exists = Vehicle::query()->whereKey($vehicleId)->exists();
        if (!$exists) {
            $this->addError('vehicle_to_add', 'El vehiculo seleccionado no existe.');
            return;
        }

        $this->selected_vehicle_ids = collect($this->selected_vehicle_ids)
            ->push((string) $vehicleId)
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $this->resetErrorBag('vehicle_to_add');
        $this->vehicle_to_add = null;
    }

    public function addAllVisibleVehicles(): void
    {
        $visibleVehicleIds = $this->filteredVehicles()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $this->selected_vehicle_ids = collect($this->selected_vehicle_ids)
            ->merge($visibleVehicleIds)
            ->unique()
            ->values()
            ->all();
    }

    public function removeSelectedVehicle(int $vehicleId): void
    {
        $this->selected_vehicle_ids = collect($this->selected_vehicle_ids)
            ->reject(fn ($id) => (int) $id === $vehicleId)
            ->values()
            ->all();
    }

    public function clearSelectedVehicles(): void
    {
        $this->selected_vehicle_ids = [];
        $this->vehicle_to_add = null;
    }

    public function save()
    {
        $this->validate();
        if (!in_array($this->maintenance_form_type, self::MAINTENANCE_FORM_TYPES, true)) {
            $this->addError('maintenance_form_type', 'El tipo de vehiculo no es valido.');
            return;
        }

        $selectedVehicleIds = collect($this->selected_vehicle_ids)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedVehicleIds->isNotEmpty()) {
            $validVehicleCount = Vehicle::query()->whereIn('id', $selectedVehicleIds->all())->count();
            if ($validVehicleCount !== $selectedVehicleIds->count()) {
                $this->addError('selected_vehicle_ids', 'Uno o mas vehiculos seleccionados no existen.');
                return;
            }
        }

        if ($this->isEdit && $this->editingId) {
            $type = MaintenanceType::find($this->editingId);
            if ($type) {
                $type->update([
                    'nombre' => $this->nombre,
                    'maintenance_form_type' => $this->maintenance_form_type,
                    'es_preventivo' => $this->es_preventivo,
                    ...$this->resolveClassPayload(),
                    ...$this->resolveIntervalPayload(),
                    ...$this->resolveAlertPayload(),
                    'descripcion' => $this->descripcion,
                ]);
                $type->vehicles()->sync($selectedVehicleIds->all());
                session()->flash('message', 'Tipo de mantenimiento actualizado correctamente.');
            }
        } else {
            $type = MaintenanceType::create([
                'nombre' => $this->nombre,
                'maintenance_form_type' => $this->maintenance_form_type,
                'es_preventivo' => $this->es_preventivo,
                ...$this->resolveClassPayload(),
                ...$this->resolveIntervalPayload(),
                ...$this->resolveAlertPayload(),
                'descripcion' => $this->descripcion,
            ]);
            $type->vehicles()->sync($selectedVehicleIds->all());
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
        $this->selected_vehicle_ids = $type->vehicles()->pluck('vehicles.id')->map(fn ($id) => (string) $id)->all();
        $this->maintenance_form_type = (string) ($type->maintenance_form_type ?: 'vehiculo');
        $this->es_preventivo = (bool) ($type->es_preventivo ?? false);
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
        $this->selected_vehicle_ids = [];
        $this->vehicle_to_add = null;
        $this->maintenance_form_type = 'vehiculo';
        $this->es_preventivo = false;
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
        return [];
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

    private function filteredVehicles()
    {
        $selectedType = trim((string) $this->maintenance_form_type);

        return Vehicle::query()
            ->with('vehicleClass:id,maintenance_form_type')
            ->where('activo', true)
            ->where(function ($vehicleQuery) use ($selectedType) {
                $vehicleQuery
                    ->where('maintenance_form_type', $selectedType)
                    ->orWhere(function ($fallbackQuery) use ($selectedType) {
                        $fallbackQuery
                            ->whereNull('maintenance_form_type')
                            ->whereHas('vehicleClass', function ($classQuery) use ($selectedType) {
                                $classQuery->where('maintenance_form_type', $selectedType);
                            });
                    });
            })
            ->orderBy('placa')
            ->get(['id', 'placa', 'vehicle_class_id', 'maintenance_form_type']);
    }
}
