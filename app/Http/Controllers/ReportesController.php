<?php

namespace App\Http\Controllers;

use App\Exports\ReportesExport;
use App\Models\Estado;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReportesController extends Controller
{
    private const EVENTO_ENTREGADO_ID = 316;
    private const EVENTO_EMS_SOLICITUD_ID = 295;
    private const CERTI_ORDI_GREEN_DAYS = 7;
    private const CERTI_ORDI_YELLOW_DAYS = 15;
    private const DESTINOS_LARGA_DISTANCIA = ['SANTA CRUZ', 'BENI', 'TARIJA'];
    private const DESTINOS_BASE = ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'CHUQUISACA', 'BENI', 'PANDO'];
    private const DESTINOS_CAPITALES = ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'SUCRE', 'TRINIDAD', 'COBIJA'];
    private const SCOPES = ['general', 'contrato', 'ems', 'certi', 'ordi'];

    private const MODULES = [
        'contrato' => ['label' => 'CONTRATOS', 'table' => 'paquetes_contrato', 'state_col' => 'estados_id'],
        'ems' => ['label' => 'EMS', 'table' => 'paquetes_ems', 'state_col' => 'estado_id'],
        'certi' => ['label' => 'CERTIFICADOS', 'table' => 'paquetes_certi', 'state_col' => 'fk_estado'],
        'ordi' => ['label' => 'ORDINARIOS', 'table' => 'paquetes_ordi', 'state_col' => 'fk_estado'],
    ];

    public function index(Request $request)
    {
        return redirect()->route('reportes.scope', array_merge(['scope' => 'general'], $request->query()));
    }

    public function show(Request $request, string $scope)
    {
        $scope = $this->normalizeScope($scope);
        $data = $this->buildReportData($request, $scope, false);

        return view('reportes.index', $data);
    }

    public function exportExcel(Request $request, string $scope)
    {
        $scope = $this->normalizeScope($scope);
        $data = $this->buildReportData($request, $scope, true);
        $filename = 'reportes-' . $scope . '-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new ReportesExport($data), $filename);
    }

    public function exportPdf(Request $request, string $scope)
    {
        $scope = $this->normalizeScope($scope);
        $data = $this->buildReportData($request, $scope, true);
        $pdf = Pdf::loadView('reportes.report-pdf', $data)->setPaper('A4', 'landscape');
        $filename = 'reportes-' . $scope . '-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    private function buildReportData(Request $request, string $scope, bool $forExport): array
    {
        $selectedModules = $this->resolveSelectedModules($scope, $request);
        [$from, $to, $range] = $this->resolveDateRange($request);
        $search = trim((string) $request->query('q', ''));
        $statuses = $this->resolveStatusFilters($request);
        $estadoIds = [];
        $limit = $this->resolveLimit($request);
        $perPage = $this->resolvePerPage($request);
        $estadoEntregadoId = $this->resolveEstadoEntregadoId();

        $rows = collect();
        foreach ($selectedModules as $moduleKey) {
            $rows = $rows->concat(
                $this->fetchRowsForModule(
                    $moduleKey,
                    $search,
                    'all',
                    [],
                    $estadoEntregadoId,
                    $from,
                    $to
                )
            );
        }

        $registradosTotal = $rows->count();
        $rows = $this->filterRowsByState($rows, $statuses);
        $filteredTotal = $rows->count();
        $summary = $this->buildSummary($rows);
        $summary['registrados'] = $registradosTotal;
        $summary['total_filtrado'] = $filteredTotal;

        $rows = $rows->sortByDesc(fn ($row) => $row['created_at_ts'] ?? 0)->values();
        if ($limit !== null) {
            $rows = $rows->take($limit)->values();
        }

        $moduleSummary = $this->buildModuleSummary($rows, $selectedModules);
        $totals = $this->buildTotals($rows);
        $rowsView = $forExport ? $rows : $this->paginateCollection($rows, $perPage, $request);

        return [
            'scope' => $scope,
            'scopeLabel' => $this->scopeLabel($scope),
            'moduleLabels' => array_map(fn ($m) => self::MODULES[$m]['label'], $selectedModules),
            'selectedModules' => $selectedModules,
            'states' => Estado::query()->orderBy('nombre_estado')->get(['id', 'nombre_estado']),
            'selectedEstadoIds' => $estadoIds,
            'search' => $search,
            'statuses' => $statuses,
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'range' => $range,
            'limit' => $limit === null ? 'all' : (string) $limit,
            'perPage' => $perPage,
            'rows' => $rowsView,
            'summary' => $summary,
            'moduleSummary' => $moduleSummary,
            'totals' => $totals,
            'isExport' => $forExport,
        ];
    }

    private function fetchRowsForModule(
        string $moduleKey,
        string $search,
        string $status,
        array $estadoIds,
        ?int $estadoEntregadoId,
        ?Carbon $from,
        ?Carbon $to
    ): Collection {
        $config = self::MODULES[$moduleKey];
        $query = match ($moduleKey) {
            'contrato' => $this->buildContratoQuery(),
            'ems' => $this->buildEmsQuery(),
            'certi' => $this->buildCertiQuery(),
            'ordi' => $this->buildOrdiQuery(),
        };

        $this->applyDateFilter($query, 't.created_at', $from, $to);
        $this->applyStatusFilter($query, 't.' . $config['state_col'], $status, $estadoEntregadoId);
        if (!empty($estadoIds)) {
            $query->whereIn('t.' . $config['state_col'], $estadoIds);
        }

        $this->applySearchFilter($query, $moduleKey, $search);

        return $query->get()->map(function ($row) use ($moduleKey, $estadoEntregadoId) {
            return $this->decorateRow($moduleKey, $row, $estadoEntregadoId);
        });
    }

    private function buildContratoQuery(): Builder
    {
        return DB::table('paquetes_contrato as t')
            ->leftJoin('estados as e', 'e.id', '=', 't.estados_id')
            ->leftJoin('empresa as emp', 'emp.id', '=', 't.empresa_id')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->select([
                DB::raw("'contrato' as modulo_key"),
                DB::raw("'CONTRATOS' as modulo_label"),
                't.codigo',
                DB::raw('t.estados_id as estado_id'),
                DB::raw("coalesce(e.nombre_estado, '-') as estado_nombre"),
                DB::raw("coalesce(t.origen, '-') as origen"),
                DB::raw("coalesce(t.destino, '-') as destino"),
                DB::raw("coalesce(t.nombre_r, '-') as remitente"),
                DB::raw("coalesce(t.nombre_d, '-') as destinatario"),
                DB::raw("coalesce(emp.nombre, '-') as empresa"),
                DB::raw("coalesce(u.name, '-') as usuario"),
                DB::raw('coalesce(t.peso, 0) as peso'),
                DB::raw('coalesce(t.precio, 0) as precio'),
                't.created_at',
                't.updated_at',
                't.fecha_recojo',
                't.provincia',
            ]);
    }

    private function buildEmsQuery(): Builder
    {
        $solicitudSub = DB::table('eventos_ems')
            ->select('codigo', DB::raw('MIN(created_at) as solicitud_at'))
            ->where('evento_id', self::EVENTO_EMS_SOLICITUD_ID)
            ->groupBy('codigo');

        return DB::table('paquetes_ems as t')
            ->leftJoin('estados as e', 'e.id', '=', 't.estado_id')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->leftJoinSub($solicitudSub, 'ev_s', function ($join) {
                $join->on('ev_s.codigo', '=', 't.codigo');
            })
            ->select([
                DB::raw("'ems' as modulo_key"),
                DB::raw("'EMS' as modulo_label"),
                't.codigo',
                DB::raw('t.estado_id as estado_id'),
                DB::raw("coalesce(e.nombre_estado, '-') as estado_nombre"),
                DB::raw("coalesce(t.origen, '-') as origen"),
                DB::raw("coalesce(t.ciudad, '-') as destino"),
                DB::raw("coalesce(t.nombre_remitente, '-') as remitente"),
                DB::raw("coalesce(t.nombre_destinatario, '-') as destinatario"),
                DB::raw("'-' as empresa"),
                DB::raw("coalesce(u.name, '-') as usuario"),
                DB::raw('coalesce(t.peso, 0) as peso'),
                DB::raw('coalesce(t.precio, 0) as precio'),
                't.created_at',
                't.updated_at',
                'ev_s.solicitud_at',
            ]);
    }

    private function buildCertiQuery(): Builder
    {
        $inicioSub = DB::table('eventos_certi')
            ->select('codigo', DB::raw('MIN(created_at) as primer_evento_at'))
            ->groupBy('codigo');

        return DB::table('paquetes_certi as t')
            ->leftJoin('estados as e', 'e.id', '=', 't.fk_estado')
            ->leftJoinSub($inicioSub, 'ev_i', function ($join) {
                $join->on('ev_i.codigo', '=', 't.codigo');
            })
            ->select([
                DB::raw("'certi' as modulo_key"),
                DB::raw("'CERTIFICADOS' as modulo_label"),
                't.codigo',
                DB::raw('t.fk_estado as estado_id'),
                DB::raw("coalesce(e.nombre_estado, '-') as estado_nombre"),
                DB::raw("'-' as origen"),
                DB::raw("coalesce(t.cuidad, '-') as destino"),
                DB::raw("'-' as remitente"),
                DB::raw("coalesce(t.destinatario, '-') as destinatario"),
                DB::raw("'-' as empresa"),
                DB::raw("'-' as usuario"),
                DB::raw('coalesce(t.peso, 0) as peso'),
                DB::raw('0 as precio'),
                't.created_at',
                't.updated_at',
                'ev_i.primer_evento_at',
            ]);
    }

    private function buildOrdiQuery(): Builder
    {
        $inicioSub = DB::table('eventos_ordi')
            ->select('codigo', DB::raw('MIN(created_at) as primer_evento_at'))
            ->groupBy('codigo');

        return DB::table('paquetes_ordi as t')
            ->leftJoin('estados as e', 'e.id', '=', 't.fk_estado')
            ->leftJoinSub($inicioSub, 'ev_i', function ($join) {
                $join->on('ev_i.codigo', '=', 't.codigo');
            })
            ->select([
                DB::raw("'ordi' as modulo_key"),
                DB::raw("'ORDINARIOS' as modulo_label"),
                't.codigo',
                DB::raw('t.fk_estado as estado_id'),
                DB::raw("coalesce(e.nombre_estado, '-') as estado_nombre"),
                DB::raw("'-' as origen"),
                DB::raw("coalesce(t.ciudad, '-') as destino"),
                DB::raw("'-' as remitente"),
                DB::raw("coalesce(t.destinatario, '-') as destinatario"),
                DB::raw("'-' as empresa"),
                DB::raw("'-' as usuario"),
                DB::raw('coalesce(t.peso, 0) as peso'),
                DB::raw('0 as precio'),
                't.created_at',
                't.updated_at',
                'ev_i.primer_evento_at',
            ]);
    }

    private function decorateRow(string $moduleKey, object $row, ?int $estadoEntregadoId): array
    {
        $isEntregado = $estadoEntregadoId && (int) ($row->estado_id ?? 0) === $estadoEntregadoId;
        $bucket = 'sin_datos';
        $situacion = 'Sin datos';

        if ($isEntregado) {
            $bucket = 'entregado';
            $situacion = 'Entregado';
        } else {
            $inicio = match ($moduleKey) {
                'contrato' => $this->safeCarbon($row->fecha_recojo ?? null),
                'ems' => $this->safeCarbon($row->solicitud_at ?? null) ?? $this->safeCarbon($row->created_at ?? null),
                default => $this->safeCarbon($row->primer_evento_at ?? null) ?? $this->safeCarbon($row->created_at ?? null),
            };

            if ($moduleKey === 'contrato') {
                $esProvincia = trim((string) ($row->provincia ?? '')) !== '';
                $umbral = $this->resolveEmsThresholdDays((string) ($row->destino ?? ''), $esProvincia);
                $bucket = $this->resolveSituacionBucket($inicio, now(), $umbral['green'], $umbral['yellow']);
            } elseif ($moduleKey === 'ems') {
                $destino = (string) ($row->destino ?? '');
                $esProvincia = $this->isEmsProvincia($destino);
                $umbral = $this->resolveEmsThresholdDays($destino, $esProvincia);
                $bucket = $this->resolveSituacionBucket($inicio, now(), $umbral['green'], $umbral['yellow']);
            } else {
                $bucket = $this->resolveSituacionBucket($inicio, now(), self::CERTI_ORDI_GREEN_DAYS, self::CERTI_ORDI_YELLOW_DAYS);
            }

            $situacion = match ($bucket) {
                'correcto' => 'Correcto',
                'retraso' => 'Retraso',
                'rezago' => 'Rezago',
                default => 'Sin datos',
            };
        }

        $createdAt = $this->safeCarbon($row->created_at ?? null);
        $updatedAt = $this->safeCarbon($row->updated_at ?? null);

        return [
            'modulo_key' => $row->modulo_key,
            'modulo_label' => $row->modulo_label,
            'codigo' => (string) ($row->codigo ?? '-'),
            'estado' => (string) ($row->estado_nombre ?? '-'),
            'origen' => (string) ($row->origen ?? '-'),
            'destino' => (string) ($row->destino ?? '-'),
            'remitente' => (string) ($row->remitente ?? '-'),
            'destinatario' => (string) ($row->destinatario ?? '-'),
            'empresa' => (string) ($row->empresa ?? '-'),
            'usuario' => (string) ($row->usuario ?? '-'),
            'peso' => (float) ($row->peso ?? 0),
            'precio' => (float) ($row->precio ?? 0),
            'is_entregado' => $isEntregado,
            'situacion_bucket' => $bucket,
            'situacion' => $situacion,
            'created_at' => $createdAt?->format('d/m/Y H:i') ?? '-',
            'updated_at' => $updatedAt?->format('d/m/Y H:i') ?? '-',
            'created_at_ts' => $createdAt?->timestamp ?? 0,
            'estado_id' => (int) ($row->estado_id ?? 0),
        ];
    }

    private function buildSummary(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'entregados' => $rows->where('is_entregado', true)->count(),
            'no_entregados' => $rows->where('is_entregado', false)->count(),
            'correcto' => $rows->where('situacion_bucket', 'correcto')->count(),
            'retraso' => $rows->where('situacion_bucket', 'retraso')->count(),
            'rezago' => $rows->where('situacion_bucket', 'rezago')->count(),
        ];
    }

    private function buildModuleSummary(Collection $rows, array $selectedModules): array
    {
        $result = [];
        foreach ($selectedModules as $moduleKey) {
            $label = self::MODULES[$moduleKey]['label'] ?? strtoupper($moduleKey);
            $moduleRows = $rows->where('modulo_key', $moduleKey)->values();

            $result[] = [
                'key' => $moduleKey,
                'label' => $label,
                'total' => $moduleRows->count(),
                'entregados' => $moduleRows->where('is_entregado', true)->count(),
                'no_entregados' => $moduleRows->where('is_entregado', false)->count(),
                'correcto' => $moduleRows->where('situacion_bucket', 'correcto')->count(),
                'retraso' => $moduleRows->where('situacion_bucket', 'retraso')->count(),
                'rezago' => $moduleRows->where('situacion_bucket', 'rezago')->count(),
                'peso' => round((float) $moduleRows->sum('peso'), 3),
                'precio' => round((float) $moduleRows->sum('precio'), 2),
            ];
        }

        return $result;
    }

    private function buildTotals(Collection $rows): array
    {
        return [
            'peso_total' => round((float) $rows->sum('peso'), 3),
            'precio_total' => round((float) $rows->sum('precio'), 2),
        ];
    }

    private function paginateCollection(Collection $rows, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $items = $rows->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $rows->count(),
            $perPage,
            $page,
            ['path' => url()->current(), 'query' => $request->query()]
        );
    }

    private function resolveSelectedModules(string $scope, Request $request): array
    {
        if ($scope !== 'general') {
            return [$scope];
        }

        $requested = $request->query('modules', array_keys(self::MODULES));
        $requested = is_array($requested) ? $requested : [$requested];
        $valid = array_values(array_filter(array_map('strtolower', $requested), fn ($k) => isset(self::MODULES[$k])));

        return empty($valid) ? array_keys(self::MODULES) : $valid;
    }

    private function filterRowsByState(
        Collection $rows,
        array $statuses
    ): Collection {
        $normalizedStatuses = array_values(array_unique(array_filter(array_map('strtolower', $statuses))));
        if (empty($normalizedStatuses)) {
            $normalizedStatuses = ['entregado', 'pendiente', 'rezago'];
        }

        $selectedMap = array_fill_keys($normalizedStatuses, true);

        return $rows->filter(function (array $row) use ($selectedMap) {
            $isEntregado = (bool) ($row['is_entregado'] ?? false);
            $bucket = (string) ($row['situacion_bucket'] ?? '');

            if (isset($selectedMap['entregado']) && $isEntregado) {
                return true;
            }

            if (isset($selectedMap['pendiente']) && !$isEntregado) {
                return true;
            }

            if (isset($selectedMap['rezago']) && $bucket === 'rezago') {
                return true;
            }

            return false;
        })->values();
    }

    private function resolveDateRange(Request $request): array
    {
        $range = strtolower(trim((string) $request->query('range', 'all')));
        if ($range === 'all') {
            return [null, null, 'all'];
        }

        $from = $this->safeCarbon((string) $request->query('from', ''))?->startOfDay();
        $to = $this->safeCarbon((string) $request->query('to', ''))?->endOfDay();

        if ($from && !$to) {
            $to = $from->copy()->endOfDay();
        } elseif ($to && !$from) {
            $from = $to->copy()->startOfDay();
        }

        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        if ($from || $to) {
            return [$from, $to, 'custom'];
        }

        $now = now();
        if ($range === 'today') {
            return [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'today'];
        }
        if ($range === '7d') {
            return [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), '7d'];
        }
        if ($range === 'month') {
            return [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'month'];
        }

        return [null, null, 'all'];
    }

    private function resolveStatusFilters(Request $request): array
    {
        $requested = $request->query('statuses');
        if ($requested === null) {
            $legacy = strtolower(trim((string) $request->query('status', '')));
            $legacy = $legacy === 'pendientes' ? 'pendiente' : $legacy;
            if ($legacy !== '') {
                $requested = [$legacy];
            }
        }

        if ($requested === null) {
            return ['entregado', 'pendiente', 'rezago'];
        }

        $requested = is_array($requested) ? $requested : [$requested];
        $requested = array_values(array_unique(array_filter(array_map(
            static fn ($value) => strtolower(trim((string) $value)),
            $requested
        ))));

        $allowed = ['entregado', 'pendiente', 'no_entregado', 'rezago'];
        $valid = array_values(array_filter($requested, static fn ($status) => in_array($status, $allowed, true)));
        $valid = array_map(static fn ($status) => $status === 'no_entregado' ? 'pendiente' : $status, $valid);
        $valid = array_values(array_unique($valid));

        if (empty($valid)) {
            return ['entregado', 'pendiente', 'rezago'];
        }

        return $valid;
    }

    private function resolveEstadoIds(Request $request): array
    {
        $ids = $request->query('estado_ids', []);
        $ids = is_array($ids) ? $ids : [$ids];
        return array_values(array_filter(array_map('intval', $ids), fn ($v) => $v > 0));
    }

    private function resolveLimit(Request $request): ?int
    {
        $value = strtolower((string) $request->query('limit', '200'));
        if ($value === 'all') {
            return null;
        }
        $allowed = [50, 100, 200, 500, 1000];
        $intValue = (int) $value;
        return in_array($intValue, $allowed, true) ? $intValue : 200;
    }

    private function resolvePerPage(Request $request): int
    {
        $allowed = [25, 50, 100];
        $value = (int) $request->query('per_page', 50);
        return in_array($value, $allowed, true) ? $value : 50;
    }

    private function applyStatusFilter(Builder $query, string $stateColumn, string $status, ?int $estadoEntregadoId): void
    {
        if (!$estadoEntregadoId || $status === 'all') {
            return;
        }

        if ($status === 'entregado') {
            $query->where($stateColumn, $estadoEntregadoId);
            return;
        }

        $query->where(function (Builder $sub) use ($stateColumn, $estadoEntregadoId) {
            $sub->whereNull($stateColumn)->orWhere($stateColumn, '<>', $estadoEntregadoId);
        });
    }

    private function applyDateFilter(Builder $query, string $column, ?Carbon $from, ?Carbon $to): void
    {
        if ($from && $to) {
            $query->whereBetween($column, [$from, $to]);
            return;
        }
        if ($from) {
            $query->where($column, '>=', $from);
            return;
        }
        if ($to) {
            $query->where($column, '<=', $to);
        }
    }

    private function applySearchFilter(Builder $query, string $moduleKey, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $like = '%' . strtolower($search) . '%';
        $columns = $this->searchColumnsForModule($moduleKey);

        $query->where(function (Builder $sub) use ($columns, $like) {
            foreach ($columns as $column) {
                $sub->orWhereRaw("LOWER(COALESCE(CAST($column AS TEXT), '')) LIKE ?", [$like]);
            }
        });
    }

    private function searchColumnsForModule(string $moduleKey): array
    {
        return match ($moduleKey) {
            'contrato' => [
                't.codigo',
                'e.nombre_estado',
                't.origen',
                't.destino',
                't.nombre_r',
                't.nombre_d',
                'emp.nombre',
                'u.name',
            ],
            'ems' => [
                't.codigo',
                'e.nombre_estado',
                't.origen',
                't.ciudad',
                't.nombre_remitente',
                't.nombre_destinatario',
                'u.name',
            ],
            'certi' => [
                't.codigo',
                'e.nombre_estado',
                't.cuidad',
                't.destinatario',
            ],
            default => [
                't.codigo',
                'e.nombre_estado',
                't.ciudad',
                't.destinatario',
            ],
        };
    }

    private function resolveEstadoEntregadoId(): ?int
    {
        $id = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['ENTREGADO'])
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function resolveSituacionBucket(?Carbon $inicio, Carbon $fin, int $greenDays, int $yellowDays): string
    {
        if (!$inicio || $fin->lessThan($inicio)) {
            return 'sin_datos';
        }

        $hours = $inicio->diffInHours($fin);
        if ($hours <= ($greenDays * 24)) {
            return 'correcto';
        }
        if ($hours <= ($yellowDays * 24)) {
            return 'retraso';
        }

        return 'rezago';
    }

    private function resolveEmsThresholdDays(string $destino, bool $esProvincia): array
    {
        $baseDestino = $this->resolveEmsBaseDestino($destino);
        $green = in_array($baseDestino, self::DESTINOS_LARGA_DISTANCIA, true) ? 2 : 1;
        $yellow = $green + 1;
        if ($esProvincia) {
            $green++;
            $yellow++;
        }

        return ['green' => $green, 'yellow' => $yellow];
    }

    private function resolveEmsBaseDestino(string $destino): string
    {
        $normalized = $this->normalizeDestino($destino);
        if (str_contains($normalized, 'SANTA CRUZ')) {
            return 'SANTA CRUZ';
        }
        if (str_contains($normalized, 'TARIJA')) {
            return 'TARIJA';
        }
        if (str_contains($normalized, 'BENI') || str_contains($normalized, 'TRINIDAD')) {
            return 'BENI';
        }
        foreach (self::DESTINOS_BASE as $base) {
            if (str_contains($normalized, $base)) {
                return $base;
            }
        }
        return $normalized;
    }

    private function isEmsProvincia(string $destino): bool
    {
        $normalized = $this->normalizeDestino($destino);
        if ($normalized === '' || $normalized === '-') {
            return false;
        }
        if (str_contains($normalized, 'PROV')) {
            return true;
        }
        if (in_array($normalized, self::DESTINOS_BASE, true) || in_array($normalized, self::DESTINOS_CAPITALES, true)) {
            return false;
        }
        foreach (self::DESTINOS_BASE as $base) {
            if ($normalized === $base) {
                return false;
            }
            if (
                str_starts_with($normalized, $base . ' ')
                || str_starts_with($normalized, $base . '-')
                || str_starts_with($normalized, $base . ',')
                || str_starts_with($normalized, $base . '/')
            ) {
                return true;
            }
        }
        return true;
    }

    private function normalizeDestino(string $value): string
    {
        $value = strtoupper(trim($value));
        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function normalizeScope(string $scope): string
    {
        $scope = strtolower(trim($scope));
        return in_array($scope, self::SCOPES, true) ? $scope : 'general';
    }

    private function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'contrato' => 'Reporte Contratos',
            'ems' => 'Reporte EMS',
            'certi' => 'Reporte Certificados',
            'ordi' => 'Reporte Ordinarios',
            default => 'Reporte General',
        };
    }

    private function safeCarbon(?string $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
