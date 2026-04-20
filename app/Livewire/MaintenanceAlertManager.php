<?php

namespace App\Livewire;

use App\Models\MaintenanceAlert;
use App\Models\MaintenanceAlertUserRead;
use App\Models\VehicleAssignment;
use App\Models\Workshop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;

class MaintenanceAlertManager extends Component
{
    use WithPagination;
    use WithoutUrlPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $search = '';
    public string $filterTipo = '';
    public string $filterEstado = 'abiertas';

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'recepcion', 'conductor']), 403);
    }

    public function render()
    {
        $query = MaintenanceAlert::query()
            ->with(['vehicle', 'maintenanceType', 'maintenanceAppointment', 'workshops'])
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

        if ($this->filterEstado === 'abiertas') {
            $query->whereIn('status', MaintenanceAlert::openStatuses());
        } elseif ($this->filterEstado === 'resueltas') {
            $query->where('status', MaintenanceAlert::STATUS_RESOLVED);
        } elseif ($this->filterEstado === 'solicitado') {
            $query->where('status', MaintenanceAlert::STATUS_REQUESTED);
        } elseif ($this->filterEstado === 'en_taller') {
            $query->where('status', MaintenanceAlert::STATUS_IN_WORKSHOP);
        } elseif ($this->filterEstado === 'pospuestas') {
            $query
                ->whereIn('status', [MaintenanceAlert::STATUS_ACTIVE, MaintenanceAlert::STATUS_REQUESTED])
                ->whereNotNull('postponed_until')
                ->where('postponed_until', '>', now());
        } elseif ($this->filterEstado === 'vencidas') {
            $query
                ->where('status', MaintenanceAlert::STATUS_ACTIVE)
                ->whereNotNull('faltante_km')
                ->where('faltante_km', '<', 0);
        }

        $pendingCountQuery = MaintenanceAlert::query();
        $pendingCountQuery = $this->applyVisibilityScope($pendingCountQuery);
        $pendingCountQuery->whereIn('status', MaintenanceAlert::openStatuses());
        if ($this->usesPerUserReadState()) {
            $pendingCountQuery->whereDoesntHave('userReads', function ($query) {
                $query->where('user_id', (int) auth()->id());
            });
        }
        $alerts = $this->paginateWithinBounds($query, 12);
        $this->applyReadStateToPaginator($alerts);
        $pendingCount = $pendingCountQuery->count();

        return view('livewire.maintenance-alert-manager', [
            'alerts' => $alerts,
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

        if (!in_array($alert->status, MaintenanceAlert::openStatuses(), true)) {
            session()->flash('error', 'Solo puede marcar como leida una alerta abierta.');
            return;
        }

        $this->setAlertReadState($alert, true);
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

        if (!in_array($alert->status, MaintenanceAlert::openStatuses(), true)) {
            session()->flash('error', 'Solo puede marcar como pendiente una alerta abierta.');
            return;
        }

        $this->setAlertReadState($alert, false);
        session()->flash('message', 'Alerta marcada como pendiente.');
    }

    public function markAllAsRead(): void
    {
        $query = MaintenanceAlert::query()
            ->whereIn('status', MaintenanceAlert::openStatuses())
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

        if ($this->usesPerUserReadState()) {
            $alertIds = $query->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($alertIds as $alertId) {
                MaintenanceAlertUserRead::query()->updateOrCreate(
                    [
                        'maintenance_alert_id' => $alertId,
                        'user_id' => (int) auth()->id(),
                    ],
                    [
                        'read_at' => now(),
                    ]
                );
            }
        } else {
            $query->update(['leida' => true]);
        }
        session()->flash('message', 'Alertas pendientes marcadas como leidas.');
    }

    public function dispatchToWorkshop(int $alertId)
    {
        $alert = MaintenanceAlert::query()->with(['vehicle', 'maintenanceType', 'maintenanceAppointment'])->find($alertId);
        if (!$alert) {
            return;
        }

        if (!$this->canAccessAlert($alert)) {
            return;
        }

        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para despachar vehiculos a taller.');
            return;
        }

        if (!in_array($alert->status, [MaintenanceAlert::STATUS_ACTIVE, MaintenanceAlert::STATUS_REQUESTED], true)) {
            session()->flash('error', 'Solo se puede despachar a taller desde alertas abiertas.');
            return;
        }

        $existingWorkshop = Workshop::query()
            ->where('maintenance_alert_id', $alert->id)
            ->whereIn('estado', [
                Workshop::STATUS_PENDING,
                Workshop::STATUS_DISPATCHED,
                Workshop::STATUS_DIAGNOSIS,
                Workshop::STATUS_APPROVED,
                Workshop::STATUS_REPAIR,
                Workshop::STATUS_READY,
            ])
            ->first();

        $alert->update([
            'status' => MaintenanceAlert::STATUS_IN_WORKSHOP,
            'leida' => true,
        ]);

        if ($existingWorkshop) {
            return redirect()->route('livewire.workshops', [
                'edit_workshop_id' => $existingWorkshop->id,
            ]);
        }

        return redirect()->route('livewire.workshops', [
            'from_alert_id' => $alert->id,
        ]);
    }

    public function requestDiagnosis(int $alertId)
    {
        $alert = MaintenanceAlert::query()->with(['vehicle', 'maintenanceType', 'maintenanceAppointment'])->find($alertId);
        if (!$alert) {
            return;
        }

        if (!$this->canAccessAlert($alert)) {
            return;
        }

        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para solicitar diagnostico a taller.');
            return;
        }

        if (!in_array($alert->status, [MaintenanceAlert::STATUS_ACTIVE, MaintenanceAlert::STATUS_REQUESTED], true)) {
            session()->flash('error', 'Solo se puede solicitar diagnostico desde alertas abiertas.');
            return;
        }

        $existingWorkshop = Workshop::query()
            ->where('maintenance_alert_id', $alert->id)
            ->whereIn('estado', [
                Workshop::STATUS_PENDING,
                Workshop::STATUS_DISPATCHED,
                Workshop::STATUS_DIAGNOSIS,
                Workshop::STATUS_APPROVED,
                Workshop::STATUS_REPAIR,
                Workshop::STATUS_READY,
            ])
            ->first();

        $alert->update([
            'status' => MaintenanceAlert::STATUS_IN_WORKSHOP,
            'leida' => true,
        ]);

        if ($existingWorkshop) {
            return redirect()->route('livewire.workshops', [
                'edit_workshop_id' => $existingWorkshop->id,
            ]);
        }

        return redirect()->route('livewire.workshops', [
            'from_alert_id' => $alert->id,
            'request_diagnosis' => 1,
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

    public function postponeAlert(int $alertId): void
    {
        $alert = MaintenanceAlert::find($alertId);
        if (!$alert || !$this->canAccessAlert($alert)) {
            return;
        }

        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'No tiene permiso para posponer alertas.');
            return;
        }

        if (!in_array($alert->status, [MaintenanceAlert::STATUS_ACTIVE, MaintenanceAlert::STATUS_REQUESTED], true)) {
            session()->flash('error', 'Solo se pueden posponer alertas preventivas o solicitadas.');
            return;
        }

        if ((bool) ($alert->postponed_once ?? false)) {
            session()->flash('error', 'Esta alerta ya fue pospuesta una vez y no puede volver a posponerse.');
            return;
        }

        $alert->update([
            'postponed_until' => now()->addDays(3),
            'postponed_once' => true,
            'leida' => true,
        ]);

        session()->flash('message', 'Alerta pospuesta por 3 dias.');
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

    private function usesPerUserReadState(): bool
    {
        return auth()->user()?->role === 'conductor';
    }

    private function setAlertReadState(MaintenanceAlert $alert, bool $read): void
    {
        if (!$this->usesPerUserReadState()) {
            $alert->update(['leida' => $read]);
            return;
        }

        $userId = (int) auth()->id();
        if ($read) {
            MaintenanceAlertUserRead::query()->updateOrCreate(
                [
                    'maintenance_alert_id' => (int) $alert->id,
                    'user_id' => $userId,
                ],
                [
                    'read_at' => now(),
                ]
            );

            return;
        }

        MaintenanceAlertUserRead::query()
            ->where('maintenance_alert_id', (int) $alert->id)
            ->where('user_id', $userId)
            ->delete();
    }

    private function applyReadStateToPaginator(LengthAwarePaginator $alerts): void
    {
        if (!$this->usesPerUserReadState()) {
            return;
        }

        $alertIds = $alerts->getCollection()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($alertIds)) {
            return;
        }

        $readIds = MaintenanceAlertUserRead::query()
            ->where('user_id', (int) auth()->id())
            ->whereIn('maintenance_alert_id', $alertIds)
            ->pluck('maintenance_alert_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $alerts->setCollection(
            $alerts->getCollection()->map(function (MaintenanceAlert $alert) use ($readIds) {
                $alert->leida = in_array((int) $alert->id, $readIds, true);
                return $alert;
            })
        );
    }

    private function paginateWithinBounds($query, int $perPage, string $pageName = 'page')
    {
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = max(1, (int) $this->getPage($pageName));

        if ($currentPage > $lastPage) {
            $this->setPage($lastPage, $pageName);
        }

        return $query->paginate($perPage, ['*'], $pageName);
    }

}
