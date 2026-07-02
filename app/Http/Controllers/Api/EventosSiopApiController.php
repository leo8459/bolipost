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
                $row->foto = $this->resolveImageForRecord($row->tabla, (string) $row->codigo);

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
            'eventos_tiktoker' => 'TIKTOKER',
        ];
    }

    private function resolveImageForRecord(string $sourceTable, string $codigo): ?string
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return null;
        }

        return match ($sourceTable) {
            'eventos_ems' => $this->resolveEmsImage($codigo),
            'eventos_certi' => $this->resolveSimpleImage('paquetes_certi', $codigo),
            'eventos_ordi' => $this->resolveSimpleImage('paquetes_ordi', $codigo),
            'eventos_contrato' => $this->resolveSimpleImage('paquetes_contrato', $codigo),
            'eventos_tiktoker' => $this->resolveTiktokerImage($codigo),
            default => null,
        };
    }

    private function resolveEmsImage(string $codigo): ?string
    {
        return DB::table('paquetes_ems as pe')
            ->leftJoin('cartero as c', 'c.id_paquetes_ems', '=', 'pe.id')
            ->whereRaw('TRIM(UPPER(pe.codigo)) = TRIM(UPPER(?))', [$codigo])
            ->orderByRaw('c.updated_at DESC NULLS LAST, c.id DESC, pe.id DESC')
            ->value(DB::raw('COALESCE(c.imagen_devolucion, c.imagen, pe.imagen)'));
    }

    private function resolveSimpleImage(string $table, string $codigo): ?string
    {
        return DB::table($table)
            ->whereRaw('TRIM(UPPER(codigo)) = TRIM(UPPER(?))', [$codigo])
            ->orderByDesc('id')
            ->value('imagen');
    }

    private function resolveTiktokerImage(string $codigo): ?string
    {
        return DB::table('solicitud_clientes')
            ->where(function ($query) use ($codigo) {
                $query->whereRaw('TRIM(UPPER(COALESCE(codigo_solicitud, \'\'))) = TRIM(UPPER(?))', [$codigo])
                    ->orWhereRaw('TRIM(UPPER(COALESCE(barcode, \'\'))) = TRIM(UPPER(?))', [$codigo])
                    ->orWhereRaw('TRIM(UPPER(COALESCE(cod_especial, \'\'))) = TRIM(UPPER(?))', [$codigo]);
            })
            ->orderByDesc('id')
            ->value('imagen');
    }
}
