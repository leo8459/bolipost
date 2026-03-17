<?php

namespace App\Livewire;

use App\Models\MaintenanceAppointment;
use App\Models\MaintenanceAlert;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\MaintenanceType;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Validate;

class MaintenanceAppointmentManager extends Component
{
    use WithPagination;
    public bool $showForm = false; // Control de vista
    public string $search = '';

    

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

    #[Validate('nullable|string|max:500')]
    public ?string $descripcion_problema = null;

    #[Validate('required|string|in:Pendiente,Realizado,Cancelado')]
    public string $estado = 'Pendiente';

    public bool $isEdit = false;
    public ?int $editingId = null;

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'recepcion']), 403);
    }

    public function openForm()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function render()
    {
        $query = MaintenanceAppointment::with(['vehicle', 'driver', 'tipoMantenimiento'])
            ->orderBy('fecha_programada', 'desc');

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('estado', 'like', "%{$search}%")
                    ->orWhere('descripcion_problema', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(fecha_programada AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(es_accidente AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('placa', 'like', "%{$search}%"))
                    ->orWhereHas('driver', fn ($driverQuery) => $driverQuery->where('nombre', 'like', "%{$search}%"))
                    ->orWhereHas('tipoMantenimiento', fn ($typeQuery) => $typeQuery->where('nombre', 'like', "%{$search}%"));
            });
        }

        $appointments = $query->paginate(10);
        
        $vehicles = Vehicle::where('activo', true)->orderBy('placa')->get();
        $drivers = Driver::where('activo', true)->orderBy('nombre')->get();
        $types = MaintenanceType::orderBy('nombre')->get();
        
        return view('livewire.maintenance-appointment-manager', [
            'appointments' => $appointments,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'types' => $types,
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function save()
    {
        $this->validate();

        if ($this->isEdit && $this->editingId) {
            $appointment = MaintenanceAppointment::find($this->editingId);
            if ($appointment) {
                $appointment->update([
                    'vehicle_id' => $this->vehicle_id,
                    'driver_id' => $this->driver_id,
                    'tipo_mantenimiento_id' => $this->tipo_mantenimiento_id,
                    'fecha_programada' => $this->fecha_programada,
                    'es_accidente' => $this->es_accidente,
                    'descripcion_problema' => $this->descripcion_problema,
                    'estado' => $this->estado,
                ]);
                $this->syncProgrammedAlert($appointment);
                session()->flash('message', 'Cita de mantenimiento actualizada correctamente.');
            }
        } else {
            $appointment = MaintenanceAppointment::create([
                'vehicle_id' => $this->vehicle_id,
                'driver_id' => $this->driver_id,
                'tipo_mantenimiento_id' => $this->tipo_mantenimiento_id,
                'fecha_programada' => $this->fecha_programada,
                'es_accidente' => $this->es_accidente,
                'descripcion_problema' => $this->descripcion_problema,
                'estado' => $this->estado,
            ]);
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
        $this->fecha_programada = $appointment->fecha_programada->format('Y-m-d\TH:i');
        $this->es_accidente = $appointment->es_accidente;
        $this->descripcion_problema = $appointment->descripcion_problema;
        $this->estado = $appointment->estado;
        
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
        }

        $appointment->delete();
        session()->flash('message', 'Cita de mantenimiento eliminada correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'vehicle_id', 'driver_id', 'tipo_mantenimiento_id', 
            'fecha_programada', 'es_accidente', 'descripcion_problema', 
            'estado', 'isEdit', 'editingId'
        ]);
        $this->vehicle_id = 0;
        $this->estado = 'Pendiente';
        $this->showForm = false; // Volver a la tabla
        $this->resetPage();
    }

    private function syncProgrammedAlert(MaintenanceAppointment $appointment): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('maintenance_alerts')) {
            return;
        }

        if (!$appointment->es_accidente || $appointment->estado !== 'Pendiente') {
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
}
