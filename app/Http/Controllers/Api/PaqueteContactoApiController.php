<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalApiToken;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaqueteContactoApiController extends Controller
{
    private const LEGACY_ABILITY = 'paquetes-contactos:read';

    private const TYPE_ABILITIES = [
        'certi' => 'paquetes-contactos:certi:read',
        'contrato' => 'paquetes-contactos:contrato:read',
        'ems' => 'paquetes-contactos:ems:read',
        'ordinario' => 'paquetes-contactos:ordinario:read',
        'solicitud' => 'paquetes-contactos:solicitud:read',
    ];

    private const RESOURCES = [
        'certi' => [
            'table' => 'paquetes_certi',
            'code' => 'codigo',
            'sender_name' => null,
            'sender_phone' => null,
            'recipient_name' => 'destinatario',
            'recipient_phone' => 'telefono',
        ],
        'contrato' => [
            'table' => 'paquetes_contrato',
            'code' => 'codigo',
            'sender_name' => 'nombre_r',
            'sender_phone' => 'telefono_r',
            'recipient_name' => 'nombre_d',
            'recipient_phone' => 'telefono_d',
        ],
        'ems' => [
            'table' => 'paquetes_ems',
            'code' => 'codigo',
            'sender_name' => 'nombre_remitente',
            'sender_phone' => 'telefono_remitente',
            'recipient_name' => 'nombre_destinatario',
            'recipient_phone' => 'telefono_destinatario',
        ],
        'ordinario' => [
            'table' => 'paquetes_ordi',
            'code' => 'codigo',
            'sender_name' => null,
            'sender_phone' => null,
            'recipient_name' => 'destinatario',
            'recipient_phone' => 'telefono',
        ],
        'solicitud' => [
            'table' => 'solicitud_clientes',
            'code' => 'codigo_solicitud',
            'sender_name' => 'nombre_remitente',
            'sender_phone' => 'telefono_remitente',
            'recipient_name' => 'nombre_destinatario',
            'recipient_phone' => 'telefono_destinatario',
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

        $items = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($currentPage, $perPage)
            ->get()
            ->map(fn (object $row): array => [
                'tipo' => $row->tipo,
                'id' => (int) $row->id,
                'codigo' => $row->codigo,
                'remitente' => [
                    'nombre' => $row->nombre_remitente,
                    'telefono' => $row->telefono_remitente !== null ? (string) $row->telefono_remitente : null,
                ],
                'destinatario' => [
                    'nombre' => $row->nombre_destinatario,
                    'telefono' => $row->telefono_destinatario !== null ? (string) $row->telefono_destinatario : null,
                ],
                'fecha_registro' => $row->created_at !== null
                    ? Carbon::parse($row->created_at)->toIso8601String()
                    : null,
            ]);

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
            'nota' => 'CERTI y ordinario no almacenan datos del remitente; esos campos se devuelven como null.',
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

        if ($requestedType !== null) {
            $requiredAbility = self::TYPE_ABILITIES[$requestedType];
            abort_unless(
                $hasLegacyAccess || in_array($requiredAbility, $abilities, true),
                403,
                'El token no tiene permiso para consultar este tipo de paquete.'
            );

            return [$requestedType];
        }

        $types = collect(array_keys(self::RESOURCES))
            ->filter(fn (string $type): bool => $hasLegacyAccess
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
            $query = DB::table($config['table'])->select([
                DB::raw("'{$type}' as tipo"),
                'id',
                $this->textColumn($config['code'], 'codigo'),
                $this->textColumn($config['sender_name'], 'nombre_remitente'),
                $this->textColumn($config['sender_phone'], 'telefono_remitente'),
                $this->textColumn($config['recipient_name'], 'nombre_destinatario'),
                $this->textColumn($config['recipient_phone'], 'telefono_destinatario'),
                'created_at',
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

        return DB::raw('CAST('.$grammar->wrap($column).' AS TEXT) as '.$alias);
    }
}
