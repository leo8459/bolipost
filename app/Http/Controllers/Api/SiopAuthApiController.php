<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmpresaContractUserSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SiopAuthApiController extends Controller
{
    public function login(Request $request, EmpresaContractUserSyncService $contractSync): JsonResponse
    {
        $request->merge([
            'alias' => Str::lower(trim((string) $request->input('alias'))),
        ]);

        $validated = $request->validate([
            'alias' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $contractSync->syncExpiredUsers();

        $user = User::query()
            ->whereRaw('LOWER(alias) = ?', [$validated['alias']])
            ->first();

        if (! $user || ! Hash::check($validated['password'], (string) $user->password)) {
            return response()->json([
                'message' => 'El usuario o la contrasena son incorrectos.',
            ], 401);
        }

        $deviceName = trim((string) ($validated['device_name'] ?? ''));
        if ($deviceName === '') {
            $deviceName = 'Aplicacion SIOP';
        }

        $user->tokens()->where('name', $deviceName)->delete();
        $accessToken = $user->createToken($deviceName, ['siop'])->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesion SIOP correcto.',
            'token_type' => 'Bearer',
            'access_token' => $accessToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesion SIOP cerrada correctamente.',
        ]);
    }

    private function userPayload(?User $user): array
    {
        $primaryRole = $user?->roles()
            ->orderBy('roles.id')
            ->first(['roles.id', 'roles.name']);

        return [
            'id' => $user?->id,
            'name' => $user?->name,
            'alias' => $user?->alias,
            'email' => $user?->email,
            'ciudad' => $user?->ciudad,
            'role_id' => $primaryRole ? (int) $primaryRole->id : null,
            'role' => $primaryRole ? (string) $primaryRole->name : null,
            'roles' => $user?->getRoleNames()->values()->all() ?? [],
        ];
    }
}
