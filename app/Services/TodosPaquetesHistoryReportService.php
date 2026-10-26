<?php

namespace App\Services;

use App\Models\User;
use App\Support\CarteroEvent;
use App\Support\CodigoContinuacionEvent;
use App\Support\EncargadoEvent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TodosPaquetesHistoryReportService
{
    private const EVENT_TABLES = [
        'ems' => 'eventos_ems',
        'contrato' => 'eventos_contrato',
        'certi' => 'eventos_certi',
        'ordi' => 'eventos_ordi',
        'solicitud' => 'eventos_tiktoker',
    ];

    private const LOCATION_ALIASES = [
        'BENI' => 'TRINIDAD',
        'CHUQUISACA' => 'SUCRE',
        'PANDO' => 'COBIJA',
    ];

    /**
     * @return Collection<int, string>
     */
    public function parseCodes(string $rawCodes): Collection
    {
        return collect(preg_split('/[\s,;]+/u', $rawCodes) ?: [])
            ->map(fn ($code): string => $this->normalizedCode($code))
            ->filter(fn (string $code): bool => $code !== '' && $code !== 'SIN CODIGO')
            ->unique()
            ->values();
    }

    /**
     * @param  array<int, string>  $requestedCodes
     * @param  callable(int, string): void|null  $onProgress
     * @return array{contents: string, package_count: int}
     */
    public function generate(array $requestedCodes, User $generatedBy, ?callable $onProgress = null): array
    {
        $requestedCodes = collect($requestedCodes)->map(fn ($code): string => $this->normalizedCode($code))->unique()->values();
        if ($onProgress !== null) {
            $onProgress(10, 'Buscando las guías solicitadas...');
        }

        $rows = DB::query()
            ->fromSub(app(TodosPaquetesReportQuery::class)->union(), 'p')
            ->whereIn(DB::raw('UPPER(TRIM(codigo))'), $requestedCodes->all())
            ->get();

        $requestedOrder = $requestedCodes->flip();
        $rows = $rows
            ->sortBy(fn (object $row): int => (int) ($requestedOrder[$this->normalizedCode($row->codigo)] ?? PHP_INT_MAX))
            ->values()
            ->map(function (object $row): object {
                $row->origen = $this->canonicalLocationName((string) $row->origen);
                $row->destino = $this->canonicalLocationName((string) $row->destino);

                return $row;
            });

        if ($onProgress !== null) {
            $onProgress(40, 'Consultando los movimientos del historial...');
        }
        $eventsByPackage = $this->historyEventsForPackages($rows);
        if ($onProgress !== null) {
            $onProgress(70, 'Organizando los eventos y sus tiempos...');
        }
        $rows->each(function (object $row) use ($eventsByPackage): void {
            $row->eventos = $eventsByPackage->get(
                $this->historyPackageKey((string) $row->type_key, $row->codigo),
                collect(),
            );
            $row->duracion_historial = $this->historyTotalDuration($row->eventos);
        });

        $foundCodes = $rows
            ->pluck('codigo')
            ->map(fn ($code): string => $this->normalizedCode($code))
            ->unique();
        $notFoundCodes = $requestedCodes->diff($foundCodes)->values();
        $generatedAt = now();
        if ($onProgress !== null) {
            $onProgress(85, 'Generando el documento PDF...');
        }
        $pdf = Pdf::loadView('todos_paquetes.historial-pdf', [
            'packages' => $rows,
            'requestedCodes' => $requestedCodes,
            'notFoundCodes' => $notFoundCodes,
            'generatedAt' => $generatedAt,
            'generatedBy' => $generatedBy,
            'totalEvents' => $rows->sum(fn (object $row): int => $row->eventos->count()),
        ])->setPaper('A4', 'portrait');
        $contents = $pdf->output();
        if ($onProgress !== null) {
            $onProgress(95, 'Guardando el reporte PDF...');
        }

        return [
            'contents' => $contents,
            'package_count' => $rows->count(),
        ];
    }

    /**
     * @param  Collection<int, object>  $packages
     * @return Collection<string, Collection<int, object>>
     */
    private function historyEventsForPackages(Collection $packages): Collection
    {
        if ($packages->isEmpty() || ! Schema::hasTable('eventos')) {
            return collect();
        }

        return $packages
            ->groupBy('type_key')
            ->flatMap(function (Collection $typePackages, string $type): Collection {
                $eventTable = self::EVENT_TABLES[$type] ?? null;

                if ($eventTable === null || ! Schema::hasTable($eventTable)) {
                    return collect();
                }

                $codes = $typePackages
                    ->pluck('codigo')
                    ->map(fn ($code): string => $this->normalizedCode($code))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($codes === []) {
                    return collect();
                }

                $hasUserId = Schema::hasColumn($eventTable, 'user_id');
                $hasUsers = $hasUserId && Schema::hasTable('users');
                $hasClients = $type === 'solicitud'
                    && Schema::hasColumn($eventTable, 'cliente_id')
                    && Schema::hasTable('clientes');

                $query = DB::table($eventTable.' as tracking')
                    ->leftJoin('eventos as evento', 'evento.id', '=', 'tracking.evento_id')
                    ->whereIn(DB::raw('UPPER(TRIM(tracking.codigo))'), $codes)
                    ->select([
                        'tracking.id',
                        'tracking.codigo',
                        'tracking.evento_id',
                        'evento.nombre_evento',
                        'tracking.created_at',
                        Schema::hasColumn($eventTable, 'codigo_relacionado')
                            ? 'tracking.codigo_relacionado'
                            : DB::raw('NULL as codigo_relacionado'),
                        Schema::hasColumn($eventTable, 'detalle_evento')
                            ? 'tracking.detalle_evento'
                            : DB::raw('NULL as detalle_evento'),
                    ]);

                if ($hasUsers) {
                    $query->leftJoin('users as usuario', 'usuario.id', '=', 'tracking.user_id')
                        ->addSelect([
                            'tracking.user_id',
                            'usuario.name as usuario_nombre',
                            'usuario.email as usuario_email',
                            'usuario.ciudad as usuario_regional',
                            Schema::hasColumn('users', 'alias')
                                ? 'usuario.alias as usuario_alias'
                                : DB::raw('NULL as usuario_alias'),
                        ]);
                } else {
                    $query->addSelect([
                        DB::raw('NULL as user_id'),
                        DB::raw('NULL as usuario_nombre'),
                        DB::raw('NULL as usuario_email'),
                        DB::raw('NULL as usuario_regional'),
                        DB::raw('NULL as usuario_alias'),
                    ]);
                }

                if ($hasClients) {
                    $query->leftJoin('clientes as cliente', 'cliente.id', '=', 'tracking.cliente_id')
                        ->addSelect('cliente.name as cliente_nombre');
                } else {
                    $query->addSelect(DB::raw('NULL as cliente_nombre'));
                }

                return $query
                    ->orderBy('tracking.created_at')
                    ->orderBy('tracking.id')
                    ->get()
                    ->map(function (object $event) use ($type): object {
                        $eventName = CodigoContinuacionEvent::nombreMostrado(
                            trim((string) ($event->nombre_evento ?? '')),
                            $event->codigo_relacionado ?? null,
                        );
                        $eventName = EncargadoEvent::nombreMostrado($eventName, $event->detalle_evento ?? null);
                        $eventName = CarteroEvent::nombreMostrado($eventName, $event->detalle_evento ?? null);

                        $event->nombre_evento = $eventName !== '' ? $eventName : 'Movimiento sin descripcion';
                        $event->_package_key = $this->historyPackageKey($type, $event->codigo);

                        return $event;
                    });
            })
            ->groupBy('_package_key')
            ->map(function (Collection $events): Collection {
                $orderedEvents = $events->values();

                return $orderedEvents->map(function (object $event, int $index) use ($orderedEvents): object {
                    $currentDate = $event->created_at ? Carbon::parse($event->created_at) : null;
                    $previousEvent = $index > 0 ? $orderedEvents->get($index - 1) : null;
                    $previousDate = $previousEvent?->created_at ? Carbon::parse($previousEvent->created_at) : null;
                    $nextEvent = $orderedEvents->get($index + 1);
                    $nextDate = $nextEvent?->created_at ? Carbon::parse($nextEvent->created_at) : null;

                    $event->tiempo_desde_anterior = $previousDate !== null && $currentDate !== null
                        ? $this->formatHistoryDuration((int) $previousDate->diffInSeconds($currentDate))
                        : 'Evento inicial';
                    $event->tiempo_hasta_siguiente = $currentDate !== null && $nextDate !== null
                        ? $this->formatHistoryDuration((int) $currentDate->diffInSeconds($nextDate))
                        : 'Ultimo evento registrado';

                    return $event;
                });
            });
    }

    private function historyTotalDuration(Collection $events): string
    {
        $datedEvents = $events->filter(fn (object $event): bool => ! empty($event->created_at))->values();

        if ($datedEvents->isEmpty()) {
            return 'Sin historial';
        }

        if ($datedEvents->count() === 1) {
            return 'Un solo evento';
        }

        $firstDate = Carbon::parse($datedEvents->first()->created_at);
        $lastDate = Carbon::parse($datedEvents->last()->created_at);

        return $this->formatHistoryDuration((int) $firstDate->diffInSeconds($lastDate));
    }

    private function formatHistoryDuration(int $totalSeconds): string
    {
        $totalSeconds = max(0, $totalSeconds);
        $days = intdiv($totalSeconds, 86400);
        $hours = intdiv($totalSeconds % 86400, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;
        $parts = [];

        if ($days > 0) {
            $parts[] = $days.' d';
        }
        if ($hours > 0 || $days > 0) {
            $parts[] = $hours.' h';
        }
        if ($minutes > 0 || $hours > 0 || $days > 0) {
            $parts[] = $minutes.' min';
        }
        if ($seconds > 0 || $parts === []) {
            $parts[] = $seconds.' s';
        }

        return implode(' ', $parts);
    }

    private function historyPackageKey(string $type, mixed $code): string
    {
        return $type.'|'.$this->normalizedCode($code);
    }

    private function normalizedCode(mixed $code): string
    {
        return mb_strtoupper(trim((string) $code), 'UTF-8');
    }

    private function canonicalLocationName(string $location): string
    {
        $normalized = mb_strtoupper(trim($location));

        return self::LOCATION_ALIASES[$normalized] ?? $normalized;
    }
}
