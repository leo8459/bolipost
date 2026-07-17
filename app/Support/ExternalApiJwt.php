<?php

namespace App\Support;

use App\Models\ExternalApiToken;
use Illuminate\Support\Str;

class ExternalApiJwt
{
    public static function issue(ExternalApiToken $apiToken, ?int $ttlDays = null): string
    {
        $now = now();
        $payload = [
            'iss' => config('app.url'),
            'aud' => 'bolipost-direcciones-destino',
            'sub' => (string) $apiToken->id,
            'jti' => $apiToken->jti,
            'name' => $apiToken->name,
            'iat' => $now->timestamp,
        ];

        if ($ttlDays !== null && $ttlDays > 0) {
            $payload['exp'] = $now->copy()->addDays($ttlDays)->timestamp;
        }

        return self::encode($payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decode(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $expected = self::base64UrlEncode(hash_hmac(
            'sha256',
            $encodedHeader.'.'.$encodedPayload,
            self::secret(),
            true
        ));

        if (! hash_equals($expected, $encodedSignature)) {
            return null;
        }

        $header = self::jsonDecode($encodedHeader);
        if (($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $payload = self::jsonDecode($encodedPayload);
        if (! is_array($payload)) {
            return null;
        }

        $expiresAt = $payload['exp'] ?? null;
        if (is_numeric($expiresAt) && (int) $expiresAt < now()->timestamp) {
            return null;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function encode(array $payload): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $encodedHeader = self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, self::secret(), true);

        return $encodedHeader.'.'.$encodedPayload.'.'.self::base64UrlEncode($signature);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function jsonDecode(string $encoded): ?array
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);

        return is_array($data) ? $data : null;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function secret(): string
    {
        $key = (string) config('app.key');

        if (Str::startsWith($key, 'base64:')) {
            $decoded = base64_decode(Str::after($key, 'base64:'), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }
}
