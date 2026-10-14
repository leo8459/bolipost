<?php

namespace App\Http\Controllers;

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

        $users = User::query()
            ->whereNotNull('empresa_id')
            ->with('empresa:id,nombre,sigla,codigo_cliente')
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
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $baseQuery = User::query()->whereNotNull('empresa_id');
        $withoutProvinceQuery = (clone $baseQuery)->whereRaw("TRIM(COALESCE(provincia_origen, '')) = ''");

        return view('empresa.solicitud_llenado.index', [
            'users' => $users,
            'search' => $search,
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
            ->whereRaw("TRIM(COALESCE(provincia_origen, '')) = ''");
        if (! $sendToAll) {
            $targetUsers->whereIn('id', $validated['user_ids']);
        } else {
            $targetUsers->whereNull('empresa_ubicacion_respondida_at');
        }

        $targetCount = (clone $targetUsers)->count();
        if ($targetCount === 0) {
            return redirect()->back()->with('error', 'No hay usuarios de empresa para recibir la solicitud.');
        }

        if (! $sendToAll && $targetCount !== count($validated['user_ids'])) {
            return redirect()->back()->with('error', 'La solicitud solo se puede enviar a usuarios sin provincia origen registrada.');
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
            ->route('empresa.solicitud-llenado.index', $request->only('buscar'))
            ->with('success', 'Solicitud enviada a '.$targetCount.' usuario(s) de empresa.');
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
