<?php

namespace App\Livewire;

use App\Models\MaintenanceAlert;
use App\Models\MaintenanceAlertUserRead;
use App\Models\MaintenanceAppointment;
use App\Models\MaintenanceType;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\Workshop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
    public ?string $dateFrom = null;
    public ?string $dateTo = null;

    public function mount(): void
    {
        $user = auth()->user();

        abort_unless(
            in_array($user?->role, ['admin', 'recepcion', 'conductor'], true)
                || (method_exists($user, 'can') && $user->can('livewire.maintenance-alerts')),
            403
        );
        $this->syncApprovedRequestsIntoAlerts();
        $this->reconcileInWorkshopAlertsWithoutOpenWorkshop();
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
        } elseif (in_array($this->filterEstado, ['resueltas', 'resuelta'], true)) {
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

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
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
        $this->applyWorkshopStateToPaginator($alerts);
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

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
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
            ->whereIn('status', MaintenanceAlert::openStatuses());

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
        session()->flash('message', 'Alertas pendientes marcadas como leidas.');
    }

    public function dispatchToWorkshop(int $alertId)
    {
        $result = DB::transaction(function () use ($alertId) {
            $alert = MaintenanceAlert::query()
                ->with(['vehicle', 'maintenanceType', 'maintenanceAppointment'])
                ->lockForUpdate()
                ->find($alertId);

            if (!$alert) {
                return ['type' => 'not_found'];
            }

            if (!$this->canAccessAlert($alert)) {
                return ['type' => 'forbidden'];
            }

            if (auth()->user()?->role === 'conductor') {
                return ['type' => 'error', 'message' => 'No tiene permiso para despachar vehiculos a taller.'];
            }

            if (!in_array($alert->status, [MaintenanceAlert::STATUS_ACTIVE, MaintenanceAlert::STATUS_REQUESTED], true)) {
                return ['type' => 'error', 'message' => 'Solo se puede despachar a taller desde alertas abiertas.'];
            }

            $existingWorkshop = Workshop::query()
                ->where('maintenance_alert_id', $alert->id)
                ->whereIn('estado', $this->openWorkshopStatuses())
                ->lockForUpdate()
                ->first();

            if ($existingWorkshop) {
                $alert->update([
                    'status' => MaintenanceAlert::STATUS_IN_WORKSHOP,
                    'leida' => true,
                ]);

                return ['type' => 'existing', 'workshop_id' => (int) $existingWorkshop->id];
            }

            return ['type' => 'new', 'alert_id' => (int) $alert->id];
        });

        if (($result['type'] ?? null) === 'error') {
            session()->flash('error', (string) ($result['message'] ?? 'No se pudo procesar la alerta.'));
            return;
        }
        if (($result['type'] ?? null) === 'existing') {
            return redirect()->route('livewire.workshops', [
                'edit_workshop_id' => (int) $result['workshop_id'],
            ]);
        }
        if (($result['type'] ?? null) === 'new') {
            return redirect()->route('livewire.workshops', [
                'from_alert_id' => (int) $result['alert_id'],
            ]);
        }
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

        $missingActiveAssignment = false;

        $workshop = DB::transaction(function () use ($alert, &$missingActiveAssignment) {
            $lockedAlert = MaintenanceAlert::query()
                ->with(['maintenanceType', 'maintenanceAppointment'])
                ->lockForUpdate()
                ->find($alert->id);

            if (!$lockedAlert) {
                return null;
            }

            $existingWorkshop = Workshop::query()
                ->where('maintenance_alert_id', $lockedAlert->id)
                ->whereIn('estado', [
                    Workshop::STATUS_PENDING,
                    Workshop::STATUS_DISPATCHED,
                    Workshop::STATUS_DIAGNOSIS,
                    Workshop::STATUS_APPROVED,
                    Workshop::STATUS_REPAIR,
                    Workshop::STATUS_READY,
                ])
                ->lockForUpdate()
                ->first();

            if ($existingWorkshop) {
                $lockedAlert->update([
                    'status' => MaintenanceAlert::STATUS_REQUESTED,
                    'leida' => true,
                ]);
                return $existingWorkshop;
            }

            $assignedDriverId = $this->resolveCurrentAssignmentDriverId((int) $lockedAlert->vehicle_id);
            if (!$assignedDriverId) {
                $missingActiveAssignment = true;
                return null;
            }

            $typeName = (string) ($lockedAlert->maintenanceType?->nombre ?? $lockedAlert->tipo);
            $createdWorkshop = Workshop::query()->create($this->filterWorkshopAttributes([
                'vehicle_id' => (int) $lockedAlert->vehicle_id,
                'driver_id' => (int) $assignedDriverId,
                'maintenance_appointment_id' => $lockedAlert->maintenance_appointment_id ? (int) $lockedAlert->maintenance_appointment_id : null,
                'maintenance_log_id' => null,
                'maintenance_alert_id' => (int) $lockedAlert->id,
                'workshop_catalog_id' => null,
                'nombre_taller' => 'Pendiente de asignacion',
                'fecha_ingreso' => now()->toDateString(),
                'attention_started_at' => null,
                'service_location' => null,
                'fecha_prometida_entrega' => null,
                'fecha_listo' => null,
                'fecha_salida' => null,
                'estado' => Workshop::STATUS_PENDING,
                'workflow_kind' => Workshop::FLOW_HEAVY,
                'approval_required' => true,
                'diagnosis_requested_at' => now(),
                'fixed_catalog_cost' => null,
                'labor_cost' => null,
                'additional_cost' => null,
                'total_cost' => null,
                'reassigned_from_workshop_catalog_id' => null,
                'rejection_reason' => '',
                'cancellation_reason' => '',
                'reception_photo_path' => null,
                'damage_photo_path' => null,
                'invoice_file_path' => null,
                'receipt_file_path' => null,
                'pre_entrada_estado' => "Solicitud abierta para diagnostico desde alerta activa de mantenimiento {$typeName}.",
                'observaciones_tecnicas' => '',
                'diagnostico' => '',
                'observaciones' => trim((string) $lockedAlert->mensaje),
                'activo' => true,
            ]));

            $lockedAlert->update([
                'status' => MaintenanceAlert::STATUS_REQUESTED,
                'leida' => true,
            ]);

            if (!$createdWorkshop->order_number) {
                $createdWorkshop->update([
                    'order_number' => sprintf('OT-%s-%04d', now()->format('Ymd'), (int) $createdWorkshop->id),
                ]);
            }

            return $createdWorkshop;
        });

        if ($missingActiveAssignment) {
            session()->flash('error', 'No se puede enviar a taller: el vehiculo no tiene una asignacion activa en vehicle-assignments.');
            return;
        }

        if (!$workshop) {
            session()->flash('error', 'No se pudo registrar la solicitud de diagnostico.');
            return;
        }

        session()->flash('message', 'Solicitud de diagnostico enviada. Quedo publicada para que cualquier taller la acepte, programe fecha y registre el costo.');
    }

    public function resolveAlert(int $alertId): void
    {
        $alert = MaintenanceAlert::query()->lockForUpdate()->find($alertId);
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
        $alert = MaintenanceAlert::query()->lockForUpdate()->find($alertId);
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
        $alert = MaintenanceAlert::query()->lockForUpdate()->find($alertId);
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

        return in_array((int) $alert->vehicle_id, $this->resolveCurrentDriverVehicleIds(), true);
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

        $vehicleIds = $this->resolveCurrentDriverVehicleIds();

        if (empty($vehicleIds)) {
            return $query->whereRaw('1=0');
        }

        return $query->whereIn('vehicle_id', $vehicleIds);
    }

    private function usesPerUserReadState(): bool
    {
        return true;
    }

    private function setAlertReadState(MaintenanceAlert $alert, bool $read): void
    {
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

    private function resolveCurrentDriverVehicleIds(): array
    {
        $driverId = (int) (auth()->user()?->resolvedDriver()?->id ?? 0);
        if ($driverId <= 0) {
            return [];
        }

        $today = now()->toDateString();

        return VehicleAssignment::query()
            ->where('driver_id', $driverId)
            ->where(function ($query) {
                $query->where('activo', true)->orWhereNull('activo');
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $today);
            })
            ->pluck('vehicle_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function resolveCurrentAssignmentDriverId(int $vehicleId): ?int
    {
        if ($vehicleId <= 0) {
            return null;
        }

        $today = now()->toDateString();

        $assignment = VehicleAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where(function ($query) {
                $query->where('activo', true)->orWhereNull('activo');
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $today);
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        return $assignment?->driver_id ? (int) $assignment->driver_id : null;
    }

    private function syncApprovedRequestsIntoAlerts(): void
    {
        if (auth()->user()?->role === 'conductor') {
            return;
        }

        if (!Schema::hasTable('maintenance_appointments') || !Schema::hasTable('maintenance_alerts')) {
            return;
        }

        $approvedAppointments = MaintenanceAppointment::query()
            ->active()
            ->with([
                'vehicle:id,placa,kilometraje_actual,kilometraje_inicial,kilometraje',
                'tipoMantenimiento:id,nombre,cada_km,intervalo_km,intervalo_km_init,intervalo_km_fh',
            ])
            ->where('estado', MaintenanceAppointment::STATUS_APPROVED)
            ->get();

        foreach ($approvedAppointments as $appointment) {
            $typeName = (string) ($appointment->tipoMantenimiento?->nombre ?? 'mantenimiento');
            $plate = (string) ($appointment->vehicle?->placa ?? 'N/A');
            $currentKm = $this->resolveVehicleCurrentKilometraje($appointment->vehicle);
            $requestedSnapshot = $this->buildRequestedAlertKilometrageSnapshot(
                $appointment->vehicle,
                $appointment->tipoMantenimiento
            );
            $existingAlert = MaintenanceAlert::query()
                ->with(['workshops' => function ($query) {
                    $query->where('activo', true)
                        ->whereIn('estado', [
                            Workshop::STATUS_PENDING,
                            Workshop::STATUS_DISPATCHED,
                            Workshop::STATUS_DIAGNOSIS,
                            Workshop::STATUS_APPROVED,
                            Workshop::STATUS_REPAIR,
                            Workshop::STATUS_READY,
                        ]);
                }])
                ->where('maintenance_appointment_id', (int) $appointment->id)
                ->where('tipo', 'Solicitud')
                ->first();

            $activeWorkshop = $existingAlert?->workshops?->sortByDesc('id')->first();
            $hasOpenWorkshop = $activeWorkshop !== null;
            $alertStatus = $hasOpenWorkshop
                ? MaintenanceAlert::STATUS_IN_WORKSHOP
                : ($existingAlert?->status === MaintenanceAlert::STATUS_IN_WORKSHOP
                    ? MaintenanceAlert::STATUS_IN_WORKSHOP
                    : MaintenanceAlert::STATUS_ACTIVE);
            $alertRead = $hasOpenWorkshop ? ((bool) ($existingAlert?->leida ?? true)) : ((bool) ($existingAlert?->leida ?? false));
            $defaultMessage = "Solicitud aprobada: {$typeName} para vehiculo {$plate}. Pendiente de acciones en taller.";
            $alertMessage = $hasOpenWorkshop
                ? trim((string) ($existingAlert?->mensaje ?? $defaultMessage))
                : $defaultMessage;

            MaintenanceAlert::query()->updateOrCreate(
                [
                    'maintenance_appointment_id' => (int) $appointment->id,
                    'tipo' => 'Solicitud',
                ],
                [
                    'vehicle_id' => (int) $appointment->vehicle_id,
                    'maintenance_type_id' => $appointment->tipo_mantenimiento_id,
                    'mensaje' => $alertMessage,
                    'leida' => $alertRead,
                    'status' => $alertStatus,
                    'fecha_resolucion' => null,
                    'usuario_id' => null,
                    'kilometraje_actual' => $currentKm,
                    'kilometraje_objetivo' => $requestedSnapshot['target_km'],
                    'faltante_km' => $requestedSnapshot['remaining_km'],
                ]
            );
        }
    }

    private function reconcileInWorkshopAlertsWithoutOpenWorkshop(): void
    {
        if (auth()->user()?->role === 'conductor') {
            return;
        }

        $openWorkshopStates = [
            Workshop::STATUS_PENDING,
            Workshop::STATUS_DISPATCHED,
            Workshop::STATUS_DIAGNOSIS,
            Workshop::STATUS_APPROVED,
            Workshop::STATUS_REPAIR,
            Workshop::STATUS_READY,
        ];

        $orphanInWorkshopAlerts = MaintenanceAlert::query()
            ->where('status', MaintenanceAlert::STATUS_IN_WORKSHOP)
            ->whereDoesntHave('workshops', function ($query) use ($openWorkshopStates) {
                $query->where('activo', true)->whereIn('estado', $openWorkshopStates);
            })
            ->get(['id', 'tipo']);

        foreach ($orphanInWorkshopAlerts as $alert) {
            $fallbackStatus = (string) $alert->tipo === 'Solicitud'
                ? MaintenanceAlert::STATUS_REQUESTED
                : MaintenanceAlert::STATUS_ACTIVE;

            $alert->update([
                'status' => $fallbackStatus,
                'leida' => false,
                'fecha_resolucion' => null,
                'usuario_id' => null,
            ]);
        }
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
    private function buildRequestedAlertKilometrageSnapshot(?Vehicle $vehicle, ?MaintenanceType $type): array
    {
        $currentKm = $this->resolveVehicleCurrentKilometraje($vehicle);
        if ($currentKm === null || !$type) {
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

    private function filterWorkshopAttributes(array $attributes): array
    {
        static $columns = null;

        if (!is_array($columns)) {
            $columns = Schema::hasTable('workshops')
                ? array_flip(Schema::getColumnListing('workshops'))
                : [];
        }

        return array_filter(
            $attributes,
            fn ($value, $key) => isset($columns[$key]),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function openWorkshopStatuses(): array
    {
        return [
            Workshop::STATUS_PENDING,
            Workshop::STATUS_DISPATCHED,
            Workshop::STATUS_DIAGNOSIS,
            Workshop::STATUS_APPROVED,
            Workshop::STATUS_REPAIR,
            Workshop::STATUS_READY,
        ];
    }

    private function applyWorkshopStateToPaginator(LengthAwarePaginator $alerts): void
    {
        $openStates = $this->openWorkshopStatuses();

        $alerts->setCollection(
            $alerts->getCollection()->map(function (MaintenanceAlert $alert) use ($openStates) {
                $activeWorkshop = $alert->workshops
                    ->filter(fn (Workshop $workshop) => (bool) $workshop->activo && in_array($workshop->estado, $openStates, true))
                    ->sortByDesc('id')
                    ->first();

                $alert->has_open_workshop = $activeWorkshop !== null;
                $alert->open_workshop_id = $activeWorkshop?->id;
                $alert->open_workshop_status = $activeWorkshop?->estado;

                return $alert;
            })
        );
    }

}
