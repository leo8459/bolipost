<?php

namespace App\Support;

use App\Models\TarifarioTiktoker;
use App\Models\SolicitudCliente;

class TiktokerTariffPriceCalculator
{
    public static function calculate(
        TarifarioTiktoker $tarifario,
        float $peso,
        bool $pagoDestinatario = false,
        bool $paqueteMuyGrande = false,
        bool $recargoVolumen = false
    ): float
    {
        $precio = (float) $tarifario->peso1;

        if ($pagoDestinatario) {
            $precio += SolicitudCliente::RECARGO_PAGO_DESTINO;
        }

        if ($paqueteMuyGrande) {
            $origen = self::normalizePlace((string) optional($tarifario->origen)->nombre_origen);
            $destino = self::normalizePlace((string) optional($tarifario->destino)->nombre_destino);
            $precio += $origen !== '' && $origen === $destino ? 5.00 : 10.00;
        }

        if ($recargoVolumen) {
            $precio += SolicitudCliente::RECARGO_VOLUMEN;
        }

        return round($precio, 2);
    }

    private static function normalizePlace(string $value): string
    {
        return strtoupper(trim($value));
    }
}
