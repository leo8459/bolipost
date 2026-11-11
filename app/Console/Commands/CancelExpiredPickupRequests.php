<?php

namespace App\Console\Commands;

use App\Models\Estado;
use App\Models\Evento;
use App\Models\Recojo;
use App\Support\AutomaticCancellationEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CancelExpiredPickupRequests extends Command
{
    protected $signature = 'contracts:cancel-expired-pickups {--dry-run : Muestra cuantos envios se cancelarian sin modificarlos}';

    protected $description = 'Cancela envios de contrato que siguen en SOLICITUD mas de 20 dias desde su creacion';

    public function handle(): int
    {
        $requestStateIds = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['SOLICITUD'])
            ->pluck('id');

        $cancelledStateId = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['CANCELADO'])
            ->value('id');

        if ($requestStateIds->isEmpty() || ! $cancelledStateId) {
            $this->error('Deben existir los estados SOLICITUD y CANCELADO. No se modificaron envios.');

            return self::FAILURE;
        }

        $query = Recojo::query()
            ->whereIn('estados_id', $requestStateIds)
            ->where('created_at', '<', now()->subDays(20));

        if ($this->option('dry-run')) {
            $this->info('Envios pendientes de cancelacion: '.$query->count());

            return self::SUCCESS;
        }

        $affected = 0;
        $query->chunkById(200, function ($candidates) use ($query, $cancelledStateId, &$affected): void {
            $affected += DB::transaction(function () use ($query, $candidates, $cancelledStateId): int {
                // Volver a comprobar el estado bajo bloqueo evita cancelar solicitudes ya procesadas.
                $packages = (clone $query)
                    ->whereKey($candidates->modelKeys())
                    ->lockForUpdate()
                    ->get(['id', 'codigo', 'user_id']);

                if ($packages->isEmpty()) {
                    return 0;
                }

                $event = Evento::query()->firstOrCreate([
                    'nombre_evento' => AutomaticCancellationEvent::INACTIVITY,
                ]);
                $now = now();
                $count = (clone $query)->whereKey($packages->modelKeys())->update([
                    'estados_id' => (int) $cancelledStateId,
                    'updated_at' => $now,
                ]);

                DB::table('eventos_contrato')->insert($packages->map(fn (Recojo $package): array => [
                    'codigo' => $package->codigo,
                    'evento_id' => $event->id,
                    'user_id' => $package->user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());

                return $count;
            });
        });
        $this->info("Envios cancelados automaticamente: {$affected}");

        return self::SUCCESS;
    }
}
