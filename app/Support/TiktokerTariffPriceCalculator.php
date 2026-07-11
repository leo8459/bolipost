<?php

namespace App\Support;

use App\Models\TarifarioTiktoker;

class TiktokerTariffPriceCalculator
{
    public static function calculate(TarifarioTiktoker $tarifario, float $peso, bool $pagoDestinatario = false): float
    {
        $precioBase = self::resolveBasePrice($tarifario, $peso);

        if ($pagoDestinatario) {
            $precioBase += 2.50;
        }

        return round($precioBase, 2);
    }

    private static function resolveBasePrice(TarifarioTiktoker $tarifario, float $peso): float
    {
        if (self::hasPeso3($tarifario)) {
            if ($peso <= 0.500) {
                return (float) $tarifario->peso1;
            }

            if ($peso <= 2.000) {
                return (float) $tarifario->peso2;
            }

            return (float) $tarifario->peso3;
        }

        if ($peso <= 2.000) {
            return (float) $tarifario->peso1;
        }

        if ($peso <= 5.000) {
            return (float) $tarifario->peso2;
        }

        $bloquesExtra = (int) ceil($peso - 5);

        return (float) $tarifario->peso2 + ($bloquesExtra * (float) $tarifario->peso_extra);
    }

    private static function hasPeso3(TarifarioTiktoker $tarifario): bool
    {
        return $tarifario->peso3 !== null && $tarifario->peso3 !== '';
    }
}
