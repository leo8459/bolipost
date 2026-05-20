<?php

namespace App\Services;

use App\Models\FuelInvoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FuelInvoiceDocumentService
{
    public function persistFromSnapshot(FuelInvoice $invoice, array $snapshot, ?string $sourceUrl = null): FuelInvoice
    {
        if (!Schema::hasTable('fuel_invoices') || empty($snapshot)) {
            return $invoice;
        }

        $updates = [];
        if (Schema::hasColumn('fuel_invoices', 'siat_source_url') && !empty($sourceUrl)) {
            $updates['siat_source_url'] = $sourceUrl;
        }
        if (Schema::hasColumn('fuel_invoices', 'siat_snapshot_json')) {
            $updates['siat_snapshot_json'] = $snapshot;
        }

        $slug = Str::slug((string) ($invoice->numero_factura ?: $invoice->numero ?: 'sin-numero'));
        $identifiers = $this->resolveSiatIdentifiers($invoice, $snapshot, $sourceUrl);

        if (Schema::hasColumn('fuel_invoices', 'siat_document_path')) {
            $documentPath = $this->downloadOriginalSiatPdf(
                $invoice,
                $slug,
                $identifiers,
                2,
                sprintf('fuel-invoices/siat/factura-%d-%s.pdf', (int) $invoice->id, $slug)
            );
            if ($documentPath !== null) {
                $updates['siat_document_path'] = $documentPath;
            }
        }

        if (Schema::hasColumn('fuel_invoices', 'siat_rollo_document_path')) {
            $rolloPath = $this->downloadOriginalSiatPdf(
                $invoice,
                $slug,
                $identifiers,
                1,
                sprintf('fuel-invoices/siat-rollo/factura-rollo-%d-%s.pdf', (int) $invoice->id, $slug)
            );
            if ($rolloPath !== null) {
                $updates['siat_rollo_document_path'] = $rolloPath;
            }
        }

        if (!empty($updates)) {
            $invoice->fill($updates)->save();
        }

        return $invoice->fresh() ?? $invoice;
    }

    private function downloadOriginalSiatPdf(
        FuelInvoice $invoice,
        string $slug,
        array $identifiers,
        int $tamanio,
        string $targetPath
    ): ?string {
        if (
            empty($identifiers['nit']) ||
            empty($identifiers['cuf']) ||
            empty($identifiers['numeroFactura'])
        ) {
            return null;
        }

        try {
            $verifySsl = filter_var(env('SIAT_VERIFY_SSL', false), FILTER_VALIDATE_BOOL);
            $response = Http::timeout(20)
                ->retry(2, 350)
                ->withOptions(['verify' => $verifySsl])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json, text/plain, */*',
                    'Origin' => 'https://siat.impuestos.gob.bo',
                    'Referer' => 'https://siat.impuestos.gob.bo/',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
                ])
                ->put('https://siatrest.impuestos.gob.bo/sre-sfe-shared-v2-rest/consulta/representacionGrafica', [
                    'nit' => $identifiers['nit'],
                    'cuf' => $identifiers['cuf'],
                    'numeroFactura' => $identifiers['numeroFactura'],
                    'tamanio' => $tamanio,
                ]);

            if (!$response->ok()) {
                Log::warning('SIAT representacionGrafica no exitoso', [
                    'invoice_id' => $invoice->id,
                    'status' => $response->status(),
                    'tamanio' => $tamanio,
                    'body' => Str::limit($response->body(), 500),
                ]);

                return null;
            }

            $json = $response->json();
            if (!is_array($json) || data_get($json, 'transaccion') === false) {
                Log::warning('SIAT representacionGrafica sin transaccion valida', [
                    'invoice_id' => $invoice->id,
                    'tamanio' => $tamanio,
                    'body' => Str::limit($response->body(), 500),
                ]);

                return null;
            }

            $encodedPdf = data_get($json, 'representacionGrafica')
                ?? data_get($json, 'data.representacionGrafica')
                ?? data_get($json, 'objeto.representacionGrafica');

            $encodedPdf = is_string($encodedPdf) ? trim($encodedPdf) : '';
            if ($encodedPdf === '') {
                Log::warning('SIAT representacionGrafica sin contenido PDF', [
                    'invoice_id' => $invoice->id,
                    'tamanio' => $tamanio,
                ]);

                return null;
            }

            $binaryPdf = base64_decode($encodedPdf, true);
            if ($binaryPdf === false || !str_starts_with($binaryPdf, '%PDF')) {
                Log::warning('SIAT representacionGrafica devolvio un PDF invalido', [
                    'invoice_id' => $invoice->id,
                    'tamanio' => $tamanio,
                    'slug' => $slug,
                ]);

                return null;
            }

            Storage::disk('public')->put($targetPath, $binaryPdf);

            return $targetPath;
        } catch (\Throwable $e) {
            Log::warning('Error descargando representacionGrafica SIAT', [
                'invoice_id' => $invoice->id,
                'tamanio' => $tamanio,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveSiatIdentifiers(FuelInvoice $invoice, array $snapshot, ?string $sourceUrl): array
    {
        $query = [];
        $sourceUrl = trim((string) $sourceUrl);
        if ($sourceUrl !== '' && preg_match('/^https?:\/\//i', $sourceUrl)) {
            parse_str((string) parse_url($sourceUrl, PHP_URL_QUERY), $query);
        }

        $nit = $this->sanitizeDigits(
            $query['nit'] ?? $query['nitEmisor'] ?? $query['nit_emisor'] ?? data_get($snapshot, 'nit_emisor')
        );
        $cuf = $this->sanitizeText(
            $query['cuf'] ?? $query['codigo_unico_factura'] ?? data_get($snapshot, 'cuf')
        );
        $numeroFactura = $this->sanitizeDigits(
            $query['numeroFactura']
                ?? $query['numero_factura']
                ?? $query['nro_factura']
                ?? $query['factura']
                ?? $query['nf']
                ?? $query['numero']
                ?? $invoice->numero_factura
                ?? $invoice->numero
                ?? data_get($snapshot, 'numero_factura')
        );

        return [
            'nit' => $nit,
            'cuf' => $cuf,
            'numeroFactura' => $numeroFactura,
        ];
    }

    private function sanitizeDigits(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($value ?? ''));

        return is_string($digits) ? trim($digits) : '';
    }

    private function sanitizeText(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }
}
