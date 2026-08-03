<?php

namespace App\Support;

use Carbon\Carbon;

class BoliviaBusinessCalendar
{
    /**
     * Feriados extraordinarios o traslados que solo aplican a una gestion.
     * Las fechas retiradas permiten reemplazar el feriado ordinario por su traslado.
     */
    private const YEAR_EXCEPTIONS = [
        2025 => [
            'added' => ['2025-12-26'],
            'removed' => [],
        ],
        2026 => [
            'added' => ['2026-01-02', '2026-01-23', '2026-06-05', '2026-08-07'],
            'removed' => ['2026-01-22'],
        ],
    ];

    private array $holidayCache = [];

    public function addBusinessHours(Carbon $start, int $hours): Carbon
    {
        $current = $start->copy();
        $remaining = max(0, $hours);

        while ($remaining > 0) {
            $current->addHour();

            if (! $this->isBusinessDay($current)) {
                continue;
            }

            $remaining--;
        }

        return $current;
    }

    public function isBusinessDay(Carbon $date): bool
    {
        return ! $date->isWeekend() && ! $this->isNationalHoliday($date);
    }

    public function isNationalHoliday(Carbon $date): bool
    {
        return in_array($date->toDateString(), $this->holidaysForYear($date->year), true);
    }

    public function holidaysForYear(int $year): array
    {
        if (isset($this->holidayCache[$year])) {
            return $this->holidayCache[$year];
        }

        $easterSunday = Carbon::create($year, 3, 21, 0, 0, 0, 'America/La_Paz')
            ->addDays(easter_days($year));

        $fixedHolidays = [
            ['date' => Carbon::create($year, 1, 1), 'moves_from_sunday' => true],
            ['date' => Carbon::create($year, 1, 22), 'moves_from_sunday' => true],
            ['date' => Carbon::create($year, 5, 1), 'moves_from_sunday' => true],
            ['date' => Carbon::create($year, 6, 21), 'moves_from_sunday' => true],
            ['date' => Carbon::create($year, 8, 6), 'moves_from_sunday' => true],
            ['date' => Carbon::create($year, 11, 2), 'moves_from_sunday' => false],
            ['date' => Carbon::create($year, 12, 25), 'moves_from_sunday' => true],
        ];

        $holidays = [
            $easterSunday->copy()->subDays(48)->toDateString(), // Lunes de Carnaval
            $easterSunday->copy()->subDays(47)->toDateString(), // Martes de Carnaval
            $easterSunday->copy()->subDays(2)->toDateString(),  // Viernes Santo
            $easterSunday->copy()->addDays(60)->toDateString(), // Corpus Christi
        ];

        foreach ($fixedHolidays as $holiday) {
            /** @var Carbon $holidayDate */
            $holidayDate = $holiday['date'];
            $holidays[] = $holidayDate->toDateString();

            if ($holiday['moves_from_sunday'] && $holidayDate->isSunday()) {
                $holidays[] = $holidayDate->copy()->addDay()->toDateString();
            }
        }

        $exceptions = self::YEAR_EXCEPTIONS[$year] ?? ['added' => [], 'removed' => []];
        $holidays = array_diff($holidays, $exceptions['removed']);
        $holidays = array_values(array_unique(array_merge($holidays, $exceptions['added'])));
        sort($holidays);

        return $this->holidayCache[$year] = $holidays;
    }
}
