<?php

namespace App\Console\Commands;

use App\Models\ClienteSolicitudQr;
use App\Services\ClienteSolicitudQrService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncClienteSolicitudQr extends Command
{
    protected $signature = 'clientes:sync-qr';

    protected $description = 'Verifica pagos QR y cancela solicitudes impagas después de 48 horas.';

    public function handle(ClienteSolicitudQrService $service): int
    {
        $failures = 0;
        ClienteSolicitudQr::where('status', 'pending')
            ->chunkById(100, function ($qrs) use ($service, &$failures): void {
                foreach ($qrs as $qr) {
                    try {
                        $service->sync($qr);
                    } catch (\Throwable $exception) {
                        $failures++;
                        Log::warning('Verificación QR pendiente de reintento.', ['qr_id' => $qr->id, 'error' => $exception->getMessage()]);
                    }
                }
            });
        $this->info('Verificación finalizada. Consultas pendientes de reintento: '.$failures);

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
