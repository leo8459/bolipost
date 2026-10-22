<?php

namespace App\Console\Commands;

use App\Services\ClienteOnlineQrReconciliationService;
use Illuminate\Console\Command;

class SyncClienteOnlineQrPayments extends Command
{
    protected $signature = 'clientes:sync-qr';

    protected $description = 'Consulta QR de clientes en Facturación y concilia solicitudes vinculadas.';

    public function handle(ClienteOnlineQrReconciliationService $service): int
    {
        try {
            $counts = $service->syncPending();
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('No se pudo consultar la cola de QR en Facturación: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'QR revisados: %d; pagos conciliados: %d; solicitudes canceladas por vencimiento: %d; errores: %d.',
            $counts['checked'],
            $counts['paid'],
            $counts['cancelled'],
            $counts['failures'],
        ));

        return $counts['failures'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
