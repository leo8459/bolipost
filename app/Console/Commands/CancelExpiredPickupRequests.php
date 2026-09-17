<?php

namespace App\Console\Commands;

use App\Models\Estado;
use App\Models\Recojo;
use Illuminate\Console\Command;

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

        // Una sola actualizacion conserva la condicion de estado al modificar cada envio.
        $affected = $query->update(['estados_id' => (int) $cancelledStateId]);
        $this->info("Envios cancelados automaticamente: {$affected}");

        return self::SUCCESS;
    }
}
