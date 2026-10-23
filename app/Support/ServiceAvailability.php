<?php

namespace App\Support;

use App\Models\AppSetting;

final class ServiceAvailability
{
    public const SETTING_KEYS = [
        'ems' => 'services.enabled.ems',
        'contratos' => 'services.enabled.contratos',
        'solicitud' => 'services.enabled.solicitud',
    ];

    public static function all(): array
    {
        $stored = AppSetting::query()
            ->whereIn('key', array_values(self::SETTING_KEYS))
            ->pluck('value', 'key');

        $availability = [];
        foreach (self::SETTING_KEYS as $service => $settingKey) {
            $availability[$service] = ($stored[$settingKey] ?? '1') === '1';
        }

        return $availability;
    }

    public static function isEnabled(string $service): bool
    {
        $settingKey = self::SETTING_KEYS[$service] ?? null;

        if ($settingKey === null) {
            return true;
        }

        return AppSetting::getValue($settingKey, '1') === '1';
    }
}
