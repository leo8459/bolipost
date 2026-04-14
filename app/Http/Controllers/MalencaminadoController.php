<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $departamentoExpr = "coalesce(nullif(trim(upper(m.departamento_origen)), ''), nullif(trim(upper(pe.origen)), ''), nullif(trim(upper(pc.origen)), ''), 'SIN ORIGEN')";

        $baseQuery = DB::table('malencaminados as m')
            ->leftJoin('paquetes_ems as pe', 'pe.id', '=', 'm.paquetes_ems_id')
            ->leftJoin('paquetes_contrato as pc', 'pc.id', '=', 'm.paquetes_contrato_id')
            ->whereDate('m.created_at', '>=', $fechaInicio)
            ->whereDate('m.created_at', '<=', $fechaFin);

        if ($departamento !== 'TODOS') {
            $baseQuery->whereRaw(
                "{$departamentoExpr} = ?",
                [$departamento]
            );
        }

        $resumen = (clone $baseQuery)
            ->selectRaw("
                {$departamentoExpr} as departamento,
                count(*) as total_registros,
                sum(m.malencaminamiento) as total_malencaminamientos
            ")
            ->groupByRaw($departamentoExpr)
            ->orderBy('departamento')
            ->get();

        $detalle = (clone $baseQuery)
            ->selectRaw("
                m.id,
                m.codigo,
                {$departamentoExpr} as departamento_origen,
                m.observacion,
                m.malencaminamiento,
                m.destino_anterior,
                m.destino_nuevo,
                m.created_at,
                case
                    when m.paquetes_ems_id is not null then 'EMS'
                    when m.paquetes_contrato_id is not null then 'CONTRATO'
                    when m.paquetes_certi_id is not null then 'CERTI'
                    when m.paquetes_ordi_id is not null then 'ORDI'
                    else '-'
                end as tipo
            ")
            ->orderByDesc('m.created_at')
            ->orderByDesc('m.id')
            ->paginate(30)
            ->withQueryString();

        return view('malencaminados.reporte', [
            'detalle' => $detalle,
            'resumen' => $resumen,
            'departamentos' => array_merge(['TODOS'], self::DEPARTAMENTOS),
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'departamento' => $departamento,
        ]);
    }

    public function reportePdf(Request $request)
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

        $departamentoExpr = "coalesce(nullif(trim(upper(m.departamento_origen)), ''), nullif(trim(upper(pe.origen)), ''), nullif(trim(upper(pc.origen)), ''), 'SIN ORIGEN')";

        $baseQuery = DB::table('malencaminados as m')
            ->leftJoin('paquetes_ems as pe', 'pe.id', '=', 'm.paquetes_ems_id')
            ->leftJoin('paquetes_contrato as pc', 'pc.id', '=', 'm.paquetes_contrato_id')
            ->whereDate('m.created_at', '>=', $fechaInicio)
            ->whereDate('m.created_at', '<=', $fechaFin);

        if ($departamento !== 'TODOS') {
            $baseQuery->whereRaw("{$departamentoExpr} = ?", [$departamento]);
        }

        $resumen = (clone $baseQuery)
            ->selectRaw("
                {$departamentoExpr} as departamento,
                count(*) as total_registros,
                sum(m.malencaminamiento) as total_malencaminamientos
            ")
            ->groupByRaw($departamentoExpr)
            ->orderBy('departamento')
            ->get();

        $detalle = (clone $baseQuery)
            ->selectRaw("
                m.id,
                m.codigo,
                {$departamentoExpr} as departamento_origen,
                m.observacion,
                m.malencaminamiento,
                m.destino_anterior,
                m.destino_nuevo,
                m.created_at,
                case
                    when m.paquetes_ems_id is not null then 'EMS'
                    when m.paquetes_contrato_id is not null then 'CONTRATO'
                    when m.paquetes_certi_id is not null then 'CERTI'
                    when m.paquetes_ordi_id is not null then 'ORDI'
                    else '-'
                end as tipo
            ")
            ->orderByDesc('m.created_at')
            ->orderByDesc('m.id')
            ->get();

        $pdf = Pdf::loadView('malencaminados.reporte-pdf', [
            'detalle' => $detalle,
            'resumen' => $resumen,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'departamento' => $departamento,
            'generadoEn' => now(),
        ])->setPaper('A4', 'landscape');

        $filename = 'reporte-malencaminados-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }
}
