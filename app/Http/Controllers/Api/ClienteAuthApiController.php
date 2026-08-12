<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ClienteAuthApiController extends Controller
{
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
}
