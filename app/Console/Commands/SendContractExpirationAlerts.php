<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Services\ContractExpirationMailService;
use Illuminate\Console\Command;

class SendContractExpirationAlerts extends Command
{
    protected $signature = 'contracts:send-expiration-alerts {--force : Enviar aunque ya se haya realizado un envio hoy}';

    protected $description = 'Envia los dias 1 y 15 las alertas de contratos que vencen en los proximos 90 dias';

    public function handle(ContractExpirationMailService $mailService): int
    {
        if (! $mailService->automaticSendingEnabled()) {
            $this->info('El envio automatico de avisos de contratos esta desactivado.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && AppSetting::getValue(ContractExpirationMailService::LAST_SENT_SETTING) === now()->toDateString()) {
            $this->info('Los avisos de contratos ya fueron enviados hoy.');

            return self::SUCCESS;
        }

        $recipients = $mailService->recipients();
        if ($recipients === []) {
            $this->warn('No hay destinatarios configurados.');

            return self::SUCCESS;
        }

        $alerts = $mailService->upcomingAlerts();
        if ($alerts->isEmpty()) {
            $this->info('No hay contratos por vencer en los proximos 90 dias.');

            return self::SUCCESS;
        }

        $sent = $mailService->send($recipients, $alerts);
        $this->info("Avisos enviados: {$sent}. Contratos incluidos: {$alerts->count()}.");

        return self::SUCCESS;
    }
}
