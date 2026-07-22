<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class DireccionDestinoApiController extends Controller
{
    private const RESOURCES = [
        'ems' => [
            'model' => \App\Models\PaqueteEms::class,
            'table' => 'paquetes_ems',
            'code' => 'codigo',
            'recipient' => 'nombre_destinatario',
            'phone' => 'telefono_destinatario',
            'city' => 'ciudad',
            'address' => 'direccion',
            'reference' => 'referencia',
        ],
        'contrato' => [
            'model' => \App\Models\Recojo::class,
            'table' => 'paquetes_contrato',
            'code' => 'codigo',
            'recipient' => 'nombre_d',
            'phone' => 'telefono_d',
            'city' => 'destino',
            'address' => 'direccion_d',
            'reference' => 'provincia',
        ],
        'certi' => [
            'model' => \App\Models\PaqueteCerti::class,
            'table' => 'paquetes_certi',
            'code' => 'codigo',
            'recipient' => 'destinatario',
            'phone' => 'telefono',
            'city' => 'cuidad',
            'address' => 'zona',
            'reference' => 'observaciones',
        ],
        'ordi' => [
            'model' => \App\Models\PaqueteOrdi::class,
            'table' => 'paquetes_ordi',
            'code' => 'codigo',
            'recipient' => 'destinatario',
            'phone' => 'telefono',
            'city' => 'ciudad',
            'address' => 'zona',
            'reference' => 'observaciones',
        ],
    ];

    public function index(Request $request)
    {
        $data = $request->validate([
            'tipo' => ['nullable', Rule::in(array_keys(self::RESOURCES))],
            'codigo' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $types = isset($data['tipo']) ? [$data['tipo']] : array_keys(self::RESOURCES);
        $perPage = (int) ($data['per_page'] ?? 25);
        $page = (int) ($data['page'] ?? 1);

        return response()->json($this->paginatedFlat($types, $perPage, $page, $data['codigo'] ?? null));
    }

    public function todos(Request $request)
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($this->paginatedFlat(
            array_keys(self::RESOURCES),
            (int) ($data['per_page'] ?? 25),
            (int) ($data['page'] ?? 1)
        ));
    }

    public function todo(Request $request)
    {
        $data = $request->validate([
            'codigo' => ['nullable', 'string', 'max:120'],
        ]);

        $items = $this->flatItems(array_keys(self::RESOURCES), $data['codigo'] ?? null);

        return response()->json([
            'data' => $items,
            'total_registros' => $items->count(),
        ]);
    }

    public function cantidad(Request $request)
    {
        $data = $request->validate([
            'cantidad' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'desde' => ['nullable', 'integer', 'min:1'],
            'hasta' => ['nullable', 'integer', 'min:1'],
            'codigo' => ['nullable', 'string', 'max:120'],
        ]);

        $items = $this->flatItems(array_keys(self::RESOURCES), $data['codigo'] ?? null);
        $desde = (int) ($data['desde'] ?? 1);
        $hasta = (int) ($data['hasta'] ?? ($data['cantidad'] ?? 100));

        if ($hasta < $desde) {
            return response()->json([
                'message' => 'El parametro hasta debe ser mayor o igual a desde.',
            ], 422);
        }

        $cantidad = min(($hasta - $desde) + 1, 10000);
        $limitedItems = $items->slice($desde - 1, $cantidad)->values();

        return response()->json([
            'data' => $limitedItems,
            'desde' => $desde,
            'hasta' => $hasta,
            'cantidad_solicitada' => $cantidad,
            'cantidad_mostrada' => $limitedItems->count(),
            'total_disponible' => $items->count(),
            'orden' => 'mas_nuevo_a_mas_antiguo',
        ]);
    }

    public function show(string $tipo, int $id)
    {
        $model = $this->findModel($tipo, $id);

        return response()->json([
            'data' => $this->payload($tipo, $model),
        ]);
    }

    public function update(Request $request, string $tipo, int $id)
    {
        $model = $this->findModel($tipo, $id);
        $config = self::RESOURCES[$tipo];

        $data = $request->validate([
            'direccion_destino' => ['nullable', 'string', 'max:500'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'ciudad' => ['nullable', 'string', 'max:150'],
            'referencia' => ['nullable', 'string', 'max:500'],
            'telefono_destinatario' => ['nullable', 'string', 'max:80'],
        ]);

        $address = $data['direccion_destino'] ?? $data['direccion'] ?? null;
        if ($address !== null) {
            $model->{$config['address']} = $address;
        }

        $this->fillIfColumnExists($model, $config['city'], $data['ciudad'] ?? null);
        $this->fillIfColumnExists($model, $config['reference'], $data['referencia'] ?? null);
        $this->fillIfColumnExists($model, $config['phone'], $data['telefono_destinatario'] ?? null);

        $model->save();

        return response()->json([
            'message' => 'Direccion destino actualizada.',
            'data' => $this->payload($tipo, $model->refresh()),
        ]);
    }

    private function findModel(string $tipo, int $id): Model
    {
        abort_unless(isset(self::RESOURCES[$tipo]), 404, 'Tipo de paquete no soportado.');

        /** @var class-string<Model> $modelClass */
        $modelClass = self::RESOURCES[$tipo]['model'];

        return $modelClass::query()->findOrFail($id);
    }

    /**
     * @param array<int, string> $types
     * @return array<string, mixed>
     */
    private function paginatedFlat(array $types, int $perPage, int $page, ?string $codigo = null): array
    {
        $allItems = $this->flatItems($types, $codigo);

        $total = $allItems->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min($page, $lastPage);
        $items = $allItems
            ->slice(($currentPage - 1) * $perPage, $perPage)
            ->values();

        return [
            'data' => $items,
            'paginacion' => [
                'pagina_actual' => $currentPage,
                'por_pagina' => $perPage,
                'total_registros' => $total,
                'ultima_pagina' => $lastPage,
                'desde' => $total > 0 ? (($currentPage - 1) * $perPage) + 1 : null,
                'hasta' => $total > 0 ? (($currentPage - 1) * $perPage) + $items->count() : null,
            ],
        ];
    }

    /**
     * @param array<int, string> $types
     */
    private function flatItems(array $types, ?string $codigo = null)
    {
        return collect($types)
            ->flatMap(function (string $type) use ($codigo) {
                $config = self::RESOURCES[$type];
                /** @var class-string<Model> $modelClass */
                $modelClass = $config['model'];

                $query = $modelClass::query()->latest('created_at')->latest('id');

                if ($codigo !== null && trim($codigo) !== '') {
                    $query->where($config['code'], 'like', '%'.trim($codigo).'%');
                }

                return $query->get()->map(function (Model $model) use ($type) {
                    return [
                        '_fecha_orden' => optional($model->created_at)->timestamp ?? 0,
                        '_id_orden' => (int) $model->getKey(),
                        'data' => $this->payload($type, $model),
                    ];
                });
            })
            ->sortByDesc(fn (array $item) => sprintf('%020d-%020d', $item['_fecha_orden'], $item['_id_orden']))
            ->map(fn (array $item) => $item['data'])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $tipo, Model $model): array
    {
        $config = self::RESOURCES[$tipo];

        return [
            'nombre' => $model->{$config['recipient']} ?? null,
            'direccion_destinatario' => $model->{$config['address']} ?? null,
            'ciudad' => $model->{$config['city']} ?? null,
        ];
    }

    private function fillIfColumnExists(Model $model, string $column, mixed $value): void
    {
        if ($value !== null && Schema::hasColumn($model->getTable(), $column)) {
            $model->{$column} = $value;
        }
    }
}
