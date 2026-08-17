<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaquetesIpsController extends Controller
{
    private const PER_PAGE_OPTIONS = [25, 50, 100];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 25);
        $items = collect();
        $error = null;

        try {
            $payload = $this->fetchPackages($page, $perPage, $search);
            $items = collect(Arr::get($payload, 'data', []));
        } catch (ConnectionException $exception) {
            Log::warning('No fue posible conectar con la API de Paquetes IPS.', [
                'message' => $exception->getMessage(),
            ]);
            $error = 'No fue posible conectar con el servicio de Paquetes IPS. Intenta nuevamente en unos minutos.';
        } catch (\Throwable $exception) {
            report($exception);
            $error = 'El servicio de Paquetes IPS no pudo responder correctamente. Intenta nuevamente.';
        }

        $packages = new Paginator(
            $items,
            $perPage,
            $page,
            [
                'path' => route('paquetes-ips.index'),
                'pageName' => 'page',
            ],
        );
        $packages->hasMorePagesWhen($error === null && $items->count() >= $perPage);
        $packages->appends($request->except('page'));

        return view('paquetes_ips.index', [
            'packages' => $packages,
            'search' => $search,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'error' => $error,
        ]);
    }

    private function fetchPackages(int $page, int $perPage, string $search): array
    {
        $url = trim((string) config('services.tracking_sqlserver.paquetes_url'));
        $token = $this->normalizeBearerToken((string) config('services.tracking_sqlserver.token'));

        if ($url === '' || $token === '') {
            throw new \RuntimeException('La API de Paquetes IPS no esta configurada.');
        }

        $query = [
            'page' => $page,
            'per_page' => $perPage,
        ];

        if ($search !== '') {
            $query['q'] = $search;
        }

        return Http::acceptJson()
            ->withToken($token)
            ->timeout(max(1, (int) config('services.tracking_sqlserver.timeout', 15)))
            ->get($url, $query)
            ->throw()
            ->json();
    }

    private function normalizeBearerToken(string $token): string
    {
        $token = trim($token);

        return str_starts_with(strtolower($token), 'bearer ')
            ? trim(substr($token, 7))
            : $token;
    }
}
