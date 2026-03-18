<?php

namespace App\Services;

use App\Models\MaintenanceAlert;
use App\Models\MaintenanceLog;
use App\Models\MaintenanceType;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Schema;

class MaintenanceAlertService
{
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
        $hasVehicleClass = Schema::hasColumn('maintenance_types', 'vehicle_class_id');
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
        if ($hasVehicleClass) {
            $select[] = 'vehicle_class_id';
        }

        $query = MaintenanceType::query();
        if ($hasVehicleClass) {
            $vehicleClassId = $vehicle->vehicle_class_id ?? null;
            if ($vehicleClassId) {
                $query->where(function ($q) use ($vehicleClassId) {
                    $q->whereNull('vehicle_class_id')
                        ->orWhere('vehicle_class_id', (int) $vehicleClassId);
                });
            } else {
                $query->whereNull('vehicle_class_id');
            }
        }

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
