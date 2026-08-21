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
            'fecha_desde' => ['nullable', 'required_with:fecha_hasta', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'required_with:fecha_desde', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $fechaDesde = $filters['fecha_desde'] ?? null;
        $fechaHasta = $filters['fecha_hasta'] ?? null;
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 25);
        $items = collect();
        $totalPackages = null;
        $error = null;

        try {
            $payload = $this->fetchPackages($page, $perPage, $search, $fechaDesde, $fechaHasta);
            $items = collect(Arr::get($payload, 'data', []));
            $totalFromApi = (
                Arr::get($payload, 'meta.total')
                ?? Arr::get($payload, 'pagination.total')
                ?? Arr::get($payload, 'total')
            );
            $totalPackages = is_numeric($totalFromApi) ? (int) $totalFromApi : null;
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
            'totalPackages' => $totalPackages,
            'search' => $search,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'error' => $error,
        ]);
    }

    private function fetchPackages(
        int $page,
        int $perPage,
        string $search,
        ?string $fechaDesde,
        ?string $fechaHasta,
    ): array
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

        if ($fechaDesde !== null && $fechaHasta !== null) {
            $query['fecha_desde'] = $fechaDesde;
            $query['fecha_hasta'] = $fechaHasta;
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
