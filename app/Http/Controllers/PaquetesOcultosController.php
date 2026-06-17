<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaquetesOcultosController extends Controller
{
    public function show(): BinaryFileResponse
    {
        $path = storage_path('app/private/.paquetes_privados/paquetes.html');

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
