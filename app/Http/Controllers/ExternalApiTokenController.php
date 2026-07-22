<?php

namespace App\Http\Controllers;

use App\Models\ExternalApiToken;
use App\Support\ExternalApiJwt;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ExternalApiTokenController extends Controller
{
    private const MANUAL_PATH = 'docs/documentacion_api_direcciones_destino.docx';

    public function index()
    {
        $tokens = ExternalApiToken::query()
            ->latest('id')
            ->get();

        return view('configuracion.apis', compact('tokens'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $expiresAt = ! empty($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null;

        $apiToken = ExternalApiToken::query()->create([
            'user_id' => $request->user()?->id,
            'name' => $data['name'],
            'jti' => hash('sha256', Str::uuid()->toString().Str::random(32)),
            'token_hash' => hash('sha256', Str::random(80)),
            'abilities' => ['direcciones-destino:read', 'direcciones-destino:update'],
            'is_active' => true,
            'expires_at' => $expiresAt,
        ]);

        $jwt = ExternalApiJwt::issue($apiToken, null);
        $apiToken->forceFill([
            'token_hash' => hash('sha256', $jwt),
            'token_encrypted' => Crypt::encryptString($jwt),
            'token_plain' => $jwt,
        ])->save();

        return redirect()
            ->route('configuracion.apis.index')
            ->with('status', 'Token generado correctamente. Copia el token ahora; no se mostrara completo otra vez.')
            ->with('new_token', $jwt);
    }

    public function deactivate(ExternalApiToken $token)
    {
        $token->forceFill([
            'token_hash' => hash('sha256', Str::random(80).now()->timestamp),
            'token_encrypted' => null,
            'token_plain' => null,
            'is_active' => false,
            'revoked_at' => now(),
        ])->save();

        return back()->with('status', 'Token dado de baja y eliminado. La API ya no aceptara ese token.');
    }

    public function regenerate(ExternalApiToken $token)
    {
        $token->forceFill([
            'is_active' => true,
            'revoked_at' => null,
        ])->save();

        $jwt = ExternalApiJwt::issue($token, null);
        $token->forceFill([
            'token_hash' => hash('sha256', $jwt),
            'token_encrypted' => Crypt::encryptString($jwt),
            'token_plain' => $jwt,
        ])->save();

        return redirect()
            ->route('configuracion.apis.index')
            ->with('status', 'Token regenerado correctamente. El token anterior ya no sera aceptado.')
            ->with('new_token', $jwt);
    }

    public function activate(ExternalApiToken $token)
    {
        $token->forceFill([
            'is_active' => true,
            'revoked_at' => null,
        ])->save();

        $jwt = ExternalApiJwt::issue($token, null);
        $token->forceFill([
            'token_hash' => hash('sha256', $jwt),
            'token_encrypted' => Crypt::encryptString($jwt),
            'token_plain' => $jwt,
        ])->save();

        return redirect()
            ->route('configuracion.apis.index')
            ->with('status', 'Token activado y generado nuevamente.')
            ->with('new_token', $jwt);
    }

    public function downloadManual()
    {
        $path = base_path(self::MANUAL_PATH);

        abort_unless(is_file($path), 404, 'No se encontro el manual de la API.');

        return response()->download($path, 'manual-api-direcciones-destino.docx');
    }
}
