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
    private const EVENTO_ID_PAQUETE_CAMINO_ENTREGA_FISICA = 184;
    private const EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE = 316;
    private const EVENTO_ID_INTENTO_FALLIDO_ENTREGA = 315;

    public function distribucion()
    {
        return view('carteros.distribucion');
    }

    public function asignados()
    {
        return view('carteros.asignados');
    }

    public function cartero()
    {
        return view('carteros.cartero');
    }

    public function devolucion()
    {
        return view('carteros.devolucion');
    }

    public function domicilio()
    {
        return view('carteros.domicilio');
    }

    public function entregaForm(Request $request)
    {
        $data = $request->validate([
            'tipo_paquete' => ['required', 'in:EMS,CERTI'],
            'id' => ['required', 'integer'],
        ]);

        $estadoCarteroId = $this->resolveEstadoCarteroId();
        $estadoProvinciaId = $this->resolveEstadoProvinciaId();
        $asignacion = $this->findAssignmentForUserByStates(
            $data['tipo_paquete'],
            (int) $data['id'],
            (int) $request->user()->id,
            [$estadoCarteroId, $estadoProvinciaId]
        );

        $paquete = $this->getPackageForType($data['tipo_paquete'], (int) $data['id']);

        return view('carteros.entrega', [
            'tipo_paquete' => $data['tipo_paquete'],
            'id' => (int) $data['id'],
            'paquete' => $paquete,
            'asignacion' => $asignacion,
        ]);
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
        return $this->combinedDataResponse($request, $this->resolveEstadoCarteroId());
    }

    public function carteroData(Request $request): JsonResponse
    {
        return $this->combinedDataResponse(
            $request,
            $this->resolveEstadoCarteroId(),
            (int) $request->user()->id
        );
    }

    public function provinciaData(Request $request): JsonResponse
    {
        return $this->combinedDataResponse(
            $request,
            $this->resolveEstadoProvinciaId(),
            (int) $request->user()->id
        );
    }

    public function devolucionData(Request $request): JsonResponse
    {
        return $this->combinedDataResponse(
            $request,
            $this->resolveEstadoDevolucionId(),
            (int) $request->user()->id
        );
    }

    public function domicilioData(Request $request): JsonResponse
    {
        return $this->combinedDataResponse(
            $request,
            $this->resolveEstadoDomicilioId()
        );
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

        $eventoId = self::EVENTO_ID_PAQUETE_CAMINO_ENTREGA_FISICA;
        $eventoExiste = DB::table('eventos')
            ->where('id', $eventoId)
            ->exists();

        if (!$eventoExiste) {
            throw ValidationException::withMessages([
                'items' => "No existe el evento con ID {$eventoId} (Paquete en camino para entrega fisica.).",
            ]);
        }

        $estadoAsignadoId = $this->resolveEstadoCarteroId();

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
            $assigneeUserId,
            $eventoId
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

                $emsCodigos = PaqueteEms::query()
                    ->whereIn('id', $emsIds)
                    ->pluck('codigo')
                    ->map(fn ($codigo) => trim((string) $codigo))
                    ->filter(fn ($codigo) => $codigo !== '')
                    ->values();

                if ($emsCodigos->isNotEmpty()) {
                    $now = now();
                    $rows = $emsCodigos->map(function ($codigo) use ($eventoId, $assigneeUserId, $now) {
                        return [
                            'codigo' => $codigo,
                            'evento_id' => $eventoId,
                            'user_id' => $assigneeUserId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })->all();

                    DB::table('eventos_ems')->insert($rows);
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
            'message' => 'Paquetes asignados correctamente en estado CARTERO.',
            'updated' => [
                'ems' => $updatedEms,
                'certi' => $updatedCerti,
                'total' => $updatedEms + $updatedCerti,
            ],
        ]);
    }

    public function returnToAlmacen(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.tipo_paquete' => ['required', 'in:EMS,CERTI'],
        ]);

        $estadoAlmacenId = 1;
        $actorUserId = (int) $request->user()->id;

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
            $estadoAlmacenId,
            $actorUserId
        ) {
            if (!empty($emsIds)) {
                $updatedEms = PaqueteEms::query()
                    ->whereIn('id', $emsIds)
                    ->update([
                        'estado_id' => $estadoAlmacenId,
                    ]);

                foreach ($emsIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_ems' => $id]);
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_estados = $estadoAlmacenId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                }
            }

            if (!empty($certiIds)) {
                $updatedCerti = PaqueteCerti::query()
                    ->whereIn('id', $certiIds)
                    ->update([
                        'fk_estado' => $estadoAlmacenId,
                    ]);

                foreach ($certiIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_certi' => $id]);
                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_estados = $estadoAlmacenId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                }
            }
        });

        return response()->json([
            'message' => 'Paquetes devueltos a ALMACEN (estado 1).',
            'updated' => [
                'ems' => $updatedEms,
                'certi' => $updatedCerti,
                'total' => $updatedEms + $updatedCerti,
            ],
        ]);
    }

    public function acceptPackages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.tipo_paquete' => ['required', 'in:EMS,CERTI'],
        ]);

        $estadoCarteroId = $this->resolveEstadoCarteroId();
        $actorUserId = (int) $request->user()->id;

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
            $estadoCarteroId,
            $actorUserId
        ) {
            if (!empty($emsIds)) {
                $updatedEms = PaqueteEms::query()
                    ->whereIn('id', $emsIds)
                    ->update([
                        'estado_id' => $estadoCarteroId,
                    ]);

                foreach ($emsIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_ems' => $id]);
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_estados = $estadoCarteroId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                }
            }

            if (!empty($certiIds)) {
                $updatedCerti = PaqueteCerti::query()
                    ->whereIn('id', $certiIds)
                    ->update([
                        'fk_estado' => $estadoCarteroId,
                    ]);

                foreach ($certiIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_certi' => $id]);
                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_estados = $estadoCarteroId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                }
            }
        });

        return response()->json([
            'message' => 'Paquetes aceptados correctamente y enviados a estado CARTERO.',
            'updated' => [
                'ems' => $updatedEms,
                'certi' => $updatedCerti,
                'total' => $updatedEms + $updatedCerti,
            ],
        ]);
    }

    public function registerGuide(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transportadora' => ['required', 'string', 'max:255'],
            'provincia' => ['required', 'string', 'max:255'],
            'factura' => ['nullable', 'string', 'max:255'],
            'precio_total' => ['nullable', 'numeric', 'min:0'],
            'peso_total' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.tipo_paquete' => ['required', 'in:EMS,CERTI'],
        ]);

        $estadoCarteroId = $this->resolveEstadoCarteroId();
        $estadoProvinciaId = $this->resolveEstadoProvinciaId();
        $userId = (int) $request->user()->id;

        $items = collect($validated['items'])
            ->map(fn ($item) => [
                'id' => (int) $item['id'],
                'tipo_paquete' => (string) $item['tipo_paquete'],
            ])
            ->unique(fn ($item) => $item['tipo_paquete'] . '-' . $item['id'])
            ->values();

        $emsIds = $items->where('tipo_paquete', 'EMS')->pluck('id')->all();
        $certiIds = $items->where('tipo_paquete', 'CERTI')->pluck('id')->all();

        $allowedBase = Cartero::query()
            ->where('id_user', $userId)
            ->where('id_estados', $estadoCarteroId);

        $allowedEmsIds = (clone $allowedBase)
            ->whereNotNull('id_paquetes_ems')
            ->pluck('id_paquetes_ems')
            ->map(fn ($id) => (int) $id)
            ->all();

        $allowedCertiIds = (clone $allowedBase)
            ->whereNotNull('id_paquetes_certi')
            ->pluck('id_paquetes_certi')
            ->map(fn ($id) => (int) $id)
            ->all();

        $invalidEms = collect($emsIds)->diff($allowedEmsIds)->values()->all();
        $invalidCerti = collect($certiIds)->diff($allowedCertiIds)->values()->all();

        if (!empty($invalidEms) || !empty($invalidCerti)) {
            throw ValidationException::withMessages([
                'items' => 'Incluiste paquetes que no pertenecen a tu bandeja CARTERO.',
            ]);
        }

        $emsRows = PaqueteEms::query()
            ->whereIn('id', $emsIds ?: [0])
            ->get(['id', 'codigo', 'peso', 'precio'])
            ->keyBy('id');

        $certiRows = PaqueteCerti::query()
            ->whereIn('id', $certiIds ?: [0])
            ->get(['id', 'codigo'])
            ->keyBy('id');

        foreach ($items as $item) {
            if ($item['tipo_paquete'] === 'EMS' && !isset($emsRows[$item['id']])) {
                throw ValidationException::withMessages([
                    'items' => 'Uno o mas paquetes EMS ya no existen.',
                ]);
            }
        }

        $result = DB::transaction(function () use ($validated, $userId, $items, $emsIds, $certiIds, $emsRows, $certiRows, $estadoProvinciaId) {
            $guia = $this->nextGuideNumber();
            $rowsToInsert = [];
            $manualPesoTotal = $validated['peso_total'] ?? null;
            $manualPrecioTotal = $validated['precio_total'] ?? null;
            $lastIndex = max(0, $items->count() - 1);

            foreach ($items->values() as $index => $item) {
                if ($item['tipo_paquete'] === 'EMS') {
                    $pkg = $emsRows[$item['id']];
                } else {
                    $pkg = $certiRows[$item['id']];
                }

                $rowsToInsert[] = [
                    'guia' => $guia,
                    'transportadora' => $validated['transportadora'],
                    'provincia' => $validated['provincia'],
                    'user_id' => $userId,
                    'factura' => $validated['factura'] ?? null,
                    'codigo' => (string) $pkg->codigo,
                    'precio_total' => $index === $lastIndex ? $manualPrecioTotal : null,
                    'peso_total' => $index === $lastIndex ? $manualPesoTotal : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('cartero_guias')->insert($rowsToInsert);

            if (!empty($emsIds)) {
                PaqueteEms::query()
                    ->whereIn('id', $emsIds)
                    ->update(['estado_id' => $estadoProvinciaId]);
            }

            if (!empty($certiIds)) {
                PaqueteCerti::query()
                    ->whereIn('id', $certiIds)
                    ->update(['fk_estado' => $estadoProvinciaId]);
            }

            Cartero::query()
                ->where('id_user', $userId)
                ->where(function ($query) use ($emsIds, $certiIds) {
                    if (!empty($emsIds)) {
                        $query->whereIn('id_paquetes_ems', $emsIds);
                    }
                    if (!empty($certiIds)) {
                        $query->orWhereIn('id_paquetes_certi', $certiIds);
                    }
                })
                ->update(['id_estados' => $estadoProvinciaId]);

            return [
                'guia' => $guia,
                'total_registros' => count($rowsToInsert),
                'suma_peso' => $manualPesoTotal,
                'suma_precio' => $manualPrecioTotal,
            ];
        });

        return response()->json([
            'message' => 'Guia registrada correctamente.',
            'data' => $result,
        ]);
    }

    public function deliverPackage(Request $request)
    {
        $validated = $request->validate([
            'tipo_paquete' => ['required', 'in:EMS,CERTI'],
            'id' => ['required', 'integer'],
            'recibido_por' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $estadoCarteroId = $this->resolveEstadoCarteroId();
        $estadoProvinciaId = $this->resolveEstadoProvinciaId();
        $estadoDomicilioId = $this->resolveEstadoDomicilioId();
        $userId = (int) $request->user()->id;
        $eventoEntregaId = self::EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE;

        if ($validated['tipo_paquete'] === 'EMS') {
            $eventoExiste = DB::table('eventos')
                ->where('id', $eventoEntregaId)
                ->exists();

            if (!$eventoExiste) {
                throw ValidationException::withMessages([
                    'id' => "No existe el evento con ID {$eventoEntregaId} (Paquete entregado exitosamente.).",
                ]);
            }
        }

        $asignacion = $this->findAssignmentForUserByStates(
            $validated['tipo_paquete'],
            (int) $validated['id'],
            $userId,
            [$estadoCarteroId, $estadoProvinciaId]
        );

        DB::transaction(function () use ($validated, $asignacion, $estadoDomicilioId, $userId, $eventoEntregaId) {
            $this->updatePackageState($validated['tipo_paquete'], (int) $validated['id'], $estadoDomicilioId);
            $asignacion->id_estados = $estadoDomicilioId;
            $asignacion->recibido_por = $validated['recibido_por'];
            $asignacion->descripcion = $validated['descripcion'] ?? null;
            $asignacion->save();

            if ($validated['tipo_paquete'] === 'EMS') {
                $codigo = trim((string) PaqueteEms::query()
                    ->where('id', (int) $validated['id'])
                    ->value('codigo'));

                if ($codigo !== '') {
                    DB::table('eventos_ems')->insert([
                        'codigo' => $codigo,
                        'evento_id' => $eventoEntregaId,
                        'user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        return redirect()
            ->route('carteros.cartero')
            ->with('success', 'Correspondencia entregada correctamente.');
    }

    public function addAttempt(Request $request)
    {
        $validated = $request->validate([
            'tipo_paquete' => ['required', 'in:EMS,CERTI'],
            'id' => ['required', 'integer'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $estadoCarteroId = $this->resolveEstadoCarteroId();
        $estadoProvinciaId = $this->resolveEstadoProvinciaId();
        $estadoDevolucionId = $this->resolveEstadoDevolucionId();
        $userId = (int) $request->user()->id;
        $eventoIntentoId = self::EVENTO_ID_INTENTO_FALLIDO_ENTREGA;

        if ($validated['tipo_paquete'] === 'EMS') {
            $eventoExiste = DB::table('eventos')
                ->where('id', $eventoIntentoId)
                ->exists();

            if (!$eventoExiste) {
                throw ValidationException::withMessages([
                    'id' => "No existe el evento con ID {$eventoIntentoId} (Intento fallido de entrega del paquete.).",
                ]);
            }
        }

        $asignacion = $this->findAssignmentForUserByStates(
            $validated['tipo_paquete'],
            (int) $validated['id'],
            $userId,
            [$estadoCarteroId, $estadoProvinciaId]
        );

        DB::transaction(function () use ($validated, $asignacion, $estadoDevolucionId, $userId, $eventoIntentoId) {
            $this->updatePackageState($validated['tipo_paquete'], (int) $validated['id'], $estadoDevolucionId);
            $asignacion->intento = ((int) $asignacion->intento) + 1;
            $asignacion->id_estados = $estadoDevolucionId;
            $asignacion->descripcion = $validated['descripcion'] ?? $asignacion->descripcion;
            $asignacion->save();

            if ($validated['tipo_paquete'] === 'EMS') {
                $codigo = trim((string) PaqueteEms::query()
                    ->where('id', (int) $validated['id'])
                    ->value('codigo'));

                if ($codigo !== '') {
                    DB::table('eventos_ems')->insert([
                        'codigo' => $codigo,
                        'evento_id' => $eventoIntentoId,
                        'user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        return redirect()
            ->route('carteros.devolucion')
            ->with('success', 'Intento registrado y paquete enviado a DEVOLUCION.');
    }

    private function combinedDataResponse(Request $request, ?int $estadoId = null, ?int $userId = null): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $codigo = trim((string) $request->query('codigo', ''));

        $emsFilterIds = null;
        $certiFilterIds = null;

        if ($estadoId !== null || $userId !== null) {
            $base = Cartero::query();

            if ($estadoId !== null) {
                $base->where('id_estados', $estadoId);
            }

            if ($userId !== null) {
                $base->where('id_user', $userId);
            }

            $emsFilterIds = (clone $base)
                ->whereNotNull('id_paquetes_ems')
                ->pluck('id_paquetes_ems')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $certiFilterIds = (clone $base)
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
                'precio',
                'estado_id',
                'created_at',
            ])
            ->when($codigo !== '', function ($query) use ($codigo) {
                $query->whereRaw('LOWER(codigo) LIKE ?', ['%' . mb_strtolower($codigo) . '%']);
            })
            ->when($estadoId !== null || $userId !== null, function ($query) use ($emsFilterIds) {
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
                    'precio' => $item->precio,
                    'estado_id' => $item->estado_id,
                    'user_id' => null,
                    'asignado_a' => null,
                    'intento' => 0,
                    'recibido_por' => null,
                    'descripcion' => null,
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
            ->when($estadoId !== null || $userId !== null, function ($query) use ($certiFilterIds) {
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
                    'precio' => 0,
                    'estado_id' => $item->estado_id,
                    'user_id' => null,
                    'asignado_a' => null,
                    'intento' => 0,
                    'recibido_por' => null,
                    'descripcion' => null,
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
        $estadoNombres = Estado::query()
            ->pluck('nombre_estado', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
            ->all();
        $pageRows = collect($pageRows)->map(function ($row) use ($estadoNombres) {
            $row['estado'] = $estadoNombres[(int) ($row['estado_id'] ?? 0)] ?? null;
            return $row;
        })->values();

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
                $row['intento'] = (int) $asignacion->intento;
                $row['recibido_por'] = $asignacion->recibido_por;
                $row['descripcion'] = $asignacion->descripcion;
            }

            return $row;
        })->values();
    }

    private function resolveEstadoAsignadoId(): int
    {
        return $this->resolveEstadoByName('ASIGNADO');
    }

    private function resolveEstadoCarteroId(): int
    {
        return $this->resolveEstadoByName('CARTERO');
    }

    private function resolveEstadoDomicilioId(): int
    {
        return $this->resolveEstadoByName('DOMICILIO');
    }

    private function resolveEstadoDevolucionId(): int
    {
        return $this->resolveEstadoByName('DEVOLUCION');
    }

    private function resolveEstadoProvinciaId(): int
    {
        return $this->resolveEstadoByName('PROVINCIA');
    }

    private function findAssignmentForUser(string $tipoPaquete, int $id, int $userId, int $estadoId): Cartero
    {
        return Cartero::query()
            ->where('id_user', $userId)
            ->where('id_estados', $estadoId)
            ->where(function ($q) use ($tipoPaquete, $id) {
                if ($tipoPaquete === 'EMS') {
                    $q->where('id_paquetes_ems', $id);
                } else {
                    $q->where('id_paquetes_certi', $id);
                }
            })
            ->firstOrFail();
    }

    private function findAssignmentForUserByStates(string $tipoPaquete, int $id, int $userId, array $estadoIds): Cartero
    {
        return Cartero::query()
            ->where('id_user', $userId)
            ->whereIn('id_estados', $estadoIds)
            ->where(function ($q) use ($tipoPaquete, $id) {
                if ($tipoPaquete === 'EMS') {
                    $q->where('id_paquetes_ems', $id);
                } else {
                    $q->where('id_paquetes_certi', $id);
                }
            })
            ->firstOrFail();
    }

    private function getPackageForType(string $tipoPaquete, int $id): array
    {
        if ($tipoPaquete === 'EMS') {
            $pkg = PaqueteEms::query()
                ->where('id', $id)
                ->firstOrFail(['id', 'codigo', 'nombre_destinatario as destinatario', 'ciudad']);

            return [
                'codigo' => $pkg->codigo,
                'destinatario' => $pkg->destinatario,
                'ciudad' => $pkg->ciudad,
            ];
        }

        $pkg = PaqueteCerti::query()
            ->where('id', $id)
            ->firstOrFail(['id', 'codigo', 'destinatario', 'cuidad as ciudad']);

        return [
            'codigo' => $pkg->codigo,
            'destinatario' => $pkg->destinatario,
            'ciudad' => $pkg->ciudad,
        ];
    }

    private function updatePackageState(string $tipoPaquete, int $id, int $estadoId): void
    {
        if ($tipoPaquete === 'EMS') {
            PaqueteEms::query()->where('id', $id)->update(['estado_id' => $estadoId]);
            return;
        }

        PaqueteCerti::query()->where('id', $id)->update(['fk_estado' => $estadoId]);
    }

    private function resolveEstadoByName(string $estadoNombre): int
    {
        $estadoId = Estado::query()
            ->whereRaw('UPPER(nombre_estado) = ?', [mb_strtoupper($estadoNombre)])
            ->value('id');

        if (!$estadoId) {
            throw ValidationException::withMessages([
                'estado' => "No existe el estado {$estadoNombre} en la tabla estados.",
            ]);
        }

        return (int) $estadoId;
    }

    private function nextGuideNumber(): string
    {
        $prefix = 'AGBC';

        $last = DB::table('cartero_guias')
            ->where('guia', 'like', $prefix . '-%')
            ->orWhere('guia', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('guia');

        $next = 1;
        if ($last && preg_match('/^AGBC(\d{5})$/', (string) $last, $matches)) {
            $value = (int) $matches[1];
            if ($value > 0) {
                $next = $value + 1;
            }
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
