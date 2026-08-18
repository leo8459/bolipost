<?php

namespace App\Support;

final class CodigoContinuacionEvent
{
    public const MADRE = 'Se genero un codigo hijo como continuacion de este codigo madre.';

    public const HIJO = 'Este codigo es la continuacion de un codigo madre.';

    public static function nombreMostrado(string $nombreEvento, ?string $codigoRelacionado): string
    {
        $codigoRelacionado = strtoupper(trim((string) $codigoRelacionado));

        if ($codigoRelacionado === '') {
            return $nombreEvento;
        }

        return match (trim($nombreEvento)) {
            self::MADRE => 'Se genero el codigo hijo '.$codigoRelacionado.' como continuacion de este codigo madre.',
            self::HIJO => 'Este codigo es la continuacion del codigo madre '.$codigoRelacionado.'.',
            default => $nombreEvento,
        };
    }
}
