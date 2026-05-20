<?php

namespace App\Console\Commands;

use App\Models\VehicleAssignment;
use Illuminate\Console\Command;

class SyncExpiredVehicleAssignments extends Command
{
    protected $signature = 'vehicle-assignments:sync-expired';

    protected $description = 'Inactiva asignaciones temporales que ya terminaron';

    public function handle(): int
    {
        $affected = VehicleAssignment::query()
            ->where('activo', true)
            ->where('tipo_asignacion', 'Temporal')
            ->whereNotNull('fecha_fin')
            ->whereDate('fecha_fin', '<', now()->toDateString())
            ->update(['activo' => false]);

        $this->info("Asignaciones actualizadas: {$affected}");

        return self::SUCCESS;
    }
}
