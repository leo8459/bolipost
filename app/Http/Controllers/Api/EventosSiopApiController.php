<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class EventosSiopApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'min:3', 'max:255'],
            'tabla' => ['nullable', 'string', Rule::in(array_keys($this->sourceOptions()))],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'exacto' => ['nullable', 'boolean'],
        ]);

        $codigo = trim((string) $validated['codigo']);
        $tabla = trim((string) ($validated['tabla'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 30);
        $exacto = filter_var($validated['exacto'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $exacto = $exacto ?? true;

        $sources = $this->sourceOptions();

        if ($tabla !== '') {
            $sources = [$tabla => $sources[$tabla]];
        }

        $resultados = collect();

        foreach ($sources as $sourceTable => $servicio) {
            if (! Schema::hasTable($sourceTable)) {
                continue;
            }

            $resultados = $resultados->concat(
                $this->querySource($sourceTable, $servicio, $codigo, $exacto, $limit)
            );
        }

        $resultados = $resultados
            ->sortByDesc(fn (object $row) => (string) ($row->created_at ?? ''))
            ->take($limit)
            ->values();

        return response()->json([
            'ok' => true,
            'filtro' => [
                'codigo' => $codigo,
                'tabla' => $tabla !== '' ? $tabla : null,
                'exacto' => $exacto,
                'limit' => $limit,
            ],
            'total' => $resultados->count(),
            'data' => $resultados,
        ]);
    }

    private function querySource(string $sourceTable, string $servicio, string $codigo, bool $exacto, int $limit): Collection
    {
        $query = DB::table($sourceTable . ' as t')
            ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->select([
                DB::raw("'" . $sourceTable . "' as tabla"),
                DB::raw("'" . $servicio . "' as servicio"),
                't.id',
                't.codigo',
                't.evento_id',
                'e.nombre_evento as evento',
                't.user_id',
                'u.name as usuario',
                't.created_at',
            ]);

        if (Schema::hasColumn($sourceTable, 'cliente_id')) {
            $query->leftJoin('clientes as c', 'c.id', '=', 't.cliente_id')
                ->addSelect([
                    't.cliente_id',
                    'c.name as cliente',
                ]);
        } else {
            $query->addSelect([
                DB::raw('NULL::bigint as cliente_id'),
                DB::raw('NULL::text as cliente'),
            ]);
        }

        if ($exacto) {
            $query->whereRaw('TRIM(UPPER(t.codigo)) = TRIM(UPPER(?))', [$codigo]);
        } else {
            $query->where('t.codigo', 'ILIKE', '%' . $codigo . '%');
        }

        return $query
            ->orderByDesc('t.created_at')
            ->orderByDesc('t.id')
            ->limit($limit)
            ->get()
            ->map(function (object $row) {
                $imagenes = $this->resolveImagesForRecord($row->tabla, (string) $row->codigo);
                $row->foto_entrega = $imagenes['entrega'];
                $row->foto_devolucion = $imagenes['devolucion'];
                $row->foto = $imagenes['principal'];

                return $row;
            });
    }

    private function sourceOptions(): array
    {
        return [
            'eventos_ems' => 'EMS',
            'eventos_certi' => 'CERTI',
            'eventos_ordi' => 'ORDI',
            'eventos_contrato' => 'CONTRATO',
            'eventos_despacho' => 'DESPACHO',
            'eventos_tiktoker' => 'DELIVERY EXPRESS',
        ];
    }

    private function resolveImagesForRecord(string $sourceTable, string $codigo): array
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return $this->emptyImages();
        }

        $row = match ($sourceTable) {
            'eventos_ems' => $this->resolvePackageImages('paquetes_ems', 'id_paquetes_ems', $codigo),
            'eventos_certi' => $this->resolvePackageImages('paquetes_certi', 'id_paquetes_certi', $codigo),
            'eventos_ordi' => $this->resolvePackageImages('paquetes_ordi', 'id_paquetes_ordi', $codigo),
            'eventos_contrato' => $this->resolvePackageImages('paquetes_contrato', 'id_paquetes_contrato', $codigo),
            'eventos_tiktoker' => $this->resolveSolicitudImages($codigo),
            default => null,
        };

        return $this->normalizeImageRow($row);
    }

    private function resolvePackageImages(string $packageTable, string $carteroColumn, string $codigo): ?object
    {
        if (! Schema::hasTable($packageTable) || ! Schema::hasColumn($packageTable, 'imagen')) {
            return null;
        }

        return DB::table($packageTable . ' as p')
            ->leftJoin('cartero as c', 'c.' . $carteroColumn, '=', 'p.id')
            ->whereRaw('TRIM(UPPER(p.codigo)) = TRIM(UPPER(?))', [$codigo])
            ->orderByRaw('c.updated_at DESC NULLS LAST, c.id DESC, p.id DESC')
            ->selectRaw('COALESCE(c.imagen, p.imagen) as entrega, c.imagen_devolucion as devolucion')
            ->first();
    }

    private function resolveSolicitudImages(string $codigo): ?object
    {
        if (! Schema::hasTable('solicitud_clientes') || ! Schema::hasColumn('solicitud_clientes', 'imagen')) {
            return null;
        }

        return DB::table('solicitud_clientes as s')
            ->leftJoin('cartero as c', 'c.id_solicitud_cliente', '=', 's.id')
            ->where(function ($query) use ($codigo) {
                $query->whereRaw('TRIM(UPPER(COALESCE(s.codigo_solicitud, \'\'))) = TRIM(UPPER(?))', [$codigo])
                    ->orWhereRaw('TRIM(UPPER(COALESCE(s.barcode, \'\'))) = TRIM(UPPER(?))', [$codigo])
                    ->orWhereRaw('TRIM(UPPER(COALESCE(s.cod_especial, \'\'))) = TRIM(UPPER(?))', [$codigo]);
            })
            ->orderByRaw('c.updated_at DESC NULLS LAST, c.id DESC, s.id DESC')
            ->selectRaw('COALESCE(c.imagen, s.imagen) as entrega, c.imagen_devolucion as devolucion')
            ->first();
    }

    private function normalizeImageRow(?object $row): array
    {
        if (! $row) {
            return $this->emptyImages();
        }

        $entrega = trim((string) ($row->entrega ?? ''));
        $devolucion = trim((string) ($row->devolucion ?? ''));

        return [
            'entrega' => $entrega !== '' ? $entrega : null,
            'devolucion' => $devolucion !== '' ? $devolucion : null,
            'principal' => $devolucion !== '' ? $devolucion : ($entrega !== '' ? $entrega : null),
        ];
    }

    private function emptyImages(): array
    {
        return [
            'entrega' => null,
            'devolucion' => null,
            'principal' => null,
        ];
    }
}
