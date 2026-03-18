<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MaintenanceFileController extends Controller
{
    public function comprobante(Request $request, MaintenanceLog $maintenanceLog)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion'], true), 403);

        $stored = trim((string) ($maintenanceLog->comprobante ?? ''));
        abort_if($stored === '', 404, 'Comprobante no encontrado.');

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

        abort(404, 'Archivo de comprobante no encontrado en almacenamiento.');
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

