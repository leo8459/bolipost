<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DeliveryImageController extends Controller
{
    public function package(string $type, int $id, string $kind = 'entrega'): Response
    {
        $image = $this->imageForPackage($type, $id, $kind);

        abort_if($image === null, 404);

        return $this->imageResponse($image, false);
    }

    public function downloadPackage(string $type, int $id, string $kind = 'entrega'): Response
    {
        $image = $this->imageForPackage($type, $id, $kind);

        abort_if($image === null, 404);

        return $this->imageResponse($image, true);
    }

    public function event(string $source, string $codigo, string $kind = 'entrega'): Response
    {
        $image = $this->imageForEvent($source, $codigo, $kind);

        abort_if($image === null, 404);

        return $this->imageResponse($image, false);
    }

    public function downloadEvent(string $source, string $codigo, string $kind = 'entrega'): Response
    {
        $image = $this->imageForEvent($source, $codigo, $kind);

        abort_if($image === null, 404);

        return $this->imageResponse($image, true);
    }

    private function imageForEvent(string $source, string $codigo, string $kind): ?string
    {
        return match ($source) {
            'eventos_ems' => $this->imageForPackageByCode('paquetes_ems', 'id_paquetes_ems', $codigo, $kind),
            'eventos_certi' => $this->imageForPackageByCode('paquetes_certi', 'id_paquetes_certi', $codigo, $kind),
            'eventos_ordi' => $this->imageForPackageByCode('paquetes_ordi', 'id_paquetes_ordi', $codigo, $kind),
            'eventos_contrato' => $this->imageForPackageByCode('paquetes_contrato', 'id_paquetes_contrato', $codigo, $kind),
            'eventos_tiktoker' => $this->imageForSolicitudByCode($codigo, $kind),
            default => null,
        };
    }

    private function imageForPackage(string $type, int $id, string $kind): ?string
    {
        return match (strtolower(trim($type))) {
            'ems' => $this->imageForPackageById('paquetes_ems', 'id_paquetes_ems', $id, $kind),
            'certi' => $this->imageForPackageById('paquetes_certi', 'id_paquetes_certi', $id, $kind),
            'ordi' => $this->imageForPackageById('paquetes_ordi', 'id_paquetes_ordi', $id, $kind),
            'contrato' => $this->imageForPackageById('paquetes_contrato', 'id_paquetes_contrato', $id, $kind),
            'solicitud', 'tiktoker' => $this->imageForSolicitudById($id, $kind),
            default => null,
        };
    }

    private function imageForPackageById(string $table, string $carteroColumn, int $id, string $kind): ?string
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'imagen')) {
            return null;
        }

        $query = DB::table($table . ' as p')
            ->leftJoin('cartero as c', 'c.' . $carteroColumn, '=', 'p.id')
            ->where('p.id', $id);

        $this->whereImageAvailable($query, $kind, 'p');

        return $query
            ->orderByRaw('c.updated_at DESC NULLS LAST, c.id DESC')
            ->value($this->imageExpression($kind, 'p'));
    }

    private function imageForPackageByCode(string $table, string $carteroColumn, string $codigo, string $kind): ?string
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'imagen')) {
            return null;
        }

        $query = DB::table($table . ' as p')
            ->leftJoin('cartero as c', 'c.' . $carteroColumn, '=', 'p.id')
            ->whereRaw('TRIM(UPPER(p.codigo)) = TRIM(UPPER(?))', [$codigo]);

        $this->whereImageAvailable($query, $kind, 'p');

        return $query
            ->orderByRaw('c.updated_at DESC NULLS LAST, c.id DESC, p.id DESC')
            ->value($this->imageExpression($kind, 'p'));
    }

    private function imageForSolicitudById(int $id, string $kind): ?string
    {
        if (! Schema::hasTable('solicitud_clientes') || ! Schema::hasColumn('solicitud_clientes', 'imagen')) {
            return null;
        }

        $query = DB::table('solicitud_clientes as s')
            ->leftJoin('cartero as c', 'c.id_solicitud_cliente', '=', 's.id')
            ->where('s.id', $id);

        $this->whereImageAvailable($query, $kind, 's');

        return $query
            ->orderByRaw('c.updated_at DESC NULLS LAST, c.id DESC')
            ->value($this->imageExpression($kind, 's'));
    }

    private function imageForSolicitudByCode(string $codigo, string $kind): ?string
    {
        if (! Schema::hasTable('solicitud_clientes') || ! Schema::hasColumn('solicitud_clientes', 'imagen')) {
            return null;
        }

        $query = DB::table('solicitud_clientes as s')
            ->leftJoin('cartero as c', 'c.id_solicitud_cliente', '=', 's.id')
            ->where(function ($query) use ($codigo) {
                $query->whereRaw('TRIM(UPPER(COALESCE(s.codigo_solicitud, \'\'))) = TRIM(UPPER(?))', [$codigo])
                    ->orWhereRaw('TRIM(UPPER(COALESCE(s.barcode, \'\'))) = TRIM(UPPER(?))', [$codigo])
                    ->orWhereRaw('TRIM(UPPER(COALESCE(s.cod_especial, \'\'))) = TRIM(UPPER(?))', [$codigo]);
            });

        $this->whereImageAvailable($query, $kind, 's');

        return $query
            ->orderByRaw('c.updated_at DESC NULLS LAST, c.id DESC, s.id DESC')
            ->value($this->imageExpression($kind, 's'));
    }

    private function whereImageAvailable($query, string $kind, string $packageAlias): void
    {
        $carteroColumns = strtolower($kind) === 'devolucion'
            ? ['imagen_devolucion', 'imagen']
            : ['imagen'];

        $query->where(function ($imageQuery) use ($carteroColumns, $packageAlias) {
            foreach ($carteroColumns as $column) {
                $imageQuery->orWhere(function ($columnQuery) use ($column) {
                    $columnQuery->whereNotNull('c.' . $column)
                        ->where('c.' . $column, '<>', '');
                });
            }

            $imageQuery->orWhere(function ($packageQuery) use ($packageAlias) {
                $packageQuery->whereNotNull($packageAlias . '.imagen')
                    ->where($packageAlias . '.imagen', '<>', '');
            });
        });
    }

    private function imageExpression(string $kind, string $packageAlias)
    {
        return DB::raw(strtolower($kind) === 'devolucion'
            ? 'COALESCE(c.imagen_devolucion, c.imagen, ' . $packageAlias . '.imagen)'
            : 'COALESCE(c.imagen, ' . $packageAlias . '.imagen)');
    }

    private function imageResponse(string $image, bool $download): Response
    {
        $image = trim($image);

        if (preg_match('/^data:(image\/[a-z0-9.+-]+);base64,(.*)$/is', $image, $matches) === 1) {
            $binary = base64_decode($matches[2], true);
            abort_if($binary === false, 404);
            $extension = $this->extensionForMimeType($matches[1]);

            return response($binary, 200, [
                'Content-Type' => $matches[1],
                'Content-Disposition' => ($download ? 'attachment' : 'inline') . '; filename="imagen-entrega.' . $extension . '"',
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        if (preg_match('/^https?:\/\//i', $image) === 1) {
            return redirect()->away($image);
        }

        abort_unless(Storage::disk('public')->exists($image), 404);

        return response(Storage::disk('public')->get($image), 200, [
            'Content-Type' => Storage::disk('public')->mimeType($image) ?: 'image/jpeg',
            'Content-Disposition' => ($download ? 'attachment' : 'inline') . '; filename="' . basename($image) . '"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function extensionForMimeType(string $mimeType): string
    {
        return match (strtolower($mimeType)) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
    }
}
