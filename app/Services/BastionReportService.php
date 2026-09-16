<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BastionReportService
{
    private const TYPES = [
        'ems' => ['bastion_ems', 'paquetes_ems', 'estado_id', 'nombre_destinatario'],
        'contrato' => ['bastion_contratos', 'paquetes_contrato', 'estados_id', 'nombre_d'],
        'certi' => ['bastion_certi', 'paquetes_certi', 'fk_estado', 'destinatario'],
        'ordi' => ['bastion_ordi', 'paquetes_ordi', 'fk_estado', 'destinatario'],
    ];

    public const MONTHS = ['abril' => 'Abril', 'mayo' => 'Mayo'];

    public function source(string $month = 'abril'): array
    {
        abort_unless(isset(self::MONTHS[$month]), 404);

        return json_decode(file_get_contents(resource_path('data/bastion-gestora-'.$month.'.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function rows(?Collection $source = null, bool $includeImage = false): Collection
    {
        $source ??= collect($this->source()['paquetes']);
        $codes = $source->pluck('codigo')->unique()->values()->all();
        if ($codes === []) {
            return collect();
        }

        $states = Schema::hasTable('estados') ? DB::table('estados')->pluck('nombre_estado', 'id') : collect();
        $names = Schema::hasTable('eventos') ? DB::table('eventos')->pluck('nombre_evento', 'id') : collect();
        $history = [];
        $packages = [];
        $images = [];

        foreach (self::TYPES as $type => [$archive, $live, $stateColumn, $recipient]) {
            foreach ([$archive, $live] as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                // Exclude image blobs from the package query; fetch evidence separately.
                $columns = array_values(array_intersect(Schema::getColumnListing($table), ['id', 'id_origen', 'codigo', $stateColumn, $recipient]));
                $records = DB::table($table)->select($columns)->whereIn(DB::raw('UPPER(TRIM(codigo))'), $codes)->get();
                $ids = [];
                foreach ($records as $record) {
                    $code = strtoupper(trim($record->codigo));
                    $packages[$code] = ['destinatario' => $record->$recipient ?? '', 'estado' => $states[$record->$stateColumn ?? 0] ?? ''];
                    // Archived courier relations reference the original package ID.
                    $relationId = $table === $archive ? ($record->id_origen ?? null) : $record->id;
                    if ($relationId) {
                        $ids[$relationId] = $code;
                    }
                }
                if (Schema::hasColumn($table, 'imagen')) {
                    foreach (DB::table($table)->select('codigo')->selectRaw($includeImage ? 'imagen' : '1 AS imagen')->whereIn(DB::raw('UPPER(TRIM(codigo))'), $codes)->whereNotNull('imagen')->where('imagen', '<>', '')->orderBy('id')->get() as $image) {
                        $images[strtoupper(trim($image->codigo))] = $image->imagen;
                    }
                }
                $courier = $table === $archive ? 'bastion_carteros' : 'cartero';
                $foreign = 'id_paquetes_'.($type === 'contrato' ? 'contrato' : $type);
                if ($ids !== [] && Schema::hasTable($courier) && Schema::hasColumn($courier, $foreign)) {
                    $courierQuery = DB::table($courier)->select('id', $foreign, 'id_estados', 'created_at', 'updated_at');
                    if (Schema::hasColumn($courier, 'imagen')) {
                        $courierQuery->selectRaw($includeImage ? 'imagen' : "CASE WHEN imagen IS NOT NULL AND TRIM(imagen) <> '' THEN 1 ELSE NULL END AS imagen");
                    }
                    foreach ($courierQuery->whereIn($foreign, array_keys($ids))->orderBy('updated_at')->orderBy('id')->get() as $record) {
                        $code = $ids[$record->$foreign];
                        $state = $states[$record->id_estados ?? 0] ?? '';
                        $history[$code]['cartero:'.$type.':'.$record->id] = ['nombre' => $state, 'fecha' => $record->updated_at ?? $record->created_at, 'id' => $record->id, 'entregado' => $this->delivered($state)];
                        if (trim((string) ($record->imagen ?? '')) !== '' && $this->delivered($state)) {
                            $images[$code] = $record->imagen;
                        }
                    }
                }
            }
        }

        foreach (['bastion_eventos', 'eventos_ems', 'eventos_contrato', 'eventos_certi', 'eventos_ordi'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (DB::table($table)->whereIn(DB::raw('UPPER(TRIM(codigo))'), $codes)->orderBy('created_at')->orderBy('id')->get() as $event) {
                $code = strtoupper(trim($event->codigo));
                $key = ($event->tabla_origen ?? $table).':'.($event->id_origen ?? $event->id);
                $name = $names[$event->evento_id] ?? 'Evento #'.$event->evento_id;
                $history[$code][$key] = ['nombre' => $name, 'fecha' => $event->created_at, 'id' => $event->id_origen ?? $event->id, 'entregado' => (int) $event->evento_id === 316 || $this->delivered($name)];
            }
        }

        return $source->map(function (array $row) use ($history, $packages, $images): array {
            $code = $row['codigo'];
            $events = collect($history[$code] ?? [])->filter(fn ($event) => $event['nombre'] !== '')->sortBy(fn ($event) => ($event['fecha'] ?? '').sprintf('%020d', $event['id']))->unique(fn ($event) => $event['nombre'].'|'.$event['fecha'])->values();
            $current = $packages[$code]['estado'] ?? '';
            $delivered = $current !== '' ? $this->delivered($current) : ($events->last()['entregado'] ?? false);

            return $row + [
                'destinatario' => $packages[$code]['destinatario'] ?? '',
                'historial' => $events->all(),
                'estado_actual' => $current,
                'encontrado' => isset($packages[$code]) || $events->isNotEmpty(),
                'entregado' => $delivered,
                'imagen' => $delivered ? ($images[$code] ?? null) : null,
            ];
        });
    }

    private function delivered(string $state): bool
    {
        return preg_match('/^(entregado(?:\b|_)|entrega(?:\s+final|\s+al?\s+destinatario)?$)/iu', trim($state)) === 1;
    }
}
