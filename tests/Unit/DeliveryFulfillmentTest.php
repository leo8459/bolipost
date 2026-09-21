<?php

namespace Tests\Unit;

use App\Support\DeliveryFulfillment;
use PHPUnit\Framework\TestCase;

class DeliveryFulfillmentTest extends TestCase
{
    public function test_it_includes_counter_deliveries_in_completed_work_and_evaluation_base(): void
    {
        $percentage = DeliveryFulfillment::percentage(
            assigned: 50,
            courierDelivered: 25,
            counterDelivered: 25
        );

        $this->assertSame(66.7, $percentage);
    }

    public function test_counter_only_deliveries_reach_one_hundred_percent(): void
    {
        $this->assertSame(100.0, DeliveryFulfillment::percentage(0, 0, 20));
    }

    public function test_it_never_exceeds_one_hundred_percent(): void
    {
        $this->assertSame(100.0, DeliveryFulfillment::percentage(10, 15, 5));
    }
}
