<?php

namespace App\Console\Commands;

use App\Services\DailyDeliveryMailService;
use App\Services\DailyDeliveryReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SendDailyDeliveries extends Command
{
    protected $signature = 'operations:send-daily-deliveries';

    protected $description = 'Envía el comparativo diario de EMS y Contratos por cartero';

    public function handle(DailyDeliveryMailService $mail, DailyDeliveryReport $reports): int
    {
        if (! $mail->automaticSendingEnabled()) {
            $this->info('El envío automático del reporte de entregas está desactivado.');

            return self::SUCCESS;
        }

        $lock = Cache::lock('operations:send-daily-deliveries', 3600);
        if (! $lock->get()) {
            return self::SUCCESS;
        }

        try {
            $now = CarbonImmutable::now(DailyDeliveryReport::TIMEZONE);
            $date = $now->toDateString();
            $sent = $mail->sentOn($date);
            $pending = array_diff($mail->recipients(), $sent);
            if ($pending === []) {
                $this->info('El reporte de entregas no tiene destinatarios pendientes.');

                return self::SUCCESS;
            }

            $cutoff = $now->setTimeFromTimeString(DailyDeliveryMailService::SEND_AT);
            $report = $reports->through($now->min($cutoff));
            $failed = 0;
            foreach ($pending as $recipient) {
                try {
                    $mail->send([$recipient], $report);
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;

                    continue;
                }

                $sent[] = $recipient;
                $mail->markSent($date, $sent);
            }

            $this->info('Reportes de entregas enviados: '.(count($pending) - $failed).'.');

            return $failed === 0 ? self::SUCCESS : self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
