<?php

namespace App\Http\Controllers;

use App\Models\Destino;
use App\Models\Servicio;
use App\Models\TrackingSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class BusquedaController extends Controller
{
    private const TRACKING_CAPTCHA_SESSION_KEY = 'tracking_captcha';
    private const TRACKING_CAPTCHA_VERIFIED_UNTIL_SESSION_KEY = 'tracking_captcha_verified_until';
    private const TRACKING_CAPTCHA_VERIFIED_MINUTES = 5;

    private const FUENTES_LOCALES = [
        ['tabla' => 'eventos_ems', 'servicio' => 'EMS'],
        ['tabla' => 'eventos_certi', 'servicio' => 'CERTI'],
        ['tabla' => 'eventos_contrato', 'servicio' => 'CONTRATO'],
        ['tabla' => 'eventos_ordi', 'servicio' => 'ORDI'],
    ];

    public function landing(Request $request): View
    {
        // Siempre generar uno nuevo al entrar a la landing para evitar
        // reutilizacion al volver con el boton "atras".
        $captcha = $this->refrescarCaptchaTracking($request);
        $captchaPublico = $this->formatearCaptchaPublico($captcha);

        return view('welcome', [
            'captchaPregunta' => $captcha['question'],
            'captchaChallenge' => $captchaPublico['challenge'],
            'preregistroServicios' => Servicio::query()->orderBy('nombre_servicio')->get(),
            'preregistroDestinos' => Destino::query()->orderBy('nombre_destino')->get(),
            'preregistroCiudades' => [
                'LA PAZ',
                'SANTA CRUZ',
                'COBIJA',
                'TRINIDAD',
                'TARIJA',
                'SUCRE',
                'ORURO',
                'COCHABAMBA',
                'POTOSI',
            ],
        ]);
    }

    public function captchaTracking(Request $request): JsonResponse
    {
        $captcha = $this->refrescarCaptchaTracking($request);
        $captchaPublico = $this->formatearCaptchaPublico($captcha);

        return response()->json([
            'pregunta' => $captcha['question'],
            'challenge' => $captchaPublico['challenge'],
            'expires_at' => $captchaPublico['expires_at'],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function captchaTrackingPublico(): JsonResponse
    {
        $captcha = $this->generarCaptchaTracking();

        return response()
            ->json($this->formatearCaptchaPublico($captcha))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function mostrarTracking(Request $request): View|RedirectResponse
    {
        if ($captchaError = $this->validarCaptchaTracking($request)) {
            return redirect('/')
                ->with('tracking_error', $captchaError);
        }

        return $this->renderTrackingDetalle($request);
    }

    public function mostrarTrackingFirmado(Request $request): View|RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        return $this->renderTrackingDetalle($request);
    }

    public function consultarEventosTracking(Request $request): JsonResponse
    {
        if ($captchaError = $this->validarCaptchaTracking($request)) {
            return response()->json([
                'message' => $captchaError,
                'captcha' => $this->datosCaptchaTracking($request),
            ], 422);
        }

        return $this->responderEventosTracking($request);
    }

    public function consultarEventosTrackingPublico(Request $request): JsonResponse
    {
        return $this->responderEventosTracking($request);
    }

    public function autorizarTrackingPublico(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9]+$/'],
            'captcha_answer' => ['required', 'string', 'max:20'],
            'captcha_challenge' => ['required', 'string'],
        ]);

        $codigo = $this->normalizeTrackingCode((string) $validated['codigo']);

        if (! $this->validarCaptchaPublico(
            (string) $validated['captcha_answer'],
            (string) $validated['captcha_challenge']
        )) {
            return response()->json([
                'message' => 'La verificacion de seguridad no es correcta.',
                'captcha' => $this->formatearCaptchaPublico($this->generarCaptchaTracking()),
            ], 422);
        }

        return response()->json([
            'codigo' => strtoupper($codigo),
            'redirect_url' => URL::temporarySignedRoute(
                'tracking.demo.signed',
                now()->addMinutes(self::TRACKING_CAPTCHA_VERIFIED_MINUTES),
                ['codigo' => $codigo]
            ),
        ]);
    }

    private function responderEventosTracking(Request $request): JsonResponse
    {
        $codigo = $this->obtenerCodigoValidado($request);
        $resultado = $this->buscarEventosPorCodigo($codigo);
        $eventos = $resultado['eventos'];

        if ($eventos->isEmpty()) {
            return response()->json([
                'tipo' => 'tracking_eventos',
                'filtro' => ['codigo' => $codigo],
                'existe_paquete' => false,
                'fuente' => $resultado['fuente'],
                'total_registros' => 0,
                'resultado' => [],
                'message' => 'No existe dicho paquete',
            ], 404);
        }

        return response()->json([
            'tipo' => 'tracking_eventos',
            'filtro' => ['codigo' => $codigo],
            'existe_paquete' => true,
            'fuente' => $resultado['fuente'],
            'total_registros' => $eventos->count(),
            'resultado' => $this->formatearEventosAgrupados($eventos),
        ]);
    }

    private function renderTrackingDetalle(Request $request): View|RedirectResponse
    {
        $codigo = $this->obtenerCodigoValidado($request);
        $resultado = $this->buscarEventosPorCodigo($codigo);
        $eventos = $resultado['eventos'];

        if ($eventos->isEmpty()) {
            return redirect('/')
                ->with('tracking_error', 'Paquete no encontrado');
        }

        return view('tracking-demo', [
            'codigo' => strtoupper($codigo),
            'eventos' => $eventos,
            'ultimoEvento' => $eventos->first(),
            'fuenteTracking' => $resultado['fuente'],
            'preregistroServicios' => Servicio::query()->orderBy('nombre_servicio')->get(),
            'preregistroDestinos' => Destino::query()->orderBy('nombre_destino')->get(),
            'preregistroCiudades' => [
                'LA PAZ',
                'SANTA CRUZ',
                'COBIJA',
                'TRINIDAD',
                'TARIJA',
                'SUCRE',
                'ORURO',
                'COCHABAMBA',
                'POTOSI',
            ],
        ]);
    }

    private function obtenerCodigoValidado(Request $request): string
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9]+$/'],
        ]);

        return $this->normalizeTrackingCode((string) $validated['codigo']);
    }

    private function validarCaptchaTracking(Request $request): ?string
    {
        $respuesta = trim((string) $request->input('captcha_answer', ''));
        $challenge = trim((string) $request->input('captcha_challenge', ''));

        // Si llega captcha desde el formulario, SIEMPRE se valida aunque
        // exista una verificacion previa en sesion.
        if ($challenge !== '' || $respuesta !== '') {
            if ($respuesta === '') {
                return 'Completa la verificacion de seguridad.';
            }

            if ($challenge !== '') {
                if (! $this->validarCaptchaPublico($respuesta, $challenge)) {
                    $this->refrescarCaptchaTracking($request);

                    return 'La verificacion de seguridad no es correcta.';
                }
            } else {
                $captcha = $request->session()->get(self::TRACKING_CAPTCHA_SESSION_KEY);

                if (!is_array($captcha) || !array_key_exists('answer', $captcha)) {
                    $this->refrescarCaptchaTracking($request);

                    return 'Completa la verificacion de seguridad.';
                }

                if (!hash_equals((string) $captcha['answer'], strtoupper($respuesta))) {
                    $this->refrescarCaptchaTracking($request);

                    return 'La verificacion de seguridad no es correcta.';
                }
            }

            $request->session()->put(
                self::TRACKING_CAPTCHA_VERIFIED_UNTIL_SESSION_KEY,
                now()->addMinutes(self::TRACKING_CAPTCHA_VERIFIED_MINUTES)->timestamp
            );
            $request->session()->forget(self::TRACKING_CAPTCHA_SESSION_KEY);

            return null;
        }

        // Sin captcha en el request (ej. redireccion al detalle), permite
        // continuar solo si ya fue verificado recientemente.
        if ($this->captchaTrackingYaFueVerificado($request)) {
            return null;
        }

        $captcha = $request->session()->get(self::TRACKING_CAPTCHA_SESSION_KEY);

        if (!is_array($captcha) || !array_key_exists('answer', $captcha)) {
            $this->refrescarCaptchaTracking($request);

            return 'Completa la verificacion de seguridad.';
        }

        return 'Completa la verificacion de seguridad.';
    }

    private function captchaTrackingYaFueVerificado(Request $request): bool
    {
        $verifiedUntil = (int) $request->session()->get(self::TRACKING_CAPTCHA_VERIFIED_UNTIL_SESSION_KEY, 0);

        return $verifiedUntil > now()->timestamp;
    }

    private function obtenerOCrearCaptchaTracking(Request $request): array
    {
        $captcha = $request->session()->get(self::TRACKING_CAPTCHA_SESSION_KEY);

        if (!is_array($captcha) || !array_key_exists('question', $captcha) || !array_key_exists('answer', $captcha)) {
            $captcha = $this->refrescarCaptchaTracking($request);
        }

        return $captcha;
    }

    private function refrescarCaptchaTracking(Request $request): array
    {
        $captcha = $this->generarCaptchaTracking();

        $request->session()->forget(self::TRACKING_CAPTCHA_VERIFIED_UNTIL_SESSION_KEY);
        $request->session()->put(self::TRACKING_CAPTCHA_SESSION_KEY, $captcha);

        return $captcha;
    }

    private function datosCaptchaTracking(Request $request): array
    {
        $captcha = $this->obtenerOCrearCaptchaTracking($request);
        $captchaPublico = $this->formatearCaptchaPublico($captcha);

        return [
            'pregunta' => $captcha['question'],
            'challenge' => $captchaPublico['challenge'],
            'expires_at' => $captchaPublico['expires_at'],
        ];
    }

    private function generarCaptchaTracking(): array
    {
        $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codigo = '';

        for ($i = 0; $i < 5; $i++) {
            $codigo .= $caracteres[random_int(0, strlen($caracteres) - 1)];
        }

        return [
            'question' => $codigo,
            'answer' => $codigo,
        ];
    }

    private function formatearCaptchaPublico(array $captcha): array
    {
        $expiresAt = now()->addMinutes(self::TRACKING_CAPTCHA_VERIFIED_MINUTES)->timestamp;

        return [
            'pregunta' => $captcha['question'],
            'challenge' => Crypt::encryptString(json_encode([
                'answer' => $captcha['answer'],
                'expires_at' => $expiresAt,
            ], JSON_THROW_ON_ERROR)),
            'expires_at' => $expiresAt,
        ];
    }

    private function validarCaptchaPublico(string $answer, string $challenge): bool
    {
        try {
            $payload = json_decode(Crypt::decryptString($challenge), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            return false;
        }

        if (! is_array($payload)) {
            return false;
        }

        $expected = strtoupper(trim((string) ($payload['answer'] ?? '')));
        $expiresAt = (int) ($payload['expires_at'] ?? 0);
        $provided = strtoupper(trim($answer));

        if ($expected === '' || $provided === '' || $expiresAt <= now()->timestamp) {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    private function formatearEventosAgrupados(Collection $eventos): Collection
    {
        return $eventos
            ->groupBy('codigo')
            ->map(function (Collection $grupo, string $codigo) {
                return [
                    'codigo' => $codigo,
                    'total_eventos' => $grupo->count(),
                    'eventos' => $grupo->values(),
                ];
            })
            ->values();
    }

    private function buscarEventosPorCodigo(string $codigo): array
    {
        $eventosApi = collect();

        try {
            $eventosApi = $this->consultarEventosDesdeApi($codigo);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($eventosApi->isNotEmpty()) {
            return [
                'eventos' => $eventosApi,
                'fuente' => 'api',
            ];
        }

        $eventosLocales = $this->consultarEventosLocales($codigo);

        if ($eventosLocales->isNotEmpty()) {
            return [
                'eventos' => $eventosLocales,
                'fuente' => 'local',
            ];
        }

        return [
            'eventos' => collect(),
            'fuente' => 'ninguna',
        ];
    }

    private function consultarEventosDesdeApi(string $codigo): Collection
    {
        $codigo = $this->normalizeTrackingCode($codigo);
        $baseUrl = $this->resolveTrackingApiUrl(
            trim((string) config('services.tracking_sqlserver.base_url', ''))
        );
        $token = trim((string) config('services.tracking_sqlserver.token', ''));

        if ($codigo === '' || $baseUrl === '' || $token === '') {
            return collect();
        }

        $response = Http::timeout(10)
            ->acceptJson()
            ->withToken($token)
            ->get($baseUrl, ['codigo' => $codigo]);

        if ($response->status() === 422) {
            Log::notice('Tracking externo rechazo el codigo por validacion.', [
                'codigo' => $codigo,
                'base_url' => $baseUrl,
            ]);

            return collect();
        }

        if (!$response->ok()) {
            throw new \RuntimeException('Error consultando API externa de tracking. HTTP ' . $response->status());
        }

        $payload = $response->json();
        $eventosApi = collect(data_get($payload, 'eventos_locales', []))
            ->merge(data_get($payload, 'eventos_externos', []))
            ->values();

        return $eventosApi
            ->map(fn ($item, $index) => $this->transformarEventoApi($item, $index, $codigo, $payload))
            ->sort(function ($a, $b) {
                $ts = ((int) ($b->_sort_ts ?? 0)) <=> ((int) ($a->_sort_ts ?? 0));
                if ($ts !== 0) {
                    return $ts;
                }

                return ((int) ($b->_sort_priority ?? 0)) <=> ((int) ($a->_sort_priority ?? 0));
            })
            ->map(function (object $evento) {
                unset($evento->_sort_ts, $evento->_sort_priority);
                return $evento;
            })
            ->values();
    }

    private function normalizeTrackingCode(string $codigo): string
    {
        $codigo = strtoupper(trim($codigo));
        $codigo = preg_replace('/\s+/', '', $codigo) ?? $codigo;

        return trim($codigo);
    }

    private function resolveTrackingApiUrl(string $configuredUrl): string
    {
        if ($configuredUrl === '') {
            return '';
        }

        $parts = parse_url($configuredUrl);
        $path = trim((string) ($parts['path'] ?? ''));

        if ($path === '' || $path === '/') {
            return rtrim($configuredUrl, '/') . '/api/tracking/eventos';
        }

        if ($path === '/api/sqlserver/busqueda') {
            return preg_replace('#/api/sqlserver/busqueda$#', '/api/tracking/eventos', $configuredUrl) ?: $configuredUrl;
        }

        return $configuredUrl;
    }

    private function transformarEventoApi(mixed $item, int $index, string $codigo, array $payload): object
    {
        $evento = is_array($item) ? $item : (array) $item;
        $nombreEvento = (string) (
            $evento['nombre_evento']
            ?? $evento['eventType']
            ?? $evento['evento']
            ?? $evento['descripcion_evento']
            ?? $evento['descripcion']
            ?? 'Evento de seguimiento'
        );
        $createdAt = (string) (
            $evento['created_at']
            ?? $evento['eventDate']
            ?? $evento['fecha_hora']
            ?? $evento['fecha_registro']
            ?? $evento['fecha']
            ?? now()->toDateTimeString()
        );
        $timestamp = strtotime($createdAt);

        return (object) [
            'id' => $evento['id'] ?? null,
            'codigo' => $this->obtenerCodigoEvento($evento, $payload, $codigo),
            'evento_id' => $evento['evento_id'] ?? $evento['id_evento'] ?? null,
            'user_id' => $evento['user_id'] ?? null,
            'created_at' => $createdAt,
            'updated_at' => $evento['updated_at'] ?? $createdAt,
            'nombre_evento' => $nombreEvento,
            'servicio' => $this->determinarServicio($evento, $payload, $codigo),
            'tabla_origen' => $evento['tabla_origen'] ?? 'api_sqlserver',
            'office' => trim((string) ($evento['office'] ?? '')),
            'next_office' => trim((string) ($evento['nextOffice'] ?? '')),
            'ciudad_origen' => null,
            'ciudad_destino' => null,
            'condition' => trim((string) ($evento['condition'] ?? '')),
            '_sort_ts' => $timestamp !== false ? $timestamp : (PHP_INT_MAX - $index),
            '_sort_priority' => $this->calcularPrioridadEvento($nombreEvento),
        ];
    }

    private function obtenerCodigoEvento(array $evento, array $payload, string $codigoDefault): string
    {
        $codigoEvento = trim((string) ($evento['codigo'] ?? ''));
        if ($codigoEvento !== '') {
            return $codigoEvento;
        }

        $codigoExterno = trim((string) ($evento['mailitM_FID'] ?? ''));
        if ($codigoExterno !== '') {
            return $codigoExterno;
        }

        return (string) ($payload['codigo'] ?? $codigoDefault);
    }

    private function consultarEventosLocales(string $codigo): Collection
    {
        $queries = collect(self::FUENTES_LOCALES)
            ->filter(fn (array $fuente) => Schema::hasTable($fuente['tabla']))
            ->map(fn (array $fuente) => $this->construirConsultaEventosLocales($fuente, $codigo))
            ->values();

        if ($queries->isEmpty()) {
            return collect();
        }

        $union = $queries->shift();
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return DB::query()
            ->fromSub($union, 'tracking')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    private function construirConsultaEventosLocales(array $fuente, string $codigo)
    {
        $query = DB::table($fuente['tabla'] . ' as ee')
            ->leftJoin('eventos as e', 'e.id', '=', 'ee.evento_id')
            ->whereRaw('TRIM(UPPER(ee.codigo)) = TRIM(UPPER(?))', [$codigo]);

        $select = [
            'ee.id',
            'ee.codigo',
            'ee.evento_id',
            'ee.user_id',
            'ee.created_at',
            'ee.updated_at',
            'e.nombre_evento',
            DB::raw("'" . $fuente['servicio'] . "' as servicio"),
            DB::raw("'" . $fuente['tabla'] . "' as tabla_origen"),
            DB::raw('NULL as office'),
            DB::raw('NULL as next_office'),
            DB::raw('NULL as condition'),
        ];

        if ($fuente['tabla'] === 'eventos_ems') {
            $query->leftJoin('paquetes_ems as p', 'p.codigo', '=', 'ee.codigo');
            $select[] = DB::raw('p.origen as ciudad_origen');
            $select[] = DB::raw('p.ciudad as ciudad_destino');
        } elseif ($fuente['tabla'] === 'eventos_certi') {
            $query->leftJoin('paquetes_certi as p', 'p.codigo', '=', 'ee.codigo');
            $select[] = DB::raw("(SELECT u.ciudad FROM eventos_certi ec LEFT JOIN users u ON u.id = ec.user_id WHERE ec.codigo = ee.codigo ORDER BY ec.created_at ASC, ec.id ASC LIMIT 1) as ciudad_origen");
            $select[] = DB::raw('p.cuidad as ciudad_destino');
        } elseif ($fuente['tabla'] === 'eventos_ordi') {
            $query->leftJoin('paquetes_ordi as p', 'p.codigo', '=', 'ee.codigo');
            $select[] = DB::raw("(SELECT u.ciudad FROM eventos_ordi eo LEFT JOIN users u ON u.id = eo.user_id WHERE eo.codigo = ee.codigo ORDER BY eo.created_at ASC, eo.id ASC LIMIT 1) as ciudad_origen");
            $select[] = DB::raw('p.ciudad as ciudad_destino');
        } elseif ($fuente['tabla'] === 'eventos_contrato' && Schema::hasTable('recojos')) {
            $query->leftJoin('recojos as r', 'r.codigo', '=', 'ee.codigo');
            $select[] = DB::raw('r.origen as ciudad_origen');
            $select[] = DB::raw('r.destino as ciudad_destino');
        } else {
            $select[] = DB::raw('NULL as ciudad_origen');
            $select[] = DB::raw('NULL as ciudad_destino');
        }

        return $query->select($select);
    }

    private function calcularPrioridadEvento(string $nombreEvento): int
    {
        $texto = mb_strtolower($nombreEvento);

        return match (true) {
            str_contains($texto, 'entregado exitosamente') => 100,
            str_contains($texto, 'intento fallido') => 90,
            str_contains($texto, 'listo para entregar') => 80,
            str_contains($texto, 'devoluci') => 70,
            str_contains($texto, 'camino a ubicaci') => 60,
            str_contains($texto, 'oficina de tr') => 50,
            str_contains($texto, 'aduana del paquete registrada') => 40,
            str_contains($texto, 'send item to customs') => 30,
            str_contains($texto, 'saca de env') => 20,
            str_contains($texto, 'recibido del cliente') => 10,
            default => 0,
        };
    }

    private function determinarServicio(array $evento, array $payload, string $codigoDefault): string
    {
        $candidatos = [
            $payload['servicio'] ?? null,
            $payload['tipo_servicio'] ?? null,
            $payload['service'] ?? null,
            $payload['service_type'] ?? null,
            $evento['servicio'] ?? null,
            $evento['tipo_servicio'] ?? null,
            $evento['service'] ?? null,
            $evento['service_type'] ?? null,
        ];

        foreach ($candidatos as $valor) {
            $servicio = strtoupper(trim((string) $valor));
            if ($servicio !== '') {
                return $servicio;
            }
        }

        $codigo = trim((string) ($evento['mailitM_FID'] ?? ''));
        if ($codigo === '') {
            $codigo = trim((string) ($payload['codigo'] ?? $codigoDefault));
        }

        $codigo = strtoupper($codigo);

        if (preg_match('/^R[A-Z]\d{9}[A-Z]{2}$/', $codigo) === 1) {
            return 'CERTIFICADAS';
        }

        if (preg_match('/^E[A-Z]\d{9}[A-Z]{2}$/', $codigo) === 1) {
            return 'EMS';
        }

        return 'TRACKING';
    }

    public function subscribe(Request $request)
{
    $data = $request->validate([
        'codigo' => ['required', 'size:13'],
        'fcm_token' => ['required', 'string'],
        'package_name' => ['nullable', 'string', 'max:120'],
    ]);

    $sub = TrackingSubscription::firstOrNew([
        'codigo' => $data['codigo'],
        'fcm_token' => $data['fcm_token'],
    ]);

    // guardar/actualizar nombre siempre que venga
    if (array_key_exists('package_name', $data) && $data['package_name'] !== null) {
        $sub->package_name = trim($data['package_name']);
    }

    if (!$sub->exists) {
        $sub->last_sig = null; // se llena en el cron
    }

    $sub->save();

    return response()->json(['ok' => true]);
}

public function unsubscribe(Request $request)
{
    $data = $request->validate([
        'codigo' => ['required', 'size:13'],
        'fcm_token' => ['required', 'string'],
    ]);

    TrackingSubscription::where('codigo', $data['codigo'])
        ->where('fcm_token', $data['fcm_token'])
        ->delete();

    return response()->json(['ok' => true]);
}

}

