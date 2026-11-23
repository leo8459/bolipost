<?php

namespace App\Services;

use App\Mail\DailyDeliveryMail;
use App\Models\AppSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DailyDeliveryMailService
{
    public const SEND_AT = '20:00';

    public const NATIONAL = 'NACIONAL';

    public const ENABLED_SETTING = 'operations.daily_deliveries_enabled';

    public const SENT_SETTING = 'operations.daily_deliveries_sent';

    private const MANUAL_RECIPIENTS_SETTING = 'operations.daily_deliveries_manual_recipients';

    public function __construct(private readonly DailyDeliveryReport $reports) {}

    /** @return array<string, array<string, array{name: string, manual: bool, role: bool}>> */
    public function recipients(): array
    {
        $manualRecipients = $this->manualRecipients();
        $recipients = [];
        $users = User::query()
            ->whereHas('roles', fn ($roles) => $roles->where('roles.name', 'encargado_ems')->where('roles.guard_name', 'web'))
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'ciudad', 'regionales']);
        foreach ($users as $user) {
            $email = mb_strtolower(trim((string) $user->email));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            foreach ($user->regionalesLista() as $assignment) {
                $regional = DailyDeliveryReport::regionalFor($assignment);
                if ($regional !== null) {
                    $recipients[$regional][$email] ??= [
                        'name' => $user->name,
                        'manual' => isset($manualRecipients[$regional][$email]),
                        'role' => true,
                    ];
                }
            }
        }

        foreach ($manualRecipients as $scope => $addresses) {
            foreach ($addresses as $email => $name) {
                $recipients[$scope][$email] ??= ['name' => $name, 'manual' => true, 'role' => false];
            }
        }

        ksort($recipients);

        return $recipients;
    }

    public function addRecipient(string $scope, string $email, ?string $name = null): bool
    {
        $email = mb_strtolower(trim($email));

        return Cache::store('database')->lock(self::MANUAL_RECIPIENTS_SETTING, 15)->block(5, function () use ($scope, $email, $name): bool {
            if (isset($this->recipients()[$scope][$email])) {
                return false;
            }

            $recipients = $this->manualRecipients();
            $recipients[$scope][$email] = trim($name ?? '');
            AppSetting::setValue(self::MANUAL_RECIPIENTS_SETTING, json_encode($recipients, JSON_THROW_ON_ERROR));

            return true;
        });
    }

    public function removeRecipient(string $scope, string $email): bool
    {
        $email = mb_strtolower(trim($email));

        return Cache::store('database')->lock(self::MANUAL_RECIPIENTS_SETTING, 15)->block(5, function () use ($scope, $email): bool {
            $recipients = $this->manualRecipients();
            if (! isset($recipients[$scope][$email])) {
                return false;
            }

            unset($recipients[$scope][$email]);
            if ($recipients[$scope] === []) {
                unset($recipients[$scope]);
            }
            AppSetting::setValue(self::MANUAL_RECIPIENTS_SETTING, json_encode($recipients, JSON_THROW_ON_ERROR));

            return true;
        });
    }

    private function manualRecipients(): array
    {
        $recipients = json_decode((string) AppSetting::getValue(self::MANUAL_RECIPIENTS_SETTING, '{}'), true);

        return is_array($recipients) ? $recipients : [];
    }

    public function automaticSendingEnabled(): bool
    {
        return AppSetting::getValue(self::ENABLED_SETTING, '1') === '1';
    }

    public function setAutomaticSendingEnabled(bool $enabled): void
    {
        AppSetting::setValue(self::ENABLED_SETTING, $enabled ? '1' : '0');
    }

    public function sendReports(array $recipients, CarbonImmutable $cutoff, bool $automatic = false): array
    {
        $date = $cutoff->toDateString();
        $delivered = $automatic ? $this->sentOn($date) : [];
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $scope => $addresses) {
            $pending = array_diff(array_keys($addresses), $delivered[$scope] ?? []);
            if ($pending === []) {
                continue;
            }

            try {
                $report = $this->reports->through($cutoff, $scope === self::NATIONAL ? null : $scope);
            } catch (Throwable $exception) {
                report($exception);
                $failed += count($pending);

                continue;
            }

            foreach ($pending as $recipient) {
                try {
                    $this->send($recipient, $report);
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;

                    continue;
                }

                $sent++;
                if ($automatic) {
                    $delivered[$scope][] = $recipient;
                    AppSetting::setValue(self::SENT_SETTING, json_encode(['date' => $date, 'regional_recipients' => $delivered]));
                }
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    public function send(string $recipient, array $report): void
    {
        Mail::to($recipient)->send(new DailyDeliveryMail($report));
    }

    public function sentOn(string $date): array
    {
        $record = json_decode((string) AppSetting::getValue(self::SENT_SETTING, '{}'), true);

        return ($record['date'] ?? null) === $date && is_array($record['regional_recipients'] ?? null)
            ? $record['regional_recipients']
            : [];
    }
}
