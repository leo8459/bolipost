<?php

namespace Tests\Unit;

use App\Support\BoliviaBusinessCalendar;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class BoliviaBusinessCalendarTest extends TestCase
{
    private BoliviaBusinessCalendar $calendar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calendar = new BoliviaBusinessCalendar;
    }

    public function test_72_hours_skip_weekends_and_a_fixed_national_holiday(): void
    {
        $start = Carbon::parse('2026-04-30 10:00', 'America/La_Paz');

        $deadline = $this->calendar->addBusinessHours($start, 72);

        $this->assertSame('2026-05-06 10:00:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_72_hours_skip_carnival_holidays(): void
    {
        $start = Carbon::parse('2026-02-13 10:00', 'America/La_Paz');

        $deadline = $this->calendar->addBusinessHours($start, 72);

        $this->assertSame('2026-02-20 10:00:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_2026_special_holidays_and_transfer_are_applied(): void
    {
        $this->assertTrue($this->calendar->isBusinessDay(Carbon::parse('2026-01-22')));
        $this->assertFalse($this->calendar->isBusinessDay(Carbon::parse('2026-01-23')));
        $this->assertFalse($this->calendar->isBusinessDay(Carbon::parse('2026-06-05')));
        $this->assertFalse($this->calendar->isBusinessDay(Carbon::parse('2026-08-07')));
    }

    public function test_sunday_fixed_holiday_is_also_observed_on_monday(): void
    {
        $this->assertFalse($this->calendar->isBusinessDay(Carbon::parse('2023-01-02')));
    }
}
