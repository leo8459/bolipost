<?php

namespace App\Support;

final class BolivianNumber
{
    /**
     * Format a number for presentation in Bolivia: 1.234,56.
     *
     * The last two arguments intentionally match number_format's signature so
     * existing view calls can migrate without changing their numeric precision.
     */
    public static function format(
        int|float|string|null $number,
        int $decimals = 0,
        ?string $decimalSeparator = null,
        ?string $thousandsSeparator = null,
    ): string {
        return number_format((float) $number, $decimals, ',', '.');
    }
}
