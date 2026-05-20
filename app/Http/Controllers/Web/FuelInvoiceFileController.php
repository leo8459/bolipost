<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\FuelInvoice;
use App\Services\FuelInvoiceDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FuelInvoiceFileController extends Controller
{
    public function document(Request $request, FuelInvoice $fuelInvoice)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion', 'conductor'], true), 403);

        return $this->serveFromPublicDisk(
            (string) ($fuelInvoice->siat_document_path ?? ''),
            'Documento SIAT no encontrado.',
            $request->boolean('download') ? $this->buildDownloadName($fuelInvoice, 'factura-siat', $fuelInvoice->siat_document_path) : null
        );
    }

    public function rollo(Request $request, FuelInvoice $fuelInvoice)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion', 'conductor'], true), 403);

        $fuelInvoice = $this->ensureSiatArtifacts($fuelInvoice);

        return $this->serveFromPublicDisk(
            (string) ($fuelInvoice->siat_rollo_document_path ?? ''),
            'Documento rollo no encontrado.',
            $request->boolean('download') ? $this->buildDownloadName($fuelInvoice, 'factura-rollo', $fuelInvoice->siat_rollo_document_path) : null
        );
    }

    public function photo(Request $request, FuelInvoice $fuelInvoice)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion', 'conductor'], true), 403);

        return $this->serveFromPublicDisk(
            (string) ($fuelInvoice->invoice_photo_path ?? ''),
            'Foto de factura no encontrada.'
        );
    }

    public function meterPhoto(Request $request, FuelInvoice $fuelInvoice)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion', 'conductor'], true), 403);

        return $this->serveFromPublicDisk(
            (string) data_get($fuelInvoice->antifraud_payload_json, 'evidence.fuel_meter_photo_path', ''),
            'Foto del medidor no encontrada.'
        );
    }

    private function serveFromPublicDisk(string $stored, string $notFoundMessage, ?string $downloadName = null)
    {
        $stored = trim($stored);
        abort_if($stored === '', 404, $notFoundMessage);

        $candidates = $this->candidatePaths($stored);
        $disk = Storage::disk('public');

        foreach ($candidates as $relativePath) {
            if (!$disk->exists($relativePath)) {
                continue;
            }

            $fullPath = $disk->path($relativePath);
            $mime = $disk->mimeType($relativePath) ?: 'application/octet-stream';

            if ($downloadName) {
                return response()->download($fullPath, $downloadName, [
                    'Content-Type' => $mime,
                ]);
            }

            return response()->file($fullPath, [
                'Content-Type' => $mime,
            ]);
        }

        abort(404, $notFoundMessage);
    }

    private function ensureSiatArtifacts(FuelInvoice $fuelInvoice): FuelInvoice
    {
        if (!empty($fuelInvoice->siat_rollo_document_path)) {
            return $fuelInvoice;
        }

        $snapshot = is_array($fuelInvoice->siat_snapshot_json) ? $fuelInvoice->siat_snapshot_json : [];
        $sourceUrl = trim((string) ($fuelInvoice->siat_source_url ?? ''));
        if ($snapshot === [] && $sourceUrl === '') {
            return $fuelInvoice;
        }

        return app(FuelInvoiceDocumentService::class)
            ->persistFromSnapshot($fuelInvoice, $snapshot, $sourceUrl !== '' ? $sourceUrl : null);
    }

    private function candidatePaths(string $stored): array
    {
        $values = [];
        $push = function (string $path) use (&$values): void {
            $path = trim(str_replace('\\', '/', $path));
            $path = ltrim($path, '/');
            if ($path !== '' && !in_array($path, $values, true)) {
                $values[] = $path;
            }
        };

        $push($stored);

        if (str_starts_with($stored, 'public/')) {
            $push(substr($stored, 7));
        }

        if (str_contains($stored, '/storage/')) {
            $pos = strpos($stored, '/storage/');
            if ($pos !== false) {
                $push(substr($stored, $pos + 9));
            }
        }

        return $values;
    }

    private function buildDownloadName(FuelInvoice $fuelInvoice, string $prefix, ?string $storedPath): string
    {
        $invoiceNumber = trim((string) ($fuelInvoice->numero_factura ?? $fuelInvoice->numero ?? 'sin-numero'));
        $invoiceNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', $invoiceNumber) ?: 'sin-numero';
        $extension = strtolower((string) pathinfo((string) $storedPath, PATHINFO_EXTENSION));
        $extension = $extension !== '' ? $extension : 'pdf';

        return "{$prefix}-{$invoiceNumber}.{$extension}";
    }
}
