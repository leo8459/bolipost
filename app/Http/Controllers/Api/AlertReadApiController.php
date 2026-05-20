<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceAlertUserRead;
use App\Models\VehicleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AlertReadApiController extends Controller
{
    public function markRead(Request $request, MaintenanceAlert $alert): JsonResponse
    {
        $payload = $request->validate([
            'read' => 'required|boolean',
        ]);

        $user = $request->user();
        abort_unless($user, 401);

        if (!$this->canAccessAlert($user, $alert)) {
            return response()->json([
                'message' => 'No tiene permiso para acceder a esta alerta.',
            ], 403);
        }

        if (!Schema::hasTable('maintenance_alert_user_reads')) {
            return response()->json([
                'ok' => false,
                'message' => 'La sincronizacion de lectura de alertas aun no esta migrada en el servidor.',
                'alert_id' => (int) $alert->id,
                'read' => false,
                'read_at' => null,
            ], 503);
        }

        if ((bool) $payload['read']) {
            $read = MaintenanceAlertUserRead::query()->updateOrCreate(
                [
                    'maintenance_alert_id' => (int) $alert->id,
                    'user_id' => (int) $user->id,
                ],
                [
                    'read_at' => now(),
                ]
            );
        } else {
            MaintenanceAlertUserRead::query()
                ->where('maintenance_alert_id', (int) $alert->id)
                ->where('user_id', (int) $user->id)
                ->delete();

            $read = null;
        }

        return response()->json([
            'ok' => true,
            'alert_id' => (int) $alert->id,
            'read' => (bool) $payload['read'],
            'read_at' => $read?->read_at?->toIso8601String(),
        ]);
    }

    private function canAccessAlert($user, MaintenanceAlert $alert): bool
    {
        if ($user?->role !== 'conductor') {
            return true;
        }

        $driverId = (int) ($user->resolvedDriver()?->id ?? 0);
        if ($driverId <= 0) {
            return false;
        }

        return VehicleAssignment::query()
            ->where('driver_id', $driverId)
            ->where('vehicle_id', (int) $alert->vehicle_id)
            ->exists();
    }
}
