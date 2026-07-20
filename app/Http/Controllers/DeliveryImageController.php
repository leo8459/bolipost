<?php

namespace App\Http\Controllers;

use App\Models\Cartero;
use App\Models\PaqueteCerti;
use App\Models\PaqueteEms;
use App\Models\PaqueteOrdi;
use App\Models\Recojo as RecojoContrato;
use App\Models\SolicitudCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DeliveryImageController extends Controller
{
    public function show(Request $request, string $source, int $id, string $field = 'imagen'): Response
    {
        $field = $field === 'imagen_devolucion' ? 'imagen_devolucion' : 'imagen';
        $image = $this->resolveImage($source, $id, $field);

        abort_if(empty($image), 404);

        if (str_starts_with($image, 'data:image/')) {
            return $this->base64ImageResponse($image, $request->boolean('download'));
        }

        $path = $this->normalizeStoragePath($image);
        abort_if(!Storage::disk('public')->exists($path), 404);

        $headers = [];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="imagen-entrega.' . pathinfo($path, PATHINFO_EXTENSION) . '"';
        }

        return response()->file(Storage::disk('public')->path($path), $headers);
    }

    public function showByCode(Request $request, string $source, string $field = 'imagen'): Response
    {
        $codigo = trim((string) $request->query('codigo', ''));
        abort_if($codigo === '', 404);

        $id = $this->resolveIdByCode($source, $codigo);
        abort_if($id <= 0, 404);

        return $this->show($request, $source, $id, $field);
    }

    private function resolveImage(string $source, int $id, string $field): ?string
    {
        $source = mb_strtolower($source);
        $model = match ($source) {
            'cartero' => Cartero::query()->find($id),
            'ems' => PaqueteEms::query()->find($id),
            'certi' => PaqueteCerti::query()->find($id),
            'ordi' => PaqueteOrdi::query()->find($id),
            'contrato' => RecojoContrato::query()->find($id),
            'solicitud' => SolicitudCliente::query()->find($id),
            default => null,
        };

        if (!$model) {
            return null;
        }

        $image = (string) ($model->{$field} ?? '');
        if ($source === 'cartero' && $field === 'imagen' && $image === '') {
            $image = (string) ($model->foto ?? '');
        }

        if ($image !== '') {
            return $image;
        }

        if ($source === 'cartero') {
            return null;
        }

        $assignmentColumn = match ($source) {
            'ems' => 'id_paquetes_ems',
            'certi' => 'id_paquetes_certi',
            'ordi' => 'id_paquetes_ordi',
            'contrato' => 'id_paquetes_contrato',
            'solicitud' => 'id_solicitud_cliente',
            default => null,
        };

        if (!$assignmentColumn) {
            return null;
        }

        $assignment = Cartero::query()
            ->where($assignmentColumn, $id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if (!$assignment) {
            return null;
        }

        $image = (string) ($assignment->{$field} ?? '');
        if ($field === 'imagen' && $image === '') {
            $image = (string) ($assignment->foto ?? '');
        }

        return $image !== '' ? $image : null;
    }

    private function resolveIdByCode(string $source, string $codigo): int
    {
        return (int) match (mb_strtolower($source)) {
            'ems' => PaqueteEms::query()
                ->whereRaw('TRIM(UPPER(codigo)) = TRIM(UPPER(?))', [$codigo])
                ->orderByDesc('id')
                ->value('id'),
            'certi' => PaqueteCerti::query()
                ->whereRaw('TRIM(UPPER(codigo)) = TRIM(UPPER(?))', [$codigo])
                ->orderByDesc('id')
                ->value('id'),
            'ordi' => PaqueteOrdi::query()
                ->whereRaw('TRIM(UPPER(codigo)) = TRIM(UPPER(?))', [$codigo])
                ->orderByDesc('id')
                ->value('id'),
            'contrato' => RecojoContrato::query()
                ->whereRaw('TRIM(UPPER(codigo)) = TRIM(UPPER(?))', [$codigo])
                ->orderByDesc('id')
                ->value('id'),
            'solicitud' => SolicitudCliente::query()
                ->whereRaw('TRIM(UPPER(COALESCE(codigo_solicitud, \'\'))) = TRIM(UPPER(?))', [$codigo])
                ->orWhereRaw('TRIM(UPPER(COALESCE(barcode, \'\'))) = TRIM(UPPER(?))', [$codigo])
                ->orWhereRaw('TRIM(UPPER(COALESCE(cod_especial, \'\'))) = TRIM(UPPER(?))', [$codigo])
                ->orderByDesc('id')
                ->value('id'),
            default => 0,
        };
    }

    private function base64ImageResponse(string $image, bool $download): Response
    {
        if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.*)$/s', $image, $matches)) {
            abort(404);
        }

        $mime = $matches[1];
        $binary = base64_decode(preg_replace('/\s+/', '', $matches[2]), true);
        abort_if($binary === false || $binary === '', 404);

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $disposition = $download ? 'attachment' : 'inline';

        return response($binary, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($binary),
            'Content-Disposition' => $disposition . '; filename="imagen-entrega.' . $extension . '"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function normalizeStoragePath(string $image): string
    {
        $path = trim($image);

        if (preg_match('/^https?:\/\//i', $path)) {
            $urlPath = parse_url($path, PHP_URL_PATH);
            $path = is_string($urlPath) ? $urlPath : $path;
        }

        $path = ltrim($path, '/');

        foreach (['storage/', 'public/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }

        return $path;
    }
}
