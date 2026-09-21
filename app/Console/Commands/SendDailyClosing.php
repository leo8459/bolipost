<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Services\ContractExpirationMailService;
use App\Services\DailyClosingMailService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendDailyClosing extends Command
{
    protected $signature = 'operations:send-daily-closing {--force : Reenviar el cierre de hoy}';

    protected $description = 'Envia los movimientos diarios de contratos y EMS';

    public function handle(DailyClosingMailService $service, ContractExpirationMailService $recipientsService): int
    {
        if (! $service->automaticSendingEnabled()) {
            $this->info('El cierre diario automatico esta desactivado.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && AppSetting::getValue(DailyClosingMailService::LAST_SENT_SETTING) === CarbonImmutable::now('America/La_Paz')->toDateString()) {
            $this->info('El cierre diario ya fue enviado hoy.');

            return self::SUCCESS;
        }
        $recipients = $recipientsService->recipients();
        if ($recipients === []) {
            $this->warn('No hay destinatarios configurados en Correo electronico.');

            return self::SUCCESS;
        }
        $sent = $service->send($recipients, automatic: true);
        $this->info("Cierre diario enviado a {$sent} destinatario(s).");

        return self::SUCCESS;
    }
}
