<?php

namespace App\Services;

use App\Models\MaintenanceAlert;
use App\Models\MaintenanceLog;
use App\Models\MaintenanceType;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Schema;

class MaintenanceAlertService
{
    public static function resolveVehicleLogBlockReason(?Vehicle $vehicle): ?string
    {
        if (!$vehicle) {
            return 'No se pudo resolver el vehiculo para registrar la bitacora.';
        }

        if ($vehicle->isInMaintenance()) {
            return sprintf(
                'El vehiculo %s esta en mantenimiento y no puede generar ni continuar bitacoras.',
                (string) ($vehicle->placa ?: 'sin placa')
            );
        }

        if (!Schema::hasTable('maintenance_alerts')) {
            return null;
        }

        $blockingAlert = MaintenanceAlert::query()
            ->with('maintenanceType:id,nombre')
            ->where('vehicle_id', (int) $vehicle->id)
            ->where('status', MaintenanceAlert::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('postponed_until')
                    ->orWhere('postponed_until', '<=', now());
            })
            ->where(function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->whereNotNull('faltante_km')
                        ->where('faltante_km', '<=', 0);
                })->orWhere(function ($subQuery) {
                    $subQuery->whereNotNull('kilometraje_actual')
                        ->whereNotNull('kilometraje_objetivo')
                        ->whereColumn('kilometraje_actual', '>=', 'kilometraje_objetivo');
                });
            })
            ->orderBy('kilometraje_objetivo')
            ->orderBy('id')
            ->first();

        if (!$blockingAlert) {
            return null;
        }

        $typeName = trim((string) ($blockingAlert->maintenanceType?->nombre ?? 'mantenimiento programado'));
        $plate = trim((string) ($vehicle->placa ?: 'sin placa'));

        return sprintf(
            'El vehiculo %s tiene que ser llevado a mantenimiento (%s) y no puede generar ni continuar bitacoras.',
            $plate,
            $typeName
        );
    }

    public static function evaluateVehicleByKilometraje(int $vehicleId): void
    {
        if (!Schema::hasTable('maintenance_alerts') || !Schema::hasTable('maintenance_types')) {
            return;
        }

        $vehicle = Vehicle::find($vehicleId);
        if (!$vehicle) {
            return;
        }

        $kmActual = $vehicle->kilometraje_actual ?? $vehicle->kilometraje_inicial ?? $vehicle->kilometraje;
        if ($kmActual === null) {
            return;
        }

        self::evaluateByMaintenanceTypeRanges($vehicle, (float) $kmActual);
        self::evaluateByMaintenanceLogs($vehicle, (float) $kmActual);
    }

    private static function evaluateByMaintenanceLogs(Vehicle $vehicle, float $kmActual): void
    {
        if (
            !Schema::hasTable('maintenance_logs') ||
            !Schema::hasColumn('maintenance_logs', 'maintenance_type_id') ||
            !Schema::hasColumn('maintenance_logs', 'proximo_kilometraje')
        ) {
            return;
        }

        $latestByType = MaintenanceLog::query()
            ->active()
            ->where('vehicle_id', (int) $vehicle->id)
            ->whereNotNull('maintenance_type_id')
            ->whereNotNull('proximo_kilometraje')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->unique('maintenance_type_id');

        foreach ($latestByType as $log) {
            $type = MaintenanceType::find($log->maintenance_type_id);
            if (!$type || $log->proximo_kilometraje === null) {
                continue;
            }

            $objetivo = (float) $log->proximo_kilometraje;
            $faltante = $objetivo - $kmActual;
            $kmAlerta = (int) ($type->km_alerta_previa ?? 15);

            if ($faltante > $kmAlerta) {
                continue;
            }

            if ($faltante >= 0) {
                $mensaje = sprintf(
                    'El vehiculo %s esta a %.0f km de su mantenimiento preventivo: %s.',
                    (string) $vehicle->placa,
                    $faltante,
                    (string) $type->nombre
                );
            } else {
                $mensaje = sprintf(
                    'El vehiculo %s tiene mantenimiento vencido: %s (excedido por %.0f km).',
                    (string) $vehicle->placa,
                    (string) $type->nombre,
                    abs($faltante)
                );
            }

            MaintenanceAlert::query()->updateOrCreate(
                [
                    'vehicle_id' => (int) $vehicle->id,
                    'maintenance_type_id' => (int) $type->id,
                    'tipo' => 'Preventivo',
                    'kilometraje_objetivo' => $objetivo,
                    'status' => MaintenanceAlert::STATUS_ACTIVE,
                ],
                [
                    'mensaje' => $mensaje,
                    'leida' => false,
                    'fecha_resolucion' => null,
                    'usuario_id' => null,
                    'kilometraje_actual' => $kmActual,
                    'faltante_km' => $faltante,
                ]
            );
        }
    }

    private static function evaluateByMaintenanceTypeRanges(Vehicle $vehicle, float $kmActual): void
    {
        $hasInit = Schema::hasColumn('maintenance_types', 'intervalo_km_init');
        $hasEnd = Schema::hasColumn('maintenance_types', 'intervalo_km_fh');
        $hasLegacy = Schema::hasColumn('maintenance_types', 'intervalo_km');
        $hasCadaKm = Schema::hasColumn('maintenance_types', 'cada_km');
        $hasAlert = Schema::hasColumn('maintenance_types', 'km_alerta_previa');

        if (!$hasInit && !$hasEnd && !$hasLegacy && !$hasCadaKm) {
            return;
        }

        $select = ['id', 'nombre'];
        if ($hasCadaKm) {
            $select[] = 'cada_km';
        }
        if ($hasInit) {
            $select[] = 'intervalo_km_init';
        }
        if ($hasEnd) {
            $select[] = 'intervalo_km_fh';
        }
        if ($hasLegacy) {
            $select[] = 'intervalo_km';
        }
        if ($hasAlert) {
            $select[] = 'km_alerta_previa';
        }
        if (Schema::hasColumn('maintenance_types', 'vehicle_class_id')) {
            $select[] = 'vehicle_class_id';
        }

        $query = MaintenanceType::query()->applicableToVehicle($vehicle);

        $types = $query->orderBy('id')->get($select);
        if ($types->isEmpty()) {
            return;
        }

        foreach ($types as $type) {
            // Si ya existe historial para este tipo y vehiculo, prevalece la evaluacion por mantenimiento real.
            if (
                Schema::hasTable('maintenance_logs') &&
                Schema::hasColumn('maintenance_logs', 'maintenance_type_id') &&
                MaintenanceLog::query()
                    ->active()
                    ->where('vehicle_id', (int) $vehicle->id)
                    ->where('maintenance_type_id', (int) $type->id)
                    ->exists()
            ) {
                continue;
            }

            $intervalo = $hasCadaKm && $type->cada_km !== null
                ? (float) $type->cada_km
                : ($hasInit && $type->intervalo_km_init !== null
                    ? (float) $type->intervalo_km_init
                    : ($hasLegacy && $type->intervalo_km !== null ? (float) $type->intervalo_km : null));

            if ($intervalo === null || $intervalo <= 0) {
                continue;
            }

            $kmAlerta = (int) ($hasAlert ? ($type->km_alerta_previa ?? 15) : 15);
            $objetivo = $intervalo;
            $faltante = $objetivo - $kmActual;
            if ($faltante > $kmAlerta) {
                continue;
            }

            if ($faltante >= 0) {
                $mensaje = sprintf(
                    'El vehiculo %s se aproxima al primer mantenimiento "%s" en %s km.',
                    (string) $vehicle->placa,
                    (string) $type->nombre,
                    number_format($objetivo, 0, '.', '')
                );
            } else {
                $mensaje = sprintf(
                    'El vehiculo %s tiene mantenimiento vencido "%s" (excedido por %.0f km).',
                    (string) $vehicle->placa,
                    (string) $type->nombre,
                    abs($faltante)
                );
            }

            MaintenanceAlert::query()->updateOrCreate(
                [
                    'vehicle_id' => (int) $vehicle->id,
                    'maintenance_type_id' => (int) $type->id,
                    'tipo' => 'Preventivo',
                    'kilometraje_objetivo' => $objetivo,
                    'status' => MaintenanceAlert::STATUS_ACTIVE,
                ],
                [
                    'mensaje' => $mensaje,
                    'leida' => false,
                    'fecha_resolucion' => null,
                    'usuario_id' => null,
                    'kilometraje_actual' => $kmActual,
                    'faltante_km' => $faltante,
                ]
            );
        }
    }
}
