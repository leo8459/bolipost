<?php

namespace App\Support;

use App\Models\TarifarioTiktoker;

class TiktokerTariffPriceCalculator
{
    public static function calculate(
        TarifarioTiktoker $tarifario,
        float $peso,
        bool $pagoDestinatario = false,
        bool $paqueteMuyGrande = false
    ): float
    {
        $precio = (float) $tarifario->peso1;

        if ($paqueteMuyGrande) {
            $origen = self::normalizePlace((string) optional($tarifario->origen)->nombre_origen);
            $destino = self::normalizePlace((string) optional($tarifario->destino)->nombre_destino);
            $precio += $origen !== '' && $origen === $destino ? 5.00 : 10.00;
        }

        return round($precio, 2);
    }

    private static function normalizePlace(string $value): string
    {
        return strtoupper(trim($value));
    }
}
