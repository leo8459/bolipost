<?php

namespace App\Support;

use Illuminate\Support\Str;

class FuelProductGuard
{
    private const ACCEPTED_CODES = [
        '7',
    ];

    private const ACCEPTED_DESCRIPTION_PATTERNS = [
        'GASOLINA',
        'GASOHOL',
        'G ESPECIAL',
        'GESPECIAL',
        'GASOLINA ESPECIAL',
        'GASOLINA PREMIUM',
        'G PREMIUM',
        'GPREMIUM',
        'ESPECIAL PLUS',
        'PREMIUM PLUS',
        'OCTAN',
    ];

    private const REJECTED_DESCRIPTION_PATTERNS = [
        'DIESEL',
        'UCOM',
        'GLP',
        'GNV',
        'ACEITE',
        'LUBRICANTE',
        'ADITIVO',
        'KEROSENE',
    ];

    public static function validateSiatDetails(array $details): array
    {
        $meaningfulDetails = collect($details)
            ->filter(fn ($detail) => is_array($detail))
            ->map(function (array $detail): array {
                return [
                    'codigo' => trim((string) ($detail['codigo'] ?? $detail['codigoProducto'] ?? '')),
                    'descripcion' => trim((string) ($detail['descripcion'] ?? $detail['descripcionProducto'] ?? '')),
                ];
            })
            ->filter(fn (array $detail) => $detail['codigo'] !== '' || $detail['descripcion'] !== '')
            ->values();

        if ($meaningfulDetails->isEmpty()) {
            return [
                'valid' => false,
                'message' => 'No se pudo identificar el producto facturado en SIAT. Solo se permite registrar gasolina.',
            ];
        }

        foreach ($meaningfulDetails as $detail) {
            $codigo = $detail['codigo'];
            $descripcion = $detail['descripcion'];
            $normalizedDescription = self::normalizeText($descripcion);

            foreach (self::REJECTED_DESCRIPTION_PATTERNS as $pattern) {
                if ($normalizedDescription !== '' && str_contains($normalizedDescription, $pattern)) {
                    return [
                        'valid' => false,
                        'message' => self::buildRejectedMessage($codigo, $descripcion),
                    ];
                }
            }

            if (in_array($codigo, self::ACCEPTED_CODES, true)) {
                continue;
            }

            $accepted = false;
            foreach (self::ACCEPTED_DESCRIPTION_PATTERNS as $pattern) {
                if ($normalizedDescription !== '' && str_contains($normalizedDescription, $pattern)) {
                    $accepted = true;
                    break;
                }
            }

            if (!$accepted) {
                return [
                    'valid' => false,
                    'message' => self::buildRejectedMessage($codigo, $descripcion),
                ];
            }
        }

        return ['valid' => true, 'message' => null];
    }

    private static function buildRejectedMessage(string $codigo, string $descripcion): string
    {
        $label = trim($codigo . ($codigo !== '' && $descripcion !== '' ? ' - ' : '') . $descripcion);
        $label = $label !== '' ? $label : 'sin descripcion';

        return 'La factura SIAT contiene un producto no permitido para este modulo: ' . $label . '. Solo se permite gasolina.';
    }

    private static function normalizeText(string $value): string
    {
        $ascii = Str::upper(Str::ascii($value));
        $ascii = preg_replace('/[^A-Z0-9]+/', ' ', $ascii) ?? '';

        return trim(preg_replace('/\s+/', ' ', $ascii) ?? '');
    }
}
