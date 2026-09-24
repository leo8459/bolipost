<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserIpsLink;
use App\Services\SitraIpsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserIpsLinkController extends Controller
{
    public function index(Request $request, SitraIpsClient $ips)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'in:todos,vinculados,pendientes'],
            'ips_q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = User::query()
            ->with(['ipsLink', 'sucursal'])
            ->withTrashed()
            ->orderBy('name');

        if (! empty($filters['q'])) {
            $search = mb_strtolower(trim((string) $filters['q']));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(alias) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(ciudad) LIKE ?', ['%'.$search.'%']);
            });
        }

        if (($filters['estado'] ?? 'todos') === 'vinculados') {
            $query->whereHas('ipsLink', fn ($q) => $q->where('active', true));
        } elseif (($filters['estado'] ?? 'todos') === 'pendientes') {
            $query->whereDoesntHave('ipsLink');
        }

        $users = $query->paginate(25)->appends($request->query());
        $ipsUsers = [];
        $ipsError = null;

        try {
            $search = $filters['ips_q'] ?? $filters['q'] ?? '';
            if (trim((string) $search) !== '') {
                $ipsUsers = $ips->users(['q' => $search, 'per_page' => 25])['data'] ?? [];
            }
        } catch (ConnectionException $exception) {
            Log::warning('No fue posible consultar usuarios IPS.', ['message' => $exception->getMessage()]);
            $ipsError = 'No fue posible conectar con SITRA para buscar usuarios IPS.';
        } catch (\Throwable $exception) {
            report($exception);
            $ipsError = 'No fue posible consultar usuarios IPS.';
        }

        return view('user_ips_links.index', [
            'users' => $users,
            'ipsUsers' => $ipsUsers,
            'ipsError' => $ipsError,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, User $user, SitraIpsClient $ips)
    {
        $data = $request->validate([
            'ips_user_pid' => [
                'required',
                'integer',
                'min:1',
                'max:65535',
                Rule::unique('user_ips_links', 'ips_user_pid')->ignore($user->ipsLink?->id),
            ],
        ]);

        $ipsUser = $ips->user((int) $data['ips_user_pid']);
        $this->saveLink($user, $ipsUser);

        return back()->with('success', 'Usuario vinculado con IPS correctamente.');
    }

    public function createIpsUser(Request $request, User $user, SitraIpsClient $ips)
    {
        abort_if($user->ipsLink()->exists(), 409, 'El usuario ya tiene un vínculo IPS.');

        $data = $request->validate([
            'user_domain' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9._-]+$/'],
            'user_fid' => ['required', 'string', 'max:256', 'regex:/^[A-Za-z0-9._-]+$/'],
            'user_name' => ['required', 'string', 'max:256'],
            'office_cd' => ['nullable', 'integer', 'min:1', 'max:32767'],
            'email' => ['nullable', 'email', 'max:256'],
        ]);

        $ipsUser = $ips->createUser([
            'user_domain' => strtoupper(trim((string) $data['user_domain'])),
            'user_fid' => trim((string) $data['user_fid']),
            'user_name' => trim((string) $data['user_name']),
            'office_cd' => $data['office_cd'] ?? null,
            'email' => $data['email'] ?? null,
        ]);

        $this->saveLink($user, $ipsUser);

        return back()->with('success', 'Usuario IPS creado y vinculado correctamente.');
    }

    public function verify(User $user, SitraIpsClient $ips)
    {
        $link = $user->ipsLink;
        abort_unless($link, 404);

        $ipsUser = $ips->user((int) $link->ips_user_pid);
        $this->saveLink($user, $ipsUser);

        return back()->with('success', 'Vinculo IPS verificado y actualizado.')
            ->with('verification', [
                'pid' => $link->ips_user_pid,
                'usuario' => $link->label(),
                'oficina' => $link->ips_office_name ?: 'Sin oficina IPS',
                'verificado' => optional($link->last_verified_at)->format('Y-m-d H:i:s'),
            ]);
    }

    public function destroy(User $user)
    {
        $user->ipsLink?->delete();

        return back()->with('success', 'Vinculo IPS eliminado.');
    }

    private function saveLink(User $user, array $ipsUser): UserIpsLink
    {
        return UserIpsLink::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'ips_user_pid' => (int) $ipsUser['user_pid'],
                'ips_user_domain' => (string) ($ipsUser['user_domain'] ?? ''),
                'ips_user_fid' => (string) ($ipsUser['user_fid'] ?? ''),
                'ips_user_name' => (string) ($ipsUser['user_name'] ?? ''),
                'ips_office_cd' => $ipsUser['office_cd'] ?? null,
                'ips_office_fcd' => $ipsUser['office_fcd'] ?? null,
                'ips_office_name' => $ipsUser['office_name'] ?? null,
                'ipsweb' => (bool) ($ipsUser['ipsweb'] ?? false),
                'restrict_user_offices' => (bool) ($ipsUser['restrict_user_offices'] ?? false),
                'active' => true,
                'last_verified_at' => now(),
                'last_snapshot' => $ipsUser,
            ],
        );
    }
}
