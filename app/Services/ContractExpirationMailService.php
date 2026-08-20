<?php

namespace App\Services;

use App\Mail\ContractExpirationAlertMail;
use App\Models\AppSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class ContractExpirationMailService
{
    public const RECIPIENTS_SETTING = 'contracts.expiration_email_recipients';

    public const LAST_SENT_SETTING = 'contracts.expiration_email_last_sent';

    public const AUTOMATIC_SENDING_ENABLED_SETTING = 'contracts.expiration_email_automatic_sending_enabled';

    public function __construct(
        private readonly EmpresaContractUserSyncService $contractService,
    ) {}

    public function recipients(): array
    {
        $decoded = json_decode((string) AppSetting::getValue(self::RECIPIENTS_SETTING, '[]'), true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->unique()
            ->values()
            ->all();
    }

    public function saveRecipients(array $recipients): void
    {
        AppSetting::setValue(self::RECIPIENTS_SETTING, json_encode(array_values($recipients)));
    }

    public function automaticSendingEnabled(): bool
    {
        return AppSetting::getValue(self::AUTOMATIC_SENDING_ENABLED_SETTING, '1') === '1';
    }

    public function setAutomaticSendingEnabled(bool $enabled): void
    {
        AppSetting::setValue(self::AUTOMATIC_SENDING_ENABLED_SETTING, $enabled ? '1' : '0');
    }

    public function upcomingAlerts(): Collection
    {
        return $this->contractService->buildUpcomingExpirationAlerts();
    }

    public function send(array $recipients, ?Collection $alerts = null): int
    {
        $alerts ??= $this->upcomingAlerts();

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(new ContractExpirationAlertMail($alerts));
        }

        AppSetting::setValue(self::LAST_SENT_SETTING, now()->toDateString());

        return count($recipients);
    }
}
