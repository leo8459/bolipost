<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmpresaSolicitudLlenadoController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('buscar', ''));
        $companyId = $request->integer('empresa_id');
        $responseStatus = (string) $request->query('respuesta', '');
        $responseStatuses = ['respondidas', 'pendientes', 'sin_solicitud', 'ubicacion_registrada'];
        if (! in_array($responseStatus, $responseStatuses, true)) {
            $responseStatus = '';
        }

        $companies = Empresa::query()
            ->whereIn('id', User::query()->whereNotNull('empresa_id')->select('empresa_id')->distinct())
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'sigla', 'codigo_cliente']);
        if ($companyId > 0 && ! $companies->contains('id', $companyId)) {
            $companyId = 0;
        }

        $users = User::query()
            ->whereNotNull('empresa_id')
            ->with('empresa:id,nombre,sigla,codigo_cliente')
            ->when($companyId > 0, fn ($query) => $query->where('empresa_id', $companyId))
            ->when($responseStatus === 'respondidas', fn ($query) => $query->whereNotNull('empresa_ubicacion_respondida_at'))
            ->when($responseStatus === 'pendientes', fn ($query) => $query
                ->whereNotNull('empresa_ubicacion_solicitada_at')
                ->whereNull('empresa_ubicacion_respondida_at'))
            ->when($responseStatus === 'sin_solicitud', fn ($query) => $query
                ->whereNull('empresa_ubicacion_solicitada_at')
                ->whereNull('empresa_ubicacion_respondida_at'))
            ->when($responseStatus === 'ubicacion_registrada', fn ($query) => $query
                ->whereRaw("TRIM(COALESCE(provincia_origen, '')) <> ''"))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('alias', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhereHas('empresa', function ($empresaQuery) use ($search): void {
                            $empresaQuery->where('nombre', 'like', '%'.$search.'%')
                                ->orWhere('sigla', 'like', '%'.$search.'%')
                                ->orWhere('codigo_cliente', 'like', '%'.$search.'%');
                        });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(100)
            ->withQueryString();

        $baseQuery = User::query()->whereNotNull('empresa_id');
        $withoutProvinceQuery = (clone $baseQuery)->whereRaw("TRIM(COALESCE(provincia_origen, '')) = ''");

        return view('empresa.solicitud_llenado.index', [
            'users' => $users,
            'search' => $search,
            'companies' => $companies,
            'companyId' => $companyId,
            'responseStatus' => $responseStatus,
            'totalUsers' => (clone $baseQuery)->count(),
            'eligibleUsers' => (clone $withoutProvinceQuery)->whereNull('empresa_ubicacion_respondida_at')->count(),
            'pendingUsers' => (clone $withoutProvinceQuery)->whereNotNull('empresa_ubicacion_solicitada_at')
                ->whereNull('empresa_ubicacion_respondida_at')->count(),
            'answeredUsers' => (clone $baseQuery)->whereNotNull('empresa_ubicacion_respondida_at')->count(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $sendToAll = $request->boolean('todos');
        $validated = $request->validate([
            'todos' => ['sometimes', 'boolean'],
            'user_ids' => [$sendToAll ? 'nullable' : 'required', 'array', 'min:1'],
            'user_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereNotNull('empresa_id')
                    ->whereNull('deleted_at')),
            ],
        ], [
            'user_ids.required' => 'Selecciona al menos un usuario de empresa.',
            'user_ids.min' => 'Selecciona al menos un usuario de empresa.',
            'user_ids.*.exists' => 'Uno de los usuarios seleccionados ya no pertenece a una empresa activa.',
        ]);

        $targetUsers = User::query()
            ->whereNotNull('empresa_id')
            ->whereRaw("TRIM(COALESCE(provincia_origen, '')) = ''")
            ->whereNull('empresa_ubicacion_respondida_at');
        if (! $sendToAll) {
            $targetUsers->whereIn('id', $validated['user_ids']);
        }

        $targetCount = (clone $targetUsers)->count();
        if ($targetCount === 0) {
            return redirect()->back()->with('error', 'No hay usuarios de empresa para recibir la solicitud.');
        }

        if (! $sendToAll && $targetCount !== count($validated['user_ids'])) {
            return redirect()->back()->with('error', 'La solicitud solo se puede enviar a usuarios sin provincia origen que aún no respondieron.');
        }

        $now = now();

        DB::transaction(function () use ($targetUsers, $now): void {
            $targetUsers->update([
                'empresa_ubicacion_solicitada_at' => $now,
                'empresa_ubicacion_respondida_at' => null,
                'empresa_ubicacion_capital' => false,
                'updated_at' => $now,
            ]);
        });

        return redirect()
            ->route('empresa.solicitud-llenado.index', $request->only('buscar', 'empresa_id', 'respuesta'))
            ->with('success', 'Solicitud enviada a '.$targetCount.' usuario(s) de empresa.');
    }

    public function updateResponse(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->empresa_id, 404);

        $validated = $request->validateWithBag('responseEdit', [
            'respuesta_tipo' => ['required', Rule::in(['sin_respuesta', 'ubicacion', 'capital'])],
            'ubicacion' => ['nullable', 'required_if:respuesta_tipo,ubicacion', 'string', 'max:255'],
        ], [
            'ubicacion.required_if' => 'Escribe el municipio o la provincia.',
        ]);

        $now = now();
        if ($validated['respuesta_tipo'] === 'sin_respuesta') {
            $user->provincia_origen = null;
            $user->empresa_ubicacion_capital = false;
            $user->empresa_ubicacion_respondida_at = null;
        } elseif ($validated['respuesta_tipo'] === 'capital') {
            $user->provincia_origen = null;
            $user->empresa_ubicacion_capital = true;
            if (! $user->empresa_ubicacion_respondida_at) {
                $user->empresa_ubicacion_respondida_at = $now;
            }
        } else {
            $location = trim((string) ($validated['ubicacion'] ?? ''));
            $location = function_exists('mb_strtoupper') ? mb_strtoupper($location, 'UTF-8') : strtoupper($location);
            $location = preg_replace('/\\s+/', ' ', $location) ?: '';

            if ($location === '') {
                throw ValidationException::withMessages([
                    'ubicacion' => 'Escribe el municipio o la provincia.',
                ])->errorBag('responseEdit');
            }

            $user->provincia_origen = $location;
            $user->empresa_ubicacion_capital = false;
            if (! $user->empresa_ubicacion_respondida_at) {
                $user->empresa_ubicacion_respondida_at = $now;
            }
        }

        $user->save();

        return redirect()
            ->route('empresa.solicitud-llenado.index', $request->only('buscar', 'empresa_id', 'respuesta'))
            ->with('success', 'La respuesta de '.$user->name.' fue actualizada.');
    }

    public function updateResponses(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('responseEdit', [
            'updates' => ['required', 'array', 'min:1'],
            'updates.*' => ['required', 'array'],
            'updates.*.respuesta_tipo' => ['required', Rule::in(['sin_respuesta', 'ubicacion', 'capital'])],
            'updates.*.ubicacion' => ['nullable', 'string', 'max:255'],
        ]);

        $updates = $validated['updates'];
        $userIds = array_keys($updates);
        foreach ($userIds as $userId) {
            if (! ctype_digit((string) $userId) || (int) $userId < 1) {
                throw ValidationException::withMessages([
                    'updates' => 'La lista contiene un usuario no válido.',
                ])->errorBag('responseEdit');
            }
        }

        $users = User::query()
            ->whereNotNull('empresa_id')
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        if ($users->count() !== count($updates)) {
            return redirect()->back()->with('error', 'Uno o más usuarios ya no pertenecen a una empresa activa.');
        }

        $normalizedUpdates = [];
        foreach ($updates as $userId => $data) {
            $location = null;
            if ($data['respuesta_tipo'] === 'ubicacion') {
                $location = trim((string) ($data['ubicacion'] ?? ''));
                $location = function_exists('mb_strtoupper') ? mb_strtoupper($location, 'UTF-8') : strtoupper($location);
                $location = preg_replace('/\s+/', ' ', $location) ?: '';

                if ($location === '') {
                    throw ValidationException::withMessages([
                        'updates.'.$userId.'.ubicacion' => 'Escribe el municipio o la provincia.',
                    ])->errorBag('responseEdit');
                }
            }

            $normalizedUpdates[$userId] = [
                'respuesta_tipo' => $data['respuesta_tipo'],
                'ubicacion' => $location,
            ];
        }

        $now = now();
        DB::transaction(function () use ($normalizedUpdates, $users, $now): void {
            foreach ($normalizedUpdates as $userId => $data) {
                $user = $users->get($userId);
                if (! $user) {
                    continue;
                }

                if ($data['respuesta_tipo'] === 'sin_respuesta') {
                    $user->provincia_origen = null;
                    $user->empresa_ubicacion_capital = false;
                    $user->empresa_ubicacion_respondida_at = null;
                } elseif ($data['respuesta_tipo'] === 'capital') {
                    $user->provincia_origen = null;
                    $user->empresa_ubicacion_capital = true;
                    if (! $user->empresa_ubicacion_respondida_at) {
                        $user->empresa_ubicacion_respondida_at = $now;
                    }
                } else {
                    $user->provincia_origen = $data['ubicacion'];
                    $user->empresa_ubicacion_capital = false;
                    if (! $user->empresa_ubicacion_respondida_at) {
                        $user->empresa_ubicacion_respondida_at = $now;
                    }
                }

                $user->save();
            }
        });

        $savedCount = count($normalizedUpdates);

        return redirect()
            ->route('empresa.solicitud-llenado.index', $request->only('buscar', 'empresa_id', 'respuesta'))
            ->with('success', 'Se guardaron los cambios de '.$savedCount.' usuario(s).');
    }

    public function respond(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->empresa_id, 403);
        abort_unless(trim((string) $user->provincia_origen) === '', 403);
        abort_unless(
            $user->empresa_ubicacion_solicitada_at && ! $user->empresa_ubicacion_respondida_at,
            403
        );

        $validated = $request->validate([
            'tipo' => ['required', Rule::in(['ubicacion', 'capital'])],
            'ubicacion' => ['nullable', 'required_if:tipo,ubicacion', 'string', 'max:255'],
        ], [
            'ubicacion.required_if' => 'Escribe el nombre del municipio o provincia.',
        ]);

        $now = now();
        if ($validated['tipo'] === 'capital') {
            $user->provincia_origen = null;
            $user->empresa_ubicacion_capital = true;
        } else {
            $location = trim((string) ($validated['ubicacion'] ?? ''));
            $location = function_exists('mb_strtoupper') ? mb_strtoupper($location, 'UTF-8') : strtoupper($location);
            $location = preg_replace('/\s+/', ' ', $location) ?: '';

            if ($location === '') {
                throw ValidationException::withMessages([
                    'ubicacion' => 'Escribe el nombre del municipio o provincia.',
                ]);
            }

            $user->provincia_origen = $location;
            $user->empresa_ubicacion_capital = false;
        }

        $user->empresa_ubicacion_respondida_at = $now;
        $user->save();

        return redirect()
            ->route('home.welcome')
            ->with('success', 'Gracias. La ubicación de tu entidad fue registrada.');
    }
}
