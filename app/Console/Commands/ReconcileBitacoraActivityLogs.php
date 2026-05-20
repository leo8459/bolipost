<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\VehicleLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ReconcileBitacoraActivityLogs extends Command
{
    protected $signature = 'bitacora:reconcile-activity-logs';

    protected $description = 'Re-vincula activity_logs de bitacora con su vehicle_log correspondiente.';

    public function handle(): int
    {
        if (!Schema::hasColumn('activity_logs', 'vehicle_log_id')) {
            $this->warn('La columna activity_logs.vehicle_log_id aun no existe.');
            return self::SUCCESS;
        }

        $processed = 0;
        $linked = 0;

        ActivityLog::query()
            ->whereNull('vehicle_log_id')
            ->where(function ($query) {
                $query->where('model', 'vehicle_log')
                    ->orWhere('action', 'like', 'BITACORA_LOAD_%');
            })
            ->orderBy('id')
            ->chunkById(100, function ($logs) use (&$processed, &$linked) {
                foreach ($logs as $log) {
                    $processed++;
                    $vehicleLogId = $this->resolveVehicleLogId($log);
                    if (!$vehicleLogId) {
                        continue;
                    }

                    $log->update([
                        'vehicle_log_id' => $vehicleLogId,
                    ]);
                    $linked++;
                }
            });

        $this->info("Procesados: {$processed}. Re-vinculados: {$linked}.");

        return self::SUCCESS;
    }

    private function resolveVehicleLogId(ActivityLog $log): ?int
    {
        $changes = is_array($log->changes_json) ? $log->changes_json : [];

        $candidates = collect(data_get($changes, 'vehicle_log_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        foreach ($candidates as $candidateId) {
            if (VehicleLog::query()->whereKey($candidateId)->exists()) {
                return $candidateId;
            }
        }

        if ((string) $log->model === 'vehicle_log' && (int) $log->record_id > 0) {
            if (VehicleLog::query()->whereKey((int) $log->record_id)->exists()) {
                return (int) $log->record_id;
            }
        }

        $sessionReference = trim((string) data_get($changes, 'session_reference', ''));
        if ($sessionReference !== '') {
            $matched = VehicleLog::query()
                ->where('session_reference', $sessionReference)
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->value('id');

            if ($matched) {
                return (int) $matched;
            }
        }

        return null;
    }
}
