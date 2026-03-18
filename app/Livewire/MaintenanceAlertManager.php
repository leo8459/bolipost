<?php

namespace App\Livewire;

use App\Models\MaintenanceAlert;
use App\Models\VehicleAssignment;
use Livewire\Component;
use Livewire\WithPagination;

class MaintenanceAlertManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterTipo = '';
    public string $filterEstado = 'activa';

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'recepcion', 'conductor']), 403);
    }

    public function render()
    {
        $query = MaintenanceAlert::query()
            ->with(['vehicle', 'maintenanceType', 'maintenanceAppointment'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $query = $this->applyVisibilityScope($query);

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('tipo', 'like', "%{$search}%")
                    ->orWhere('mensaje', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(kilometraje_actual AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(kilometraje_objetivo AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(faltante_km AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(created_at AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('placa', 'like', "%{$search}%"))
                    ->orWhereHas('maintenanceType', fn ($typeQuery) => $typeQuery->where('nombre', 'like', "%{$search}%"));
            });
        }

        if ($this->filterTipo !== '') {
            $query->where('tipo', $this->filterTipo);
        }

        if ($this->filterEstado === 'activa') {
            $query->where('status', MaintenanceAlert::STATUS_ACTIVE);
        } elseif ($this->filterEstado === 'resuelta') {
            $query->where('status', MaintenanceAlert::STATUS_RESOLVED);
        } elseif ($this->filterEstado === 'omitida') {
            $query->where('status', MaintenanceAlert::STATUS_OMITTED);
        }

        $pendingCountQuery = MaintenanceAlert::query();
        $pendingCountQuery = $this->applyVisibilityScope($pendingCountQuery);
        $pendingCount = $pendingCountQuery->where('status', MaintenanceAlert::STATUS_ACTIVE)->count();

        return view('livewire.maintenance-alert-manager', [
            'alerts' => $query->paginate(12),
            'pendingCount' => $pendingCount,
        ]);
    }

    public function updatedFilterTipo(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterEstado(): void
    {
        $this->resetPage();
    }

    public function markAsRead(int $alertId): void
    {
        $alert = MaintenanceAlert::find($alertId);
        if (!$alert) {
            return;
        }

        if (!$this->canAccessAlert($alert)) {
            return;
        }

        if ($alert->status !== MaintenanceAlert::STATUS_ACTIVE) {
            session()->flash('error', 'Solo puede marcar como leida una alerta activa.');
            return;
        }

        $alert->update(['leida' => true]);
        session()->flash('message', 'Alerta marcada como leida.');
    }

    public function markAsUnread(int $alertId): void
    {
        $alert = MaintenanceAlert::find($alertId);
        if (!$alert) {
            return;
        }

        if (!$this->canAccessAlert($alert)) {
            return;
        }

        if ($alert->status !== MaintenanceAlert::STATUS_ACTIVE) {
            session()->flash('error', 'Solo puede marcar como pendiente una alerta activa.');
            return;
        }

        $alert->update(['leida' => false]);
        session()->flash('message', 'Alerta marcada como pendiente.');
    }

    public function markAllAsRead(): void
    {
        $query = MaintenanceAlert::query()
            ->where('status', MaintenanceAlert::STATUS_ACTIVE)
            ->where('leida', false);

        if (auth()->user()?->role === 'conductor') {
            $driverId = (int) (auth()->user()?->resolvedDriver()?->id ?? 0);
            $vehicleIds = VehicleAssignment::query()
                ->where('driver_id', $driverId)
                ->pluck('vehicle_id')
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->toArray();

            if (empty($vehicleIds)) {
                session()->flash('error', 'No tiene vehiculos asignados.');
                return;
            }

            $query->whereIn('vehicle_id', $vehicleIds);
        }

        $query->update(['leida' => true]);
        session()->flash('message', 'Alertas pendientes marcadas como leidas.');
    }

    public function registerMaintenance(int $alertId)
    {
        $alert = MaintenanceAlert::find($alertId);
        if (!$alert) {
            return;
        }

        if (!$this->canAccessAlert($alert)) {
            return;
        }

        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para registrar mantenimientos.');
            return;
        }

        if ($alert->status !== MaintenanceAlert::STATUS_ACTIVE) {
            session()->flash('error', 'Solo se puede registrar mantenimiento desde alertas activas.');
            return;
        }

        if (!$alert->leida) {
            session()->flash('error', 'Primero debe marcar la alerta como leida para habilitar el registro de mantenimiento.');
            return;
        }

        return redirect()->route('livewire.maintenance-logs', [
            'from_alert_id' => $alert->id,
        ]);
    }

    public function resolveAlert(int $alertId): void
    {
        $alert = MaintenanceAlert::find($alertId);
        if (!$alert || !$this->canAccessAlert($alert)) {
            return;
        }

        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para resolver alertas.');
            return;
        }

        $alert->update([
            'status' => MaintenanceAlert::STATUS_RESOLVED,
            'leida' => true,
            'fecha_resolucion' => now(),
            'usuario_id' => auth()->id(),
        ]);

        session()->flash('message', 'Alerta resuelta.');
    }

    public function omitAlert(int $alertId): void
    {
        $alert = MaintenanceAlert::find($alertId);
        if (!$alert || !$this->canAccessAlert($alert)) {
            return;
        }

        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para omitir alertas.');
            return;
        }

        $alert->update([
            'status' => MaintenanceAlert::STATUS_OMITTED,
            'leida' => true,
            'fecha_resolucion' => now(),
            'usuario_id' => auth()->id(),
        ]);

        session()->flash('message', 'Alerta marcada como omitida.');
    }

    private function canAccessAlert(MaintenanceAlert $alert): bool
    {
        if (auth()->user()?->role !== 'conductor') {
            return true;
        }

        $driverId = (int) (auth()->user()?->resolvedDriver()?->id ?? 0);
        if (!$driverId) {
            return false;
        }

        return VehicleAssignment::query()
            ->where('driver_id', $driverId)
            ->where('vehicle_id', $alert->vehicle_id)
            ->exists();
    }

    private function applyVisibilityScope($query)
    {
        if (auth()->user()?->role !== 'conductor') {
            return $query;
        }

        $driverId = (int) (auth()->user()?->resolvedDriver()?->id ?? 0);
        if (!$driverId) {
            return $query->whereRaw('1=0');
        }

        $vehicleIds = VehicleAssignment::query()
            ->where('driver_id', $driverId)
            ->pluck('vehicle_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->toArray();

        if (empty($vehicleIds)) {
            return $query->whereRaw('1=0');
        }

        return $query->whereIn('vehicle_id', $vehicleIds);
    }

}
