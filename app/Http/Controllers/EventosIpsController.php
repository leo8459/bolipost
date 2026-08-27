<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EventosIpsController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'codigo' => ['nullable', 'string', 'max:100'],
        ]);

        $codigo = strtoupper(trim((string) ($filters['codigo'] ?? '')));
        $tracking = null;
        $package = null;
        $eventos = collect();
        $error = null;

        if ($codigo !== '') {
            try {
                $tracking = $this->fetchTracking($codigo);
                $eventos = collect(Arr::get($tracking, 'eventos_externos', []));
                $package = $this->fetchPackage($codigo);
            } catch (ConnectionException $exception) {
                Log::warning('No fue posible conectar con la API de Eventos IPS.', [
                    'codigo' => $codigo,
                    'message' => $exception->getMessage(),
                ]);
                $error = 'No fue posible conectar con el servicio de Eventos IPS. Intenta nuevamente en unos minutos.';
            } catch (\Throwable $exception) {
                report($exception);
                $error = 'El servicio de Eventos IPS no pudo responder correctamente. Intenta nuevamente.';
            }
        }

        return view('eventos_ips.index', compact('codigo', 'tracking', 'package', 'eventos', 'error'));
    }

    private function fetchTracking(string $codigo): array
    {
        $url = trim((string) config('services.tracking_sqlserver.eventos_todos_url'));

        if ($url === '') {
            throw new \RuntimeException('La API de Eventos IPS no esta configurada.');
        }

        return $this->client()->get($url, ['codigo' => $codigo])->throw()->json();
    }

    private function fetchPackage(string $codigo): ?array
    {
        $url = trim((string) config('services.tracking_sqlserver.paquetes_url'));

        if ($url === '') {
            return null;
        }

        try {
            $payload = $this->client()->get($url, [
                'page' => 1,
                'per_page' => 25,
                'q' => $codigo,
            ])->throw()->json();

            return collect(Arr::get($payload, 'data', []))
                ->first(fn ($item) => strcasecmp(trim((string) data_get($item, 'codigo')), $codigo) === 0);
        } catch (\Throwable $exception) {
            Log::warning('No fue posible obtener la ficha complementaria del paquete IPS.', [
                'codigo' => $codigo,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function client()
    {
        $token = $this->normalizeBearerToken((string) config('services.tracking_sqlserver.token'));

        if ($token === '') {
            throw new \RuntimeException('El token de la API de Eventos IPS no esta configurado.');
        }

        return Http::acceptJson()
            ->withToken($token)
            ->timeout(max(1, (int) config('services.tracking_sqlserver.timeout', 15)));
    }

    private function normalizeBearerToken(string $token): string
    {
        $token = trim($token);

        return str_starts_with(strtolower($token), 'bearer ')
            ? trim(substr($token, 7))
            : $token;
    }
}
