<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmpresaContractUserSyncService;
use App\Support\ChasquiCartero;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ChasquiAuthApiController extends Controller
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

        if (! ChasquiCartero::isAllowed($user)) {
            return response()->json([
                'message' => 'El usuario no tiene un rol de cartero habilitado para ChasquiApp.',
            ], 403);
        }

        $deviceName = trim((string) ($validated['device_name'] ?? ''));
        if ($deviceName === '') {
            $deviceName = 'ChasquiApp';
        }

        $user->tokens()->where('name', $deviceName)->delete();
        $accessToken = $user->createToken($deviceName, ['chasqui'])->plainTextToken;
        $primaryRole = $user->roles()->orderBy('roles.id')->first(['roles.id', 'roles.name']);

        return response()->json([
            'message' => 'Inicio de sesion ChasquiApp correcto.',
            'token_type' => 'Bearer',
            'access_token' => $accessToken,
            'user' => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'alias' => (string) $user->alias,
                'email' => (string) $user->email,
                'ciudad' => (string) $user->ciudad,
                'role_id' => $primaryRole ? (int) $primaryRole->id : null,
                'role' => $primaryRole ? (string) $primaryRole->name : null,
                'roles' => $user->getRoleNames()->values()->all(),
            ],
        ]);
    }
}
