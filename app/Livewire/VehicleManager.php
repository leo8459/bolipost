<?php

namespace App\Livewire;

use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleClass;
use App\Models\VehicleAssignment;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceAppointment;
use App\Models\MaintenanceType;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class VehicleManager extends Component
{
    use WithPagination;

    public string $search = '';

    private const FUEL_TYPES = [
        'gasolina',
        'diesel',
        'gas',
        'hibrido',
        'electrico',
    ];

    #[Validate('required|string|max:20|unique:vehicles,placa')]
    public string $placa = '';

    #[Validate('required|integer|min:1|exists:vehicle_brands,id')]
    public int $marca_id = 0;

    #[Validate('required|string|max:50')]
    public string $modelo = '';

    public string $tipo_combustible = '';
    public string $color = '';
    public ?int $anio = null;
    public ?float $capacidad_tanque = null;
    public ?float $kilometraje = null;
    public bool $activo = true;

    public bool $isEdit = false;
    public ?int $editingVehicleId = null;
    public bool $showForm = false;

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'recepcion', 'conductor']), 403);
    }

    public function render()
    {
        $query = Vehicle::with(['brand', 'vehicleClass'])
            ->withCount([
                'maintenanceAlerts as pending_maintenance_alerts_count' => fn ($q) => $q->where('status', MaintenanceAlert::STATUS_ACTIVE),
            ])
            ->orderBy('placa');

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('placa', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%")
                    ->orWhere('tipo_combustible', 'like', "%{$search}%")
                    ->orWhere('color', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(anio AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(activo AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('brand', fn ($brandQuery) => $brandQuery->where('nombre', 'like', "%{$search}%"))
                    ->orWhereHas('vehicleClass', fn ($classQuery) => $classQuery->where('nombre', 'like', "%{$search}%"));
            });
        }
        $currentAssignment = null;
        $assignedVehicle = null;
        $scheduledMaintenances = collect();

        if (auth()->user()?->role === 'conductor') {
            $driverId = (int) (auth()->user()?->resolvedDriver()?->id ?? 0);
            if (!$driverId) {
                $query->whereRaw('1=0');
            } else {
                $currentAssignment = $this->resolveAssignmentForDriver($driverId);
                $assignedVehicle = $currentAssignment?->vehicle;
                $scheduledMaintenances = $this->resolveMaintenancesForDriverVehicle($assignedVehicle);

                $vehicleIds = VehicleAssignment::query()
                    ->where('driver_id', $driverId)
                    ->pluck('vehicle_id')
                    ->unique()
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                if (empty($vehicleIds) && $assignedVehicle?->id) {
                    $vehicleIds = [(int) $assignedVehicle->id];
                }

                if (empty($vehicleIds)) {
                    $query->whereRaw('1=0');
                } else {
                    $query->whereIn('id', $vehicleIds);
                }
            }
        }

        $vehicles = $query->paginate(10);
        $brands = VehicleBrand::orderBy('nombre')->get(['id', 'nombre']);

        return view('livewire.vehicle-manager', [
            'vehicles' => $vehicles,
            'brands' => $brands,
            'fuelTypes' => self::FUEL_TYPES,
            'currentAssignment' => $currentAssignment,
            'assignedVehicle' => $assignedVehicle,
            'scheduledMaintenances' => $scheduledMaintenances,
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function requestMaintenance(int $maintenanceTypeId): void
    {
        if (auth()->user()?->role !== 'conductor') {
            session()->flash('error', 'Solo el conductor puede solicitar mantenimiento desde esta vista.');
            return;
        }

        $driver = auth()->user()?->resolvedDriver();
        if (!$driver) {
            session()->flash('error', 'No se encontro perfil de conductor asociado al usuario.');
            return;
        }

        $assignment = $this->resolveAssignmentForDriver((int) $driver->id);
        $vehicleId = (int) ($assignment?->vehicle_id ?? 0);
        if ($vehicleId <= 0) {
            session()->flash('error', 'No tiene vehiculo asignado.');
            return;
        }

        $type = MaintenanceType::find($maintenanceTypeId);
        if (!$type) {
            session()->flash('error', 'El tipo de mantenimiento no existe.');
            return;
        }
        $vehicle = $assignment?->vehicle;
        $vehicleClassId = $vehicle?->vehicle_class_id ? (int) $vehicle->vehicle_class_id : null;
        $typeClassId = $type->vehicle_class_id ? (int) $type->vehicle_class_id : null;
        if ($typeClassId !== null && $vehicleClassId !== $typeClassId) {
            session()->flash('error', 'El tipo de mantenimiento no corresponde a la clase del vehiculo asignado.');
            return;
        }

        $exists = MaintenanceAlert::query()
            ->where('vehicle_id', $vehicleId)
            ->where('maintenance_type_id', (int) $type->id)
            ->where('tipo', 'Solicitud')
            ->where('status', MaintenanceAlert::STATUS_ACTIVE)
            ->exists();
        if ($exists) {
            session()->flash('message', 'Ya existe una solicitud pendiente para este mantenimiento.');
            return;
        }

        $kmActual = $vehicle?->kilometraje_actual ?? $vehicle?->kilometraje_inicial ?? $vehicle?->kilometraje;

        MaintenanceAlert::create([
            'vehicle_id' => $vehicleId,
            'maintenance_type_id' => (int) $type->id,
            'maintenance_appointment_id' => null,
            'tipo' => 'Solicitud',
            'mensaje' => sprintf(
                'Solicitud de mantenimiento "%s" registrada por conductor %s para vehiculo %s.',
                (string) $type->nombre,
                (string) ($driver->nombre ?? auth()->user()?->name ?? 'N/A'),
                (string) ($vehicle?->placa ?? 'N/A')
            ),
            'leida' => false,
            'status' => MaintenanceAlert::STATUS_ACTIVE,
            'fecha_resolucion' => null,
            'usuario_id' => null,
            'kilometraje_actual' => $kmActual !== null ? (float) $kmActual : null,
            'kilometraje_objetivo' => null,
            'faltante_km' => null,
        ]);

        session()->flash('message', 'Solicitud de mantenimiento enviada correctamente.');
    }

    private function resolveAssignmentForDriver(int $driverId): ?VehicleAssignment
    {
        $today = now()->toDateString();
        $base = VehicleAssignment::query()
            ->with([
                'vehicle' => fn ($q) => $q
                    ->with(['brand', 'vehicleClass'])
                    ->withCount([
                        'maintenanceAlerts as pending_maintenance_alerts_count' => fn ($qa) => $qa->where('status', MaintenanceAlert::STATUS_ACTIVE),
                    ]),
            ])
            ->where('driver_id', $driverId)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id');

        $activeByDate = (clone $base)
            ->where('activo', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $today);
            })
            ->first();

        if ($activeByDate) {
            return $activeByDate;
        }

        $active = (clone $base)->where('activo', true)->first();
        if ($active) {
            return $active;
        }

        return (clone $base)->first();
    }

    public function save()
    {
        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para crear o editar vehiculos.');
            return;
        }

        $this->validate([
            'placa' => [
                'required',
                'string',
                'max:20',
                Rule::unique('vehicles', 'placa')->ignore($this->editingVehicleId),
            ],
            'marca_id' => 'required|integer|min:1|exists:vehicle_brands,id',
            'modelo' => 'required|string|max:50',
            'tipo_combustible' => ['required', 'string', Rule::in(self::FUEL_TYPES)],
            'anio' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'capacidad_tanque' => 'nullable|numeric|min:0',
            'kilometraje' => 'nullable|numeric|min:0',
        ]);

        $payload = [
            'placa' => $this->placa,
            'marca_id' => $this->marca_id,
            'vehicle_class_id' => $this->resolveVehicleClassId(),
            'modelo' => $this->modelo,
            'tipo_combustible' => $this->tipo_combustible,
            'color' => $this->color,
            'anio' => $this->anio,
            'capacidad_tanque' => $this->capacidad_tanque,
            'kilometraje_actual' => $this->kilometraje,
            'activo' => $this->activo,
        ];

        if ($this->isEdit && $this->editingVehicleId) {
            $vehicle = Vehicle::find($this->editingVehicleId);
            if ($vehicle) {
                if ($vehicle->kilometraje_inicial === null && $this->kilometraje !== null) {
                    $payload['kilometraje_inicial'] = $this->kilometraje;
                }
                $vehicle->update($payload);
                session()->flash('message', 'Vehiculo actualizado correctamente.');
            }
        } else {
            $payload['kilometraje_inicial'] = $this->kilometraje;
            Vehicle::create($payload);
            session()->flash('message', 'Vehiculo creado correctamente.');
        }

        $this->resetForm();
    }

    public function edit(Vehicle $vehicle)
    {
        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para editar vehiculos.');
            return;
        }

        $this->showForm = true;
        $this->isEdit = true;
        $this->editingVehicleId = $vehicle->id;
        $this->placa = $vehicle->placa;
        $this->marca_id = (int) ($vehicle->marca_id ?? 0);
        $this->modelo = $vehicle->modelo;
        $this->tipo_combustible = (string) ($vehicle->tipo_combustible ?? '');
        $this->color = (string) ($vehicle->color ?? '');
        $this->anio = $vehicle->anio;
        $this->capacidad_tanque = $vehicle->capacidad_tanque !== null ? (float) $vehicle->capacidad_tanque : null;
        $this->kilometraje = $vehicle->kilometraje_actual !== null
            ? (float) $vehicle->kilometraje_actual
            : ($vehicle->kilometraje_inicial !== null ? (float) $vehicle->kilometraje_inicial : ($vehicle->kilometraje !== null ? (float) $vehicle->kilometraje : null));
        $this->activo = (bool) $vehicle->activo;
    }

    public function delete(Vehicle $vehicle)
    {
        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para eliminar vehiculos.');
            return;
        }

        $vehicle->delete();
        session()->flash('message', 'Vehiculo eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->placa = '';
        $this->marca_id = 0;
        $this->modelo = '';
        $this->tipo_combustible = '';
        $this->color = '';
        $this->anio = null;
        $this->capacidad_tanque = null;
        $this->kilometraje = null;
        $this->activo = true;
        $this->isEdit = false;
        $this->editingVehicleId = null;
        $this->showForm = false;
        $this->resetPage();
    }

    public function create()
    {
        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para crear vehiculos.');
            return;
        }

        $this->resetForm();
        $this->showForm = true;
    }

    public function cancelForm()
    {
        $this->resetForm();
    }

    private function resolveVehicleClassId(): ?int
    {
        if ($this->marca_id <= 0 || trim($this->modelo) === '' || $this->anio === null) {
            return null;
        }

        $modelo = trim($this->modelo);
        $brandName = (string) (VehicleBrand::find($this->marca_id)?->nombre ?? '');
        $nombreClase = trim($brandName . ' ' . $this->anio . ' - ' . $modelo);

        $existing = VehicleClass::query()
            ->where('marca_id', $this->marca_id)
            ->where('modelo', $modelo)
            ->where('anio', (int) $this->anio)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $class = VehicleClass::create([
            'marca_id' => $this->marca_id,
            'modelo' => $modelo,
            'anio' => (int) $this->anio,
            'nombre' => $nombreClase !== '' ? $nombreClase : ($modelo . ' ' . $this->anio),
            'activo' => true,
        ]);

        return (int) $class->id;
    }

    private function resolveMaintenancesForDriverVehicle(?Vehicle $vehicle)
    {
        if (!$vehicle) {
            return collect();
        }

        $scheduled = MaintenanceAppointment::query()
            ->with(['tipoMantenimiento:id,nombre,cada_km,vehicle_class_id'])
            ->where('vehicle_id', (int) $vehicle->id)
            ->where('estado', '!=', 'Realizado')
            ->orderBy('fecha_programada')
            ->get()
            ->map(function ($appointment) {
                $type = $appointment->tipoMantenimiento;
                if (!$type) {
                    return null;
                }

                return [
                    'maintenance_type_id' => (int) $type->id,
                    'nombre' => (string) $type->nombre,
                    'cada_km' => $type->cada_km !== null ? (int) $type->cada_km : null,
                    'fecha_programada' => optional($appointment->fecha_programada)->format('d/m/Y H:i'),
                    'estado' => (string) $appointment->estado,
                    'fuente' => 'programado',
                ];
            })
            ->filter();

        $classTypeQuery = MaintenanceType::query()->select(['id', 'nombre', 'cada_km', 'vehicle_class_id']);
        $vehicleClassId = $vehicle->vehicle_class_id ? (int) $vehicle->vehicle_class_id : null;
        if ($vehicleClassId) {
            $classTypeQuery->where(function ($q) use ($vehicleClassId) {
                $q->whereNull('vehicle_class_id')
                    ->orWhere('vehicle_class_id', $vehicleClassId);
            });
        } else {
            $classTypeQuery->whereNull('vehicle_class_id');
        }

        $baseTypes = $classTypeQuery
            ->orderBy('nombre')
            ->get()
            ->map(fn ($type) => [
                'maintenance_type_id' => (int) $type->id,
                'nombre' => (string) $type->nombre,
                'cada_km' => $type->cada_km !== null ? (int) $type->cada_km : null,
                'fecha_programada' => null,
                'estado' => null,
                'fuente' => 'tipo',
            ]);

        return $scheduled
            ->concat($baseTypes)
            ->unique('maintenance_type_id')
            ->values();
    }
}
