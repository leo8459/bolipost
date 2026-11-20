<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

final class CarteroEvent
{
    public const ENTREGADO_ID = 316;

    public const ASIGNADO = 'Paquete asignado a cartero para entrega fisica.';

    public const CAMBIADO = 'Cambio de cartero registrado.';

    public const QUITADO = 'Cartero quitado y paquete devuelto a su estado anterior.';

    public const INTENTO_VENTANILLA = 'Intento de entrega devuelto a ventanilla.';

    public static function filterAssignments(Builder $query, string $eventNameColumn): void
    {
        $name = 'LOWER('.$eventNameColumn.')';
        $query->where(function (Builder $events) use ($name): void {
            $events->whereRaw($name.' LIKE ?', ['%asignado a cartero%'])
                ->orWhereRaw($name.' LIKE ?', ['%camino para entrega fisica%'])
                ->orWhereRaw($name.' LIKE ?', ['%transferido al agente de entrega%']);
        });
    }

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
