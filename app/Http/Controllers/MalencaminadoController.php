<?php

namespace App\Http\Controllers;

use App\Exports\MalencaminadosReportExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class MalencaminadoController extends Controller
{
    private const DEPARTAMENTOS = [
        'LA PAZ',
        'COCHABAMBA',
        'SANTA CRUZ',
        'ORURO',
        'POTOSI',
        'TARIJA',
        'SUCRE',
        'TRINIDAD',
        'COBIJA',
    ];

    public function index()
    {
        return view('malencaminados.index');
    }

    public function reporte(Request $request)
    {
        $data = $this->buildReportData($request, true);

        return view('malencaminados.reporte', $data);
    }

    public function reporteExcel(Request $request)
    {
        $data = $this->buildReportData($request, false);
        $filename = 'reporte-malencaminados-analisis-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new MalencaminadosReportExport($data), $filename);
    }

    public function reportePdf(Request $request)
    {
        $data = $this->buildReportData($request, false);

        $pdf = Pdf::loadView('malencaminados.reporte-pdf', array_merge($data, [
            'generadoEn' => now(),
        ]))->setPaper('A4', 'landscape');

        $filename = 'reporte-malencaminados-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    private function buildReportData(Request $request, bool $paginate): array
    {
        [$fechaInicio, $fechaFin, $departamento] = $this->resolveFilters($request);

        $baseQuery = $this->buildMalencaminadosBaseQuery($fechaInicio, $fechaFin, $departamento);
        $departamentoExpr = $this->departamentoOrigenExpression();
        $tipoExpr = $this->tipoExpression();

        $errores = (clone $baseQuery)
            ->selectRaw("
                {$departamentoExpr} as departamento,
                count(*) as total_registros,
                coalesce(sum(m.malencaminamiento), 0) as total_malencaminamientos,
                sum(case when {$tipoExpr} = 'EMS' then 1 else 0 end) as ems,
                sum(case when {$tipoExpr} = 'CONTRATO' then 1 else 0 end) as contratos,
                sum(case when {$tipoExpr} = 'CERTI' then 1 else 0 end) as certificados,
                sum(case when {$tipoExpr} = 'ORDI' then 1 else 0 end) as ordinarios
            ")
            ->groupByRaw($departamentoExpr)
            ->orderBy('departamento')
            ->get()
            ->keyBy('departamento');

        $envios = $this->buildEnviosPorDepartamento($fechaInicio, $fechaFin, $departamento);
        $departamentos = $errores->keys()->merge($envios->keys())->unique()->sort()->values();

        $resumen = $departamentos->map(function (string $dep) use ($errores, $envios) {
            $error = $errores->get($dep);
            $envio = $envios->get($dep);
            $totalEnvios = (int) ($envio->total_envios ?? 0);
            $totalErrores = (int) ($error->total_registros ?? 0);

            return (object) [
                'departamento' => $dep,
                'total_envios' => $totalEnvios,
                'total_registros' => $totalErrores,
                'total_malencaminamientos' => (int) ($error->total_malencaminamientos ?? 0),
                'porcentaje_error' => $totalEnvios > 0 ? round(($totalErrores * 100) / $totalEnvios, 2) : 0.0,
                'ems' => (int) ($error->ems ?? 0),
                'contratos' => (int) ($error->contratos ?? 0),
                'certificados' => (int) ($error->certificados ?? 0),
                'ordinarios' => (int) ($error->ordinarios ?? 0),
                'envios_ems' => (int) ($envio->ems ?? 0),
                'envios_contratos' => (int) ($envio->contratos ?? 0),
                'envios_certificados' => (int) ($envio->certificados ?? 0),
                'envios_ordinarios' => (int) ($envio->ordinarios ?? 0),
            ];
        })->sortByDesc('porcentaje_error')->values();

        $detalleQuery = (clone $baseQuery)
            ->selectRaw("
                m.id,
                m.codigo,
                {$departamentoExpr} as departamento_origen,
                m.observacion,
                m.malencaminamiento,
                m.destino_anterior,
                m.destino_nuevo,
                m.created_at,
                {$tipoExpr} as tipo,
                coalesce(upe.name, uee.name, upc.name, uconev.name, uci.name, ueo.name, '-') as usuario_creador_guia,
                coalesce(upe.ciudad, uee.ciudad, upc.ciudad, uconev.ciudad, uci.ciudad, ueo.ciudad, '-') as departamento_usuario_creador,
                {$this->usuarioReportoExpression()} as usuario_reporto_malencaminado
            ")
            ->orderByDesc('m.created_at')
            ->orderByDesc('m.id');

        $detalle = $paginate
            ? $detalleQuery->paginate(30)->withQueryString()
            : $detalleQuery->get();

        $totalEnvios = (int) $resumen->sum('total_envios');
        $totalMalencaminados = (int) $resumen->sum('total_registros');

        return [
            'detalle' => $detalle,
            'resumen' => $resumen,
            'departamentos' => array_merge(['TODOS'], self::DEPARTAMENTOS),
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'departamento' => $departamento,
            'totalEnvios' => $totalEnvios,
            'totalMalencaminados' => $totalMalencaminados,
            'porcentajeErrorGeneral' => $totalEnvios > 0 ? round(($totalMalencaminados * 100) / $totalEnvios, 2) : 0.0,
        ];
    }

    private function resolveFilters(Request $request): array
    {
        $hoy = now()->toDateString();
        $fechaInicio = (string) $request->query('fecha_inicio', now()->subDays(30)->toDateString());
        $fechaFin = (string) $request->query('fecha_fin', $hoy);
        $departamento = strtoupper(trim((string) $request->query('departamento', 'TODOS')));

        $request->merge([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'departamento' => $departamento,
        ]);

        $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'departamento' => ['required', 'string'],
        ]);

        return [$fechaInicio, $fechaFin, $departamento];
    }

    private function buildMalencaminadosBaseQuery(string $fechaInicio, string $fechaFin, string $departamento): Builder
    {
        $firstEms = $this->firstEventSubquery('eventos_ems');
        $firstContrato = $this->firstEventSubquery('eventos_contrato');
        $firstCerti = $this->firstEventSubquery('eventos_certi');
        $firstOrdi = $this->firstEventSubquery('eventos_ordi');

        $query = DB::table('malencaminados as m')
            ->leftJoin('paquetes_ems as pe', 'pe.id', '=', 'm.paquetes_ems_id')
            ->leftJoin('paquetes_contrato as pc', 'pc.id', '=', 'm.paquetes_contrato_id')
            ->leftJoin('paquetes_certi as pce', 'pce.id', '=', 'm.paquetes_certi_id')
            ->leftJoin('paquetes_ordi as po', 'po.id', '=', 'm.paquetes_ordi_id')
            ->leftJoin('users as upe', 'upe.id', '=', 'pe.user_id')
            ->leftJoin('users as upc', 'upc.id', '=', 'pc.user_id')
            ->leftJoinSub($firstEms, 'fee', fn ($join) => $join->on('fee.codigo', '=', 'm.codigo'))
            ->leftJoinSub($firstContrato, 'fec', fn ($join) => $join->on('fec.codigo', '=', 'm.codigo'))
            ->leftJoinSub($firstCerti, 'feci', fn ($join) => $join->on('feci.codigo', '=', 'm.codigo'))
            ->leftJoinSub($firstOrdi, 'feo', fn ($join) => $join->on('feo.codigo', '=', 'm.codigo'))
            ->leftJoin('eventos_ems as ee', 'ee.id', '=', 'fee.evento_id')
            ->leftJoin('eventos_contrato as ec', 'ec.id', '=', 'fec.evento_id')
            ->leftJoin('eventos_certi as eci', 'eci.id', '=', 'feci.evento_id')
            ->leftJoin('eventos_ordi as eo', 'eo.id', '=', 'feo.evento_id')
            ->leftJoin('users as uee', 'uee.id', '=', 'ee.user_id')
            ->leftJoin('users as uconev', 'uconev.id', '=', 'ec.user_id')
            ->leftJoin('users as uci', 'uci.id', '=', 'eci.user_id')
            ->leftJoin('users as ueo', 'ueo.id', '=', 'eo.user_id')
            ->whereDate('m.created_at', '>=', $fechaInicio)
            ->whereDate('m.created_at', '<=', $fechaFin);

        if (Schema::hasColumn('malencaminados', 'user_id')) {
            $query->leftJoin('users as ur', 'ur.id', '=', 'm.user_id');
        }

        if ($departamento !== 'TODOS') {
            $query->whereRaw($this->departamentoOrigenExpression() . ' = ?', [$departamento]);
        }

        return $query;
    }

    private function buildEnviosPorDepartamento(string $fechaInicio, string $fechaFin, string $departamento): Collection
    {
        $firstCerti = $this->firstEventSubquery('eventos_certi');
        $firstOrdi = $this->firstEventSubquery('eventos_ordi');

        $ems = DB::table('paquetes_ems as p')
            ->selectRaw("coalesce(nullif(trim(upper(p.origen)), ''), 'SIN ORIGEN') as departamento")
            ->selectRaw("'EMS' as tipo")
            ->whereDate('p.created_at', '>=', $fechaInicio)
            ->whereDate('p.created_at', '<=', $fechaFin);

        $contratos = DB::table('paquetes_contrato as p')
            ->selectRaw("coalesce(nullif(trim(upper(p.origen)), ''), 'SIN ORIGEN') as departamento")
            ->selectRaw("'CONTRATO' as tipo")
            ->whereDate('p.created_at', '>=', $fechaInicio)
            ->whereDate('p.created_at', '<=', $fechaFin);

        $certi = DB::table('paquetes_certi as p')
            ->leftJoinSub($firstCerti, 'fe', fn ($join) => $join->on('fe.codigo', '=', 'p.codigo'))
            ->leftJoin('eventos_certi as ev', 'ev.id', '=', 'fe.evento_id')
            ->leftJoin('users as u', 'u.id', '=', 'ev.user_id')
            ->selectRaw("coalesce(nullif(trim(upper(u.ciudad)), ''), 'SIN ORIGEN') as departamento")
            ->selectRaw("'CERTI' as tipo")
            ->whereDate('p.created_at', '>=', $fechaInicio)
            ->whereDate('p.created_at', '<=', $fechaFin);

        $ordi = DB::table('paquetes_ordi as p')
            ->leftJoinSub($firstOrdi, 'fe', fn ($join) => $join->on('fe.codigo', '=', 'p.codigo'))
            ->leftJoin('eventos_ordi as ev', 'ev.id', '=', 'fe.evento_id')
            ->leftJoin('users as u', 'u.id', '=', 'ev.user_id')
            ->selectRaw("coalesce(nullif(trim(upper(u.ciudad)), ''), 'SIN ORIGEN') as departamento")
            ->selectRaw("'ORDI' as tipo")
            ->whereDate('p.created_at', '>=', $fechaInicio)
            ->whereDate('p.created_at', '<=', $fechaFin);

        $union = $ems->unionAll($contratos)->unionAll($certi)->unionAll($ordi);

        $query = DB::query()
            ->fromSub($union, 'envios')
            ->selectRaw("
                departamento,
                count(*) as total_envios,
                sum(case when tipo = 'EMS' then 1 else 0 end) as ems,
                sum(case when tipo = 'CONTRATO' then 1 else 0 end) as contratos,
                sum(case when tipo = 'CERTI' then 1 else 0 end) as certificados,
                sum(case when tipo = 'ORDI' then 1 else 0 end) as ordinarios
            ")
            ->groupBy('departamento');

        if ($departamento !== 'TODOS') {
            $query->where('departamento', $departamento);
        }

        return $query->get()->keyBy('departamento');
    }

    private function firstEventSubquery(string $table): Builder
    {
        return DB::table($table)
            ->select('codigo', DB::raw('min(id) as evento_id'))
            ->groupBy('codigo');
    }

    private function departamentoOrigenExpression(): string
    {
        return "coalesce(
            nullif(trim(upper(m.departamento_origen)), ''),
            nullif(trim(upper(pe.origen)), ''),
            nullif(trim(upper(pc.origen)), ''),
            nullif(trim(upper(uee.ciudad)), ''),
            nullif(trim(upper(uconev.ciudad)), ''),
            nullif(trim(upper(uci.ciudad)), ''),
            nullif(trim(upper(ueo.ciudad)), ''),
            'SIN ORIGEN'
        )";
    }

    private function tipoExpression(): string
    {
        return "case
            when m.paquetes_ems_id is not null then 'EMS'
            when m.paquetes_contrato_id is not null then 'CONTRATO'
            when m.paquetes_certi_id is not null then 'CERTI'
            when m.paquetes_ordi_id is not null then 'ORDI'
            else '-'
        end";
    }

    private function usuarioReportoExpression(): string
    {
        return Schema::hasColumn('malencaminados', 'user_id')
            ? "coalesce(ur.name, 'SIN DATO')"
            : "'SIN DATO'";
    }
}
