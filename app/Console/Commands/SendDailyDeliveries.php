<?php

namespace App\Console\Commands;

use App\Services\DailyDeliveryMailService;
use App\Services\DailyDeliveryReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendDailyDeliveries extends Command
{
    protected $signature = 'operations:send-daily-deliveries';

    protected $description = 'Envía los reportes regionales y nacionales de entregas diarias de EMS y Contratos';

    public function handle(DailyDeliveryMailService $mail): int
    {
        if (! $mail->automaticSendingEnabled()) {
            $this->info('El envío automático del reporte de entregas está desactivado.');

            return self::SUCCESS;
        }

        $lock = Cache::store('database')->lock('operations:send-daily-deliveries', 3600);
        if (! $lock->get()) {
            return self::SUCCESS;
        }

        try {
            $now = CarbonImmutable::now(DailyDeliveryReport::TIMEZONE);
            $cutoff = $now->setTimeFromTimeString(DailyDeliveryMailService::SEND_AT);
            ['sent' => $sent, 'failed' => $failed] = $mail->sendReports($mail->recipients(), $now->min($cutoff), automatic: true);

            $this->info("Correos de entregas enviados: {$sent}. Fallidos: {$failed}.");

            return $failed === 0 ? self::SUCCESS : self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
