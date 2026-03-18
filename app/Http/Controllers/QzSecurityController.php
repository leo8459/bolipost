<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QzSecurityController extends Controller
{
    public function qzCertificate(): Response
    {
        $certificatePath = $this->resolveConfiguredPath(
            'QZ_CERTIFICATE_PATH',
            storage_path('app/keys/digital-certificate.txt')
        );

        if (!is_file($certificatePath)) {
            return response(
                'No se encontro el certificado QZ. Archivo esperado: ' . $certificatePath,
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain']
            );
        }

        $certificate = file_get_contents($certificatePath);

        if ($certificate === false || trim($certificate) === '') {
            return response(
                'No se pudo leer el certificado QZ.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain']
            );
        }

        return response($certificate, Response::HTTP_OK, [
            'Content-Type' => 'text/plain',
        ]);
    }

    public function qzSign(Request $request): Response
    {
        $payload = $request->validate([
            'request' => ['required', 'string', 'max:20000'],
        ]);

        $privateKeyPath = $this->resolveConfiguredPath(
            'QZ_PRIVATE_KEY_PATH',
            storage_path('app/keys/private-key.pem')
        );

        if (!is_file($privateKeyPath)) {
            return response(
                'No se encontro la llave privada QZ. Archivo esperado: ' . $privateKeyPath,
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain']
            );
        }

        $privateKeyContents = file_get_contents($privateKeyPath);

        if ($privateKeyContents === false || trim($privateKeyContents) === '') {
            return response(
                'No se pudo leer la llave privada QZ.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain']
            );
        }

        $privateKeyPassphrase = env('QZ_PRIVATE_KEY_PASSPHRASE');
        $privateKey = ($privateKeyPassphrase !== null && $privateKeyPassphrase !== '')
            ? openssl_pkey_get_private($privateKeyContents, (string) $privateKeyPassphrase)
            : openssl_pkey_get_private($privateKeyContents);

        if ($privateKey === false) {
            return response(
                'La llave privada QZ es invalida o la passphrase no coincide.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain']
            );
        }

        $signature = '';
        $signed = openssl_sign(
            $payload['request'],
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA512
        );

        if (!$signed) {
            return response(
                'No se pudo firmar el mensaje de QZ.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain']
            );
        }

        return response(base64_encode($signature), Response::HTTP_OK, [
            'Content-Type' => 'text/plain',
        ]);
    }

    private function resolveConfiguredPath(string $envKey, string $defaultPath): string
    {
        $path = trim((string) env($envKey, $defaultPath));

        if ($path === '') {
            return $defaultPath;
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return true;
        }

        if (str_starts_with($path, '\\\\')) {
            return true;
        }

        return str_starts_with($path, '/');
    }
}

/* <?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QzSecurityController extends Controller
{
    public function qzCertificate(): Response
    {
        $certificatePath = storage_path('app/keys/digital-certificate.txt');

        if (!is_file($certificatePath)) {
            return response(
                'No se encontró el certificado QZ.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain']
            );
        }

        $certificate = file_get_contents($certificatePath);

        if ($certificate === false || trim($certificate) === '') {
            return response(
                'No se pudo leer el certificado QZ.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain']
            );
        }

        return response($certificate, Response::HTTP_OK, [
            'Content-Type' => 'text/plain',
        ]);
    }

    public function qzSign(Request $request): Response
    {
        $payload = $request->validate([
            'request' => ['required', 'string', 'max:20000'],
        ]);

        $privateKeyPath = storage_path('app/keys/private-key.pem');

        if (!is_file($privateKeyPath)) {
            return response(
                'No se encontró la llave privada QZ.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain']
            );
        }

        $privateKeyContents = file_get_contents($privateKeyPath);

        if ($privateKeyContents === false || trim($privateKeyContents) === '') {
            return response(
                'No se pudo leer la llave privada QZ.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain']
            );
        }

        // ⚠️ Si NO usas passphrase, déjalo así:
        $privateKey = openssl_pkey_get_private($privateKeyContents);

        if ($privateKey === false) {
            return response(
                'La llave privada QZ es inválida.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain']
            );
        }

        $signature = '';
        $signed = openssl_sign(
            $payload['request'],
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA512
        );

        if (!$signed) {
            return response(
                'No se pudo firmar el mensaje de QZ.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain']
            );
        }

        return response(base64_encode($signature), Response::HTTP_OK, [
            'Content-Type' => 'text/plain',
        ]);
    }
} */