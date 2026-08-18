<?php

namespace App\Support;

final class CarteroEvent
{
    public const ASIGNADO = 'Paquete asignado a cartero para entrega fisica.';

    public const CAMBIADO = 'Cambio de cartero registrado.';

    public const QUITADO = 'Cartero quitado y paquete devuelto a su estado anterior.';

    public const INTENTO_VENTANILLA = 'Intento de entrega devuelto a ventanilla.';

    public static function nombreMostrado(string $nombreEvento, ?string $detalleEvento): string
    {
        $detalleEvento = trim((string) $detalleEvento);

        if ($detalleEvento === '') {
            return $nombreEvento;
        }

        return in_array(trim($nombreEvento), [
            self::ASIGNADO,
            self::CAMBIADO,
            self::QUITADO,
            self::INTENTO_VENTANILLA,
        ], true)
            ? $detalleEvento
            : $nombreEvento;
    }
}
