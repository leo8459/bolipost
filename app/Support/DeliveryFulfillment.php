<?php

namespace App\Support;

final class DeliveryFulfillment
{
    public static function percentage(int $assigned, int $courierDelivered, int $counterDelivered): float
    {
        $assigned = max(0, $assigned);
        $courierDelivered = max(0, $courierDelivered);
        $counterDelivered = max(0, $counterDelivered);

        // Las entregas directas por ventanilla forman parte tanto del trabajo
        // considerado como del trabajo completado.
        $base = $assigned + $counterDelivered;
        if ($base === 0) {
            return 0.0;
        }

        $delivered = $courierDelivered + $counterDelivered;

        return min(100.0, round(($delivered * 100) / $base, 1));
    }
}
