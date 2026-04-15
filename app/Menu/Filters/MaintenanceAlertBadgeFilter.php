<?php

namespace App\Menu\Filters;

use App\Models\MaintenanceAlert;
use App\Models\Workshop;
use App\Models\VehicleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use JeroenNoten\LaravelAdminLte\Menu\Filters\FilterInterface;

class MaintenanceAlertBadgeFilter implements FilterInterface
{
    public function transform($item)
    {
        if (!is_array($item)) {
            return $item;
        }

        $targetUrl = trim((string) ($item['url'] ?? ''));
        if ($targetUrl === 'livewire/maintenance-alerts') {
            $count = $this->resolveAlertCount();
            if ($count <= 0) {
                unset($item['label'], $item['label_color']);

                return $item;
            }

            $item['label'] = (string) $count;
            $item['label_color'] = 'danger';

            return $item;
        }

        if ($targetUrl === 'livewire/workshops') {
            $count = $this->resolveDeliveredWorkshopCount();
            if ($count <= 0) {
                if (($item['label_color'] ?? null) === 'success') {
                    unset($item['label'], $item['label_color']);
                }

                return $item;
            }

            $item['label'] = (string) $count;
            $item['label_color'] = 'success';
        }

        return $item;
    }

    private function resolveAlertCount(): int
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return 0;
        }

        $query = MaintenanceAlert::query()
            ->where('status', MaintenanceAlert::STATUS_ACTIVE);

        if (($user->role ?? null) === 'conductor') {
            $driverId = (int) ($user->resolvedDriver()?->id ?? 0);
            if ($driverId <= 0) {
                return 0;
            }

            $today = now()->toDateString();
            $vehicleIds = VehicleAssignment::query()
                ->where('driver_id', $driverId)
                ->where('activo', true)
                ->where(function ($q) use ($today) {
                    $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $today);
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $today);
                })
                ->pluck('vehicle_id')
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if (empty($vehicleIds)) {
                return 0;
            }

            $query->whereIn('vehicle_id', $vehicleIds);
        }

        return (int) $query->count();
    }

    private function resolveDeliveredWorkshopCount(): int
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return 0;
        }

        $query = Workshop::query()->where('estado', Workshop::STATUS_DELIVERED);

        if (($user->role ?? null) === 'taller') {
            $query->whereHas('workshopCatalog', fn ($catalogQuery) => $catalogQuery->where('user_id', $user->id));
        }

        return (int) $query->count();
    }
}
