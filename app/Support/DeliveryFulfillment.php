<?php

namespace App\Support;

final class DeliveryFulfillment
{
    public static function percentage(int $assigned, int $courierDelivered, int $counterDelivered): float
    {
        $assigned = max(0, $assigned);
        $courierDelivered = max(0, $courierDelivered);
        $counterDelivered = max(0, $counterDelivered);

        $pending = max(0, $assigned - $courierDelivered);

        return self::percentageFromPending($assigned, $pending, $counterDelivered);
    }

    public static function percentageFromPending(int $assigned, int $pending, int $counterDelivered): float
    {
        $assigned = max(0, $assigned);
        $pending = max(0, $pending);
        $counterDelivered = max(0, $counterDelivered);

        // Ventanilla adds both workload and completions. Summed pending counts
        // keep one courier's over-delivery from hiding another courier's gap.
        $base = $assigned + $counterDelivered;
        if ($base === 0) {
            return 0.0;
        }

        $delivered = max(0, $base - $pending);

        return min(100.0, round(($delivered * 100) / $base, 1));
    }
}
