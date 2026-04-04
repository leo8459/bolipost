<?php

namespace App\Console\Commands;

use App\Models\TrackingSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckTracking extends Command
{
    protected $signature = 'tracking:check';

    protected $description = 'Check tracking updates and send FCM';

    private ?string $cachedFcmAccessToken = null;

    private int $cachedFcmAccessTokenExpiresAt = 0;

    public function handle(): int
    {
        $subs = TrackingSubscription::query()->get();

        if ($subs->isEmpty()) {
            Log::info('TRACKING_EMPTY');

            return self::SUCCESS;
        }

        $groupedByCodigo = $subs->groupBy(function (TrackingSubscription $sub): string {
            return trim((string) $sub->codigo);
        });

        Log::info('TRACKING_START', [
            'count' => $subs->count(),
            'codes' => $groupedByCodigo->count(),
            'at' => now()->toDateTimeString(),
        ]);

        foreach ($groupedByCodigo as $codigo => $subsByCode) {
            if ($codigo === '') {
                Log::warning('TRACKING_SKIP_EMPTY_CODE', [
                    'subscriptions' => $subsByCode->count(),
                ]);
                continue;
            }

            $tracking = $this->resolveTrackingForCodigo($codigo, $subsByCode->count());
            if ($tracking === null) {
                continue;
            }

            foreach ($subsByCode as $sub) {
                if ($this->verboseLoggingEnabled()) {
                    Log::info('TRACKING_SUB', [
                        'id' => $sub->id,
                        'codigo' => $sub->codigo,
                        'token_prefix' => $sub->fcm_token ? substr($sub->fcm_token, 0, 20) : null,
                        'last_sig' => $sub->last_sig,
                    ]);
                }

                $this->processSubscriptionUpdate(
                    $sub,
                    $tracking['sig'],
                    $tracking['latest']
                );
            }
        }

        Log::info('TRACKING_DONE', [
            'at' => now()->toDateTimeString(),
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array{sig:string,latest:array<string,mixed>}|null
     */
    private function resolveTrackingForCodigo(string $codigo, int $subscriptionsCount): ?array
    {
        try {
            $request = Http::timeout(15)
                ->withoutVerifying()
                ->acceptJson();

            $token = trim((string) env('TRACKING_API_TOKEN', ''));
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $resp = $request->get(env('TRACKING_API_URL'), [
                'codigo' => $codigo,
            ]);
        } catch (\Throwable $e) {
            Log::error('TRACKING_API_EX', [
                'codigo' => $codigo,
                'msg' => $e->getMessage(),
            ]);

            return null;
        }

        $payload = [
            'codigo' => $codigo,
            'subscriptions' => $subscriptionsCount,
            'status' => $resp->status(),
            'ok' => $resp->ok(),
            'content_type' => $resp->header('Content-Type'),
        ];
        if ($this->verboseLoggingEnabled()) {
            $payload['body'] = $resp->body();
        }
        Log::info('TRACKING_API', $payload);

        if (! $resp->ok()) {
            return null;
        }

        $data = $resp->json();

        if (! is_array($data)) {
            $warning = ['codigo' => $codigo];
            if ($this->verboseLoggingEnabled()) {
                $warning['body'] = $resp->body();
            }
            Log::warning('TRACKING_JSON_INVALID', $warning);

            return null;
        }

        $eventos = $this->extractEvents($data);

        Log::info('TRACKING_COUNTS', [
            'codigo' => $codigo,
            'eventos' => $eventos->count(),
            'subscriptions' => $subscriptionsCount,
        ]);

        $sig = $this->latestSignature($eventos);

        Log::info('TRACKING_LATEST', [
            'codigo' => $codigo,
            'computed_sig' => $sig,
        ]);

        if (! $sig) {
            Log::warning('TRACKING_NO_SIG', ['codigo' => $codigo]);

            return null;
        }

        return [
            'sig' => $sig,
            'latest' => $this->latestEventData($eventos, $sig),
        ];
    }

    /**
     * @param  array<string,mixed>  $latest
     */
    private function processSubscriptionUpdate(TrackingSubscription $sub, string $sig, array $latest): void
    {
        Log::info('TRACKING_SIG', [
            'codigo' => $sub->codigo,
            'prev' => $sub->last_sig,
            'new' => $sig,
        ]);

        if ($sub->last_sig === null) {
            $sub->last_sig = $sig;
            $sub->save();

            Log::info('TRACKING_INIT', [
                'codigo' => $sub->codigo,
                'saved' => $sig,
                'notified' => true,
            ]);

            $this->sendFcmSafe(
                $sub->fcm_token,
                (string) $sub->codigo,
                $latest,
                $sub->package_name
            );

            return;
        }

        if ($sub->last_sig !== $sig) {
            $old = $sub->last_sig;

            $sub->last_sig = $sig;
            $sub->save();

            Log::info('TRACKING_CHANGED', [
                'codigo' => $sub->codigo,
                'from' => $old,
                'to' => $sig,
            ]);

            $this->sendFcmSafe(
                $sub->fcm_token,
                (string) $sub->codigo,
                $latest,
                $sub->package_name
            );

            return;
        }

        if ($this->verboseLoggingEnabled()) {
            Log::info('TRACKING_NO_CHANGE', [
                'codigo' => $sub->codigo,
            ]);
        }
    }

    private function extractEvents(array $data): Collection
    {
        return collect($data['resultado'] ?? [])
            ->flatMap(function ($grupo) {
                $eventos = data_get($grupo, 'eventos', []);

                return is_array($eventos) ? $eventos : [];
            })
            ->filter(fn ($evento) => is_array($evento) || is_object($evento))
            ->map(fn ($evento) => (array) $evento)
            ->values();
    }

    private function latestSignature(Collection $eventos): ?string
    {
        $items = [];

        foreach ($eventos as $ev) {
            $date = $ev['updated_at'] ?? $ev['created_at'] ?? null;
            if (! $date) {
                continue;
            }

            $ts = strtotime($date);
            if ($ts === false) {
                continue;
            }

            $items[] = [
                'ts' => $ts,
                'sig' => 'tracking|'.$date.'|'
                    .($ev['id'] ?? '').'|'
                    .($ev['evento_id'] ?? '').'|'
                    .($ev['nombre_evento'] ?? '').'|'
                    .($ev['office'] ?? '').'|'
                    .($ev['condition'] ?? '').'|'
                    .($ev['next_office'] ?? ''),
            ];
        }

        if ($items === []) {
            return null;
        }

        usort($items, fn ($a, $b) => $b['ts'] <=> $a['ts']);

        return $items[0]['sig'];
    }

    private function latestEventData(Collection $eventos, string $sig = ''): array
    {
        $items = [];

        foreach ($eventos as $ev) {
            $date = $ev['updated_at'] ?? $ev['created_at'] ?? null;
            if (! $date) {
                continue;
            }

            $ts = strtotime($date);
            if ($ts === false) {
                continue;
            }

            $items[] = [
                'ts' => $ts,
                'data' => [
                    'source' => (string) ($ev['tabla_origen'] ?? $ev['servicio'] ?? 'tracking'),
                    'eventDate' => $date,
                    'eventTitle' => $ev['nombre_evento'] ?? 'Nuevo evento de tracking',
                    'eventBody' => trim((string) (($ev['servicio'] ?? '').' '.($ev['office'] ?? ''))),
                    'office' => $ev['office'] ?? '',
                    'condition' => $ev['condition'] ?? '',
                    'nextOffice' => $ev['next_office'] ?? '',
                    'sig' => $sig,
                ],
            ];
        }

        if ($items === []) {
            return [];
        }

        usort($items, fn ($a, $b) => $b['ts'] <=> $a['ts']);

        return $items[0]['data'];
    }

    private function sendFcmSafe(?string $token, string $codigo, array $latest = [], ?string $packageName = null): void
    {
        if (! $token) {
            Log::warning('FCM_SKIP_NO_TOKEN', ['codigo' => $codigo]);

            return;
        }

        Log::info('FCM_SEND', [
            'codigo' => $codigo,
            'token_prefix' => substr($token, 0, 20),
        ]);

        try {
            $this->sendFcm($token, $codigo, $latest, $packageName);
        } catch (\Throwable $e) {
            Log::error('FCM_EX', [
                'codigo' => $codigo,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    private function sendFcm(string $token, string $codigo, array $latest = [], ?string $packageName = null): void
    {
        $name = trim((string) ($packageName ?: $codigo));
        $eventText = trim((string) (
            ($latest['eventBody'] ?? '') !== ''
                ? $latest['eventBody']
                : ($latest['eventTitle'] ?? 'Se detecto un nuevo movimiento')
        ));

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => 'Nuevo evento de TrackingBo',
                    'body' => "Nombre: {$name}\nCodigo: {$codigo}\nEvento nuevo: {$eventText}",
                ],
                'data' => [
                    'codigo' => (string) $codigo,
                    'packageName' => (string) $name,
                    'eventSource' => (string) ($latest['source'] ?? ''),
                    'eventDate' => (string) ($latest['eventDate'] ?? ''),
                    'eventTitle' => (string) ($latest['eventTitle'] ?? ''),
                    'eventBody' => (string) ($latest['eventBody'] ?? ''),
                    'office' => (string) ($latest['office'] ?? ''),
                    'condition' => (string) ($latest['condition'] ?? ''),
                    'nextOffice' => (string) ($latest['nextOffice'] ?? ''),
                    'highlightSig' => (string) ($latest['sig'] ?? ''),
                    'type' => 'tracking_update',
                ],
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'channel_id' => 'default',
                        'sound' => 'default',
                    ],
                ],
            ],
        ];

        $accessToken = $this->getAccessToken();

        $resp = Http::withToken($accessToken)
            ->post(
                'https://fcm.googleapis.com/v1/projects/'.env('FIREBASE_PROJECT_ID').'/messages:send',
                $payload
            );

        Log::info('FCM_STATUS', [
            'codigo' => $codigo,
            'status' => $resp->status(),
            'body' => $this->verboseLoggingEnabled() ? $resp->body() : null,
        ]);

        if (in_array($resp->status(), [404, 410], true)) {
            TrackingSubscription::where('fcm_token', $token)->delete();

            Log::warning('FCM_TOKEN_DELETED', [
                'codigo' => $codigo,
                'status' => $resp->status(),
            ]);
        }

        if ($resp->failed()) {
            Log::warning('FCM_FAILED', [
                'codigo' => $codigo,
                'status' => $resp->status(),
            ]);
        }
    }

    private function getAccessToken(): string
    {
        if (
            is_string($this->cachedFcmAccessToken)
            && $this->cachedFcmAccessToken !== ''
            && $this->cachedFcmAccessTokenExpiresAt > (time() + 60)
        ) {
            return $this->cachedFcmAccessToken;
        }

        $client = new \Google_Client();
        $client->setAuthConfig(storage_path('app/firebase-service-account.json'));
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $token = $client->fetchAccessTokenWithAssertion();

        if (! is_array($token) || empty($token['access_token'])) {
            Log::error('FCM_ACCESS_TOKEN_FAIL', [
                'token' => $token,
            ]);

            throw new \RuntimeException('No se pudo obtener access_token para FCM');
        }

        $expiresIn = max(60, (int) ($token['expires_in'] ?? 3600));
        $this->cachedFcmAccessToken = (string) $token['access_token'];
        $this->cachedFcmAccessTokenExpiresAt = time() + $expiresIn;

        return $this->cachedFcmAccessToken;
    }

    private function verboseLoggingEnabled(): bool
    {
        return filter_var(env('TRACKING_VERBOSE_LOG', false), FILTER_VALIDATE_BOOL);
    }
}
