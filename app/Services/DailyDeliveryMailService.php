<?php

namespace App\Services;

use App\Mail\DailyDeliveryMail;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Mail;

class DailyDeliveryMailService
{
    public const SEND_AT = '20:00';

    public const RECIPIENTS_SETTING = 'operations.daily_deliveries_email_recipients';

    public const ENABLED_SETTING = 'operations.daily_deliveries_enabled';

    public const SENT_SETTING = 'operations.daily_deliveries_sent';

    public function recipients(): array
    {
        $emails = json_decode((string) AppSetting::getValue(self::RECIPIENTS_SETTING, '[]'), true);

        return collect(is_array($emails) ? $emails : [])
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
        return AppSetting::getValue(self::ENABLED_SETTING, '1') === '1';
    }

    public function setAutomaticSendingEnabled(bool $enabled): void
    {
        AppSetting::setValue(self::ENABLED_SETTING, $enabled ? '1' : '0');
    }

    public function send(array $recipients, array $report): int
    {
        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(new DailyDeliveryMail($report));
        }

        return count($recipients);
    }

    public function sentOn(string $date): array
    {
        $record = json_decode((string) AppSetting::getValue(self::SENT_SETTING, '{}'), true);

        return ($record['date'] ?? null) === $date && is_array($record['recipients'] ?? null)
            ? $record['recipients']
            : [];
    }

    public function markSent(string $date, array $recipients): void
    {
        AppSetting::setValue(self::SENT_SETTING, json_encode(['date' => $date, 'recipients' => $recipients]));
    }
}
