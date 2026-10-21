<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class LocationRecordingSchedule
{
    private const TIMEZONE = 'America/La_Paz';

    private const STARTS_AT = '08:30:00';

    private const ENDS_AT = '20:00:00';

    public static function allowsRecording(): bool
    {
        $localTime = Carbon::now(self::TIMEZONE)->format('H:i:s');

        return $localTime >= self::STARTS_AT && $localTime <= self::ENDS_AT;
    }
}
