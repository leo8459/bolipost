<?php

namespace App\Services;

use App\Models\Estado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PackageCancellationService
{
    public function cancel(Model $package, string $stateColumn): bool
    {
        $cancelledStateId = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['CANCELADO'])
            ->value('id');

        if (! $cancelledStateId) {
            return false;
        }

        $package->forceFill([$stateColumn => (int) $cancelledStateId])->save();

        return true;
    }

    public function cancelAndRecordEvent(
        Model $package,
        string $stateColumn,
        string $eventTable,
        string $packageCode,
        int $eventId,
        int $userId,
        ?string $eventDetail = null
    ): bool {
        $packageCode = trim($packageCode);

        if ($packageCode === '' || $eventId <= 0 || $userId <= 0) {
            return false;
        }

        return DB::transaction(function () use (
            $package,
            $stateColumn,
            $eventTable,
            $packageCode,
            $eventId,
            $userId,
            $eventDetail
        ): bool {
            if (! $this->cancel($package, $stateColumn)) {
                return false;
            }

            $eventExists = DB::table($eventTable)
                ->where('codigo', $packageCode)
                ->where('evento_id', $eventId)
                ->exists();

            if (! $eventExists) {
                $payload = [
                    'codigo' => $packageCode,
                    'evento_id' => $eventId,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn($eventTable, 'detalle_evento')) {
                    $payload['detalle_evento'] = trim((string) $eventDetail) ?: null;
                }

                DB::table($eventTable)->insert($payload);
            }

            return true;
        });
    }
}
