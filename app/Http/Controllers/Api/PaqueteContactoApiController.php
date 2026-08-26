<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalApiToken;
use App\Support\CarteroEvent;
use App\Support\CodigoContinuacionEvent;
use App\Support\EncargadoEvent;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaqueteContactoApiController extends Controller
{
    private const LEGACY_ABILITY = 'paquetes-contactos:read';

    private const ALL_PACKAGES_EVENTS_ABILITY = 'paquetes-eventos:read';

    private const TYPE_ABILITIES = [
        'certi' => 'paquetes-contactos:certi:read',
        'contrato' => 'paquetes-contactos:contrato:read',
        'ems' => 'paquetes-contactos:ems:read',
        'ordinario' => 'paquetes-contactos:ordinario:read',
        'solicitud' => 'paquetes-contactos:solicitud:read',
    ];

    private const EVENT_TABLES = [
        'certi' => 'eventos_certi',
        'contrato' => 'eventos_contrato',
        'ems' => 'eventos_ems',
        'ordinario' => 'eventos_ordi',
        'solicitud' => 'eventos_tiktoker',
    ];

    private const RESOURCES = [
        'certi' => [
            'table' => 'paquetes_certi',
            'code' => 'codigo',
            'origin' => null,
            'destination' => 'cuidad',
            'state_key' => 'fk_estado',
            'sender_name' => null,
            'sender_phone' => null,
            'recipient_name' => 'destinatario',
            'recipient_phone' => 'telefono',
            'recipient_address' => 'zona',
        ],
        'contrato' => [
            'table' => 'paquetes_contrato',
            'code' => 'codigo',
            'origin' => 'origen',
            'destination' => 'destino',
            'state_key' => 'estados_id',
            'sender_name' => 'nombre_r',
            'sender_phone' => 'telefono_r',
            'recipient_name' => 'nombre_d',
            'recipient_phone' => 'telefono_d',
            'recipient_address' => 'direccion_d',
        ],
        'ems' => [
            'table' => 'paquetes_ems',
            'code' => 'codigo',
            'origin' => 'origen',
            'destination' => 'ciudad',
            'state_key' => 'estado_id',
            'sender_name' => 'nombre_remitente',
            'sender_phone' => 'telefono_remitente',
            'recipient_name' => 'nombre_destinatario',
            'recipient_phone' => 'telefono_destinatario',
            'recipient_address' => 'direccion',
        ],
        'ordinario' => [
            'table' => 'paquetes_ordi',
            'code' => 'codigo',
            'origin' => null,
            'destination' => 'ciudad',
            'state_key' => 'fk_estado',
            'sender_name' => null,
            'sender_phone' => null,
            'recipient_name' => 'destinatario',
            'recipient_phone' => 'telefono',
            'recipient_address' => 'zona',
        ],
        'solicitud' => [
            'table' => 'solicitud_clientes',
            'code' => 'codigo_solicitud',
            'origin' => 'origen',
            'destination' => 'ciudad',
            'state_key' => 'estado_id',
            'sender_name' => 'nombre_remitente',
            'sender_phone' => 'telefono_remitente',
            'recipient_name' => 'nombre_destinatario',
            'recipient_phone' => 'telefono_destinatario',
            'recipient_address' => 'direccion',
        ],
    ];

    public function index(Request $request, ?string $tipo = null): JsonResponse
    {
        abort_if($tipo !== null && ! isset(self::RESOURCES[$tipo]), 404, 'Tipo de paquete no soportado.');

        $data = $request->validate([
            'codigo' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $types = $this->authorizedTypes($request, $tipo);
        $perPage = (int) ($data['per_page'] ?? 50);
        $page = (int) ($data['page'] ?? 1);
        $query = $this->unifiedQuery($types, $data['codigo'] ?? null);
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min($page, $lastPage);

        $rows = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($currentPage, $perPage)
            ->get();
        $eventsByPackage = $this->eventsForPackages($rows);

        $items = $rows->map(function (object $row) use ($eventsByPackage): array {
            $events = $eventsByPackage->get($this->packageKey($row->tipo, $row->codigo), collect());

            return [
                'tipo' => $row->tipo,
                'id' => (int) $row->id,
                'codigo' => $row->codigo,
                'origen' => $row->origen,
                'destino' => $row->destino,
                'estado' => [
                    'id' => $row->estado_id !== null ? (int) $row->estado_id : null,
                    'nombre' => $row->estado,
                ],
                'remitente' => [
                    'nombre' => $row->nombre_remitente,
                    'telefono' => $row->telefono_remitente !== null ? (string) $row->telefono_remitente : null,
                ],
                'destinatario' => [
                    'nombre' => $row->nombre_destinatario,
                    'telefono' => $row->telefono_destinatario !== null ? (string) $row->telefono_destinatario : null,
                    'direccion' => $row->direccion_destinatario,
                ],
                'fecha_registro' => $row->created_at !== null
                    ? Carbon::parse($row->created_at)->toIso8601String()
                    : null,
                'cantidad_eventos' => $events->count(),
                'eventos' => $events->values(),
            ];
        });

        return response()->json([
            'data' => $items,
            'paginacion' => [
                'pagina_actual' => $currentPage,
                'por_pagina' => $perPage,
                'total_registros' => $total,
                'ultima_pagina' => $lastPage,
                'desde' => $total > 0 ? (($currentPage - 1) * $perPage) + 1 : null,
                'hasta' => $total > 0 ? (($currentPage - 1) * $perPage) + $items->count() : null,
            ],
            'tipos_incluidos' => $types,
            'nota' => 'CERTI y ordinario no almacenan origen ni datos del remitente; esos campos se devuelven como null.',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function authorizedTypes(Request $request, ?string $requestedType): array
    {
        /** @var ExternalApiToken|null $token */
        $token = $request->attributes->get('external_api_token');
        $abilities = is_array($token?->abilities) ? $token->abilities : [];
        $hasLegacyAccess = in_array(self::LEGACY_ABILITY, $abilities, true);
        $hasAllPackagesEventsAccess = in_array(self::ALL_PACKAGES_EVENTS_ABILITY, $abilities, true);

        if ($requestedType !== null) {
            $requiredAbility = self::TYPE_ABILITIES[$requestedType];
            abort_unless(
                $hasLegacyAccess || $hasAllPackagesEventsAccess || in_array($requiredAbility, $abilities, true),
                403,
                'El token no tiene permiso para consultar este tipo de paquete.'
            );

            return [$requestedType];
        }

        $types = collect(array_keys(self::RESOURCES))
            ->filter(fn (string $type): bool => $hasLegacyAccess || $hasAllPackagesEventsAccess
                || in_array(self::TYPE_ABILITIES[$type], $abilities, true))
            ->values()
            ->all();

        abort_if($types === [], 403, 'El token no tiene permisos para consultar paquetes.');

        return $types;
    }

    /**
     * @param  array<int, string>  $types
     */
    private function unifiedQuery(array $types, ?string $codigo): Builder
    {
        $queries = collect($types)->map(function (string $type) use ($codigo): Builder {
            $config = self::RESOURCES[$type];
            $query = DB::table($config['table'])
                ->leftJoin('estados', $config['table'].'.'.$config['state_key'], '=', 'estados.id')
                ->select([
                    DB::raw("'{$type}' as tipo"),
                    $config['table'].'.id',
                    $this->textColumn($config['code'], 'codigo'),
                    $this->textColumn($config['origin'], 'origen'),
                    $this->textColumn($config['destination'], 'destino'),
                    $this->textColumn($config['state_key'], 'estado_id'),
                    $this->textColumn('estados.nombre_estado', 'estado'),
                    $this->textColumn($config['sender_name'], 'nombre_remitente'),
                    $this->textColumn($config['sender_phone'], 'telefono_remitente'),
                    $this->textColumn($config['recipient_name'], 'nombre_destinatario'),
                    $this->textColumn($config['recipient_phone'], 'telefono_destinatario'),
                    $this->textColumn($config['recipient_address'], 'direccion_destinatario'),
                    $config['table'].'.created_at',
                ]);

            if ($codigo !== null && trim($codigo) !== '') {
                $query->where($config['code'], 'like', '%'.trim($codigo).'%');
            }

            return $query;
        })->values();

        /** @var Builder $union */
        $union = $queries->shift();
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return DB::query()->fromSub($union, 'paquetes_contactos');
    }

    private function textColumn(?string $column, string $alias): mixed
    {
        $grammar = DB::connection()->getQueryGrammar();
        $alias = $grammar->wrap($alias);

        if ($column === null) {
            return DB::raw('CAST(NULL AS TEXT) as '.$alias);
        }

        $qualifiedColumn = collect(explode('.', $column))
            ->map(fn (string $segment): string => $grammar->wrap($segment))
            ->implode('.');

        return DB::raw('CAST('.$qualifiedColumn.' AS TEXT) as '.$alias);
    }

    /**
     * Obtiene el historial completo en una consulta por tipo, evitando una consulta por paquete.
     *
     * @param  Collection<int, object>  $packages
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    private function eventsForPackages(Collection $packages): Collection
    {
        return $packages
            ->groupBy('tipo')
            ->flatMap(function (Collection $typePackages, string $type): Collection {
                $eventTable = self::EVENT_TABLES[$type] ?? null;

                if ($eventTable === null || ! Schema::hasTable($eventTable) || ! Schema::hasTable('eventos')) {
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

                $hasUsers = Schema::hasTable('users');
                $hasClients = $type === 'solicitud'
                    && Schema::hasTable('clientes')
                    && Schema::hasColumn($eventTable, 'cliente_id');

                $query = DB::table($eventTable.' as tracking')
                    ->leftJoin('eventos as evento', 'evento.id', '=', 'tracking.evento_id')
                    ->whereIn(DB::raw('UPPER(TRIM(tracking.codigo))'), $codes)
                    ->select([
                        'tracking.id',
                        'tracking.codigo',
                        'tracking.evento_id',
                        'evento.nombre_evento',
                        'tracking.created_at',
                    ]);

                if ($hasUsers) {
                    $query->leftJoin('users as usuario', 'usuario.id', '=', 'tracking.user_id')
                        ->addSelect(['tracking.user_id', 'usuario.name as usuario_nombre']);
                } else {
                    $query->addSelect([DB::raw('NULL as user_id'), DB::raw('NULL as usuario_nombre')]);
                }

                if ($hasClients) {
                    $query->leftJoin('clientes as cliente', 'cliente.id', '=', 'tracking.cliente_id')
                        ->addSelect(['tracking.cliente_id', 'cliente.name as cliente_nombre']);
                } else {
                    $query->addSelect([DB::raw('NULL as cliente_id'), DB::raw('NULL as cliente_nombre')]);
                }

                $query->addSelect([
                    Schema::hasColumn($eventTable, 'codigo_relacionado')
                        ? 'tracking.codigo_relacionado'
                        : DB::raw('NULL as codigo_relacionado'),
                    Schema::hasColumn($eventTable, 'detalle_evento')
                        ? 'tracking.detalle_evento'
                        : DB::raw('NULL as detalle_evento'),
                ]);

                return $query
                    ->orderBy('tracking.created_at')
                    ->orderBy('tracking.id')
                    ->get()
                    ->map(function (object $event) use ($type): array {
                        $eventName = CodigoContinuacionEvent::nombreMostrado(
                            (string) ($event->nombre_evento ?? ''),
                            $event->codigo_relacionado ?? null
                        );
                        $eventName = EncargadoEvent::nombreMostrado($eventName, $event->detalle_evento ?? null);
                        $eventName = CarteroEvent::nombreMostrado($eventName, $event->detalle_evento ?? null);

                        return [
                            '_package_key' => $this->packageKey($type, $event->codigo),
                            'id' => (int) $event->id,
                            'evento_id' => $event->evento_id !== null ? (int) $event->evento_id : null,
                            'nombre' => $eventName !== '' ? $eventName : null,
                            'detalle' => $event->detalle_evento,
                            'codigo_relacionado' => $event->codigo_relacionado,
                            'usuario' => [
                                'id' => $event->user_id !== null ? (int) $event->user_id : null,
                                'nombre' => $event->usuario_nombre,
                            ],
                            'cliente' => [
                                'id' => $event->cliente_id !== null ? (int) $event->cliente_id : null,
                                'nombre' => $event->cliente_nombre,
                            ],
                            'fecha' => $event->created_at !== null
                                ? Carbon::parse($event->created_at)->toIso8601String()
                                : null,
                        ];
                    });
            })
            ->groupBy('_package_key')
            ->map(fn (Collection $events): Collection => $events
                ->map(function (array $event): array {
                    unset($event['_package_key']);

                    return $event;
                })
                ->values());
    }

    private function packageKey(string $type, mixed $code): string
    {
        return $type.'|'.$this->normalizedCode($code);
    }

    private function normalizedCode(mixed $code): string
    {
        return mb_strtoupper(trim((string) $code), 'UTF-8');
    }
}
