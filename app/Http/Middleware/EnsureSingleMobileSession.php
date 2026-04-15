<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleMobileSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $userId = (int) ($user?->id ?? 0);
        $sessionId = trim((string) $request->session()->getId());
        $deviceId = $this->resolveMobileDeviceId($request);

        if ($userId <= 0 || $sessionId === '') {
            return $next($request);
        }

        $cacheKey = $this->mobileSessionCacheKey($userId);
        $activeSession = $this->resolveActiveSessionPayload(Cache::get($cacheKey));

        if (!$activeSession) {
            Cache::put($cacheKey, [
                'session_id' => $sessionId,
                'device_id' => $deviceId,
            ], now()->addMinutes($this->mobileSessionWindowMinutes()));
            return $next($request);
        }

        $activeSessionId = trim((string) ($activeSession['session_id'] ?? ''));
        $activeDeviceId = trim((string) ($activeSession['device_id'] ?? ''));

        if ($activeSessionId === '' || !$this->sessionRecordExists($activeSessionId)) {
            Cache::put($cacheKey, [
                'session_id' => $sessionId,
                'device_id' => $deviceId,
            ], now()->addMinutes($this->mobileSessionWindowMinutes()));
            return $next($request);
        }

        if (
            $deviceId !== ''
            && $activeDeviceId !== ''
            && hash_equals($activeDeviceId, $deviceId)
        ) {
            Cache::put($cacheKey, [
                'session_id' => $sessionId,
                'device_id' => $deviceId,
            ], now()->addMinutes($this->mobileSessionWindowMinutes()));
            return $next($request);
        }

        if ($deviceId !== '' && $activeDeviceId === '') {
            Cache::put($cacheKey, [
                'session_id' => $sessionId,
                'device_id' => $deviceId,
            ], now()->addMinutes($this->mobileSessionWindowMinutes()));
            return $next($request);
        }

        if ($activeSessionId === $sessionId) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Tu cuenta se inicio en otro dispositivo. Esta sesion fue cerrada por seguridad.',
            'session_conflict' => true,
        ], 401);
    }

    private function mobileSessionCacheKey(int $userId): string
    {
        return "mobile_auth:active_session:user:{$userId}";
    }

    private function mobileSessionWindowMinutes(): int
    {
        return 5;
    }

    private function resolveMobileDeviceId(Request $request): string
    {
        return trim((string) ($request->header('X-Mobile-Device-Id') ?: $request->header('X-Device-Id') ?: ''));
    }

    private function resolveActiveSessionPayload(mixed $cached): ?array
    {
        if (is_array($cached)) {
            $sessionId = trim((string) ($cached['session_id'] ?? ''));
            return $sessionId !== ''
                ? [
                    'session_id' => $sessionId,
                    'device_id' => trim((string) ($cached['device_id'] ?? '')),
                ]
                : null;
        }

        if (is_string($cached) && trim($cached) !== '') {
            return [
                'session_id' => trim($cached),
                'device_id' => '',
            ];
        }

        return null;
    }

    private function sessionRecordExists(string $sessionId): bool
    {
        $resolvedSessionId = trim($sessionId);
        if ($resolvedSessionId === '') {
            return false;
        }

        if (config('session.driver') === 'file') {
            $path = storage_path('framework/sessions/' . $resolvedSessionId);
            if (!is_file($path)) {
                return false;
            }

            $lifetimeSeconds = max(60, ((int) config('session.lifetime', 120)) * 60);
            $lastTouchedAt = @filemtime($path);
            if (!is_int($lastTouchedAt) || $lastTouchedAt <= 0) {
                return true;
            }

            return ($lastTouchedAt + $lifetimeSeconds) >= time();
        }

        return false;
    }
}
