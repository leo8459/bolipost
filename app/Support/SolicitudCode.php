<?php

namespace App\Support;

class SolicitudCode
{
    private const ORIGIN_ABBREVIATIONS = [
        'LA PAZ' => 'LP',
        'SANTA CRUZ' => 'SC',
        'COCHABAMBA' => 'CB',
        'ORURO' => 'OR',
        'POTOSI' => 'PT',
        'SUCRE' => 'CH',
        'CHUQUISACA' => 'CH',
        'TARIJA' => 'TJ',
        'TRINIDAD' => 'BN',
        'BENI' => 'BN',
        'COBIJA' => 'PD',
        'PANDO' => 'PD',
    ];

    public static function make(int $id, ?string $origin): string
    {
        return 'SL' . str_pad((string) $id, 8, '0', STR_PAD_LEFT) . self::originAbbreviation($origin);
    }

    public static function originAbbreviation(?string $origin): string
    {
        $normalized = self::normalize($origin);

        if (isset(self::ORIGIN_ABBREVIATIONS[$normalized])) {
            return self::ORIGIN_ABBREVIATIONS[$normalized];
        }

        $letters = preg_replace('/[^A-Z]/', '', $normalized) ?: 'SN';

        return substr(str_pad($letters, 2, 'X'), 0, 2);
    }

    private static function normalize(?string $value): string
    {
        $text = strtoupper(trim((string) $value));

        return strtr($text, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ñ' => 'N',
        ]);
    }
}
