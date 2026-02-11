<?php

namespace App\Http\Controllers;

use App\Models\Cartero;
use App\Models\Estado;
use App\Models\PaqueteCerti;
use App\Models\PaqueteEms;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CarterosController extends Controller
{
    public function distribucion()
    {
        return view('carteros.distribucion');
    }

    public function asignados()
    {
        return view('carteros.asignados');
    }

    public function users(): JsonResponse
    {
        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $users]);
    }

    public function distribucionData(Request $request): JsonResponse
    {
        return $this->combinedDataResponse($request);
    }

    public function asignadosData(Request $request): JsonResponse
    {
        return $this->combinedDataResponse($request, $this->resolveEstadoAsignadoId());
    }

    public function assign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assignment_mode' => ['required', 'in:auto,user'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.tipo_paquete' => ['required', 'in:EMS,CERTI'],
        ]);

        $assigneeUserId = $validated['assignment_mode'] === 'auto'
            ? (int) $request->user()->id
            : (int) ($validated['user_id'] ?? 0);

        if ($assigneeUserId <= 0) {
            throw ValidationException::withMessages([
                'user_id' => 'Debes seleccionar un usuario para asignar.',
            ]);
        }

        $estadoAsignadoId = $this->resolveEstadoAsignadoId();

        $emsIds = collect($validated['items'])
            ->where('tipo_paquete', 'EMS')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $certiIds = collect($validated['items'])
            ->where('tipo_paquete', 'CERTI')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $updatedEms = 0;
        $updatedCerti = 0;

        DB::transaction(function () use (
            &$updatedEms,
            &$updatedCerti,
            $emsIds,
            $certiIds,
            $estadoAsignadoId,
            $assigneeUserId
        ) {
            if (!empty($emsIds)) {
                $updatedEms = PaqueteEms::query()
                    ->whereIn('id', $emsIds)
                    ->update([
                        'estado_id' => $estadoAsignadoId,
                    ]);

                foreach ($emsIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_ems' => $id]);
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_estados = $estadoAsignadoId;
                    $asignacion->id_user = $assigneeUserId;
                    $asignacion->save();
                }
            }

            if (!empty($certiIds)) {
                $updatedCerti = PaqueteCerti::query()
                    ->whereIn('id', $certiIds)
                    ->update([
                        'fk_estado' => $estadoAsignadoId,
                    ]);

                foreach ($certiIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_certi' => $id]);
                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_estados = $estadoAsignadoId;
                    $asignacion->id_user = $assigneeUserId;
                    $asignacion->save();
                }
            }
        });

        return response()->json([
            'message' => 'Paquetes asignados correctamente.',
            'updated' => [
                'ems' => $updatedEms,
                'certi' => $updatedCerti,
                'total' => $updatedEms + $updatedCerti,
            ],
        ]);
    }

    private function combinedDataResponse(Request $request, ?int $estadoId = null): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $codigo = trim((string) $request->query('codigo', ''));

        $emsFilterIds = null;
        $certiFilterIds = null;

        if ($estadoId !== null) {
            $emsFilterIds = Cartero::query()
                ->where('id_estados', $estadoId)
                ->whereNotNull('id_paquetes_ems')
                ->pluck('id_paquetes_ems')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $certiFilterIds = Cartero::query()
                ->where('id_estados', $estadoId)
                ->whereNotNull('id_paquetes_certi')
                ->pluck('id_paquetes_certi')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $ems = PaqueteEms::query()
            ->select([
                'id',
                'codigo',
                'nombre_destinatario as destinatario',
                'telefono_destinatario as telefono',
                'ciudad',
                'peso',
                'estado_id',
                'created_at',
            ])
            ->when($codigo !== '', function ($query) use ($codigo) {
                $query->whereRaw('LOWER(codigo) LIKE ?', ['%' . mb_strtolower($codigo) . '%']);
            })
            ->when($estadoId !== null, function ($query) use ($emsFilterIds) {
                $query->whereIn('id', $emsFilterIds ?: [0]);
            })
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'tipo_paquete' => 'EMS',
                    'codigo' => $item->codigo,
                    'destinatario' => $item->destinatario,
                    'telefono' => $item->telefono,
                    'ciudad' => $item->ciudad,
                    'zona' => null,
                    'peso' => $item->peso,
                    'estado_id' => $item->estado_id,
                    'user_id' => null,
                    'asignado_a' => null,
                    'created_at' => optional($item->created_at)->toDateTimeString(),
                ];
            });

        $certi = PaqueteCerti::query()
            ->select([
                'id',
                'codigo',
                'destinatario',
                'telefono',
                'cuidad as ciudad',
                'zona',
                'peso',
                'fk_estado as estado_id',
                'created_at',
            ])
            ->when($codigo !== '', function ($query) use ($codigo) {
                $query->whereRaw('LOWER(codigo) LIKE ?', ['%' . mb_strtolower($codigo) . '%']);
            })
            ->when($estadoId !== null, function ($query) use ($certiFilterIds) {
                $query->whereIn('id', $certiFilterIds ?: [0]);
            })
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'tipo_paquete' => 'CERTI',
                    'codigo' => $item->codigo,
                    'destinatario' => $item->destinatario,
                    'telefono' => $item->telefono,
                    'ciudad' => $item->ciudad,
                    'zona' => $item->zona,
                    'peso' => $item->peso,
                    'estado_id' => $item->estado_id,
                    'user_id' => null,
                    'asignado_a' => null,
                    'created_at' => optional($item->created_at)->toDateTimeString(),
                ];
            });

        $all = $ems
            ->concat($certi)
            ->sortByDesc('created_at')
            ->values();

        $total = $all->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $pageRows = $all->slice($offset, $perPage)->values();
        $pageRows = $this->attachCarteroData($pageRows);

        return response()->json([
            'data' => $pageRows,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ]);
    }

    private function attachCarteroData($rows)
    {
        $emsIds = collect($rows)
            ->where('tipo_paquete', 'EMS')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $certiIds = collect($rows)
            ->where('tipo_paquete', 'CERTI')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($emsIds) && empty($certiIds)) {
            return $rows;
        }

        $asignaciones = Cartero::query()
            ->with('user:id,name')
            ->where(function ($query) use ($emsIds, $certiIds) {
                if (!empty($emsIds)) {
                    $query->whereIn('id_paquetes_ems', $emsIds);
                }
                if (!empty($certiIds)) {
                    $query->orWhereIn('id_paquetes_certi', $certiIds);
                }
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $mapEms = [];
        $mapCerti = [];

        foreach ($asignaciones as $a) {
            if ($a->id_paquetes_ems && !isset($mapEms[$a->id_paquetes_ems])) {
                $mapEms[$a->id_paquetes_ems] = $a;
            }
            if ($a->id_paquetes_certi && !isset($mapCerti[$a->id_paquetes_certi])) {
                $mapCerti[$a->id_paquetes_certi] = $a;
            }
        }

        return collect($rows)->map(function ($row) use ($mapEms, $mapCerti) {
            $asignacion = null;
            if ($row['tipo_paquete'] === 'EMS' && isset($mapEms[$row['id']])) {
                $asignacion = $mapEms[$row['id']];
            }
            if ($row['tipo_paquete'] === 'CERTI' && isset($mapCerti[$row['id']])) {
                $asignacion = $mapCerti[$row['id']];
            }

            if ($asignacion) {
                $row['estado_id'] = $asignacion->id_estados;
                $row['user_id'] = $asignacion->id_user;
                $row['asignado_a'] = optional($asignacion->user)->name;
            }

            return $row;
        })->values();
    }

    private function resolveEstadoAsignadoId(): int
    {
        $estadoId = Estado::query()
            ->whereRaw('UPPER(nombre_estado) = ?', ['ASIGNADO'])
            ->value('id');

        if (!$estadoId) {
            throw ValidationException::withMessages([
                'estado' => 'No existe el estado ASIGNADO en la tabla estados.',
            ]);
        }

        return (int) $estadoId;
    }
}
