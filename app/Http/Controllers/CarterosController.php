<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Cartero;
use App\Models\CarteroAssignmentReport;
use App\Models\Estado;
use App\Models\Evento;
use App\Models\PaqueteCerti;
use App\Models\PaqueteEms;
use App\Models\PaqueteOrdi;
use App\Models\Recojo as RecojoContrato;
use App\Models\SolicitudCliente;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CarterosController extends Controller
{
    private const EVENTO_ID_PAQUETE_CAMINO_ENTREGA_FISICA = 184;
    private const EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE = 316;
    private const EVENTO_ID_INTENTO_FALLIDO_ENTREGA = 315;
    private const EVENTO_DESASIGNAR_CARTERO = 'Desasignado de CARTERO y devuelto a estado anterior desde Carteros Asignados.';
    private const RETURN_TO_WINDOW_OBSERVATIONS = [
        'DESCONOCIDO',
        'SE MUDO',
        'DIRECCION INSUFICIENTE',
        'SE AUSENTO',
        'RECHAZADO',
        'NO RECLAMADO',
        'NOTIFICADO',
    ];
    private const DISTRIBUTION_ASSIGNEE_ROLES = [
        'auxiliar_urbano',
        'auxiliar_urbano_dnd',
        'auxiliar_7',
        'cartero_ems',
        'carteros_ems',
    ];

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

    public function devolucion(Request $request)
    {
        $userCity = $this->normalizeUserCity((string) optional($request->user())->ciudad);
        $carterosDepartamento = collect();

        if ($userCity !== '') {
            $carterosDepartamento = User::query()
                ->whereHas('roles', function ($query) {
                    $query->whereIn(DB::raw('LOWER(name)'), self::DISTRIBUTION_ASSIGNEE_ROLES);
                })
                ->whereRaw('TRIM(UPPER(ciudad)) = ?', [$userCity])
                ->orderBy('name')
                ->get(['id', 'name', 'ciudad']);
        }

        return view('carteros.devolucion', [
            'carterosDepartamento' => $carterosDepartamento,
        ]);
    }

    public function domicilio()
    {
        return view('carteros.domicilio');
    }

    public function distributionAssignmentReport(Request $request, string $token)
    {
        $this->authorizeRoutePermission('carteros.distribucion');
        $this->authorizeAnyFeaturePermission([
            'feature.carteros.distribucion.assign',
            'feature.carteros.distribucion.selfassign',
        ]);

        $storedReport = CarteroAssignmentReport::query()
            ->with(['assignedUser:id,name,ciudad', 'actorUser:id,name,ciudad'])
            ->where('token', $token)
            ->orWhere('codigo', $token)
            ->first();

        $report = $storedReport ? [
            'rows' => $storedReport->rows ?? [],
            'assigned_at' => $storedReport->assigned_at,
            'assigned_user' => $storedReport->assignedUser,
            'actor_user' => $storedReport->actorUser,
            'regional' => $storedReport->regional,
            'summary_by_type' => $storedReport->summary_by_type ?? [],
            'total_assigned' => (int) $storedReport->total_assigned,
            'codigo_reporte' => $storedReport->codigo,
        ] : null;

        if (! $report) {
            $reports = (array) $request->session()->get('carteros.assignment_reports', []);
            $report = $reports[$token] ?? null;
        }

        if (!is_array($report) || empty($report['rows'])) {
            abort(404, 'No se encontro el reporte de asignacion solicitado.');
        }

        $filename = 'reporte-asignacion-cartero-' . ($report['codigo_reporte'] ?? $token) . '.pdf';
        $pdf = Pdf::loadView('carteros.asignacion-reporte', $report)->setPaper('A4', 'portrait');

        return $pdf->stream($filename);
    }

    public function entregaForm(Request $request)
    {
        $data = $request->validate([
            'tipo_paquete' => ['required', 'in:EMS,CERTI,CONTRATO,ORDI,SOLICITUD'],
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
        $this->authorizeRoutePermission('carteros.distribucion');
        $this->authorizeFeaturePermission('feature.carteros.distribucion.assign');

        $userCity = $this->normalizeUserCity((string) optional(auth()->user())->ciudad);
        if ($userCity === '') {
            return response()->json(['data' => []]);
        }

        $users = User::query()
            ->whereHas('roles', function ($query) {
                $query->whereIn(DB::raw('LOWER(name)'), self::DISTRIBUTION_ASSIGNEE_ROLES);
            })
            ->where(function ($query) use ($userCity) {
                $query->whereRaw('TRIM(UPPER(ciudad)) = ?', [$userCity]);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'ciudad']);

        return response()->json(['data' => $users]);
    }

    public function distribucionData(Request $request): JsonResponse
    {
        $this->authorizeRoutePermission('carteros.distribucion');

        return $this->combinedDataResponse(
            $request,
            null,
            null,
            false,
            false,
            false,
            null,
            $this->normalizeUserCity((string) optional($request->user())->ciudad)
        );
    }

    public function asignadosData(Request $request): JsonResponse
    {
        $this->authorizeRoutePermission('carteros.asignados');

        return $this->combinedDataResponse($request, $this->resolveEstadoCarteroId(), null, true);
    }

    public function carteroData(Request $request): JsonResponse
    {
        $this->authorizeRoutePermission('carteros.cartero');

        return $this->combinedDataResponse(
            $request,
            $this->resolveEstadoCarteroId(),
            (int) $request->user()->id,
            true
        );
    }

    public function provinciaData(Request $request): JsonResponse
    {
        $this->authorizeRoutePermission('carteros.cartero');
        $this->authorizeFeaturePermission('feature.carteros.cartero.province');

        return $this->combinedDataResponse(
            $request,
            $this->resolveEstadoProvinciaId(),
            (int) $request->user()->id,
            true
        );
    }

    public function devolucionData(Request $request): JsonResponse
    {
        $this->authorizeRoutePermission('carteros.devolucion');

        $allowedPackageTypes = $this->devolucionPackageTypesForUser($request->user());

        return $this->combinedDataResponse(
            $request,
            $this->resolveEstadoDevolucionId(),
            (int) $request->user()->id,
            false,
            false,
            true,
            $allowedPackageTypes
        );
    }

    public function domicilioData(Request $request): JsonResponse
    {
        $this->authorizeRoutePermission('carteros.domicilio');

        return $this->combinedDataResponse(
            $request,
            $this->resolveEstadoByName('ENTREGADO'),
            null,
            false,
            true
        );
    }

    public function assign(Request $request): JsonResponse
    {
        $this->authorizeRoutePermission('carteros.distribucion');

        $validated = $request->validate([
            'assignment_mode' => ['required', 'in:auto,user'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.tipo_paquete' => ['required', 'in:EMS,CERTI,CONTRATO,ORDI,SOLICITUD'],
        ]);

        if ($validated['assignment_mode'] === 'auto') {
            $this->authorizeAnyFeaturePermission([
                'feature.carteros.distribucion.assign',
                'feature.carteros.distribucion.selfassign',
            ]);
        } else {
            $this->authorizeFeaturePermission('feature.carteros.distribucion.assign');
        }

        $assigneeUserId = $validated['assignment_mode'] === 'auto'
            ? (int) $request->user()->id
            : (int) ($validated['user_id'] ?? 0);
        if ($assigneeUserId <= 0) {
            throw ValidationException::withMessages([
                'user_id' => 'Debes seleccionar un usuario para asignar.',
            ]);
        }
        $actorUserId = (int) $request->user()->id;
        $actorUser = User::query()->find($actorUserId, ['id', 'name', 'ciudad']);
        $assigneeUser = User::query()->find($assigneeUserId, ['id', 'name', 'ciudad']);

        if ($validated['assignment_mode'] === 'user') {
            $assigneeHasAllowedRole = User::query()
                ->whereKey($assigneeUserId)
                ->whereHas('roles', function ($query) {
                    $query->whereIn(DB::raw('LOWER(name)'), self::DISTRIBUTION_ASSIGNEE_ROLES);
                })
                ->exists();

            if (! $assigneeHasAllowedRole) {
                throw ValidationException::withMessages([
                    'user_id' => 'El usuario seleccionado no tiene un rol habilitado para distribucion.',
                ]);
            }

            if (! $this->isSameUserCity($request->user(), $assigneeUser)) {
                throw ValidationException::withMessages([
                    'user_id' => 'Solo puedes asignar a usuarios de tu mismo departamento.',
                ]);
            }
        }
        $eventoId = $this->resolveDynamicEventId($this->assignmentEventName($actorUser, $assigneeUser));
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
        $ordiIds = collect($validated['items'])
            ->where('tipo_paquete', 'ORDI')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $contratoIds = collect($validated['items'])
            ->where('tipo_paquete', 'CONTRATO')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $solicitudIds = collect($validated['items'])
            ->where('tipo_paquete', 'SOLICITUD')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $updatedEms = 0;
        $updatedCerti = 0;
        $updatedOrdi = 0;
        $updatedContrato = 0;
        $updatedSolicitud = 0;
        $previousEmsStates = PaqueteEms::query()->whereIn('id', $emsIds)->pluck('estado_id', 'id')->map(fn ($value) => (int) $value)->all();
        $previousCertiStates = PaqueteCerti::query()->whereIn('id', $certiIds)->pluck('fk_estado', 'id')->map(fn ($value) => (int) $value)->all();
        $previousOrdiStates = PaqueteOrdi::query()->whereIn('id', $ordiIds)->pluck('fk_estado', 'id')->map(fn ($value) => (int) $value)->all();
        $previousContratoStates = RecojoContrato::query()->whereIn('id', $contratoIds)->pluck('estados_id', 'id')->map(fn ($value) => (int) $value)->all();
        $previousSolicitudStates = SolicitudCliente::query()->whereIn('id', $solicitudIds)->pluck('estado_id', 'id')->map(fn ($value) => (int) $value)->all();
        $destinationConflicts = $this->packageDestinationConflicts($validated['items'], (string) ($assigneeUser?->ciudad ?? ''));
        if ($destinationConflicts !== []) {
            throw ValidationException::withMessages([
                'items' => 'Solo puedes asignar paquetes con destino igual al departamento del cartero: ' . implode(' ', $destinationConflicts),
            ]);
        }

        $assignmentConflicts = $this->assignedToAnotherUserConflicts($validated['items'], $assigneeUserId);
        if ($assignmentConflicts !== []) {
            throw ValidationException::withMessages([
                'items' => 'No se puede asignar: ' . implode(' ', $assignmentConflicts),
            ]);
        }

        DB::transaction(function () use (
            &$updatedEms,
            &$updatedCerti,
            &$updatedOrdi,
            &$updatedContrato,
            &$updatedSolicitud,
            $emsIds,
            $certiIds,
            $ordiIds,
            $contratoIds,
            $solicitudIds,
            $estadoAsignadoId,
            $assigneeUserId,
            $actorUserId,
            $eventoId,
            $previousEmsStates,
            $previousCertiStates,
            $previousOrdiStates,
            $previousContratoStates,
            $previousSolicitudStates,
            $validated
        ) {
            $assignmentConflicts = $this->assignedToAnotherUserConflicts($validated['items'], $assigneeUserId, true);
            if ($assignmentConflicts !== []) {
                throw ValidationException::withMessages([
                    'items' => 'No se puede asignar: ' . implode(' ', $assignmentConflicts),
                ]);
            }

            if (!empty($emsIds)) {
                $updatedEms = PaqueteEms::query()
                    ->whereIn('id', $emsIds)
                    ->update([
                        'estado_id' => $estadoAsignadoId,
                    ]);
                foreach ($emsIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_ems' => $id]);
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $estadoAsignadoId;
                    $asignacion->id_estado_anterior = $previousEmsStates[$id] ?? null;
                    $asignacion->id_user = $assigneeUserId;
                    $asignacion->save();
                }
                $this->insertEventosPorTipoDesdeIds('EMS', $emsIds, $eventoId, $assigneeUserId);
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
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $estadoAsignadoId;
                    $asignacion->id_estado_anterior = $previousCertiStates[$id] ?? null;
                    $asignacion->id_user = $assigneeUserId;
                    $asignacion->save();
                }
                $this->insertEventosPorTipoDesdeIds('CERTI', $certiIds, $eventoId, $assigneeUserId);
            }
            if (!empty($ordiIds)) {
                $updatedOrdi = PaqueteOrdi::query()
                    ->whereIn('id', $ordiIds)
                    ->update([
                        'fk_estado' => $estadoAsignadoId,
                    ]);
                foreach ($ordiIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_ordi' => $id]);
                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $estadoAsignadoId;
                    $asignacion->id_estado_anterior = $previousOrdiStates[$id] ?? null;
                    $asignacion->id_user = $assigneeUserId;
                    $asignacion->save();
                }
                $this->insertEventosPorTipoDesdeIds('ORDI', $ordiIds, $eventoId, $assigneeUserId);
            }
            if (!empty($contratoIds)) {
                $updatedContrato = RecojoContrato::query()
                    ->whereIn('id', $contratoIds)
                    ->update([
                        'estados_id' => $estadoAsignadoId,
                    ]);
                foreach ($contratoIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_contrato' => $id]);
                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $estadoAsignadoId;
                    $asignacion->id_estado_anterior = $previousContratoStates[$id] ?? null;
                    $asignacion->id_user = $assigneeUserId;
                    $asignacion->save();
                }
                $this->insertEventosPorTipoDesdeIds('CONTRATO', $contratoIds, $eventoId, $assigneeUserId);
            }
            if (!empty($solicitudIds)) {
                $updatedSolicitud = SolicitudCliente::query()
                    ->whereIn('id', $solicitudIds)
                    ->update([
                        'estado_id' => $estadoAsignadoId,
                    ]);
                foreach ($solicitudIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_solicitud_cliente' => $id]);
                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_estados = $estadoAsignadoId;
                    $asignacion->id_estado_anterior = $previousSolicitudStates[$id] ?? null;
                    $asignacion->id_user = $assigneeUserId;
                    $asignacion->save();
                }
                $this->insertEventosPorTipoDesdeIds('SOLICITUD', $solicitudIds, $eventoId, $assigneeUserId);
            }
        });
        return response()->json([
            'message' => 'Paquetes asignados correctamente en estado CARTERO.',
            'updated' => [
                'ems' => $updatedEms,
                'certi' => $updatedCerti,
                'ordi' => $updatedOrdi,
                'contrato' => $updatedContrato,
                'solicitud' => $updatedSolicitud,
                'total' => $updatedEms + $updatedCerti + $updatedOrdi + $updatedContrato + $updatedSolicitud,
            ],
            'report_url' => route('carteros.distribucion.report', [
                'token' => $this->storeAssignmentReport(
                    $request,
                    $validated['items'],
                    $assigneeUserId,
                    (int) $request->user()->id
                ),
            ]),
        ]);
    }
    public function returnToAlmacen(Request $request): JsonResponse
    {
        $this->authorizeRoutePermission('carteros.devolucion');
        $this->authorizeFeaturePermission('feature.carteros.devolucion.restore');

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.tipo_paquete' => ['required', 'in:EMS,CERTI,CONTRATO,ORDI,SOLICITUD'],
        ]);
        $estadoRecibidoId = $this->resolveEstadoByName('RECIBIDO');
        $actorUserId = (int) $request->user()->id;
        $emsIds = collect($validated['items'])->where('tipo_paquete', 'EMS')->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $certiIds = collect($validated['items'])->where('tipo_paquete', 'CERTI')->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $ordiIds = collect($validated['items'])->where('tipo_paquete', 'ORDI')->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $contratoIds = collect($validated['items'])->where('tipo_paquete', 'CONTRATO')->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $solicitudIds = collect($validated['items'])->where('tipo_paquete', 'SOLICITUD')->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $updatedEms = 0;
        $updatedCerti = 0;
        $updatedOrdi = 0;
        $updatedContrato = 0;
        $updatedSolicitud = 0;
        DB::transaction(function () use (&$updatedEms, &$updatedCerti, &$updatedOrdi, &$updatedContrato, &$updatedSolicitud, $emsIds, $certiIds, $ordiIds, $contratoIds, $solicitudIds, $estadoRecibidoId, $actorUserId) {
            if (!empty($emsIds)) {
                $updatedEms = PaqueteEms::query()->whereIn('id', $emsIds)->update(['estado_id' => $estadoRecibidoId]);
                foreach ($emsIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_ems' => $id]);
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $estadoRecibidoId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                }
            }
            if (!empty($certiIds)) {
                $updatedCerti = PaqueteCerti::query()->whereIn('id', $certiIds)->update(['fk_estado' => $estadoRecibidoId]);
                foreach ($certiIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_certi' => $id]);
                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $estadoRecibidoId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                }
            }
            if (!empty($ordiIds)) {
                $updatedOrdi = PaqueteOrdi::query()->whereIn('id', $ordiIds)->update(['fk_estado' => $estadoRecibidoId]);
                foreach ($ordiIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_ordi' => $id]);
                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $estadoRecibidoId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                }
            }
            if (!empty($contratoIds)) {
                $updatedContrato = RecojoContrato::query()->whereIn('id', $contratoIds)->update(['estados_id' => $estadoRecibidoId]);
                foreach ($contratoIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_contrato' => $id]);
                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $estadoRecibidoId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                }
            }
            if (!empty($solicitudIds)) {
                $updatedSolicitud = SolicitudCliente::query()->whereIn('id', $solicitudIds)->update(['estado_id' => $estadoRecibidoId]);
                foreach ($solicitudIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_solicitud_cliente' => $id]);
                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_estados = $estadoRecibidoId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                }
            }
        });
        return response()->json([
            'message' => 'Paquetes devueltos a RECIBIDO.',
            'updated' => [
                'ems' => $updatedEms,
                'certi' => $updatedCerti,
                'ordi' => $updatedOrdi,
                'contrato' => $updatedContrato,
                'solicitud' => $updatedSolicitud,
                'total' => $updatedEms + $updatedCerti + $updatedOrdi + $updatedContrato + $updatedSolicitud,
            ],
        ]);
    }
    public function acceptPackages(Request $request): JsonResponse
    {
        $this->authorizeRoutePermission('carteros.cartero');

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.tipo_paquete' => ['required', 'in:EMS,CERTI,CONTRATO,ORDI,SOLICITUD'],
        ]);
        $estadoCarteroId = $this->resolveEstadoCarteroId();
        $actorUserId = (int) $request->user()->id;
        $emsIds = collect($validated['items'])->where('tipo_paquete', 'EMS')->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $certiIds = collect($validated['items'])->where('tipo_paquete', 'CERTI')->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $ordiIds = collect($validated['items'])->where('tipo_paquete', 'ORDI')->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $contratoIds = collect($validated['items'])->where('tipo_paquete', 'CONTRATO')->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $solicitudIds = collect($validated['items'])->where('tipo_paquete', 'SOLICITUD')->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $updatedEms = 0;
        $updatedCerti = 0;
        $updatedOrdi = 0;
        $updatedContrato = 0;
        $updatedSolicitud = 0;
        $previousEmsStates = PaqueteEms::query()->whereIn('id', $emsIds)->pluck('estado_id', 'id')->map(fn ($value) => (int) $value)->all();
        $previousCertiStates = PaqueteCerti::query()->whereIn('id', $certiIds)->pluck('fk_estado', 'id')->map(fn ($value) => (int) $value)->all();
        $previousOrdiStates = PaqueteOrdi::query()->whereIn('id', $ordiIds)->pluck('fk_estado', 'id')->map(fn ($value) => (int) $value)->all();
        $previousContratoStates = RecojoContrato::query()->whereIn('id', $contratoIds)->pluck('estados_id', 'id')->map(fn ($value) => (int) $value)->all();
        $previousSolicitudStates = SolicitudCliente::query()->whereIn('id', $solicitudIds)->pluck('estado_id', 'id')->map(fn ($value) => (int) $value)->all();
        $assignmentConflicts = $this->assignedToAnotherUserConflicts($validated['items'], $actorUserId);
        if ($assignmentConflicts !== []) {
            throw ValidationException::withMessages([
                'items' => 'No se puede aceptar: ' . implode(' ', $assignmentConflicts),
            ]);
        }

        DB::transaction(function () use (&$updatedEms, &$updatedCerti, &$updatedOrdi, &$updatedContrato, &$updatedSolicitud, $emsIds, $certiIds, $ordiIds, $contratoIds, $solicitudIds, $estadoCarteroId, $actorUserId, $validated) {
            $assignmentConflicts = $this->assignedToAnotherUserConflicts($validated['items'], $actorUserId, true);
            if ($assignmentConflicts !== []) {
                throw ValidationException::withMessages([
                    'items' => 'No se puede aceptar: ' . implode(' ', $assignmentConflicts),
                ]);
            }

            if (!empty($emsIds)) {
                $updatedEms = PaqueteEms::query()->whereIn('id', $emsIds)->update(['estado_id' => $estadoCarteroId]);
                foreach ($emsIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_ems' => $id]);
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $estadoCarteroId;
                    $asignacion->id_estado_anterior = $previousEmsStates[$id] ?? null;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                }
            }
            if (!empty($certiIds)) {
                $updatedCerti = PaqueteCerti::query()->whereIn('id', $certiIds)->update(['fk_estado' => $estadoCarteroId]);
                foreach ($certiIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_certi' => $id]);
                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $estadoCarteroId;
                    $asignacion->id_estado_anterior = $previousCertiStates[$id] ?? null;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                }
            }
            if (!empty($ordiIds)) {
                $updatedOrdi = PaqueteOrdi::query()->whereIn('id', $ordiIds)->update(['fk_estado' => $estadoCarteroId]);
                foreach ($ordiIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_ordi' => $id]);
                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $estadoCarteroId;
                    $asignacion->id_estado_anterior = $previousOrdiStates[$id] ?? null;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                }
            }
            if (!empty($contratoIds)) {
                $updatedContrato = RecojoContrato::query()->whereIn('id', $contratoIds)->update(['estados_id' => $estadoCarteroId]);
                foreach ($contratoIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_contrato' => $id]);
                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $estadoCarteroId;
                    $asignacion->id_estado_anterior = $previousContratoStates[$id] ?? null;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                }
            }
            if (!empty($solicitudIds)) {
                $updatedSolicitud = SolicitudCliente::query()->whereIn('id', $solicitudIds)->update(['estado_id' => $estadoCarteroId]);
                foreach ($solicitudIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_solicitud_cliente' => $id]);
                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_estados = $estadoCarteroId;
                    $asignacion->id_estado_anterior = $previousSolicitudStates[$id] ?? null;
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
                'ordi' => $updatedOrdi,
                'contrato' => $updatedContrato,
                'solicitud' => $updatedSolicitud,
                'total' => $updatedEms + $updatedCerti + $updatedOrdi + $updatedContrato + $updatedSolicitud,
            ],
        ]);
    }

    public function dropOffSelected(Request $request): JsonResponse
    {
        $this->authorizeRoutePermission('carteros.cartero');
        $this->authorizeFeaturePermission('feature.carteros.cartero.deliver');

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.tipo_paquete' => ['required', 'in:CERTI,ORDI'],
        ]);

        $estadoCarteroId = $this->resolveEstadoCarteroId();
        $estadoEntregadoId = $this->resolveEstadoByName('ENTREGADO');
        $actorUserId = (int) $request->user()->id;

        $certiIds = collect($validated['items'])
            ->where('tipo_paquete', 'CERTI')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $ordiIds = collect($validated['items'])
            ->where('tipo_paquete', 'ORDI')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $certiIds = Cartero::query()
            ->where('id_user', $actorUserId)
            ->where('id_estados', $estadoCarteroId)
            ->whereIn('id_paquetes_certi', $certiIds ?: [0])
            ->pluck('id_paquetes_certi')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $ordiIds = Cartero::query()
            ->where('id_user', $actorUserId)
            ->where('id_estados', $estadoCarteroId)
            ->whereIn('id_paquetes_ordi', $ordiIds ?: [0])
            ->pluck('id_paquetes_ordi')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($certiIds) && empty($ordiIds)) {
            throw ValidationException::withMessages([
                'items' => 'Selecciona certificados u ordinarios que esten en tu bandeja CARTERO.',
            ]);
        }

        $updatedCerti = 0;
        $updatedOrdi = 0;

        DB::transaction(function () use (&$updatedCerti, &$updatedOrdi, $certiIds, $ordiIds, $estadoEntregadoId, $actorUserId) {
            if (!empty($certiIds)) {
                $updatedCerti = PaqueteCerti::query()
                    ->whereIn('id', $certiIds)
                    ->update(['fk_estado' => $estadoEntregadoId]);

                Cartero::query()
                    ->whereIn('id_paquetes_certi', $certiIds)
                    ->where('id_user', $actorUserId)
                    ->update(['id_estados' => $estadoEntregadoId]);

                $this->insertEventosPorTipoDesdeIds('CERTI', $certiIds, self::EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE, $actorUserId);
            }

            if (!empty($ordiIds)) {
                $updatedOrdi = PaqueteOrdi::query()
                    ->whereIn('id', $ordiIds)
                    ->update(['fk_estado' => $estadoEntregadoId]);

                Cartero::query()
                    ->whereIn('id_paquetes_ordi', $ordiIds)
                    ->where('id_user', $actorUserId)
                    ->update(['id_estados' => $estadoEntregadoId]);

                $this->insertEventosPorTipoDesdeIds('ORDI', $ordiIds, self::EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE, $actorUserId);
            }
        });

        return response()->json([
            'message' => 'Paquetes dados de baja correctamente y enviados a ENTREGADO.',
            'updated' => [
                'certi' => $updatedCerti,
                'ordi' => $updatedOrdi,
                'total' => $updatedCerti + $updatedOrdi,
            ],
        ]);
    }

    public function returnSelectedToVentanilla(Request $request): JsonResponse
    {
        $this->authorizeRoutePermission('carteros.cartero');
        $this->authorizeFeaturePermission('feature.carteros.cartero.returnwindow');

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.tipo_paquete' => ['required', 'in:CERTI,ORDI'],
            'observacion' => ['required', 'string', 'in:' . implode(',', self::RETURN_TO_WINDOW_OBSERVATIONS)],
        ]);

        $estadoCarteroId = $this->resolveEstadoCarteroId();
        $estadoDevolucionId = $this->resolveEstadoDevolucionId();
        $actorUserId = (int) $request->user()->id;
        $actorName = trim((string) ($request->user()->name ?? 'SIN USUARIO'));
        $observacion = trim((string) $validated['observacion']);
        $eventoId = $this->resolveDynamicEventId(
            'Intento de entrega registrado por ' . ($actorName !== '' ? $actorName : 'SIN USUARIO') . '. Devuelto a ventanilla: ' . $observacion . '.'
        );

        $certiIds = collect($validated['items'])
            ->where('tipo_paquete', 'CERTI')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $ordiIds = collect($validated['items'])
            ->where('tipo_paquete', 'ORDI')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $certiIds = Cartero::query()
            ->where('id_user', $actorUserId)
            ->where('id_estados', $estadoCarteroId)
            ->whereIn('id_paquetes_certi', $certiIds ?: [0])
            ->pluck('id_paquetes_certi')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $ordiIds = Cartero::query()
            ->where('id_user', $actorUserId)
            ->where('id_estados', $estadoCarteroId)
            ->whereIn('id_paquetes_ordi', $ordiIds ?: [0])
            ->pluck('id_paquetes_ordi')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($certiIds) && empty($ordiIds)) {
            throw ValidationException::withMessages([
                'items' => 'Selecciona certificados u ordinarios que esten en tu bandeja CARTERO.',
            ]);
        }

        $updatedCerti = 0;
        $updatedOrdi = 0;

        DB::transaction(function () use (&$updatedCerti, &$updatedOrdi, $certiIds, $ordiIds, $estadoDevolucionId, $actorUserId, $eventoId, $observacion) {
            if (!empty($certiIds)) {
                $updatedCerti = PaqueteCerti::query()
                    ->whereIn('id', $certiIds)
                    ->update([
                        'fk_estado' => $estadoDevolucionId,
                        'observaciones' => $observacion,
                    ]);

                Cartero::query()
                    ->whereIn('id_paquetes_certi', $certiIds)
                    ->where('id_user', $actorUserId)
                    ->update([
                        'id_estados' => $estadoDevolucionId,
                        'intento' => DB::raw('COALESCE(intento, 0) + 1'),
                        'descripcion' => $observacion,
                    ]);

                $this->insertEventosPorTipoDesdeIds('CERTI', $certiIds, $eventoId, $actorUserId);
            }

            if (!empty($ordiIds)) {
                $updatedOrdi = PaqueteOrdi::query()
                    ->whereIn('id', $ordiIds)
                    ->update([
                        'fk_estado' => $estadoDevolucionId,
                        'observaciones' => $observacion,
                    ]);

                Cartero::query()
                    ->whereIn('id_paquetes_ordi', $ordiIds)
                    ->where('id_user', $actorUserId)
                    ->update([
                        'id_estados' => $estadoDevolucionId,
                        'intento' => DB::raw('COALESCE(intento, 0) + 1'),
                        'descripcion' => $observacion,
                    ]);

                $this->insertEventosPorTipoDesdeIds('ORDI', $ordiIds, $eventoId, $actorUserId);
            }
        });

        return response()->json([
            'message' => 'Intento registrado y paquetes enviados a DEVOLUCION para devolver a ventanilla.',
            'updated' => [
                'certi' => $updatedCerti,
                'ordi' => $updatedOrdi,
                'total' => $updatedCerti + $updatedOrdi,
            ],
        ]);
    }

    public function unassignFromCartero(Request $request): JsonResponse
    {
        $this->authorizeRoutePermission('carteros.asignados');
        $this->authorizeFeaturePermission('feature.carteros.asignados.unassign');

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.tipo_paquete' => ['required', 'in:EMS,CERTI,CONTRATO,ORDI,SOLICITUD'],
        ]);

        $actorUserId = (int) $request->user()->id;
        $eventoId = $this->resolveDynamicEventId(self::EVENTO_DESASIGNAR_CARTERO);

        $emsIds = collect($validated['items'])->where('tipo_paquete', 'EMS')->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $certiIds = collect($validated['items'])->where('tipo_paquete', 'CERTI')->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $ordiIds = collect($validated['items'])->where('tipo_paquete', 'ORDI')->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $contratoIds = collect($validated['items'])->where('tipo_paquete', 'CONTRATO')->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $solicitudIds = collect($validated['items'])->where('tipo_paquete', 'SOLICITUD')->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $updatedEms = 0;
        $updatedCerti = 0;
        $updatedOrdi = 0;
        $updatedContrato = 0;
        $updatedSolicitud = 0;

        DB::transaction(function () use (
            &$updatedEms,
            &$updatedCerti,
            &$updatedOrdi,
            &$updatedContrato,
            &$updatedSolicitud,
            $emsIds,
            $certiIds,
            $ordiIds,
            $contratoIds,
            $solicitudIds,
            $actorUserId,
            $eventoId
        ) {
            if (!empty($emsIds)) {
                foreach ($emsIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_ems' => $id]);
                    $targetEstadoId = $this->resolvePreviousStateForAssignment('EMS', $id, $asignacion);
                    if ($targetEstadoId <= 0) {
                        continue;
                    }

                    PaqueteEms::query()->where('id', $id)->update([
                        'estado_id' => $targetEstadoId,
                    ]);

                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $targetEstadoId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                    $updatedEms++;
                }

                $this->insertEventosPorTipoDesdeIds('EMS', $emsIds, $eventoId, $actorUserId);
            }

            if (!empty($certiIds)) {
                foreach ($certiIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_certi' => $id]);
                    $targetEstadoId = $this->resolvePreviousStateForAssignment('CERTI', $id, $asignacion);
                    if ($targetEstadoId <= 0) {
                        continue;
                    }

                    PaqueteCerti::query()->where('id', $id)->update([
                        'fk_estado' => $targetEstadoId,
                    ]);

                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $targetEstadoId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                    $updatedCerti++;
                }

                $this->insertEventosPorTipoDesdeIds('CERTI', $certiIds, $eventoId, $actorUserId);
            }

            if (!empty($ordiIds)) {
                foreach ($ordiIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_ordi' => $id]);
                    $targetEstadoId = $this->resolvePreviousStateForAssignment('ORDI', $id, $asignacion);
                    if ($targetEstadoId <= 0) {
                        continue;
                    }

                    PaqueteOrdi::query()->where('id', $id)->update([
                        'fk_estado' => $targetEstadoId,
                    ]);

                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $targetEstadoId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                    $updatedOrdi++;
                }

                $this->insertEventosPorTipoDesdeIds('ORDI', $ordiIds, $eventoId, $actorUserId);
            }

            if (!empty($contratoIds)) {
                foreach ($contratoIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_paquetes_contrato' => $id]);
                    $targetEstadoId = $this->resolvePreviousStateForAssignment('CONTRATO', $id, $asignacion);
                    if ($targetEstadoId <= 0) {
                        continue;
                    }

                    RecojoContrato::query()->where('id', $id)->update([
                        'estados_id' => $targetEstadoId,
                    ]);

                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_solicitud_cliente = null;
                    $asignacion->id_estados = $targetEstadoId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                    $updatedContrato++;
                }

                $this->insertEventosPorTipoDesdeIds('CONTRATO', $contratoIds, $eventoId, $actorUserId);
            }

            if (!empty($solicitudIds)) {
                foreach ($solicitudIds as $id) {
                    $asignacion = Cartero::query()->firstOrNew(['id_solicitud_cliente' => $id]);
                    $targetEstadoId = $this->resolvePreviousStateForAssignment('SOLICITUD', $id, $asignacion);
                    if ($targetEstadoId <= 0) {
                        continue;
                    }

                    SolicitudCliente::query()->where('id', $id)->update([
                        'estado_id' => $targetEstadoId,
                    ]);

                    $asignacion->id_paquetes_ems = null;
                    $asignacion->id_paquetes_certi = null;
                    $asignacion->id_paquetes_ordi = null;
                    $asignacion->id_paquetes_contrato = null;
                    $asignacion->id_estados = $targetEstadoId;
                    $asignacion->id_user = $actorUserId;
                    $asignacion->save();
                    $updatedSolicitud++;
                }

                $this->insertEventosPorTipoDesdeIds('SOLICITUD', $solicitudIds, $eventoId, $actorUserId);
            }
        });

        return response()->json([
            'message' => 'Paquete desasignado correctamente y devuelto a su estado anterior.',
            'updated' => [
                'ems' => $updatedEms,
                'certi' => $updatedCerti,
                'ordi' => $updatedOrdi,
                'contrato' => $updatedContrato,
                'solicitud' => $updatedSolicitud,
                'total' => $updatedEms + $updatedCerti + $updatedOrdi + $updatedContrato + $updatedSolicitud,
            ],
        ]);
    }

    public function registerGuide(Request $request): JsonResponse
    {
        $this->authorizeRoutePermission('carteros.cartero');
        $this->authorizeFeaturePermission('feature.carteros.cartero.guide');

        $validated = $request->validate([
            'transportadora' => ['required', 'string', 'max:255'],
            'provincia' => ['required', 'string', 'max:255'],
            'factura' => ['nullable', 'string', 'max:255'],
            'precio_total' => ['nullable', 'numeric', 'min:0'],
            'peso_total' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.tipo_paquete' => ['required', 'in:EMS,CERTI,CONTRATO,ORDI,SOLICITUD'],
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
        $contratoIds = $items->where('tipo_paquete', 'CONTRATO')->pluck('id')->all();
        $ordiIds = $items->where('tipo_paquete', 'ORDI')->pluck('id')->all();
        $solicitudIds = $items->where('tipo_paquete', 'SOLICITUD')->pluck('id')->all();

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

        $allowedContratoIds = (clone $allowedBase)
            ->whereNotNull('id_paquetes_contrato')
            ->pluck('id_paquetes_contrato')
            ->map(fn ($id) => (int) $id)
            ->all();

        $allowedOrdiIds = (clone $allowedBase)
            ->whereNotNull('id_paquetes_ordi')
            ->pluck('id_paquetes_ordi')
            ->map(fn ($id) => (int) $id)
            ->all();
        $allowedSolicitudIds = (clone $allowedBase)
            ->whereNotNull('id_solicitud_cliente')
            ->pluck('id_solicitud_cliente')
            ->map(fn ($id) => (int) $id)
            ->all();

        $invalidEms = collect($emsIds)->diff($allowedEmsIds)->values()->all();
        $invalidCerti = collect($certiIds)->diff($allowedCertiIds)->values()->all();
        $invalidContrato = collect($contratoIds)->diff($allowedContratoIds)->values()->all();
        $invalidOrdi = collect($ordiIds)->diff($allowedOrdiIds)->values()->all();
        $invalidSolicitud = collect($solicitudIds)->diff($allowedSolicitudIds)->values()->all();

        if (!empty($invalidEms) || !empty($invalidCerti) || !empty($invalidContrato) || !empty($invalidOrdi) || !empty($invalidSolicitud)) {
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

        $contratoRows = RecojoContrato::query()
            ->whereIn('id', $contratoIds ?: [0])
            ->get(['id', 'codigo', 'peso'])
            ->keyBy('id');

        $ordiRows = PaqueteOrdi::query()
            ->whereIn('id', $ordiIds ?: [0])
            ->get(['id', 'codigo', 'peso'])
            ->keyBy('id');
        $solicitudRows = SolicitudCliente::query()
            ->whereIn('id', $solicitudIds ?: [0])
            ->get(['id', 'codigo_solicitud', 'barcode', 'peso', 'precio'])
            ->keyBy('id');

        foreach ($items as $item) {
            if ($item['tipo_paquete'] === 'EMS' && !isset($emsRows[$item['id']])) {
                throw ValidationException::withMessages([
                    'items' => 'Uno o mas paquetes EMS ya no existen.',
                ]);
            }
            if ($item['tipo_paquete'] === 'CERTI' && !isset($certiRows[$item['id']])) {
                throw ValidationException::withMessages([
                    'items' => 'Uno o mas paquetes CERTI ya no existen.',
                ]);
            }
            if ($item['tipo_paquete'] === 'CONTRATO' && !isset($contratoRows[$item['id']])) {
                throw ValidationException::withMessages([
                    'items' => 'Uno o mas paquetes CONTRATO ya no existen.',
                ]);
            }
            if ($item['tipo_paquete'] === 'ORDI' && !isset($ordiRows[$item['id']])) {
                throw ValidationException::withMessages([
                    'items' => 'Uno o mas paquetes ORDI ya no existen.',
                ]);
            }
            if ($item['tipo_paquete'] === 'SOLICITUD' && !isset($solicitudRows[$item['id']])) {
                throw ValidationException::withMessages([
                    'items' => 'Una o mas solicitudes ya no existen.',
                ]);
            }
        }

        $result = DB::transaction(function () use ($validated, $userId, $items, $emsIds, $certiIds, $contratoIds, $ordiIds, $solicitudIds, $emsRows, $certiRows, $contratoRows, $ordiRows, $solicitudRows, $estadoProvinciaId) {
            $guia = $this->nextGuideNumber();
            $rowsToInsert = [];
            $manualPesoTotal = $validated['peso_total'] ?? null;
            $manualPrecioTotal = $validated['precio_total'] ?? null;
            $lastIndex = max(0, $items->count() - 1);

            foreach ($items->values() as $index => $item) {
                if ($item['tipo_paquete'] === 'EMS') {
                    $pkg = $emsRows[$item['id']];
                } elseif ($item['tipo_paquete'] === 'CONTRATO') {
                    $pkg = $contratoRows[$item['id']];
                } elseif ($item['tipo_paquete'] === 'ORDI') {
                    $pkg = $ordiRows[$item['id']];
                } elseif ($item['tipo_paquete'] === 'SOLICITUD') {
                    $pkg = $solicitudRows[$item['id']];
                } else {
                    $pkg = $certiRows[$item['id']];
                }

                $rowsToInsert[] = [
                    'guia' => $guia,
                    'transportadora' => $validated['transportadora'],
                    'provincia' => $validated['provincia'],
                    'user_id' => $userId,
                    'factura' => $validated['factura'] ?? null,
                    'codigo' => (string) ($pkg->codigo ?? $pkg->codigo_solicitud ?? $pkg->barcode ?? 'SIN CODIGO'),
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

            if (!empty($contratoIds)) {
                RecojoContrato::query()
                    ->whereIn('id', $contratoIds)
                    ->update(['estados_id' => $estadoProvinciaId]);
            }

            if (!empty($ordiIds)) {
                PaqueteOrdi::query()
                    ->whereIn('id', $ordiIds)
                    ->update(['fk_estado' => $estadoProvinciaId]);
            }
            if (!empty($solicitudIds)) {
                SolicitudCliente::query()
                    ->whereIn('id', $solicitudIds)
                    ->update(['estado_id' => $estadoProvinciaId]);
            }

            Cartero::query()
                ->where('id_user', $userId)
                ->where(function ($query) use ($emsIds, $certiIds, $contratoIds, $ordiIds, $solicitudIds) {
                    if (!empty($emsIds)) {
                        $query->whereIn('id_paquetes_ems', $emsIds);
                    }
                    if (!empty($certiIds)) {
                        $query->orWhereIn('id_paquetes_certi', $certiIds);
                    }
                    if (!empty($contratoIds)) {
                        $query->orWhereIn('id_paquetes_contrato', $contratoIds);
                    }
                    if (!empty($ordiIds)) {
                        $query->orWhereIn('id_paquetes_ordi', $ordiIds);
                    }
                    if (!empty($solicitudIds)) {
                        $query->orWhereIn('id_solicitud_cliente', $solicitudIds);
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
        $this->authorizeAnyFeaturePermission([
            'feature.carteros.entrega.deliver',
            'feature.carteros.cartero.deliver',
        ]);

        $fotoOpcional = $this->fotoOpcionalParaContratoLaPaz($request);
        $validated = $request->validate([
            'tipo_paquete' => ['required', 'in:EMS,CERTI,CONTRATO,ORDI,SOLICITUD'],
            'id' => ['required', 'integer'],
            'recibido_por' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'foto' => [$fotoOpcional ? 'nullable' : 'required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,heif'],
        ]);

        $estadoCarteroId = $this->resolveEstadoCarteroId();
        $estadoProvinciaId = $this->resolveEstadoProvinciaId();
        // Para entregas por cartero, marcar como ENTREGADO
        $estadoEntregadoId = $this->resolveEstadoByName('ENTREGADO');
        $userId = (int) $request->user()->id;
        $eventoEntregaId = self::EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE;

        $eventoExiste = DB::table('eventos')
            ->where('id', $eventoEntregaId)
            ->exists();

        if (!$eventoExiste) {
            throw ValidationException::withMessages([
                'id' => "No existe el evento con ID {$eventoEntregaId} (Paquete entregado exitosamente.).",
            ]);
        }

        $asignacion = $this->findAssignmentForUserByStates(
            $validated['tipo_paquete'],
            (int) $validated['id'],
            $userId,
            [$estadoCarteroId, $estadoProvinciaId]
        );
        $imagenPath = $this->storeDeliveryPhoto($request, $asignacion->imagen ?? $asignacion->foto);
        $syncWarning = null;
        $externalImage = $this->buildExternalImagePayload($request->file('foto'));
        $carteroNombre = trim((string) ($request->user()->name ?? ''));

        DB::transaction(function () use ($validated, $asignacion, $estadoEntregadoId, $userId, $eventoEntregaId, $imagenPath) {
            $this->updatePackageState($validated['tipo_paquete'], (int) $validated['id'], $estadoEntregadoId);
            $asignacion->id_estados = $estadoEntregadoId;
            $asignacion->recibido_por = $validated['recibido_por'];
            $asignacion->descripcion = $validated['descripcion'] ?? null;
            $asignacion->imagen = $imagenPath;
            $asignacion->save();
            $this->updatePackageImage($validated['tipo_paquete'], (int) $validated['id'], $imagenPath);
            if ($validated['tipo_paquete'] === 'SOLICITUD') {
                $this->updateSolicitudDeliveryData(
                    (int) $validated['id'],
                    $validated['recibido_por'],
                    $validated['descripcion'] ?? null,
                    $imagenPath
                );
            }
            $this->insertEventoPorPaquete($validated['tipo_paquete'], (int) $validated['id'], $eventoEntregaId, $userId);
        });

        $syncWarning = $this->syncExternalSolicitudEntrega(
            $validated['tipo_paquete'],
            (int) $validated['id'],
            $validated['recibido_por'],
            $validated['descripcion'] ?? null,
            $externalImage,
            $carteroNombre
        );

        $redirect = redirect()
            ->route('carteros.cartero')
            ->with('success', 'Correspondencia entregada correctamente.');

        if ($syncWarning !== null) {
            $redirect->with('warning', $syncWarning);
        }

        return $redirect;
    }

    public function deliverRoundTripPackage(Request $request)
    {
        $this->authorizeAnyFeaturePermission([
            'feature.carteros.entrega.deliver',
            'feature.carteros.cartero.deliver',
        ]);

        $fotoOpcional = $this->fotoOpcionalParaContratoLaPaz($request);
        $validated = $request->validate([
            'tipo_paquete' => ['required', 'in:EMS,CERTI,CONTRATO,ORDI,SOLICITUD'],
            'id' => ['required', 'integer'],
            'recibido_por' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'foto' => [$fotoOpcional ? 'nullable' : 'required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,heif'],
        ]);

        $tiposPermitidos = ['CONTRATO', 'EMS', 'SOLICITUD'];
        if (!in_array((string) ($validated['tipo_paquete'] ?? ''), $tiposPermitidos, true)) {
            throw ValidationException::withMessages([
                'tipo_paquete' => 'Esta accion solo aplica a EMS, CONTRATO o SOLICITUD.',
            ]);
        }

        $estadoCarteroId = $this->resolveEstadoCarteroId();
        $estadoProvinciaId = $this->resolveEstadoProvinciaId();
        $estadoRecibidoId = $this->resolveEstadoByName('RECIBIDO');
        $userId = (int) $request->user()->id;
        $eventoEntregaId = self::EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE;

        $eventoExiste = DB::table('eventos')
            ->where('id', $eventoEntregaId)
            ->exists();

        if (!$eventoExiste) {
            throw ValidationException::withMessages([
                'id' => "No existe el evento con ID {$eventoEntregaId} (Paquete entregado exitosamente.).",
            ]);
        }

        $asignacion = $this->findAssignmentForUserByStates(
            $validated['tipo_paquete'],
            (int) $validated['id'],
            $userId,
            [$estadoCarteroId, $estadoProvinciaId]
        );
        $imagenPath = $this->storeDeliveryPhoto($request, $asignacion->imagen ?? $asignacion->foto);

        DB::transaction(function () use ($validated, $asignacion, $estadoRecibidoId, $userId, $eventoEntregaId, $imagenPath) {
            $this->updatePackageState($validated['tipo_paquete'], (int) $validated['id'], $estadoRecibidoId);
            $asignacion->id_estados = $estadoRecibidoId;
            $asignacion->recibido_por = $validated['recibido_por'];
            $asignacion->descripcion = $validated['descripcion'] ?? null;
            $asignacion->imagen = $imagenPath;
            $asignacion->save();
            $this->updatePackageImage($validated['tipo_paquete'], (int) $validated['id'], $imagenPath);
            if ($validated['tipo_paquete'] === 'SOLICITUD') {
                $this->updateSolicitudDeliveryData(
                    (int) $validated['id'],
                    $validated['recibido_por'],
                    $validated['descripcion'] ?? null,
                    $imagenPath
                );
            }
            $this->insertEventoPorPaquete($validated['tipo_paquete'], (int) $validated['id'], $eventoEntregaId, $userId);
        });

        return redirect()
            ->route('carteros.cartero')
            ->with('success', 'Paquete ida y vuelta enviado a ALMACEN con estado RECIBIDO.');
    }

    public function addAttempt(Request $request)
    {
        $this->authorizeAnyFeaturePermission([
            'feature.carteros.entrega.attempt',
            'feature.carteros.cartero.deliver',
        ]);

        $validated = $request->validate([
            'tipo_paquete' => ['required', 'in:EMS,CERTI,CONTRATO,ORDI,SOLICITUD'],
            'id' => ['required', 'integer'],
            'descripcion' => ['nullable', 'string'],
            'foto' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,heif'],
        ]);

        $estadoCarteroId = $this->resolveEstadoCarteroId();
        $estadoProvinciaId = $this->resolveEstadoProvinciaId();
        $estadoDevolucionId = $this->resolveEstadoDevolucionId();
        $userId = (int) $request->user()->id;
        $eventoIntentoId = self::EVENTO_ID_INTENTO_FALLIDO_ENTREGA;

        $eventoExiste = DB::table('eventos')
            ->where('id', $eventoIntentoId)
            ->exists();

        if (!$eventoExiste) {
            throw ValidationException::withMessages([
                'id' => "No existe el evento con ID {$eventoIntentoId} (Intento fallido de entrega del paquete.).",
            ]);
        }

        $asignacion = $this->findAssignmentForUserByStates(
            $validated['tipo_paquete'],
            (int) $validated['id'],
            $userId,
            [$estadoCarteroId, $estadoProvinciaId]
        );
        $imagenDevolucionPath = $this->storeDeliveryPhoto($request, $asignacion->imagen_devolucion);

        DB::transaction(function () use ($validated, $asignacion, $estadoDevolucionId, $userId, $eventoIntentoId, $imagenDevolucionPath) {
            $this->updatePackageState($validated['tipo_paquete'], (int) $validated['id'], $estadoDevolucionId);
            $asignacion->intento = ((int) $asignacion->intento) + 1;
            $asignacion->id_estados = $estadoDevolucionId;
            $asignacion->descripcion = $validated['descripcion'] ?? $asignacion->descripcion;
            $asignacion->imagen_devolucion = $imagenDevolucionPath;
            $asignacion->save();
            if ($validated['tipo_paquete'] === 'SOLICITUD') {
                $this->updateSolicitudDeliveryData(
                    (int) $validated['id'],
                    null,
                    $validated['descripcion'] ?? $asignacion->descripcion,
                    $imagenDevolucionPath
                );
            }
            $this->insertEventoPorPaquete($validated['tipo_paquete'], (int) $validated['id'], $eventoIntentoId, $userId);
        });

        return redirect()
            ->route('carteros.devolucion')
            ->with('success', 'Intento registrado y paquete enviado a DEVOLUCION.');
    }

    private function storeAssignmentReport(Request $request, array $items, int $assigneeUserId, int $actorUserId): string
    {
        $rows = $this->buildAssignmentReportRows($items);
        $token = (string) Str::uuid();
        $codigo = $this->nextAssignmentReportCode();
        $assignee = User::query()->find($assigneeUserId, ['id', 'name', 'ciudad']);
        $actor = User::query()->find($actorUserId, ['id', 'name', 'ciudad']);
        $assignedAt = now();

        $summaryByType = collect($rows)
            ->groupBy('tipo_paquete')
            ->map(fn ($group) => $group->count())
            ->all();

        $reports = (array) $request->session()->get('carteros.assignment_reports', []);
        $reports[$token] = [
            'rows' => $rows,
            'assigned_at' => $assignedAt,
            'assigned_user' => $assignee,
            'actor_user' => $actor,
            'regional' => $this->resolveAssignmentRegional($rows, $assignee, $actor),
            'summary_by_type' => $summaryByType,
            'total_assigned' => count($rows),
            'codigo_reporte' => $codigo,
        ];

        DB::transaction(function () use ($codigo, $token, $assigneeUserId, $actorUserId, $assignedAt, $summaryByType, $rows) {
            $report = CarteroAssignmentReport::query()->create([
                'codigo' => $codigo,
                'token' => $token,
                'assigned_user_id' => $assigneeUserId > 0 ? $assigneeUserId : null,
                'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
                'regional' => $this->resolveAssignmentRegional(
                    $rows,
                    User::query()->find($assigneeUserId, ['id', 'name', 'ciudad']),
                    User::query()->find($actorUserId, ['id', 'name', 'ciudad'])
                ),
                'assigned_at' => $assignedAt,
                'total_assigned' => count($rows),
                'summary_by_type' => $summaryByType,
                'rows' => $rows,
            ]);

            $items = collect($rows)
                ->map(fn ($row) => [
                    'tipo_paquete' => strtoupper((string) ($row['tipo_paquete'] ?? '')),
                    'paquete_id' => (int) ($row['id'] ?? 0),
                    'codigo' => (string) ($row['codigo'] ?? ''),
                ])
                ->filter(fn ($row) => $row['tipo_paquete'] !== '' && $row['paquete_id'] > 0)
                ->values();

            foreach ($items as $item) {
                $report->items()->create($item);
            }
        });

        if (count($reports) > 10) {
            $reports = array_slice($reports, -10, null, true);
        }

        $request->session()->put('carteros.assignment_reports', $reports);

        return $token;
    }

    private function nextAssignmentReportCode(): string
    {
        do {
            $code = 'RS-' . now()->format('Ymd-His') . '-' . strtoupper(Str::random(4));
        } while (CarteroAssignmentReport::query()->where('codigo', $code)->exists());

        return $code;
    }

    private function buildAssignmentReportRows(array $items): array
    {
        $items = collect($items)
            ->map(fn ($item) => [
                'id' => (int) ($item['id'] ?? 0),
                'tipo_paquete' => strtoupper(trim((string) ($item['tipo_paquete'] ?? ''))),
            ])
            ->filter(fn ($item) => $item['id'] > 0 && in_array($item['tipo_paquete'], ['EMS', 'CERTI', 'ORDI', 'CONTRATO', 'SOLICITUD'], true))
            ->unique(fn ($item) => $item['tipo_paquete'] . ':' . $item['id'])
            ->values();

        $emsIds = $items->where('tipo_paquete', 'EMS')->pluck('id')->all();
        $certiIds = $items->where('tipo_paquete', 'CERTI')->pluck('id')->all();
        $ordiIds = $items->where('tipo_paquete', 'ORDI')->pluck('id')->all();
        $contratoIds = $items->where('tipo_paquete', 'CONTRATO')->pluck('id')->all();
        $solicitudIds = $items->where('tipo_paquete', 'SOLICITUD')->pluck('id')->all();

        $emsRows = PaqueteEms::query()
            ->whereIn('id', $emsIds ?: [0])
            ->get(['id', 'codigo', 'cod_especial', 'nombre_destinatario', 'direccion', 'ciudad', 'peso', 'created_at'])
            ->keyBy('id');

        $certiRows = PaqueteCerti::query()
            ->whereIn('id', $certiIds ?: [0])
            ->get(['id', 'codigo', 'destinatario', 'cuidad', 'zona', 'peso'])
            ->keyBy('id');

        $ordiRows = PaqueteOrdi::query()
            ->whereIn('id', $ordiIds ?: [0])
            ->get(['id', 'codigo', 'destinatario', 'ciudad', 'zona', 'peso'])
            ->keyBy('id');

        $contratoRows = RecojoContrato::query()
            ->whereIn('id', $contratoIds ?: [0])
            ->get(['id', 'codigo', 'cod_especial', 'nombre_d', 'direccion_d', 'destino', 'peso', 'created_at'])
            ->keyBy('id');
        $solicitudRows = SolicitudCliente::query()
            ->whereIn('id', $solicitudIds ?: [0])
            ->get(['id', 'codigo_solicitud', 'barcode', 'nombre_destinatario', 'direccion', 'ciudad', 'peso'])
            ->keyBy('id');

        return $items->values()->map(function ($item, $index) use ($emsRows, $certiRows, $ordiRows, $contratoRows, $solicitudRows) {
            $tipo = $item['tipo_paquete'];
            $id = $item['id'];

            if ($tipo === 'EMS' && isset($emsRows[$id])) {
                $pkg = $emsRows[$id];

                return [
                    'no' => $index + 1,
                    'id' => $id,
                    'tipo_paquete' => $tipo,
                    'codigo' => (string) $pkg->codigo,
                    'codigo_regional' => (string) ($pkg->cod_especial ?? ''),
                    'fecha_registro' => optional($pkg->created_at)->format('d/m/Y H:i'),
                    'destinatario' => (string) $pkg->nombre_destinatario,
                    'direccion' => (string) ($pkg->direccion ?? ''),
                    'ciudad' => (string) ($pkg->ciudad ?? ''),
                    'peso' => $pkg->peso,
                ];
            }

            if ($tipo === 'CERTI' && isset($certiRows[$id])) {
                $pkg = $certiRows[$id];

                return [
                    'no' => $index + 1,
                    'id' => $id,
                    'tipo_paquete' => $tipo,
                    'codigo' => (string) $pkg->codigo,
                    'destinatario' => (string) $pkg->destinatario,
                    'direccion' => $this->joinAddressParts([$pkg->zona ?? null, $pkg->cuidad ?? null]),
                    'ciudad' => (string) ($pkg->cuidad ?? ''),
                    'peso' => $pkg->peso,
                ];
            }

            if ($tipo === 'ORDI' && isset($ordiRows[$id])) {
                $pkg = $ordiRows[$id];

                return [
                    'no' => $index + 1,
                    'id' => $id,
                    'tipo_paquete' => $tipo,
                    'codigo' => (string) $pkg->codigo,
                    'destinatario' => (string) $pkg->destinatario,
                    'direccion' => $this->joinAddressParts([$pkg->zona ?? null, $pkg->ciudad ?? null]),
                    'ciudad' => (string) ($pkg->ciudad ?? ''),
                    'peso' => $pkg->peso,
                ];
            }

            if ($tipo === 'CONTRATO' && isset($contratoRows[$id])) {
                $pkg = $contratoRows[$id];

                return [
                    'no' => $index + 1,
                    'id' => $id,
                    'tipo_paquete' => $tipo,
                    'codigo' => (string) $pkg->codigo,
                    'codigo_regional' => (string) ($pkg->cod_especial ?? ''),
                    'fecha_registro' => optional($pkg->created_at)->format('d/m/Y H:i'),
                    'destinatario' => (string) $pkg->nombre_d,
                    'direccion' => (string) ($pkg->direccion_d ?? ''),
                    'ciudad' => (string) ($pkg->destino ?? ''),
                    'peso' => $pkg->peso,
                ];
            }

            if ($tipo === 'SOLICITUD' && isset($solicitudRows[$id])) {
                $pkg = $solicitudRows[$id];

                return [
                    'no' => $index + 1,
                    'id' => $id,
                    'tipo_paquete' => $tipo,
                    'codigo' => (string) ($pkg->codigo_solicitud ?: $pkg->barcode ?: 'SIN CODIGO'),
                    'destinatario' => (string) $pkg->nombre_destinatario,
                    'direccion' => (string) ($pkg->direccion ?? ''),
                    'ciudad' => (string) ($pkg->ciudad ?? ''),
                    'peso' => $pkg->peso,
                ];
            }

            return null;
        })->filter()->values()->all();
    }

    private function joinAddressParts(array $parts): string
    {
        return collect($parts)
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->join(' / ');
    }

    private function resolveAssignmentRegional(array $rows, ?User $assignee, ?User $actor): string
    {
        $regional = trim((string) ($assignee?->ciudad ?? ''));

        if ($regional === '') {
            $regional = trim((string) ($actor?->ciudad ?? ''));
        }

        if ($regional === '') {
            $regional = trim((string) collect($rows)->pluck('ciudad')->filter()->first());
        }

        return strtoupper($regional !== '' ? $regional : 'SIN REGIONAL');
    }

    private function combinedDataResponse(Request $request, ?int $estadoId = null, ?int $userId = null, bool $useUpdatedAtAsFecha = false, bool $includePackageStateMatches = false, bool $matchUserCity = false, ?array $allowedPackageTypes = null, ?string $destinationCity = null): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $search = trim((string) $request->query('search', ''));
        $codigo = trim((string) $request->query('codigo', ''));
        $cartero = trim((string) $request->query('cartero', ''));
        $nombre = trim((string) $request->query('nombre', ''));
        $fechaInicio = trim((string) $request->query('fecha_inicio', ''));
        $fechaFin = trim((string) $request->query('fecha_fin', ''));
        if ($search !== '') {
            $codigo = '';
        }

        $emsFilterIds = null;
        $certiFilterIds = null;
        $ordiFilterIds = null;
        $contratoFilterIds = null;
        $solicitudFilterIds = null;
        $deliveryEventCodes = $includePackageStateMatches
            ? $this->deliveredEventCodesByType(self::EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE)
            : [];

        if ($estadoId !== null || $userId !== null) {
            $base = Cartero::query();

            if ($estadoId !== null) {
                $base->where('id_estados', $estadoId);
            }

            if ($userId !== null) {
                if ($matchUserCity) {
                    $userCity = $this->normalizeUserCity((string) optional($request->user())->ciudad);
                    $base->where(function ($query) use ($userId, $userCity) {
                        $query->where('id_user', $userId);

                        if ($userCity !== '') {
                            $query->orWhereHas('user', function ($userQuery) use ($userCity) {
                                $userQuery->whereRaw('TRIM(UPPER(ciudad)) = ?', [$userCity]);
                            });
                        }
                    });
                } else {
                    $base->where('id_user', $userId);
                }
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

            $contratoFilterIds = (clone $base)
                ->whereNotNull('id_paquetes_contrato')
                ->pluck('id_paquetes_contrato')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $ordiFilterIds = (clone $base)
                ->whereNotNull('id_paquetes_ordi')
                ->pluck('id_paquetes_ordi')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $solicitudFilterIds = (clone $base)
                ->whereNotNull('id_solicitud_cliente')
                ->pluck('id_solicitud_cliente')
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
                'direccion',
                'ciudad',
                'peso',
                'precio',
                'estado_id',
                'created_at',
                'updated_at',
            ])
            ->when($codigo !== '', function ($query) use ($codigo) {
                $query->whereRaw('LOWER(codigo) LIKE ?', ['%' . mb_strtolower($codigo) . '%']);
            })
            ->when($estadoId !== null || $userId !== null, function ($query) use ($emsFilterIds, $estadoId, $userId, $includePackageStateMatches, $deliveryEventCodes) {
                if ($includePackageStateMatches && $estadoId !== null && $userId === null) {
                    $query->where(function ($sub) use ($emsFilterIds, $estadoId, $deliveryEventCodes) {
                        $sub->whereIn('id', $emsFilterIds ?: [0])
                            ->orWhere('estado_id', $estadoId)
                            ->orWhereIn('codigo', $deliveryEventCodes['EMS'] ?? []);
                    });
                    return;
                }

                $query->whereIn('id', $emsFilterIds ?: [0]);
            })
            ->get()
            ->map(function ($item) use ($useUpdatedAtAsFecha) {
                $fecha = $useUpdatedAtAsFecha ? $item->updated_at : $item->created_at;

                return [
                    'id' => $item->id,
                    'tipo_paquete' => 'EMS',
                    'codigo' => $item->codigo,
                    'destinatario' => $item->destinatario,
                    'telefono' => $item->telefono,
                    'ciudad' => $item->ciudad,
                    'zona' => $item->direccion,
                    'peso' => $item->peso,
                    'precio' => $item->precio,
                    'estado_id' => $item->estado_id,
                    'user_id' => null,
                    'asignado_a' => null,
                    'intento' => 0,
                    'recibido_por' => null,
                    'descripcion' => null,
                    'created_at' => optional($fecha)->toDateTimeString(),
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
                'updated_at',
            ])
            ->when($codigo !== '', function ($query) use ($codigo) {
                $query->whereRaw('LOWER(codigo) LIKE ?', ['%' . mb_strtolower($codigo) . '%']);
            })
            ->when($estadoId !== null || $userId !== null, function ($query) use ($certiFilterIds, $estadoId, $userId, $includePackageStateMatches, $deliveryEventCodes) {
                if ($includePackageStateMatches && $estadoId !== null && $userId === null) {
                    $query->where(function ($sub) use ($certiFilterIds, $estadoId, $deliveryEventCodes) {
                        $sub->whereIn('id', $certiFilterIds ?: [0])
                            ->orWhere('fk_estado', $estadoId)
                            ->orWhereIn('codigo', $deliveryEventCodes['CERTI'] ?? []);
                    });
                    return;
                }

                $query->whereIn('id', $certiFilterIds ?: [0]);
            })
            ->get()
            ->map(function ($item) use ($useUpdatedAtAsFecha) {
                $fecha = $useUpdatedAtAsFecha ? $item->updated_at : $item->created_at;

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
                    'created_at' => optional($fecha)->toDateTimeString(),
                ];
            });

        $ordi = PaqueteOrdi::query()
            ->select([
                'id',
                'codigo',
                'cod_especial',
                'destinatario',
                'telefono',
                'ciudad',
                'zona',
                'peso',
                'fk_estado as estado_id',
                'created_at',
                'updated_at',
            ])
            ->when($codigo !== '', function ($query) use ($codigo) {
                $query->where(function ($sub) use ($codigo) {
                    $sub->whereRaw('LOWER(codigo) LIKE ?', ['%' . mb_strtolower($codigo) . '%'])
                        ->orWhereRaw('LOWER(COALESCE(cod_especial, \'\')) LIKE ?', ['%' . mb_strtolower($codigo) . '%']);
                });
            })
            ->when($estadoId !== null || $userId !== null, function ($query) use ($ordiFilterIds, $estadoId, $userId, $includePackageStateMatches, $deliveryEventCodes) {
                if ($includePackageStateMatches && $estadoId !== null && $userId === null) {
                    $query->where(function ($sub) use ($ordiFilterIds, $estadoId, $deliveryEventCodes) {
                        $sub->whereIn('id', $ordiFilterIds ?: [0])
                            ->orWhere('fk_estado', $estadoId)
                            ->orWhereIn('codigo', $deliveryEventCodes['ORDI'] ?? []);
                    });
                    return;
                }

                $query->whereIn('id', $ordiFilterIds ?: [0]);
            })
            ->get()
            ->map(function ($item) use ($useUpdatedAtAsFecha) {
                $fecha = $useUpdatedAtAsFecha ? $item->updated_at : $item->created_at;

                return [
                    'id' => $item->id,
                    'tipo_paquete' => 'ORDI',
                    'codigo' => $item->codigo,
                    'codigo_aux' => (string) ($item->cod_especial ?? ''),
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
                    'created_at' => optional($fecha)->toDateTimeString(),
                ];
            });

        $contratos = RecojoContrato::query()
            ->select([
                'id',
                'codigo',
                'cod_especial',
                'nombre_d as destinatario',
                'telefono_d as telefono',
                'destino as ciudad',
                'direccion_d as zona',
                'peso',
                'estados_id as estado_id',
                'created_at',
                'updated_at',
            ])
            ->when($codigo !== '', function ($query) use ($codigo) {
                $query->where(function ($sub) use ($codigo) {
                    $sub->whereRaw('LOWER(codigo) LIKE ?', ['%' . mb_strtolower($codigo) . '%'])
                        ->orWhereRaw('LOWER(COALESCE(cod_especial, \'\')) LIKE ?', ['%' . mb_strtolower($codigo) . '%']);
                });
            })
            ->when($estadoId !== null || $userId !== null, function ($query) use ($contratoFilterIds, $estadoId, $userId, $includePackageStateMatches, $deliveryEventCodes) {
                if ($includePackageStateMatches && $estadoId !== null && $userId === null) {
                    $query->where(function ($sub) use ($contratoFilterIds, $estadoId, $deliveryEventCodes) {
                        $sub->whereIn('id', $contratoFilterIds ?: [0])
                            ->orWhere('estados_id', $estadoId)
                            ->orWhereIn('codigo', $deliveryEventCodes['CONTRATO'] ?? []);
                    });
                    return;
                }

                $query->whereIn('id', $contratoFilterIds ?: [0]);
            })
            ->get()
            ->map(function ($item) use ($useUpdatedAtAsFecha) {
                $fecha = $useUpdatedAtAsFecha ? $item->updated_at : $item->created_at;

                return [
                    'id' => $item->id,
                    'tipo_paquete' => 'CONTRATO',
                    'codigo' => $item->codigo,
                    'codigo_aux' => (string) ($item->cod_especial ?? ''),
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
                    'created_at' => optional($fecha)->toDateTimeString(),
                ];
            });

        $solicitudes = SolicitudCliente::query()
            ->select([
                'id',
                DB::raw("COALESCE(NULLIF(TRIM(codigo_solicitud), ''), NULLIF(TRIM(barcode), ''), 'SIN CODIGO') as codigo"),
                'cod_especial',
                'nombre_destinatario as destinatario',
                'telefono_destinatario as telefono',
                'ciudad',
                'direccion',
                'peso',
                'precio',
                'estado_id',
                'created_at',
                'updated_at',
            ])
            ->when($codigo !== '', function ($query) use ($codigo) {
                $query->where(function ($sub) use ($codigo) {
                    $sub->whereRaw('LOWER(COALESCE(codigo_solicitud, \'\')) LIKE ?', ['%' . mb_strtolower($codigo) . '%'])
                        ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) LIKE ?', ['%' . mb_strtolower($codigo) . '%'])
                        ->orWhereRaw('LOWER(COALESCE(cod_especial, \'\')) LIKE ?', ['%' . mb_strtolower($codigo) . '%']);
                });
            })
            ->when($estadoId !== null || $userId !== null, function ($query) use ($solicitudFilterIds, $estadoId, $userId, $includePackageStateMatches, $deliveryEventCodes) {
                if ($includePackageStateMatches && $estadoId !== null && $userId === null) {
                    $query->where(function ($sub) use ($solicitudFilterIds, $estadoId, $deliveryEventCodes) {
                        $sub->whereIn('id', $solicitudFilterIds ?: [0])
                            ->orWhere('estado_id', $estadoId)
                            ->orWhereIn(DB::raw("COALESCE(NULLIF(TRIM(codigo_solicitud), ''), NULLIF(TRIM(barcode), ''), 'SIN CODIGO')"), $deliveryEventCodes['SOLICITUD'] ?? []);
                    });
                    return;
                }

                $query->whereIn('id', $solicitudFilterIds ?: [0]);
            })
            ->get()
            ->map(function ($item) use ($useUpdatedAtAsFecha) {
                $fecha = $useUpdatedAtAsFecha ? $item->updated_at : $item->created_at;

                return [
                    'id' => $item->id,
                    'tipo_paquete' => 'SOLICITUD',
                    'codigo' => $item->codigo,
                    'codigo_aux' => (string) ($item->cod_especial ?? ''),
                    'destinatario' => $item->destinatario,
                    'telefono' => $item->telefono,
                    'ciudad' => $item->ciudad,
                    'zona' => $item->direccion,
                    'peso' => $item->peso,
                    'precio' => $item->precio,
                    'estado_id' => $item->estado_id,
                    'user_id' => null,
                    'asignado_a' => null,
                    'intento' => 0,
                    'recibido_por' => null,
                    'descripcion' => null,
                    'imagen' => null,
                    'imagen_devolucion' => null,
                    'created_at' => optional($fecha)->toDateTimeString(),
                ];
            });

        $all = $ems
            ->concat($certi)
            ->concat($ordi)
            ->concat($contratos)
            ->concat($solicitudes)
            ->sortByDesc('created_at')
            ->values();

        $destinationCity = $this->normalizeUserCity((string) $destinationCity);
        if ($destinationCity !== '') {
            $all = $all
                ->filter(fn ($row) => $this->normalizeUserCity((string) ($row['ciudad'] ?? '')) === $destinationCity)
                ->values();
        }

        if (is_array($allowedPackageTypes)) {
            $allowedPackageTypes = collect($allowedPackageTypes)
                ->map(fn ($type) => mb_strtoupper(trim((string) $type)))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $all = $all
                ->filter(fn ($row) => in_array(mb_strtoupper((string) ($row['tipo_paquete'] ?? '')), $allowedPackageTypes, true))
                ->values();
        }

        if ($includePackageStateMatches) {
            $all = $this->attachDeliveryEventData($all)
                ->sortByDesc('created_at')
                ->values();
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $all = $this->attachCarteroData($all)
                ->filter(function ($row) use ($needle) {
                    $fields = [
                        $row['tipo_paquete'] ?? '',
                        $row['codigo'] ?? '',
                        $row['codigo_aux'] ?? '',
                        $row['destinatario'] ?? '',
                        $row['telefono'] ?? '',
                        $row['ciudad'] ?? '',
                        $row['zona'] ?? '',
                        $row['peso'] ?? '',
                        $row['estado'] ?? '',
                        $row['asignado_a'] ?? '',
                        $row['recibido_por'] ?? '',
                        $row['descripcion'] ?? '',
                        $row['created_at'] ?? '',
                    ];

                    foreach ($fields as $value) {
                        if (str_contains(mb_strtolower((string) $value), $needle)) {
                            return true;
                        }
                    }

                    return false;
                })
                ->values();
        }

        if ($cartero !== '') {
            $needle = mb_strtolower($cartero);
            $all = $this->attachCarteroData($all)
                ->filter(function ($row) use ($needle) {
                    return str_contains(
                        mb_strtolower((string) ($row['asignado_a'] ?? '')),
                        $needle
                    );
                })
                ->values();
        }

        if ($nombre !== '') {
            $needle = mb_strtolower($nombre);
            $all = $this->attachDeliveryEventData($this->attachCarteroData($all))
                ->filter(function ($row) use ($needle) {
                    return str_contains(mb_strtolower((string) ($row['destinatario'] ?? '')), $needle)
                        || str_contains(mb_strtolower((string) ($row['asignado_a'] ?? '')), $needle)
                        || str_contains(mb_strtolower((string) ($row['recibido_por'] ?? '')), $needle);
                })
                ->values();
        }

        if ($fechaInicio !== '' || $fechaFin !== '') {
            $all = $all
                ->filter(function ($row) use ($fechaInicio, $fechaFin) {
                    $fecha = substr((string) ($row['created_at'] ?? ''), 0, 10);

                    if ($fecha === '') {
                        return false;
                    }

                    if ($fechaInicio !== '' && $fecha < $fechaInicio) {
                        return false;
                    }

                    if ($fechaFin !== '' && $fecha > $fechaFin) {
                        return false;
                    }

                    return true;
                })
                ->values();
        }

        $total = $all->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $pageRows = $all->slice($offset, $perPage)->values();
        $pageRows = $this->attachCarteroData($pageRows);
        if ($includePackageStateMatches) {
            $pageRows = $this->attachDeliveryEventData($pageRows);
        }
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

    private function deliveredEventCodesByType(int $eventId): array
    {
        $tables = [
            'EMS' => 'eventos_ems',
            'CERTI' => 'eventos_certi',
            'ORDI' => 'eventos_ordi',
            'CONTRATO' => 'eventos_contrato',
            'SOLICITUD' => 'eventos_tiktoker',
        ];

        $codes = [];

        foreach ($tables as $type => $table) {
            $codes[$type] = DB::table($table)
                ->where('evento_id', $eventId)
                ->pluck('codigo')
                ->map(fn ($codigo) => trim((string) $codigo))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $codes;
    }

    private function attachDeliveryEventData($rows)
    {
        $rows = collect($rows);
        if ($rows->isEmpty()) {
            return $rows;
        }

        $tables = [
            'EMS' => 'eventos_ems',
            'CERTI' => 'eventos_certi',
            'ORDI' => 'eventos_ordi',
            'CONTRATO' => 'eventos_contrato',
            'SOLICITUD' => 'eventos_tiktoker',
        ];

        $eventMaps = [];
        $estadoEntregadoId = $this->safeResolveEstadoByName('ENTREGADO');

        foreach ($tables as $type => $table) {
            $codes = $rows
                ->where('tipo_paquete', $type)
                ->pluck('codigo')
                ->map(fn ($codigo) => trim((string) $codigo))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($codes)) {
                $eventMaps[$type] = [];
                continue;
            }

            $events = DB::table($table . ' as ep')
                ->leftJoin('users as u', 'u.id', '=', 'ep.user_id')
                ->where('ep.evento_id', self::EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE)
                ->whereIn('ep.codigo', $codes)
                ->orderByDesc('ep.created_at')
                ->orderByDesc('ep.id')
                ->get([
                    'ep.codigo',
                    'ep.user_id',
                    'ep.created_at',
                    'u.name as user_name',
                ]);

            $eventMaps[$type] = [];
            foreach ($events as $event) {
                $codigo = trim((string) $event->codigo);
                if ($codigo !== '' && !isset($eventMaps[$type][$codigo])) {
                    $eventMaps[$type][$codigo] = $event;
                }
            }
        }

        return $rows->map(function ($row) use ($eventMaps, $estadoEntregadoId) {
            $type = (string) ($row['tipo_paquete'] ?? '');
            $codigo = trim((string) ($row['codigo'] ?? ''));
            $event = $eventMaps[$type][$codigo] ?? null;

            if ($event) {
                if (empty($row['user_id']) && !empty($event->user_id)) {
                    $row['user_id'] = (int) $event->user_id;
                }

                if (empty($row['asignado_a']) && !empty($event->user_name)) {
                    $row['asignado_a'] = (string) $event->user_name;
                }

                if (!empty($event->created_at)) {
                    $row['created_at'] = (string) $event->created_at;
                    $row['entregado_at'] = (string) $event->created_at;
                }

                if ($estadoEntregadoId > 0) {
                    $row['estado_id'] = $estadoEntregadoId;
                }
            }

            return $row;
        })->values();
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

        $ordiIds = collect($rows)
            ->where('tipo_paquete', 'ORDI')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $contratoIds = collect($rows)
            ->where('tipo_paquete', 'CONTRATO')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $solicitudIds = collect($rows)
            ->where('tipo_paquete', 'SOLICITUD')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($emsIds) && empty($certiIds) && empty($ordiIds) && empty($contratoIds) && empty($solicitudIds)) {
            return $rows;
        }

        $asignaciones = Cartero::query()
            ->with('user:id,name')
            ->where(function ($query) use ($emsIds, $certiIds, $ordiIds, $contratoIds, $solicitudIds) {
                if (!empty($emsIds)) {
                    $query->whereIn('id_paquetes_ems', $emsIds);
                }
                if (!empty($certiIds)) {
                    $query->orWhereIn('id_paquetes_certi', $certiIds);
                }
                if (!empty($ordiIds)) {
                    $query->orWhereIn('id_paquetes_ordi', $ordiIds);
                }
                if (!empty($contratoIds)) {
                    $query->orWhereIn('id_paquetes_contrato', $contratoIds);
                }
                if (!empty($solicitudIds)) {
                    $query->orWhereIn('id_solicitud_cliente', $solicitudIds);
                }
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $mapEms = [];
        $mapCerti = [];
        $mapOrdi = [];
        $mapContrato = [];
        $mapSolicitud = [];

        foreach ($asignaciones as $a) {
            if ($a->id_paquetes_ems && !isset($mapEms[$a->id_paquetes_ems])) {
                $mapEms[$a->id_paquetes_ems] = $a;
            }
            if ($a->id_paquetes_certi && !isset($mapCerti[$a->id_paquetes_certi])) {
                $mapCerti[$a->id_paquetes_certi] = $a;
            }
            if ($a->id_paquetes_ordi && !isset($mapOrdi[$a->id_paquetes_ordi])) {
                $mapOrdi[$a->id_paquetes_ordi] = $a;
            }
            if ($a->id_paquetes_contrato && !isset($mapContrato[$a->id_paquetes_contrato])) {
                $mapContrato[$a->id_paquetes_contrato] = $a;
            }
            if ($a->id_solicitud_cliente && !isset($mapSolicitud[$a->id_solicitud_cliente])) {
                $mapSolicitud[$a->id_solicitud_cliente] = $a;
            }
        }

        return collect($rows)->map(function ($row) use ($mapEms, $mapCerti, $mapOrdi, $mapContrato, $mapSolicitud) {
            $asignacion = null;
            if ($row['tipo_paquete'] === 'EMS' && isset($mapEms[$row['id']])) {
                $asignacion = $mapEms[$row['id']];
            }
            if ($row['tipo_paquete'] === 'CERTI' && isset($mapCerti[$row['id']])) {
                $asignacion = $mapCerti[$row['id']];
            }
            if ($row['tipo_paquete'] === 'ORDI' && isset($mapOrdi[$row['id']])) {
                $asignacion = $mapOrdi[$row['id']];
            }
            if ($row['tipo_paquete'] === 'CONTRATO' && isset($mapContrato[$row['id']])) {
                $asignacion = $mapContrato[$row['id']];
            }
            if ($row['tipo_paquete'] === 'SOLICITUD' && isset($mapSolicitud[$row['id']])) {
                $asignacion = $mapSolicitud[$row['id']];
            }

            if ($asignacion) {
                $row['estado_id'] = $asignacion->id_estados;
                $row['user_id'] = $asignacion->id_user;
                $row['asignado_a'] = optional($asignacion->user)->name;
                $row['intento'] = (int) $asignacion->intento;
                $row['recibido_por'] = $asignacion->recibido_por;
                $row['descripcion'] = $asignacion->descripcion;
                $row['imagen'] = $asignacion->imagen ?? $asignacion->foto;
                $row['foto'] = $asignacion->imagen ?? $asignacion->foto;
                $row['imagen_devolucion'] = $asignacion->imagen_devolucion;
            }

            return $row;
        })->values();
    }

    private function storeDeliveryPhoto(Request $request, ?string $currentPath = null): ?string
    {
        if (!$request->hasFile('foto')) {
            return $currentPath;
        }

        $newPath = $request->file('foto')->store('carteros/entregas', 'public');

        if (!empty($currentPath) && Storage::disk('public')->exists($currentPath)) {
            Storage::disk('public')->delete($currentPath);
        }

        return $newPath;
    }

    private function fotoOpcionalParaContratoLaPaz(Request $request): bool
    {
        $tipoPaquete = strtoupper(trim((string) $request->input('tipo_paquete', '')));
        $ciudadUsuario = strtoupper(trim((string) optional($request->user())->ciudad));

        return $tipoPaquete === 'CONTRATO' && $ciudadUsuario === 'LA PAZ';
    }

    private function buildExternalImagePayload(?UploadedFile $file): ?string
    {
        if (!$file) {
            return null;
        }

        $contents = @file_get_contents($file->getRealPath());
        if ($contents === false) {
            return null;
        }

        $mimeType = trim((string) ($file->getMimeType() ?: 'image/jpeg'));

        return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }

    private function syncExternalSolicitudEntrega(
        string $tipoPaquete,
        int $id,
        string $recibidoPor,
        ?string $descripcion,
        ?string $imagenBase64,
        string $carteroNombre
    ): ?string {
        if ($tipoPaquete !== 'CONTRATO') {
            if ($tipoPaquete !== 'SOLICITUD') {
                return null;
            }
        }

        if ($tipoPaquete === 'SOLICITUD') {
            return null;
        }

        $baseUrl = rtrim((string) config('services.solicitudes_sync.base_url', ''), '/');
        if ($baseUrl === '') {
            Log::warning('Sincronizacion de solicitud omitida: base_url no configurada.', [
                'tipo_paquete' => $tipoPaquete,
                'id' => $id,
            ]);

            return 'La entrega se guardo aqui, pero no se pudo sincronizar con el otro sistema.';
        }

        $codigo = trim((string) $this->getCodigosPorTipo($tipoPaquete, [$id])->first());
        if ($codigo === '') {
            Log::warning('Sincronizacion de solicitud omitida: codigo no encontrado.', [
                'tipo_paquete' => $tipoPaquete,
                'id' => $id,
            ]);

            return 'La entrega se guardo aqui, pero no se pudo sincronizar con el otro sistema.';
        }

        $endpoint = str_ends_with($baseUrl, '/api')
            ? $baseUrl . '/solicitud/actualizar-estado'
            : $baseUrl . '/api/solicitud/actualizar-estado';

        $payload = [
            'guia' => $codigo,
            'estado' => 3,
            'firma_d' => $recibidoPor,
            'entrega_observacion' => $descripcion,
            'imagen' => $imagenBase64,
            'usercartero' => $carteroNombre !== '' ? $carteroNombre : 'Sin responsable',
        ];

        Log::info('Sync entrega externo: request', [
            'tipo_paquete' => $tipoPaquete,
            'id' => $id,
            'endpoint' => $endpoint,
            'guia' => $payload['guia'],
            'estado' => $payload['estado'],
            'firma_d' => $payload['firma_d'],
            'entrega_observacion' => $payload['entrega_observacion'],
            'usercartero' => $payload['usercartero'],
            'imagen_length' => is_string($imagenBase64) ? strlen($imagenBase64) : null,
            'imagen_prefix' => is_string($imagenBase64) ? substr($imagenBase64, 0, 80) : null,
        ]);

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->asJson()
                ->put($endpoint, $payload);

            Log::info('Sync entrega externo: response', [
                'guia' => $codigo,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                return null;
            }

            Log::warning('Fallo sincronizando entrega con sistema externo.', [
                'codigo' => $codigo,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Error sincronizando entrega con sistema externo.', [
                'codigo' => $codigo,
                'endpoint' => $endpoint,
                'message' => $exception->getMessage(),
            ]);
        }

        return 'La entrega se guardo aqui, pero no se pudo sincronizar con el otro sistema.';
    }

    private function updatePackageImage(string $tipoPaquete, int $id, ?string $imagePath): void
    {
        if (empty($imagePath)) {
            return;
        }

        if ($tipoPaquete === 'EMS') {
            PaqueteEms::query()->where('id', $id)->update(['imagen' => $imagePath]);
            return;
        }

        if ($tipoPaquete === 'CERTI') {
            PaqueteCerti::query()->where('id', $id)->update(['imagen' => $imagePath]);
            return;
        }

        if ($tipoPaquete === 'ORDI') {
            PaqueteOrdi::query()->where('id', $id)->update(['imagen' => $imagePath]);
            return;
        }

        if ($tipoPaquete === 'CONTRATO') {
            RecojoContrato::query()->where('id', $id)->update(['imagen' => $imagePath]);
            return;
        }

        if ($tipoPaquete === 'SOLICITUD' && \Illuminate\Support\Facades\Schema::hasColumn('solicitud_clientes', 'imagen')) {
            SolicitudCliente::query()->where('id', $id)->update(['imagen' => $imagePath]);
        }
    }

    private function updateSolicitudDeliveryData(int $id, ?string $recepcionadoPor, ?string $observacion, ?string $imagePath): void
    {
        $updates = [];

        if (\Illuminate\Support\Facades\Schema::hasColumn('solicitud_clientes', 'recepcionado_por')) {
            $updates['recepcionado_por'] = $recepcionadoPor;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('solicitud_clientes', 'observacion')) {
            $updates['observacion'] = $observacion;
        }

        if (!empty($imagePath) && \Illuminate\Support\Facades\Schema::hasColumn('solicitud_clientes', 'imagen')) {
            $updates['imagen'] = $imagePath;
        }

        if (!empty($updates)) {
            SolicitudCliente::query()->where('id', $id)->update($updates);
        }
    }

    private function insertEventosPorTipoDesdeIds(string $tipoPaquete, array $ids, int $eventoId, int $userId): void
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return;
        }

        $codigos = $this->getCodigosPorTipo($tipoPaquete, $ids);
        $this->insertEventosPorTipoYCodigos($tipoPaquete, $codigos, $eventoId, $userId);
    }

    private function insertEventoPorPaquete(string $tipoPaquete, int $id, int $eventoId, int $userId): void
    {
        if ($id <= 0) {
            return;
        }

        $codigos = $this->getCodigosPorTipo($tipoPaquete, [$id]);
        $this->insertEventosPorTipoYCodigos($tipoPaquete, $codigos, $eventoId, $userId);
    }

    private function insertEventosPorTipoYCodigos(string $tipoPaquete, iterable $codigos, int $eventoId, int $userId): void
    {
        $codigos = collect($codigos)
            ->map(fn ($codigo) => trim((string) $codigo))
            ->filter(fn ($codigo) => $codigo !== '')
            ->values();

        if ($codigos->isEmpty()) {
            return;
        }

        $tablaEventos = $this->resolveTablaEventosPorTipo($tipoPaquete);
        $now = now();

        $rows = $codigos->map(function ($codigo) use ($eventoId, $userId, $now) {
            return [
                'codigo' => $codigo,
                'evento_id' => $eventoId,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        DB::table($tablaEventos)->insert($rows);
    }

    private function resolvePreviousStateForAssignment(string $tipoPaquete, int $id, Cartero $asignacion): int
    {
        $assigneeCity = '';
        if ((int) ($asignacion->id_user ?? 0) > 0) {
            $assigneeCity = (string) (User::query()->whereKey((int) $asignacion->id_user)->value('ciudad') ?? '');
        }

        $fromEvents = $this->resolvePreviousStateFromEvents($tipoPaquete, $id, $assigneeCity);
        if ($fromEvents > 0) {
            return $fromEvents;
        }

        $stored = (int) ($asignacion->id_estado_anterior ?? 0);
        if ($stored > 0) {
            return $stored;
        }

        return $this->inferPreviousStateByPackageContext($tipoPaquete, $id, $assigneeCity);
    }

    private function resolvePreviousStateFromEvents(string $tipoPaquete, int $id, string $assigneeCity): int
    {
        $codigo = trim((string) $this->getCodigosPorTipo($tipoPaquete, [$id])->first());
        if ($codigo === '') {
            return 0;
        }

        $tablaEventos = $this->resolveTablaEventosPorTipo($tipoPaquete);
        $eventos = DB::table($tablaEventos . ' as t')
            ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
            ->whereRaw('TRIM(UPPER(t.codigo)) = TRIM(UPPER(?))', [$codigo])
            ->orderByDesc('t.created_at')
            ->orderByDesc('t.id')
            ->limit(25)
            ->get([
                't.evento_id',
                'e.nombre_evento',
            ]);

        foreach ($eventos as $evento) {
            $estadoId = $this->mapEventNameToStateId(
                (string) ($evento->nombre_evento ?? ''),
                $tipoPaquete,
                $id,
                $assigneeCity
            );

            if ($estadoId > 0 && $estadoId !== $this->resolveEstadoCarteroId()) {
                return $estadoId;
            }
        }

        return 0;
    }

    private function mapEventNameToStateId(string $eventName, string $tipoPaquete, int $id, string $assigneeCity): int
    {
        $texto = mb_strtolower(trim($eventName));
        if ($texto === '') {
            return 0;
        }

        $estadoCarteroId = $this->safeResolveEstadoByName('CARTERO');
        $estadoEntregadoId = $this->safeResolveEstadoByName('ENTREGADO');
        $estadoDevolucionId = $this->safeResolveEstadoByName('DEVOLUCION');
        $estadoProvinciaId = $this->safeResolveEstadoByName('PROVINCIA');
        $estadoRecibidoId = $this->safeResolveEstadoByName('RECIBIDO');
        $estadoAlmacenId = $this->safeResolveEstadoByName('ALMACEN');
        $estadoRezagoId = $this->safeResolveEstadoByName('REZAGO');
        $estadoVentanillaId = $this->resolveVentanillaLikeStateId($tipoPaquete);
        $estadoTransitoId = $this->safeResolveEstadoByName('TRANSITO');
        $estadoEnviadoId = $this->safeResolveEstadoByName('ENVIADO');

        if (str_contains($texto, 'desasignado de cartero')) {
            return 0;
        }

        if (str_contains($texto, 'entregado exitosamente')) {
            return $estadoEntregadoId;
        }

        if (str_contains($texto, 'intento fallido') || str_contains($texto, 'devoluci')) {
            return $estadoDevolucionId;
        }

        if (
            str_contains($texto, 'camino para entrega fisica')
            || str_contains($texto, 'asignado a cartero')
            || str_contains($texto, 'transferido al agente de entrega')
        ) {
            return $estadoCarteroId;
        }

        if (str_contains($texto, 'retenido en punto de entrega')) {
            return $estadoRezagoId;
        }

        if (
            str_contains($texto, 'ventanilla')
            || str_contains($texto, 'punto de recogida')
        ) {
            return $estadoVentanillaId;
        }

        if (
            str_contains($texto, 'oficina destino de transito')
            || str_contains($texto, 'oficina de entrega')
            || str_contains($texto, 'ubicacion especifica')
            || str_contains($texto, 'oficina de transito nacional')
            || str_contains($texto, 'oficina de transito internacional')
            || str_contains($texto, 'recibido automaticamente en oficina de transito')
            || str_contains($texto, 'recibido desde el extranjero')
            || str_contains($texto, 'oficina origen de transito')
        ) {
            return $this->resolveReceivedOrProvinceState($tipoPaquete, $id, $assigneeCity, $estadoRecibidoId, $estadoProvinciaId, $estadoAlmacenId);
        }

        if (
            str_contains($texto, 'saca interna creada')
            || str_contains($texto, 'saca nacional recibido en ubicacion')
            || str_contains($texto, 'recibido del cliente')
            || str_contains($texto, 'documento oficial registrado')
            || str_contains($texto, 'datos digitalizados para transporte')
        ) {
            return $estadoAlmacenId;
        }

        if (
            str_contains($texto, 'camino a ubicacion nacional')
            || str_contains($texto, 'incluido en la saca')
            || str_contains($texto, 'saca nacional enviado a ubicacion')
            || str_contains($texto, 'despacho')
            || str_contains($texto, 'enviado al extranjero')
            || str_contains($texto, 'centro de procesamiento')
        ) {
            return $estadoTransitoId > 0 ? $estadoTransitoId : $estadoEnviadoId;
        }

        return 0;
    }

    private function resolveReceivedOrProvinceState(
        string $tipoPaquete,
        int $id,
        string $assigneeCity,
        int $estadoRecibidoId,
        int $estadoProvinciaId,
        int $estadoAlmacenId
    ): int {
        $destino = '';

        if ($tipoPaquete === 'EMS') {
            $destino = (string) (PaqueteEms::query()->whereKey($id)->value('ciudad') ?? '');
        } elseif ($tipoPaquete === 'CONTRATO') {
            $destino = (string) (RecojoContrato::query()->whereKey($id)->value('destino') ?? '');
        } elseif ($tipoPaquete === 'ORDI') {
            $destino = (string) (PaqueteOrdi::query()->whereKey($id)->value('ciudad') ?? '');
        } elseif ($tipoPaquete === 'CERTI') {
            $destino = (string) (PaqueteCerti::query()->whereKey($id)->value('cuidad') ?? '');
        } elseif ($tipoPaquete === 'SOLICITUD') {
            $destino = (string) (SolicitudCliente::query()->whereKey($id)->value('ciudad') ?? '');
        }

        $destinoNorm = strtoupper(trim($destino));
        $assigneeCityNorm = strtoupper(trim($assigneeCity));

        if ($estadoProvinciaId > 0 && $destinoNorm !== '' && $assigneeCityNorm !== '' && $destinoNorm !== $assigneeCityNorm) {
            return $estadoProvinciaId;
        }

        if ($estadoRecibidoId > 0) {
            return $estadoRecibidoId;
        }

        if ($estadoAlmacenId > 0) {
            return $estadoAlmacenId;
        }

        return 0;
    }

    private function resolveVentanillaLikeStateId(string $tipoPaquete): int
    {
        if (in_array($tipoPaquete, ['EMS', 'SOLICITUD'], true)) {
            $estadoVentanillaEms = $this->safeResolveEstadoByName('VENTANILLA EMS');
            if ($estadoVentanillaEms > 0) {
                return $estadoVentanillaEms;
            }
        }

        return $this->safeResolveEstadoByName('VENTANILLA');
    }

    private function inferPreviousStateByPackageContext(string $tipoPaquete, int $id, string $assigneeCity): int
    {
        $estadoAlmacenId = $this->safeResolveEstadoByName('ALMACEN');
        $estadoRecibidoId = $this->safeResolveEstadoByName('RECIBIDO');
        $estadoProvinciaId = $this->safeResolveEstadoByName('PROVINCIA');

        $origen = '';
        $destino = '';

        if ($tipoPaquete === 'EMS') {
            $pkg = PaqueteEms::query()->whereKey($id)->first(['origen', 'ciudad']);
            $origen = (string) ($pkg->origen ?? '');
            $destino = (string) ($pkg->ciudad ?? '');
        } elseif ($tipoPaquete === 'CONTRATO') {
            $pkg = RecojoContrato::query()->whereKey($id)->first(['origen', 'destino']);
            $origen = (string) ($pkg->origen ?? '');
            $destino = (string) ($pkg->destino ?? '');
        } elseif ($tipoPaquete === 'ORDI') {
            $pkg = PaqueteOrdi::query()->whereKey($id)->first(['ciudad']);
            $destino = (string) ($pkg->ciudad ?? '');
        } elseif ($tipoPaquete === 'CERTI') {
            $pkg = PaqueteCerti::query()->whereKey($id)->first(['cuidad']);
            $destino = (string) ($pkg->cuidad ?? '');
        } elseif ($tipoPaquete === 'SOLICITUD') {
            $pkg = SolicitudCliente::query()->whereKey($id)->first(['origen', 'ciudad']);
            $origen = (string) ($pkg->origen ?? '');
            $destino = (string) ($pkg->ciudad ?? '');
        }

        $origenNorm = strtoupper(trim($origen));
        $destinoNorm = strtoupper(trim($destino));
        $assigneeCityNorm = strtoupper(trim($assigneeCity));

        if ($assigneeCityNorm !== '') {
            if ($destinoNorm !== '' && $destinoNorm === $assigneeCityNorm && $estadoRecibidoId > 0) {
                return $estadoRecibidoId;
            }

            if ($origenNorm !== '' && $origenNorm === $assigneeCityNorm && $estadoAlmacenId > 0) {
                return $estadoAlmacenId;
            }
        }

        if ($estadoProvinciaId > 0 && $destinoNorm !== '' && $assigneeCityNorm !== '' && $destinoNorm !== $assigneeCityNorm) {
            return $estadoProvinciaId;
        }

        if ($estadoRecibidoId > 0 && $destinoNorm !== '') {
            return $estadoRecibidoId;
        }

        if ($estadoAlmacenId > 0) {
            return $estadoAlmacenId;
        }

        if ($estadoProvinciaId > 0) {
            return $estadoProvinciaId;
        }

        return $this->resolveEstadoAsignadoId();
    }

    private function resolveDynamicEventId(string $eventName): int
    {
        return (int) Evento::query()->firstOrCreate([
            'nombre_evento' => $eventName,
        ])->id;
    }

    private function assignmentEventName(?User $actor, ?User $assignee): string
    {
        $actorName = trim((string) ($actor?->name ?? 'SIN USUARIO'));
        $assigneeName = trim((string) ($assignee?->name ?? 'SIN USUARIO'));

        return 'Paquete en camino para entrega fisica. Asignado a CARTERO por '
            . ($actorName !== '' ? $actorName : 'SIN USUARIO')
            . ' a '
            . ($assigneeName !== '' ? $assigneeName : 'SIN USUARIO')
            . '.';
    }

    private function assignedToAnotherUserConflicts(array $items, int $assigneeUserId, bool $lockForUpdate = false): array
    {
        if ($assigneeUserId <= 0 || $items === []) {
            return [];
        }

        $idsByType = collect(['EMS', 'CERTI', 'ORDI', 'CONTRATO', 'SOLICITUD'])
            ->mapWithKeys(function ($type) use ($items) {
                return [
                    $type => collect($items)
                        ->where('tipo_paquete', $type)
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->filter(fn ($id) => $id > 0)
                        ->unique()
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        if (collect($idsByType)->flatten()->isEmpty()) {
            return [];
        }

        $assignmentsQuery = Cartero::query()
            ->with('user:id,name')
            ->whereNotNull('id_user')
            ->where('id_user', '<>', $assigneeUserId)
            ->where('id_estados', $this->resolveEstadoCarteroId())
            ->where(function ($query) use ($idsByType) {
                if ($idsByType['EMS'] !== []) {
                    $query->orWhereIn('id_paquetes_ems', $idsByType['EMS']);
                }
                if ($idsByType['CERTI'] !== []) {
                    $query->orWhereIn('id_paquetes_certi', $idsByType['CERTI']);
                }
                if ($idsByType['ORDI'] !== []) {
                    $query->orWhereIn('id_paquetes_ordi', $idsByType['ORDI']);
                }
                if ($idsByType['CONTRATO'] !== []) {
                    $query->orWhereIn('id_paquetes_contrato', $idsByType['CONTRATO']);
                }
                if ($idsByType['SOLICITUD'] !== []) {
                    $query->orWhereIn('id_solicitud_cliente', $idsByType['SOLICITUD']);
                }
            });

        if ($lockForUpdate) {
            $assignmentsQuery->lockForUpdate();
        }

        $assignments = $assignmentsQuery->get();

        if ($assignments->isEmpty()) {
            return [];
        }

        $codes = [
            'EMS' => PaqueteEms::query()->whereIn('id', $idsByType['EMS'])->pluck('codigo', 'id')->all(),
            'CERTI' => PaqueteCerti::query()->whereIn('id', $idsByType['CERTI'])->pluck('codigo', 'id')->all(),
            'ORDI' => PaqueteOrdi::query()->whereIn('id', $idsByType['ORDI'])->pluck('codigo', 'id')->all(),
            'CONTRATO' => RecojoContrato::query()->whereIn('id', $idsByType['CONTRATO'])->pluck('codigo', 'id')->all(),
            'SOLICITUD' => SolicitudCliente::query()
                ->whereIn('id', $idsByType['SOLICITUD'])
                ->select(['id', DB::raw("COALESCE(NULLIF(TRIM(codigo_solicitud), ''), NULLIF(TRIM(barcode), ''), 'SIN CODIGO') as codigo")])
                ->pluck('codigo', 'id')
                ->all(),
        ];

        return $assignments
            ->map(function (Cartero $assignment) use ($codes) {
                [$type, $id] = $this->assignmentTypeAndPackageId($assignment);
                $code = $codes[$type][$id] ?? ('ID ' . $id);
                $assignedTo = trim((string) optional($assignment->user)->name);

                return "{$type} {$code} ya esta asignado a " . ($assignedTo !== '' ? $assignedTo : 'otro usuario') . '.';
            })
            ->unique()
            ->values()
            ->all();
    }

    private function packageDestinationConflicts(array $items, string $expectedCity): array
    {
        $expectedCity = $this->normalizeUserCity($expectedCity);
        if ($expectedCity === '' || $items === []) {
            return [];
        }

        $idsByType = collect(['EMS', 'CERTI', 'ORDI', 'CONTRATO', 'SOLICITUD'])
            ->mapWithKeys(function ($type) use ($items) {
                return [
                    $type => collect($items)
                        ->where('tipo_paquete', $type)
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->filter(fn ($id) => $id > 0)
                        ->unique()
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        $rows = collect();

        if ($idsByType['EMS'] !== []) {
            $rows = $rows->concat(PaqueteEms::query()
                ->whereIn('id', $idsByType['EMS'])
                ->get(['id', 'codigo', 'ciudad'])
                ->map(fn ($item) => ['tipo' => 'EMS', 'codigo' => $item->codigo, 'ciudad' => $item->ciudad]));
        }

        if ($idsByType['CERTI'] !== []) {
            $rows = $rows->concat(PaqueteCerti::query()
                ->whereIn('id', $idsByType['CERTI'])
                ->get(['id', 'codigo', 'cuidad'])
                ->map(fn ($item) => ['tipo' => 'CERTI', 'codigo' => $item->codigo, 'ciudad' => $item->cuidad]));
        }

        if ($idsByType['ORDI'] !== []) {
            $rows = $rows->concat(PaqueteOrdi::query()
                ->whereIn('id', $idsByType['ORDI'])
                ->get(['id', 'codigo', 'ciudad'])
                ->map(fn ($item) => ['tipo' => 'ORDI', 'codigo' => $item->codigo, 'ciudad' => $item->ciudad]));
        }

        if ($idsByType['CONTRATO'] !== []) {
            $rows = $rows->concat(RecojoContrato::query()
                ->whereIn('id', $idsByType['CONTRATO'])
                ->get(['id', 'codigo', 'destino'])
                ->map(fn ($item) => ['tipo' => 'CONTRATO', 'codigo' => $item->codigo, 'ciudad' => $item->destino]));
        }

        if ($idsByType['SOLICITUD'] !== []) {
            $rows = $rows->concat(SolicitudCliente::query()
                ->whereIn('id', $idsByType['SOLICITUD'])
                ->select(['id', DB::raw("COALESCE(NULLIF(TRIM(codigo_solicitud), ''), NULLIF(TRIM(barcode), ''), 'SIN CODIGO') as codigo"), 'ciudad'])
                ->get()
                ->map(fn ($item) => ['tipo' => 'SOLICITUD', 'codigo' => $item->codigo, 'ciudad' => $item->ciudad]));
        }

        return $rows
            ->filter(fn ($row) => $this->normalizeUserCity((string) ($row['ciudad'] ?? '')) !== $expectedCity)
            ->map(function ($row) use ($expectedCity) {
                $destino = trim((string) ($row['ciudad'] ?? 'SIN DESTINO'));
                return "{$row['tipo']} {$row['codigo']} tiene destino {$destino}, no {$expectedCity}.";
            })
            ->values()
            ->all();
    }

    private function assignmentTypeAndPackageId(Cartero $assignment): array
    {
        if ((int) ($assignment->id_paquetes_ems ?? 0) > 0) {
            return ['EMS', (int) $assignment->id_paquetes_ems];
        }
        if ((int) ($assignment->id_paquetes_certi ?? 0) > 0) {
            return ['CERTI', (int) $assignment->id_paquetes_certi];
        }
        if ((int) ($assignment->id_paquetes_ordi ?? 0) > 0) {
            return ['ORDI', (int) $assignment->id_paquetes_ordi];
        }
        if ((int) ($assignment->id_paquetes_contrato ?? 0) > 0) {
            return ['CONTRATO', (int) $assignment->id_paquetes_contrato];
        }

        return ['SOLICITUD', (int) $assignment->id_solicitud_cliente];
    }

    private function devolucionPackageTypesForUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $roleNames = $user->getRoleNames()
            ->push((string) ($user->role ?? ''))
            ->map(function ($role) {
                $role = mb_strtolower(trim((string) $role));
                return str_replace(['-', '_'], ' ', $role);
            })
            ->filter()
            ->unique()
            ->values();

        $allowedTypes = [];

        foreach ($roleNames as $role) {
            if (str_contains($role, 'ems')) {
                $allowedTypes = array_merge($allowedTypes, ['EMS', 'CONTRATO']);
            }

            if (str_contains($role, 'urbano')) {
                $allowedTypes = array_merge($allowedTypes, ['CERTI', 'ORDI']);
            }
        }

        $allowedTypes = array_values(array_unique($allowedTypes));

        return $allowedTypes === [] ? null : $allowedTypes;
    }

    private function normalizeUserCity(string $city): string
    {
        $city = mb_strtoupper(trim($city));
        $city = preg_replace('/\s+/', ' ', $city) ?? $city;

        return $city;
    }

    private function isSameUserCity(?User $actor, ?User $assignee): bool
    {
        if (! $actor || ! $assignee) {
            return false;
        }

        $actorCity = $this->normalizeUserCity((string) ($actor->ciudad ?? ''));
        $assigneeCity = $this->normalizeUserCity((string) ($assignee->ciudad ?? ''));

        if ($actorCity === '' || $assigneeCity === '') {
            return false;
        }

        return $actorCity === $assigneeCity;
    }

    private function getCodigosPorTipo(string $tipoPaquete, array $ids)
    {
        if ($tipoPaquete === 'EMS') {
            return PaqueteEms::query()
                ->whereIn('id', $ids)
                ->pluck('codigo');
        }

        if ($tipoPaquete === 'CERTI') {
            return PaqueteCerti::query()
                ->whereIn('id', $ids)
                ->pluck('codigo');
        }

        if ($tipoPaquete === 'ORDI') {
            return PaqueteOrdi::query()
                ->whereIn('id', $ids)
                ->pluck('codigo');
        }

        if ($tipoPaquete === 'CONTRATO') {
            return RecojoContrato::query()
                ->whereIn('id', $ids)
                ->pluck('codigo');
        }

        if ($tipoPaquete === 'SOLICITUD') {
            return SolicitudCliente::query()
                ->whereIn('id', $ids)
                ->selectRaw("COALESCE(NULLIF(TRIM(codigo_solicitud), ''), NULLIF(TRIM(barcode), '')) as codigo")
                ->pluck('codigo');
        }

        return collect();
    }

    private function resolveTablaEventosPorTipo(string $tipoPaquete): string
    {
        if ($tipoPaquete === 'EMS') {
            return 'eventos_ems';
        }

        if ($tipoPaquete === 'CERTI') {
            return 'eventos_certi';
        }

        if ($tipoPaquete === 'ORDI') {
            return 'eventos_ordi';
        }

        if ($tipoPaquete === 'CONTRATO') {
            return 'eventos_contrato';
        }

        if ($tipoPaquete === 'SOLICITUD') {
            return 'eventos_tiktoker';
        }

        throw ValidationException::withMessages([
            'tipo_paquete' => "Tipo de paquete no soportado para eventos: {$tipoPaquete}.",
        ]);
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
                } elseif ($tipoPaquete === 'SOLICITUD') {
                    $q->where('id_solicitud_cliente', $id);
                } elseif ($tipoPaquete === 'ORDI') {
                    $q->where('id_paquetes_ordi', $id);
                } elseif ($tipoPaquete === 'CONTRATO') {
                    $q->where('id_paquetes_contrato', $id);
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
                } elseif ($tipoPaquete === 'SOLICITUD') {
                    $q->where('id_solicitud_cliente', $id);
                } elseif ($tipoPaquete === 'ORDI') {
                    $q->where('id_paquetes_ordi', $id);
                } elseif ($tipoPaquete === 'CONTRATO') {
                    $q->where('id_paquetes_contrato', $id);
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

        if ($tipoPaquete === 'CONTRATO') {
            $pkg = RecojoContrato::query()
                ->where('id', $id)
                ->firstOrFail(['id', 'codigo', 'nombre_d as destinatario', 'destino as ciudad']);

            return [
                'codigo' => $pkg->codigo,
                'destinatario' => $pkg->destinatario,
                'ciudad' => $pkg->ciudad,
            ];
        }

        if ($tipoPaquete === 'ORDI') {
            $pkg = PaqueteOrdi::query()
                ->where('id', $id)
                ->firstOrFail(['id', 'codigo', 'destinatario', 'ciudad']);

            return [
                'codigo' => $pkg->codigo,
                'destinatario' => $pkg->destinatario,
                'ciudad' => $pkg->ciudad,
            ];
        }

        if ($tipoPaquete === 'SOLICITUD') {
            $pkg = SolicitudCliente::query()
                ->where('id', $id)
                ->firstOrFail(['id', 'codigo_solicitud', 'barcode', 'nombre_destinatario as destinatario', 'ciudad']);

            return [
                'codigo' => (string) ($pkg->codigo_solicitud ?: $pkg->barcode ?: 'SIN CODIGO'),
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

        if ($tipoPaquete === 'CONTRATO') {
            RecojoContrato::query()->where('id', $id)->update(['estados_id' => $estadoId]);
            return;
        }

        if ($tipoPaquete === 'ORDI') {
            PaqueteOrdi::query()->where('id', $id)->update(['fk_estado' => $estadoId]);
            return;
        }

        if ($tipoPaquete === 'SOLICITUD') {
            SolicitudCliente::query()->where('id', $id)->update(['estado_id' => $estadoId]);
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

    private function safeResolveEstadoByName(string $estadoNombre): int
    {
        try {
            return $this->resolveEstadoByName($estadoNombre);
        } catch (\Throwable $e) {
            return 0;
        }
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

    private function authorizeFeaturePermission(string $permission): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403, 'No tienes permiso para realizar esta accion.');
        }

        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');

        if ($superAdminRole !== '' && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return;
        }

        if ($user->can($permission)) {
            return;
        }

        abort(403, 'No tienes permiso para realizar esta accion.');
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function authorizeAnyFeaturePermission(array $permissions): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403, 'No tienes permiso para realizar esta accion.');
        }

        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');

        if ($superAdminRole !== '' && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return;
            }
        }

        abort(403, 'No tienes permiso para realizar esta accion.');
    }

    private function authorizeRoutePermission(string $permission): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403, 'No tienes permiso para acceder a esta ventana o accion.');
        }

        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');

        if ($superAdminRole !== '' && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return;
        }

        if ($user->can($permission)) {
            return;
        }

        abort(403, 'No tienes permiso para acceder a esta ventana o accion.');
    }
}

