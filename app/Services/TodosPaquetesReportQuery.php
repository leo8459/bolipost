<?php

namespace App\Services;

use App\Support\PackageWeightFilter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TodosPaquetesReportQuery
{
    private const TYPE_LABELS = [
        'ems' => 'EMS',
        'contrato' => 'CONTRATO',
        'certi' => 'CERTIFICADO',
        'ordi' => 'ORDINARIO',
        'solicitud' => 'SOLICITUD',
    ];

    public function filtered(array $filters): Builder
    {
        $query = DB::query()->fromSub($this->union(($filters['search'] ?? '') !== ''), 'p')
            ->when(array_key_exists($filters['type'] ?? '', self::TYPE_LABELS), fn ($query) => $query->where('type_key', $filters['type']))
            ->when(! empty($filters['estado_id']), fn ($query) => $query->whereIn('estado_id', $filters['estado_id']))
            ->when(($filters['search'] ?? '') !== '', function ($query) use ($filters) {
                $query->whereRaw('LOWER(search_blob) LIKE ?', ['%'.mb_strtolower($filters['search']).'%']);
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('record_id');

        return PackageWeightFilter::apply($query, $filters['peso_min'] ?? null, $filters['peso_max'] ?? null);
    }

    public function count(array $filters): int
    {
        return (int) $this->filtered($filters)->reorder()->count();
    }

    public function chunked(array $filters, callable $callback, int $chunkSize = 2000): void
    {
        $sortExpression = "COALESCE(updated_at, created_at, TIMESTAMP '1970-01-01 00:00:00')";
        $lastSortAt = null;
        $lastType = null;
        $lastRecordId = null;

        do {
            $query = $this->filtered($filters)
                ->reorder()
                ->select('p.*')
                ->addSelect(DB::raw($sortExpression.' as report_sort_at'));

            if ($lastSortAt !== null) {
                $query->where(function (Builder $query) use ($sortExpression, $lastSortAt, $lastType, $lastRecordId): void {
                    $query->whereRaw($sortExpression.' < ?', [$lastSortAt])
                        ->orWhere(function (Builder $query) use ($sortExpression, $lastSortAt, $lastType): void {
                            $query->whereRaw($sortExpression.' = ?', [$lastSortAt])
                                ->where('type_key', '>', $lastType);
                        })
                        ->orWhere(function (Builder $query) use ($sortExpression, $lastSortAt, $lastType, $lastRecordId): void {
                            $query->whereRaw($sortExpression.' = ?', [$lastSortAt])
                                ->where('type_key', '=', $lastType)
                                ->where('record_id', '<', $lastRecordId);
                        });
                });
            }

            $rows = $query
                ->orderByDesc('report_sort_at')
                ->orderBy('type_key')
                ->orderByDesc('record_id')
                ->limit($chunkSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $callback($rows);
            $last = $rows->last();
            $lastSortAt = (string) $last->report_sort_at;
            $lastType = (string) $last->type_key;
            $lastRecordId = (int) $last->record_id;
        } while ($rows->count() === $chunkSize);
    }

    public function union(bool $includeSearchBlob = false): Builder
    {
        $queries = [
            $this->selectFor('ems', 'paquetes_ems', 'estado_id', [
                'codigo' => 'codigo',
                'cod_especial' => 'cod_especial',
                'origen' => 'origen',
                'destino' => 'ciudad',
                'empresa' => null,
                'destinatario' => 'nombre_destinatario',
                'remitente' => 'nombre_remitente',
                'telefono' => 'telefono_destinatario',
                'peso' => 'peso',
                'precio' => 'precio',
                'justificacion' => 'justificacion',
            ], false, $includeSearchBlob),
            $this->selectForContrato($includeSearchBlob),
            $this->selectFor('certi', 'paquetes_certi', 'fk_estado', [
                'codigo' => 'codigo',
                'cod_especial' => 'cod_especial',
                'origen' => null,
                'destino' => 'cuidad',
                'empresa' => null,
                'destinatario' => 'destinatario',
                'remitente' => null,
                'telefono' => 'telefono',
                'peso' => 'peso',
                'precio' => 'precio',
                'justificacion' => null,
            ], false, $includeSearchBlob),
            $this->selectFor('ordi', 'paquetes_ordi', 'fk_estado', [
                'codigo' => 'codigo',
                'cod_especial' => 'cod_especial',
                'origen' => null,
                'destino' => 'ciudad',
                'empresa' => null,
                'destinatario' => 'destinatario',
                'remitente' => null,
                'telefono' => 'telefono',
                'peso' => 'peso',
                'precio' => 'precio',
                'justificacion' => null,
            ], false, $includeSearchBlob),
            $this->selectFor('solicitud', 'solicitud_clientes', 'estado_id', [
                'codigo' => "COALESCE(NULLIF(TRIM(codigo_solicitud), ''), NULLIF(TRIM(barcode), ''), 'SIN CODIGO')",
                'cod_especial' => 'cod_especial',
                'origen' => 'origen',
                'destino' => 'ciudad',
                'empresa' => null,
                'destinatario' => 'nombre_destinatario',
                'remitente' => 'nombre_remitente',
                'telefono' => 'telefono_destinatario',
                'peso' => 'peso',
                'precio' => 'precio',
                'justificacion' => 'justificacion',
            ], true, $includeSearchBlob),
        ];

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return $union;
    }

    private function selectFor(
        string $type,
        string $table,
        string $stateColumn,
        array $columns,
        bool $rawCodigo = false,
        bool $includeSearchBlob = false,
    ): Builder {
        $label = self::TYPE_LABELS[$type];
        $selects = [
            DB::raw("'".$type."' as type_key"),
            DB::raw("'".str_replace("'", "''", $label)."' as tipo"),
            DB::raw($table.'.id as record_id'),
        ];

        foreach (['codigo', 'cod_especial', 'origen', 'destino'] as $alias) {
            $column = $columns[$alias] ?? null;
            if ($alias === 'codigo' && $rawCodigo) {
                $selects[] = DB::raw($column.' as codigo');

                continue;
            }

            $selects[] = $column
                ? DB::raw('COALESCE('.$table.'.'.$column."::text, '') as ".$alias)
                : DB::raw("'' as ".$alias);
        }

        $empresaColumn = $columns['empresa'] ?? null;
        $selects[] = $empresaColumn
            ? DB::raw('COALESCE('.$table.'.'.$empresaColumn."::text, '') as empresa")
            : DB::raw("'' as empresa");

        foreach (['destinatario', 'remitente', 'telefono'] as $alias) {
            $column = $columns[$alias] ?? null;
            $selects[] = $column
                ? DB::raw('COALESCE('.$table.'.'.$column."::text, '') as ".$alias)
                : DB::raw("'' as ".$alias);
        }

        $selects[] = DB::raw("'' as codigo_madre");
        $selects[] = DB::raw('COALESCE('.$table.'.'.($columns['peso'] ?? 'id')."::text, '') as peso");
        $selects[] = DB::raw('COALESCE('.$table.'.'.($columns['precio'] ?? 'id')."::text, '') as precio");
        $selects[] = ($columns['justificacion'] ?? null)
            ? DB::raw('COALESCE('.$table.'.'.$columns['justificacion']."::text, '') as justificacion")
            : DB::raw("'' as justificacion");
        $selects[] = DB::raw($table.'.'.$stateColumn.' as estado_id');
        $selects[] = DB::raw("COALESCE(estados.nombre_estado, 'SIN ESTADO') as estado_nombre");
        $selects[] = DB::raw($table.'.created_at as created_at');
        $selects[] = DB::raw($table.'.updated_at as updated_at');
        $selects[] = DB::raw('NULL as fecha_recojo');
        $selects[] = $includeSearchBlob
            ? DB::raw($this->searchExpression($table, $columns, $rawCodigo).' as search_blob')
            : DB::raw("'' as search_blob");

        return DB::table($table)
            ->leftJoin('estados', 'estados.id', '=', $table.'.'.$stateColumn)
            ->select($selects);
    }

    private function selectForContrato(bool $includeSearchBlob = false): Builder
    {
        $table = 'paquetes_contrato';
        $label = self::TYPE_LABELS['contrato'];
        $empresaExpr = "COALESCE(NULLIF(TRIM(emp.nombre), ''), NULLIF(TRIM(emp_user.nombre), ''), '')";

        return DB::table($table)
            ->leftJoin('estados', 'estados.id', '=', $table.'.estados_id')
            ->leftJoin('empresa as emp', 'emp.id', '=', $table.'.empresa_id')
            ->leftJoin('users as u', 'u.id', '=', $table.'.user_id')
            ->leftJoin('empresa as emp_user', 'emp_user.id', '=', 'u.empresa_id')
            ->select([
                DB::raw("'contrato' as type_key"),
                DB::raw("'".$label."' as tipo"),
                DB::raw($table.'.id as record_id'),
                DB::raw('COALESCE('.$table.".codigo::text, '') as codigo"),
                DB::raw('COALESCE('.$table.".cod_especial::text, '') as cod_especial"),
                DB::raw('COALESCE('.$table.".origen::text, '') as origen"),
                DB::raw('COALESCE('.$table.".destino::text, '') as destino"),
                DB::raw($empresaExpr.' as empresa'),
                DB::raw('COALESCE('.$table.".nombre_d::text, '') as destinatario"),
                DB::raw('COALESCE('.$table.".nombre_r::text, '') as remitente"),
                DB::raw('COALESCE('.$table.".telefono_d::text, '') as telefono"),
                DB::raw('COALESCE('.$table.".codigo_madre::text, '') as codigo_madre"),
                DB::raw('COALESCE('.$table.".peso::text, '') as peso"),
                DB::raw('COALESCE('.$table.".precio::text, '') as precio"),
                DB::raw('COALESCE('.$table.".justificacion::text, '') as justificacion"),
                DB::raw($table.'.estados_id as estado_id'),
                DB::raw("COALESCE(estados.nombre_estado, 'SIN ESTADO') as estado_nombre"),
                DB::raw($table.'.created_at as created_at'),
                DB::raw($table.'.updated_at as updated_at'),
                DB::raw($table.'.fecha_recojo as fecha_recojo'),
                $includeSearchBlob ? DB::raw(
                    "LOWER(CONCAT_WS(' ', ".
                    'COALESCE('.$table.".codigo::text, ''), ".
                    'COALESCE('.$table.".codigo_madre::text, ''), ".
                    'COALESCE('.$table.".cod_especial::text, ''), ".
                    'COALESCE('.$table.".origen::text, ''), ".
                    'COALESCE('.$table.".destino::text, ''), ".
                    $empresaExpr.', '.
                    'COALESCE('.$table.".nombre_d::text, ''), ".
                    'COALESCE('.$table.".nombre_r::text, ''), ".
                    'COALESCE('.$table.".telefono_d::text, ''), ".
                    'COALESCE('.$table.".justificacion::text, ''), ".
                    "COALESCE(estados.nombre_estado, '')".
                    ')) as search_blob'
                ) : DB::raw("'' as search_blob"),
            ]);
    }

    private function searchExpression(string $table, array $columns, bool $rawCodigo): string
    {
        $parts = [];
        foreach ($columns as $alias => $column) {
            if ($column === null) {
                continue;
            }

            $parts[] = $rawCodigo && $alias === 'codigo'
                ? 'COALESCE(('.$column.")::text, '')"
                : 'COALESCE('.$table.'.'.$column."::text, '')";
        }

        $parts[] = "COALESCE(estados.nombre_estado, '')";

        return 'LOWER(CONCAT_WS(\' \', '.implode(', ', $parts).'))';
    }
}
