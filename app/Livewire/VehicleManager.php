<?php

namespace App\Livewire;

use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleClass;
use App\Models\VehicleAssignment;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceAppointment;
use App\Models\MaintenanceType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class VehicleManager extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $search = '';

    private const FUEL_TYPES = [
        'gasolina',
        'diesel',
        'gas',
        'hibrido',
        'electrico',
    ];

    private const MAINTENANCE_FORM_TYPES = [
        'vehiculo',
        'moto',
    ];

    #[Validate('required|string|max:20|unique:vehicles,placa')]
    public string $placa = '';

    #[Validate('required|integer|min:1|exists:vehicle_brands,id')]
    public int $marca_id = 0;

    #[Validate('required|string|max:50')]
    public string $modelo = '';

    public string $tipo_combustible = '';
    public string $maintenance_form_type = 'vehiculo';
    public string $color = '';
    public ?int $anio = null;
    public ?float $capacidad_tanque = null;
    public ?float $kilometraje = null;
    public bool $activo = true;
    public bool $tacometro_danado = false;

    public bool $isEdit = false;
    public ?int $editingVehicleId = null;
    public bool $showForm = false;
    public bool $showMaintenanceBackfillConfirm = false;
    public array $maintenanceBackfillPreview = [];
    public array $maintenanceBackfillSelections = [];
    public array $pendingVehiclePayload = [];

    public function mount(): void
    {
        abort_unless(in_array($this->currentUser()?->role, ['admin', 'recepcion', 'conductor']), 403);
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
                    ->orWhereRaw('CAST(tacometro_danado AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('brand', fn ($brandQuery) => $brandQuery->where('nombre', 'like', "%{$search}%"))
                    ->orWhereHas('vehicleClass', fn ($classQuery) => $classQuery->where('nombre', 'like', "%{$search}%"));
            });
        }
        $currentAssignment = null;
        $assignedVehicle = null;
        $scheduledMaintenances = collect();

        if ($this->currentUser()?->role === 'conductor') {
            $driverId = (int) ($this->currentUser()?->resolvedDriver()?->id ?? 0);
            if (!$driverId) {
                $query->whereRaw('1=0');
            } else {
                $currentAssignment = $this->resolveAssignmentForDriver($driverId);
                $assignedVehicle = $currentAssignment?->vehicle;
                $scheduledMaintenances = $this->resolveMaintenancesForDriverVehicle($assignedVehicle);

                $vehicleIds = VehicleAssignment::query()
                    ->where('driver_id', $driverId)
                    ->where('activo', true)
                    ->where(function ($q) {
                        $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', now()->toDateString());
                    })
                    ->where(function ($q) {
                        $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', now()->toDateString());
                    })
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
            'maintenanceFormTypes' => self::MAINTENANCE_FORM_TYPES,
            'currentAssignment' => $currentAssignment,
            'assignedVehicle' => $assignedVehicle,
            'scheduledMaintenances' => $scheduledMaintenances,
        ]);
    }

    public function updatedSearch(): void
    {
        $this->search = $this->sanitizeText($this->search, true);
        $this->resetPage();
    }

    public function updatedPlaca(string $value): void
    {
        $this->placa = mb_strtoupper($this->sanitizePlate($value));
    }

    public function updatedModelo(string $value): void
    {
        $this->modelo = $this->sanitizeText($value);
    }

    public function updatedColor(string $value): void
    {
        $this->color = $this->sanitizeText($value);
    }

    public function requestMaintenance(int $maintenanceTypeId): void
    {
        if ($this->currentUser()?->role !== 'conductor') {
            session()->flash('error', 'Solo el conductor puede solicitar mantenimiento desde esta vista.');
            return;
        }

        $driver = $this->currentUser()?->resolvedDriver();
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

        if ($assignment?->vehicle?->isInMaintenance()) {
            session()->flash('error', 'El vehiculo asignado esta en mantenimiento y no puede generar nuevas operaciones hasta volver a disponible.');
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

        $kmActual = $this->resolveVehicleCurrentKilometraje($vehicle);
        $requestedSnapshot = $this->buildRequestedAlertKilometrageSnapshot($vehicle, $type);

        MaintenanceAlert::create([
            'vehicle_id' => $vehicleId,
            'maintenance_type_id' => (int) $type->id,
            'maintenance_appointment_id' => null,
            'tipo' => 'Solicitud',
            'mensaje' => sprintf(
                'Solicitud de mantenimiento "%s" registrada por conductor %s para vehiculo %s.',
                (string) $type->nombre,
                (string) ($driver->nombre ?? $this->currentUser()?->name ?? 'N/A'),
                (string) ($vehicle?->placa ?? 'N/A')
            ),
            'leida' => false,
            'status' => MaintenanceAlert::STATUS_ACTIVE,
            'fecha_resolucion' => null,
            'usuario_id' => null,
            'kilometraje_actual' => $kmActual,
            'kilometraje_objetivo' => $requestedSnapshot['target_km'],
            'faltante_km' => $requestedSnapshot['remaining_km'],
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
        if ($this->currentUser()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para crear o editar vehiculos.');
            return;
        }

        $this->placa = mb_strtoupper($this->sanitizePlate($this->placa));
        $this->modelo = $this->sanitizeText($this->modelo);
        $this->color = $this->sanitizeText($this->color);

        $this->validate(
            [
                'placa' => [
                    'required',
                    'string',
                    'max:20',
                    'regex:/^[\pL\pN\s\-\/\.]+$/u',
                    Rule::unique('vehicles', 'placa')->ignore($this->editingVehicleId),
                ],
                'marca_id' => 'required|integer|min:1|exists:vehicle_brands,id',
                'modelo' => ['required', 'string', 'max:50', 'regex:/^[\pL\pN\s\-\/\.\(\)]+$/u'],
                'tipo_combustible' => ['required', 'string', Rule::in(self::FUEL_TYPES)],
                'maintenance_form_type' => ['required', 'string', Rule::in(self::MAINTENANCE_FORM_TYPES)],
                'color' => ['required', 'string', 'max:50', 'regex:/^[\pL\pN\s\-\/\.\(\)]+$/u'],
                'anio' => 'required|integer|min:1900|max:' . date('Y'),
                'capacidad_tanque' => 'required|numeric|min:3|max:150',
                'kilometraje' => 'required|numeric|min:5',
            ],
<<<<<<< HEAD
            'marca_id' => 'required|integer|min:1|exists:vehicle_brands,id',
            'modelo' => ['required', 'string', 'max:50', 'regex:/^[\pL\pN\s\-\/\.\(\)]+$/u'],
            'tipo_combustible' => ['required', 'string', Rule::in(self::FUEL_TYPES)],
            'maintenance_form_type' => ['required', 'string', Rule::in(self::MAINTENANCE_FORM_TYPES)],
            'color' => ['required', 'string', 'max:50', 'regex:/^[\pL\pN\s\-\/\.\(\)]+$/u'],
            'anio' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'capacidad_tanque' => 'required|numeric|min:0',
            'kilometraje' => 'required|numeric|min:0',
        ], [
            'placa.required' => 'La placa es obligatoria.',
            'placa.unique' => 'La placa ya esta registrada.',
            'placa.regex' => 'La placa contiene caracteres no permitidos.',
            'marca_id.required' => 'La marca es obligatoria.',
            'marca_id.exists' => 'La marca seleccionada no es valida.',
            'modelo.required' => 'El modelo es obligatorio.',
            'modelo.regex' => 'El modelo contiene caracteres no permitidos.',
            'tipo_combustible.required' => 'El tipo de combustible es obligatorio.',
            'tipo_combustible.in' => 'El tipo de combustible seleccionado no es valido.',
            'maintenance_form_type.required' => 'El tipo de vehiculo es obligatorio.',
            'maintenance_form_type.in' => 'El tipo de vehiculo seleccionado no es valido.',
            'color.required' => 'El color es obligatorio.',
            'color.regex' => 'El color contiene caracteres no permitidos.',
            'capacidad_tanque.required' => 'La capacidad del tanque es obligatoria.',
            'capacidad_tanque.numeric' => 'La capacidad del tanque debe ser numerica.',
            'capacidad_tanque.min' => 'La capacidad del tanque no puede ser negativa.',
            'kilometraje.required' => 'El kilometraje es obligatorio.',
            'kilometraje.numeric' => 'El kilometraje debe ser numerico.',
            'kilometraje.min' => 'El kilometraje no puede ser negativo.',
        ]);
=======
            [
                'placa.required' => 'La placa es obligatoria.',
                'placa.string' => 'La placa debe ser texto.',
                'placa.max' => 'La placa no debe superar :max caracteres.',
                'placa.regex' => 'La placa contiene caracteres no permitidos.',
                'placa.unique' => 'La placa ya esta registrada.',
                'marca_id.required' => 'La marca es obligatoria.',
                'marca_id.integer' => 'La marca seleccionada no es valida.',
                'marca_id.min' => 'Debe seleccionar una marca valida.',
                'marca_id.exists' => 'La marca seleccionada no existe.',
                'modelo.required' => 'El modelo es obligatorio.',
                'modelo.string' => 'El modelo debe ser texto.',
                'modelo.max' => 'El modelo no debe superar :max caracteres.',
                'modelo.regex' => 'El modelo contiene caracteres no permitidos.',
                'tipo_combustible.required' => 'El tipo de combustible es obligatorio.',
                'tipo_combustible.string' => 'El tipo de combustible debe ser texto.',
                'tipo_combustible.in' => 'El tipo de combustible seleccionado no es valido.',
                'maintenance_form_type.required' => 'El tipo de formulario es obligatorio.',
                'maintenance_form_type.string' => 'El tipo de formulario debe ser texto.',
                'maintenance_form_type.in' => 'El tipo de formulario seleccionado no es valido.',
                'color.required' => 'El color es obligatorio.',
                'color.string' => 'El color debe ser texto.',
                'color.max' => 'El color no debe superar :max caracteres.',
                'color.regex' => 'El color contiene caracteres no permitidos.',
                'anio.required' => 'El anio es obligatorio.',
                'anio.integer' => 'El anio debe ser un numero entero.',
                'anio.min' => 'El anio debe ser mayor o igual a :min.',
                'anio.max' => 'El anio no puede ser mayor al anio actual (:max).',
                'capacidad_tanque.required' => 'La capacidad del tanque es obligatoria.',
                'capacidad_tanque.numeric' => 'La capacidad del tanque debe ser un numero.',
                'capacidad_tanque.min' => 'La capacidad del tanque no puede ser menor a :min.',
                'capacidad_tanque.max' => 'La capacidad del tanque no puede ser mayor a :max.',
                'kilometraje.required' => 'El kilometraje es obligatorio.',
                'kilometraje.numeric' => 'El kilometraje debe ser un numero.',
                'kilometraje.min' => 'El kilometraje no puede ser menor a :min.',
            ]
        );
>>>>>>> 3709e5509fd3e61fa29bfd28ddcc74e4761897b0

        if (!$this->validateKilometrajeIntegrity()) {
            return;
        }

        $payload = [
            'placa' => $this->placa,
            'marca_id' => $this->marca_id,
            'vehicle_class_id' => $this->resolveVehicleClassId(),
            'maintenance_form_type' => $this->maintenance_form_type,
            'modelo' => $this->modelo,
            'tipo_combustible' => $this->tipo_combustible,
            'color' => $this->color,
            'anio' => $this->anio,
            'capacidad_tanque' => $this->capacidad_tanque,
            'kilometraje_actual' => $this->kilometraje,
            'kilometraje' => $this->kilometraje,
            'activo' => $this->activo,
            'tacometro_danado' => $this->tacometro_danado,
        ];

        if (!$this->isEdit && $this->shouldPromptMaintenanceBackfill($payload)) {
            return;
        }

        $this->persistVehicle($payload, false);
    }

    public function confirmMaintenanceBackfill(): void
    {
        if (empty($this->pendingVehiclePayload)) {
            $this->showMaintenanceBackfillConfirm = false;
            return;
        }

        $payload = $this->pendingVehiclePayload;
        $this->showMaintenanceBackfillConfirm = false;
        $this->pendingVehiclePayload = [];
        $this->persistVehicle($payload, true);
    }

    public function cancelMaintenanceBackfill(): void
    {
        $this->showMaintenanceBackfillConfirm = false;
        $this->pendingVehiclePayload = [];
        $this->maintenanceBackfillPreview = [];
        $this->maintenanceBackfillSelections = [];
    }

    private function persistVehicle(array $payload, bool $withMaintenanceBackfill): void
    {
        $createdVehicle = null;

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
            $payload['kilometraje_inicial'] = $this->tacometro_danado ? 0 : $this->kilometraje;
            if ($this->tacometro_danado) {
                $payload['kilometraje_actual'] = 0;
                $payload['kilometraje'] = 0;
            }
            $createdVehicle = Vehicle::create($payload);
            if ($withMaintenanceBackfill && $createdVehicle) {
                $this->createMaintenanceBackfillForVehicle($createdVehicle);
            }
            session()->flash(
                'message',
                $withMaintenanceBackfill && $createdVehicle
                    ? 'Vehiculo creado correctamente con mantenimientos pendientes generados segun su kilometraje.'
                    : 'Vehiculo creado correctamente.'
            );
        }

        $this->maintenanceBackfillPreview = [];
        $this->maintenanceBackfillSelections = [];
        $this->resetForm();
    }

    public function edit(Vehicle $vehicle)
    {
        if ($this->currentUser()?->role === 'conductor') {
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
        $this->maintenance_form_type = (string) ($vehicle->maintenance_form_type ?: 'vehiculo');
        $this->color = (string) ($vehicle->color ?? '');
        $this->anio = $vehicle->anio;
        $this->capacidad_tanque = $vehicle->capacidad_tanque !== null ? (float) $vehicle->capacidad_tanque : null;
        $this->kilometraje = $vehicle->kilometraje_actual !== null
            ? (float) $vehicle->kilometraje_actual
            : ($vehicle->kilometraje_inicial !== null ? (float) $vehicle->kilometraje_inicial : ($vehicle->kilometraje !== null ? (float) $vehicle->kilometraje : null));
        $this->activo = (bool) $vehicle->activo;
        $this->tacometro_danado = (bool) ($vehicle->tacometro_danado ?? false);
        $this->showMaintenanceBackfillConfirm = false;
        $this->maintenanceBackfillPreview = [];
        $this->maintenanceBackfillSelections = [];
        $this->pendingVehiclePayload = [];
    }

    public function delete(Vehicle $vehicle)
    {
        if ($this->currentUser()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para eliminar vehiculos.');
            return;
        }

        $vehicle->delete();

        $visibleCount = Vehicle::query()->count();
        $currentPage = $this->getPage();
        $lastPage = max(1, (int) ceil($visibleCount / 10));
        if ($currentPage > $lastPage) {
            $this->setPage($lastPage);
        }

        session()->flash('message', 'Vehiculo eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->placa = '';
        $this->marca_id = 0;
        $this->modelo = '';
        $this->tipo_combustible = '';
        $this->maintenance_form_type = 'vehiculo';
        $this->color = '';
        $this->anio = null;
        $this->capacidad_tanque = null;
        $this->kilometraje = null;
        $this->activo = true;
        $this->tacometro_danado = false;
        $this->isEdit = false;
        $this->editingVehicleId = null;
        $this->showForm = false;
        $this->showMaintenanceBackfillConfirm = false;
        $this->maintenanceBackfillPreview = [];
        $this->maintenanceBackfillSelections = [];
        $this->pendingVehiclePayload = [];
        $this->resetPage();
    }

    public function create()
    {
        if ($this->currentUser()?->role === 'conductor') {
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

        $modelo = trim(preg_replace('/\s+/', ' ', $this->modelo) ?? $this->modelo);
        $brandName = (string) (VehicleBrand::find($this->marca_id)?->nombre ?? '');
        $nombreClase = trim($brandName . ' ' . $this->anio . ' - ' . $modelo);

        $existing = VehicleClass::query()
            ->where('marca_id', $this->marca_id)
            ->whereRaw('trim(upper(modelo)) = ?', [mb_strtoupper($modelo)])
            ->where('anio', (int) $this->anio)
            ->first();

        if ($existing) {
            if (($existing->maintenance_form_type ?? null) !== $this->maintenance_form_type) {
                $existing->update([
                    'maintenance_form_type' => $this->maintenance_form_type,
                ]);
            }
            return (int) $existing->id;
        }

        $class = VehicleClass::create([
            'marca_id' => $this->marca_id,
            'modelo' => $modelo,
            'anio' => (int) $this->anio,
            'nombre' => $nombreClase !== '' ? $nombreClase : ($modelo . ' ' . $this->anio),
            'maintenance_form_type' => $this->maintenance_form_type,
            'activo' => true,
        ]);

        return (int) $class->id;
    }

    private function findMatchingVehicleClassId(): ?int
    {
        if ($this->marca_id <= 0 || trim($this->modelo) === '' || $this->anio === null) {
            return null;
        }

        $modelo = trim(preg_replace('/\s+/', ' ', $this->modelo) ?? $this->modelo);

        $existing = VehicleClass::query()
            ->where('marca_id', $this->marca_id)
            ->whereRaw('trim(upper(modelo)) = ?', [mb_strtoupper($modelo)])
            ->where('anio', (int) $this->anio)
            ->first();

        return $existing ? (int) $existing->id : null;
    }

    private function resolveMaintenancesForDriverVehicle(?Vehicle $vehicle)
    {
        if (!$vehicle) {
            return collect();
        }

        $activeAlertTypeIds = MaintenanceAlert::query()
            ->where('vehicle_id', (int) $vehicle->id)
            ->where('status', MaintenanceAlert::STATUS_ACTIVE)
            ->whereNotNull('maintenance_type_id')
            ->pluck('maintenance_type_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $scheduled = MaintenanceAppointment::query()
            ->with(['tipoMantenimiento:id,nombre,cada_km,vehicle_class_id'])
            ->where('vehicle_id', (int) $vehicle->id)
            ->whereNotIn('estado', ['Realizado', 'Cancelado', 'Rechazado'])
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
                    'can_request' => false,
                ];
            })
            ->filter();

        $classTypeQuery = MaintenanceType::query()
            ->select(['id', 'nombre', 'cada_km', 'vehicle_class_id'])
            ->applicableToVehicle($vehicle);

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
                'can_request' => !in_array((int) $type->id, $activeAlertTypeIds, true),
            ]);

        return $scheduled
            ->concat($baseTypes)
            ->unique('maintenance_type_id')
            ->values();
    }

    private function resolveVehicleCurrentKilometraje(?Vehicle $vehicle): ?float
    {
        if (!$vehicle) {
            return null;
        }

        $current = $vehicle->kilometraje_actual ?? $vehicle->kilometraje_inicial ?? $vehicle->kilometraje;
        return is_numeric($current) ? (float) $current : null;
    }

    /**
     * @return array{target_km: ?float, remaining_km: ?float}
     */
    private function buildRequestedAlertKilometrageSnapshot(?Vehicle $vehicle, MaintenanceType $type): array
    {
        $currentKm = $this->resolveVehicleCurrentKilometraje($vehicle);
        if ($currentKm === null) {
            return ['target_km' => null, 'remaining_km' => null];
        }

        $interval = null;
        if ($type->cada_km !== null) {
            $interval = (float) $type->cada_km;
        } elseif ($type->intervalo_km_init !== null) {
            $interval = (float) $type->intervalo_km_init;
        } elseif ($type->intervalo_km !== null) {
            $interval = (float) $type->intervalo_km;
        } elseif ($type->intervalo_km_fh !== null) {
            $interval = (float) $type->intervalo_km_fh;
        }

        if ($interval !== null && $interval > 0) {
            return [
                'target_km' => $currentKm + $interval,
                'remaining_km' => $interval,
            ];
        }

        return [
            'target_km' => $currentKm,
            'remaining_km' => 0.0,
        ];
    }

    private function validateKilometrajeIntegrity(): bool
    {
        if ($this->kilometraje === null || !$this->isEdit || !$this->editingVehicleId) {
            return true;
        }

        $vehicle = Vehicle::query()->find($this->editingVehicleId);
        if (!$vehicle) {
            return true;
        }

        $currentKm = $vehicle->kilometraje_actual ?? $vehicle->kilometraje_inicial ?? $vehicle->kilometraje;
        if ($currentKm === null) {
            return true;
        }

        if ((float) $this->kilometraje < (float) $currentKm) {
            $this->addError(
                'kilometraje',
                'El kilometraje no puede ser menor al kilometraje actual registrado del vehiculo ('
                . number_format((float) $currentKm, 2) . ').'
            );
            return false;
        }

        return true;
    }

    private function shouldPromptMaintenanceBackfill(array $payload): bool
    {
        if ($this->tacometro_danado || $this->kilometraje === null || (float) $this->kilometraje <= 0) {
            return false;
        }

        $vehicle = new Vehicle();
        $vehicle->forceFill([
            'placa' => $payload['placa'] ?? '',
            'marca_id' => $payload['marca_id'] ?? null,
            'vehicle_class_id' => $this->findMatchingVehicleClassId(),
            'maintenance_form_type' => $payload['maintenance_form_type'] ?? $this->maintenance_form_type,
            'kilometraje_actual' => (float) $this->kilometraje,
            'kilometraje_inicial' => (float) $this->kilometraje,
            'kilometraje' => (float) $this->kilometraje,
        ]);

        $preview = $this->buildMaintenanceBackfillPreview($vehicle);
        if (empty($preview)) {
            return false;
        }

        $this->maintenanceBackfillPreview = $preview;
        $this->maintenanceBackfillSelections = collect($preview)
            ->mapWithKeys(fn (array $item) => [(string) ($item['key'] ?? '') => true])
            ->all();
        $this->pendingVehiclePayload = $payload;
        $this->showMaintenanceBackfillConfirm = true;

        return true;
    }

    private function buildMaintenanceBackfillPreview(Vehicle $vehicle): array
    {
        $currentKm = $this->resolveVehicleCurrentKilometraje($vehicle);
        if ($currentKm === null || $currentKm <= 0) {
            return [];
        }

        $classId = $vehicle->vehicle_class_id ? (int) $vehicle->vehicle_class_id : null;
        $formType = trim((string) ($vehicle->maintenance_form_type ?: $this->maintenance_form_type));

        return MaintenanceType::query()
            ->when($formType !== '', function ($query) use ($formType) {
                $query->where(function ($typeQuery) use ($formType) {
                    $typeQuery->whereNull('maintenance_form_type')
                        ->orWhere('maintenance_form_type', $formType);
                });
            })
            ->when($classId, function ($query) use ($classId) {
                $query->where(function ($typeQuery) use ($classId) {
                    $typeQuery->whereNull('vehicle_class_id')
                        ->orWhere('vehicle_class_id', $classId);
                });
            })
            ->orderBy('nombre')
            ->get()
            ->flatMap(function (MaintenanceType $type) use ($currentKm) {
                $interval = $this->resolveMaintenanceIntervalKm($type);
                if ($interval === null || $interval <= 0 || $currentKm <= $interval) {
                    return [];
                }

                $cycles = (int) floor($currentKm / $interval);
                $cycles = min($cycles, 50);
                $items = [];

                for ($cycle = 1; $cycle <= $cycles; $cycle++) {
                    $targetKm = $interval * $cycle;
                    if ($targetKm >= $currentKm) {
                        break;
                    }

                    $items[] = [
                        'key' => (string) $type->id . '-' . (string) $targetKm,
                        'maintenance_type_id' => (int) $type->id,
                        'nombre' => (string) $type->nombre,
                        'interval_km' => $interval,
                        'target_km' => $targetKm,
                        'overdue_km' => max(0, round($currentKm - $targetKm, 2)),
                    ];
                }

                return $items;
            })
            ->values()
            ->all();
    }

    private function createMaintenanceBackfillForVehicle(Vehicle $vehicle): void
    {
        $preview = $this->buildMaintenanceBackfillPreview($vehicle);
        if (empty($preview)) {
            return;
        }

        $requestedByUserId = (int) ($this->currentUser()?->id ?? 0) ?: null;
        $driverId = VehicleAssignment::query()
            ->where('vehicle_id', (int) $vehicle->id)
            ->where('activo', true)
            ->latest('id')
            ->value('driver_id');

        foreach ($preview as $item) {
            $previewKey = (string) ($item['key'] ?? '');
            if ($previewKey === '' || empty($this->maintenanceBackfillSelections[$previewKey])) {
                continue;
            }

            $targetKm = (float) ($item['target_km'] ?? 0);
            $typeId = (int) ($item['maintenance_type_id'] ?? 0);
            if ($typeId <= 0 || $targetKm <= 0) {
                continue;
            }

            MaintenanceAppointment::query()->firstOrCreate(
                [
                    'vehicle_id' => (int) $vehicle->id,
                    'tipo_mantenimiento_id' => $typeId,
                    'fecha_programada' => Carbon::now()->startOfMinute(),
                    'origen_solicitud' => 'alta_vehiculo_km',
                ],
                [
                    'driver_id' => $driverId ? (int) $driverId : null,
                    'requested_by_user_id' => $requestedByUserId,
                    'solicitud_fecha' => Carbon::now(),
                    'es_accidente' => false,
                    'estado' => MaintenanceAppointment::STATUS_PENDING,
                ]
            );

            MaintenanceAlert::query()->firstOrCreate(
                [
                    'vehicle_id' => (int) $vehicle->id,
                    'maintenance_type_id' => $typeId,
                    'tipo' => 'Preventivo',
                    'kilometraje_objetivo' => $targetKm,
                    'status' => MaintenanceAlert::STATUS_ACTIVE,
                ],
                [
                    'mensaje' => sprintf(
                        'El vehiculo %s ingreso con kilometraje %.2f y ya tenia pendiente el mantenimiento "%s" programado para %.0f km.',
                        (string) ($vehicle->placa ?? 'N/A'),
                        (float) ($vehicle->kilometraje_actual ?? $vehicle->kilometraje_inicial ?? $vehicle->kilometraje ?? 0),
                        (string) ($item['nombre'] ?? 'Mantenimiento'),
                        $targetKm
                    ),
                    'leida' => false,
                    'fecha_resolucion' => null,
                    'usuario_id' => $requestedByUserId,
                    'kilometraje_actual' => $this->resolveVehicleCurrentKilometraje($vehicle),
                    'faltante_km' => -max(0, (float) ($item['overdue_km'] ?? 0)),
                ]
            );
        }
    }

    private function resolveMaintenanceIntervalKm(MaintenanceType $type): ?float
    {
        if ($type->cada_km !== null) {
            return (float) $type->cada_km;
        }
        if ($type->intervalo_km_init !== null) {
            return (float) $type->intervalo_km_init;
        }
        if ($type->intervalo_km !== null) {
            return (float) $type->intervalo_km;
        }
        if ($type->intervalo_km_fh !== null) {
            return (float) $type->intervalo_km_fh;
        }

        return null;
    }

    private function sanitizeText(?string $value, bool $allowSearchSymbols = false): string
    {
        $clean = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', (string) $value) ?? '';
        if (!$allowSearchSymbols) {
            $clean = preg_replace('/[^\pL\pN\s\-\/\.\(\)]/u', '', $clean) ?? $clean;
        }
        $clean = preg_replace('/\s+/', ' ', trim($clean)) ?? $clean;
        return $clean;
    }

    private function sanitizePlate(?string $value): string
    {
        $clean = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', (string) $value) ?? '';
        $clean = preg_replace('/[^\pL\pN\s\-\/\.]/u', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+/', ' ', trim($clean)) ?? $clean;
        return $clean;
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
