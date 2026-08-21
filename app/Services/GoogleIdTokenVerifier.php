<?php

namespace App\Services;

use Google\Client as GoogleClient;
use RuntimeException;

class GoogleIdTokenVerifier
{
    public function verify(string $idToken): array
    {
        $clientId = trim((string) config('services.google.client_id'));

        if ($clientId === '') {
            throw new RuntimeException('El inicio de sesion con Google no esta configurado.');
        }

        $client = new GoogleClient(['client_id' => $clientId]);
        $payload = $client->verifyIdToken($idToken);

        if (! is_array($payload) || empty($payload['sub'])) {
            throw new RuntimeException('El token de Google es invalido o ha vencido.');
        }

        return $payload;
    }
}
