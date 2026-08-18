<?php

namespace App\Support;

final class EncargadoEvent
{
    public const CANCELADO = 'Envio cancelado desde encargado.';

    public const PESO_ACTUALIZADO = 'Peso actualizado desde encargado.';

    public const CARTERO_CAMBIADO = 'Cartero cambiado desde encargado.';

    public const CARTERO_QUITADO = 'Cartero quitado desde encargado.';

    public const DEVUELTO_ORIGEN = 'Envio devuelto a almacen origen desde encargado.';

    public const DEVUELTO_DESTINO = 'Envio devuelto a almacen destino desde encargado.';

    public const DEVUELTO_VENTANILLA = 'Envio devuelto a ventanilla desde encargado.';

    public static function nombreMostrado(string $nombreEvento, ?string $detalleEvento): string
    {
        $detalleEvento = trim((string) $detalleEvento);

        if ($detalleEvento === '') {
            return $nombreEvento;
        }

        return in_array(trim($nombreEvento), [
            self::CANCELADO,
            self::PESO_ACTUALIZADO,
            self::CARTERO_CAMBIADO,
            self::CARTERO_QUITADO,
            self::DEVUELTO_ORIGEN,
            self::DEVUELTO_DESTINO,
            self::DEVUELTO_VENTANILLA,
        ], true)
            ? $detalleEvento
            : $nombreEvento;
    }
}
