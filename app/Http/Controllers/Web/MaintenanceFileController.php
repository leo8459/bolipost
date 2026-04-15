<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceAppointment;
use App\Models\MaintenanceLog;
use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MaintenanceFileController extends Controller
{
    public function appointmentEvidence(Request $request, MaintenanceAppointment $maintenanceAppointment)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion'], true), 403);

        return $this->serveFromPublicDisk((string) ($maintenanceAppointment->evidencia_path ?? ''), 'Evidencia de solicitud no encontrada.');
    }

    public function appointmentForm(Request $request, MaintenanceAppointment $maintenanceAppointment)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion'], true), 403);

        return $this->serveFromPublicDisk((string) ($maintenanceAppointment->formulario_documento_path ?? ''), 'Documento de formulario no encontrado.');
    }

    public function comprobante(Request $request, MaintenanceLog $maintenanceLog)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion'], true), 403);

        return $this->serveFromPublicDisk((string) ($maintenanceLog->comprobante ?? ''), 'Archivo de comprobante no encontrado en almacenamiento.');
    }

    public function workshopReception(Request $request, Workshop $workshop)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion', 'taller'], true), 403);

        return $this->serveFromPublicDisk((string) ($workshop->reception_photo_path ?? ''), 'Foto de ingreso no encontrada.');
    }

    public function workshopDamage(Request $request, Workshop $workshop)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion', 'taller'], true), 403);

        return $this->serveFromPublicDisk((string) ($workshop->damage_photo_path ?? ''), 'Foto del dano no encontrada.');
    }

    public function workshopInvoice(Request $request, Workshop $workshop)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion', 'taller'], true), 403);

        return $this->serveFromPublicDisk((string) ($workshop->invoice_file_path ?? ''), 'Factura de taller no encontrada.');
    }

    public function workshopReceipt(Request $request, Workshop $workshop)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion', 'taller'], true), 403);

        return $this->serveFromPublicDisk((string) ($workshop->receipt_file_path ?? ''), 'Comprobante de taller no encontrado.');
    }

    private function serveFromPublicDisk(string $stored, string $notFoundMessage)
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

            return response()->file($fullPath, [
                'Content-Type' => $mime,
            ]);
        }

        abort(404, $notFoundMessage);
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
}

