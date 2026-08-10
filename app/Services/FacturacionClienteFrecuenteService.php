<?php

namespace App\Services;

use App\Models\FacturacionClienteFrecuente;
use Illuminate\Support\Collection;

class FacturacionClienteFrecuenteService
{
    public function searchByDocument(string $query, int $limit = 8): Collection
    {
        $normalizedQuery = $this->normalizeDocument($query);
        if ($normalizedQuery === '') {
            return collect();
        }

        $escapedQuery = addcslashes($normalizedQuery, '%_');

        return FacturacionClienteFrecuente::query()
            ->where(function ($builder) use ($escapedQuery) {
                $builder->where('numero_documento', 'like', $escapedQuery . '%')
                    ->orWhere('numero_documento', 'like', '%' . $escapedQuery . '%');
            })
            ->orderByRaw(
                "CASE
                    WHEN numero_documento = ? THEN 0
                    WHEN numero_documento LIKE ? THEN 1
                    ELSE 2
                END",
                [$normalizedQuery, $normalizedQuery . '%']
            )
            ->orderByDesc('usos')
            ->orderByDesc('updated_at')
            ->limit(max(1, min($limit, 12)))
            ->get();
    }

    public function rememberFacturadaCart(?object $cart): ?FacturacionClienteFrecuente
    {
        if (!$cart) {
            return null;
        }

        $payload = $this->extractPayloadFromCart($cart);
        if ($payload === null) {
            return null;
        }

        $record = FacturacionClienteFrecuente::query()->firstOrNew([
            'tipo_documento' => $payload['tipo_documento'],
            'numero_documento' => $payload['numero_documento'],
            'complemento_documento' => $payload['complemento_documento'],
        ]);

        $currentVentaId = $payload['ultima_venta_id'];
        $sameVenta = $record->exists && (int) ($record->ultima_venta_id ?? 0) === $currentVentaId && $currentVentaId > 0;

        $record->razon_social = $payload['razon_social'];
        $record->correo_facturacion = $payload['correo_facturacion'];
        $record->ultima_venta_id = $currentVentaId > 0 ? $currentVentaId : null;
        $record->usos = $record->exists
            ? ($sameVenta ? max(1, (int) $record->usos) : max(1, (int) $record->usos) + 1)
            : 1;
        $record->save();

        return $record;
    }

    private function extractPayloadFromCart(?object $cart): ?array
    {
        $estadoEmision = strtoupper(trim((string) data_get($cart, 'estado_emision', '')));
        if ($estadoEmision !== 'FACTURADA') {
            return null;
        }

        $tipoDocumento = trim((string) data_get($cart, 'tipo_documento', ''));
        $numeroDocumento = $this->normalizeDocument((string) data_get($cart, 'numero_documento', ''));
        $complemento = trim((string) data_get($cart, 'complemento_documento', ''));
        $razonSocial = $this->normalizeName((string) data_get($cart, 'razon_social', ''));
        $correo = strtolower(trim((string) data_get($cart, 'correo_facturacion', '')));

        if ($tipoDocumento === '' || $numeroDocumento === '' || $razonSocial === '') {
            return null;
        }

        return [
            'tipo_documento' => $tipoDocumento,
            'numero_documento' => $numeroDocumento,
            'complemento_documento' => $complemento,
            'razon_social' => $razonSocial,
            'correo_facturacion' => $correo !== '' ? $correo : null,
            'ultima_venta_id' => (int) data_get($cart, 'id', 0),
        ];
    }

    private function normalizeDocument(string $value): string
    {
        return strtoupper(trim(preg_replace('/\s+/', '', $value) ?? ''));
    }

    private function normalizeName(string $value): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }
}
