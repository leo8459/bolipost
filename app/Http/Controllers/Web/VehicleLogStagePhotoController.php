<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\VehicleLogStageEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VehicleLogStagePhotoController extends Controller
{
    public function show(Request $request, VehicleLogStageEvent $stageEvent)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion', 'conductor'], true), 403);

        if ($request->user()?->role === 'conductor') {
            $driverId = (int) ($request->user()?->resolvedDriver()?->id ?? 0);
            abort_if(
                $driverId <= 0 || $driverId !== (int) ($stageEvent->responsible_driver_id ?: $stageEvent->acting_driver_id),
                403
            );
        }

        $stored = trim((string) ($stageEvent->photo_path ?? ''));
        abort_if($stored === '', 404, 'Foto de etapa no encontrada.');

        $disk = Storage::disk('public');

        foreach ($this->candidatePaths($stored) as $relativePath) {
            if (!$disk->exists($relativePath)) {
                continue;
            }

            $fullPath = $disk->path($relativePath);
            $mime = $disk->mimeType($relativePath) ?: 'application/octet-stream';

            return response()->file($fullPath, [
                'Content-Type' => $mime,
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        }

        abort(404, 'Archivo de etapa no encontrado en almacenamiento.');
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
