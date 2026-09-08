<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ApiCorreoMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class CorreoApiController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'para' => ['required', 'array', 'min:1', 'max:20'],
            'para.*' => ['required', 'email:rfc', 'distinct'],
            'cc' => ['sometimes', 'array', 'max:20'],
            'cc.*' => ['required', 'email:rfc', 'distinct'],
            'cco' => ['sometimes', 'array', 'max:20'],
            'cco.*' => ['required', 'email:rfc', 'distinct'],
            'asunto' => ['required', 'string', 'max:255'],
            'mensaje' => ['required', 'string', 'max:100000'],
            'formato' => ['sometimes', Rule::in(['texto', 'html'])],
            'responder_a' => ['sometimes', 'nullable', 'email:rfc'],
        ]);

        try {
            $pendingMail = Mail::to($data['para']);

            if (! empty($data['cc'])) {
                $pendingMail->cc($data['cc']);
            }

            if (! empty($data['cco'])) {
                $pendingMail->bcc($data['cco']);
            }

            $pendingMail->send(new ApiCorreoMail(
                asunto: $data['asunto'],
                mensaje: $data['mensaje'],
                formato: $data['formato'] ?? 'texto',
                responderA: $data['responder_a'] ?? null,
            ));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo enviar el correo mediante el servidor configurado.',
            ], 502);
        }

        return response()->json([
            'message' => 'Correo enviado correctamente.',
            'data' => [
                'para' => $data['para'],
                'cc' => $data['cc'] ?? [],
                'cco' => $data['cco'] ?? [],
                'asunto' => $data['asunto'],
                'formato' => $data['formato'] ?? 'texto',
                'enviado_en' => now()->toIso8601String(),
            ],
        ]);
    }
}
