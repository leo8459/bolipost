<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class ClienteAuthApiController extends Controller
{
    public function googleLogin(Request $request, GoogleIdTokenVerifier $verifier): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $google = $verifier->verify($validated['id_token']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        $email = strtolower(trim((string) ($google['email'] ?? '')));
        $security = (array) config('acl_cliente.security', []);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Google no devolvio un correo valido.'], 422);
        }

        if (($security['verified_google_email_required'] ?? true)
            && filter_var($google['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN) !== true) {
            return response()->json(['message' => 'La cuenta de Google debe tener un correo verificado.'], 403);
        }

        if (! $this->isAllowedGoogleDomain($email, $security)) {
            return response()->json(['message' => 'El dominio de correo no esta autorizado.'], 403);
        }

        $cliente = Cliente::query()
            ->where('google_id', (string) $google['sub'])
            ->orWhere('email', $email)
            ->first();

        if (($security['require_existing_account'] ?? false) && ! $cliente) {
            return response()->json(['message' => 'El correo aun no esta habilitado.'], 403);
        }

        $cliente ??= new Cliente([
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
        ]);

        $cliente->forceFill([
            'name' => trim((string) ($google['name'] ?? '')) ?: 'Cliente Google',
            'provider' => 'google',
            'google_id' => (string) $google['sub'],
            'avatar' => trim((string) ($google['picture'] ?? '')) ?: null,
            'email_verified_at' => now(),
            'rol' => 'tiktokero',
        ])->save();

        return response()->json([
            'message' => 'Inicio de sesion con Google correcto.',
            'token_type' => 'Bearer',
            'access_token' => $this->createToken($cliente, $validated['device_name'] ?? null),
            'cliente' => $this->clienteData($cliente),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['cliente' => $this->clienteData($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesion cerrada correctamente.']);
    }

    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', 'unique:clientes,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'device_name' => ['nullable', 'string', 'max:100'],
            'tipodocumentoidentidad' => ['nullable', 'string', 'in:1,2,3,4,5'],
            'complemento' => ['nullable', 'string', 'max:50'],
            'numero_carnet' => ['nullable', 'string', 'max:50'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ]);

        $cliente = Cliente::query()->create([
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'password' => $validated['password'],
            'provider' => 'local',
            'rol' => 'tiktokero',
            'tipodocumentoidentidad' => $this->nullableTrim($validated['tipodocumentoidentidad'] ?? null),
            'complemento' => $this->nullableTrim($validated['complemento'] ?? null),
            'numero_carnet' => $this->nullableTrim($validated['numero_carnet'] ?? null),
            'razon_social' => $this->nullableTrim($validated['razon_social'] ?? null),
            'telefono' => $this->nullableTrim($validated['telefono'] ?? null),
            'direccion' => $this->nullableTrim($validated['direccion'] ?? null),
        ]);

        return response()->json([
            'message' => 'Cliente registrado correctamente.',
            'token_type' => 'Bearer',
            'access_token' => $this->createToken($cliente, $validated['device_name'] ?? null),
            'cliente' => $this->clienteData($cliente),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $cliente = Cliente::query()
            ->where('email', strtolower(trim($validated['email'])))
            ->first();

        if (! $cliente || ! $cliente->password || ! Hash::check($validated['password'], $cliente->password)) {
            return response()->json([
                'message' => 'El correo o la contrasena son incorrectos.',
            ], 401);
        }

        return response()->json([
            'message' => 'Inicio de sesion correcto.',
            'token_type' => 'Bearer',
            'access_token' => $this->createToken($cliente, $validated['device_name'] ?? null),
            'cliente' => $this->clienteData($cliente),
        ]);
    }

    private function createToken(Cliente $cliente, ?string $deviceName): string
    {
        $name = trim((string) $deviceName) ?: 'sistema-externo';

        return $cliente->createToken($name, ['cliente'])->plainTextToken;
    }

    private function clienteData(Cliente $cliente): array
    {
        return [
            'id' => $cliente->id,
            'codigo_cliente' => $cliente->codigo_cliente,
            'name' => $cliente->name,
            'email' => $cliente->email,
            'rol' => $cliente->rol,
            'perfil_completo' => $cliente->perfilCompleto(),
            'tipodocumentoidentidad' => $cliente->tipodocumentoidentidad,
            'numero_carnet' => $cliente->numero_carnet,
            'razon_social' => $cliente->razon_social,
            'telefono' => $cliente->telefono,
            'direccion' => $cliente->direccion,
        ];
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function isAllowedGoogleDomain(string $email, array $security): bool
    {
        $allowedDomains = collect((array) ($security['allowed_google_domains'] ?? []))
            ->filter(fn (mixed $domain): bool => is_string($domain) && trim($domain) !== '')
            ->map(fn (string $domain): string => strtolower(trim($domain)));

        return $allowedDomains->isEmpty()
            || $allowedDomains->contains(strtolower((string) Str::after($email, '@')));
    }
}
