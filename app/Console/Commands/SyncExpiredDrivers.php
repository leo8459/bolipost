<?php

namespace App\Console\Commands;

use App\Models\Driver;
use Illuminate\Console\Command;

class SyncExpiredDrivers extends Command
{
    protected $signature = 'drivers:sync-expired';

    protected $description = 'Inactiva conductores con licencia vencida o que vence hoy';

    public function handle(): int
    {
        $affected = Driver::query()
            ->where('activo', true)
            ->whereDate('fecha_vencimiento_licencia', '<=', now()->toDateString())
            ->update(['activo' => false]);

        $this->info("Conductores actualizados: {$affected}");

        return self::SUCCESS;
    }
}
