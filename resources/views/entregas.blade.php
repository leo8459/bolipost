@extends('adminlte::page')

@section('title', 'Resumen ejecutivo de entregas')

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <h1 class="m-0">Resumen ejecutivo de entregas</h1>
            <div class="text-muted">Resultados consolidados por departamento y rendimiento de carteros.</div>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Volver al dashboard</a>
    </div>
@stop

@section('content')
    <style>
        .entregas-card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
            overflow: hidden;
        }

        .entregas-page {
            padding-bottom: 90px;
        }

        .entregas-filter {
            border: 1px solid #dbe3ef;
            border-radius: 12px;
            background: #f8fafc;
        }

        .entregas-filter label {
            color: #1f3d6d;
            font-weight: 800;
        }

        .entregas-summary {
            display: grid;
            grid-template-columns: repeat(7, minmax(110px, 1fr));
            gap: 10px;
        }

        .entregas-kpi {
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            padding: 9px 12px;
            background: #fff;
            border-top: 3px solid #1f5fae;
        }

        .entregas-kpi span {
            display: block;
            color: #64748b;
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .entregas-kpi strong {
            color: #0f2851;
            font-size: 1.25rem;
        }

        .entregas-kpi small {
            display: block;
            color: #64748b;
            font-size: .68rem;
        }

        .excel-wrap {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }

        .excel-table {
            width: 100%;
            min-width: 1040px;
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
        }

        .excel-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            border-right: 1px solid #b9c7d8;
            border-bottom: 2px solid #8fa4bf;
            background: #e8f0fb;
            color: #123b70;
            font-size: .68rem;
            font-weight: 900;
            text-transform: uppercase;
            line-height: 1.05;
            padding: .48rem .28rem;
            text-align: center;
            white-space: normal;
        }

        .excel-table tbody td {
            border-right: 1px solid #d7e0ec;
            border-bottom: 1px solid #d7e0ec;
            background: #fff;
            vertical-align: middle;
            padding: .4rem .34rem;
            font-size: .84rem;
        }

        .excel-table tbody tr:nth-child(even) td {
            background: #fbfdff;
        }

        .excel-table tbody tr:hover td {
            background: #fff8db;
        }

        .excel-table th:first-child,
        .excel-table td:first-child {
            border-left: 0;
        }

        .excel-rank {
            display: inline-flex;
            width: 24px;
            height: 24px;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: #edf2f7;
            color: #1e3a5f;
            font-weight: 900;
        }

        .excel-user {
            color: #0f172a;
            font-weight: 900;
            font-size: .86rem;
            line-height: 1.18;
            word-break: break-word;
        }

        .metric-cell {
            color: #0f2851;
            font-variant-numeric: tabular-nums;
            font-weight: 900;
            text-align: right;
            white-space: nowrap;
        }

        .metric-cell.total {
            background: #eef6ff !important;
            color: #0b4c8c;
            font-size: .9rem;
        }

        .fulfillment {
            min-width: 0;
        }

        .fulfillment-value {
            display: block;
            color: #0f2851;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            text-align: right;
        }

        .fulfillment-track {
            height: 5px;
            margin-top: 4px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .fulfillment-bar {
            display: block;
            height: 100%;
            border-radius: 999px;
            background: #1f5fae;
        }

        .department-card {
            margin-bottom: 10px;
            border: 1px solid #dbe3ef;
            border-radius: 10px;
            background: #fff;
            overflow: hidden;
        }

        .department-summary {
            display: grid;
            grid-template-columns: minmax(150px, 1.5fr) repeat(5, minmax(82px, 1fr)) auto;
            gap: 12px;
            align-items: center;
            padding: 13px 15px;
            cursor: pointer;
            list-style: none;
        }

        .department-summary::-webkit-details-marker {
            display: none;
        }

        .department-summary::marker {
            content: '';
        }

        .department-card[open] .department-summary {
            border-bottom: 1px solid #dbe3ef;
            background: #f8fafc;
        }

        .department-name {
            color: #123b70;
            font-weight: 900;
            font-size: 1rem;
        }

        .department-stat small,
        .department-stat strong {
            display: block;
        }

        .department-stat small {
            color: #64748b;
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .department-stat strong {
            color: #0f2851;
            font-variant-numeric: tabular-nums;
        }

        .department-toggle {
            color: #1f5fae;
            font-size: .78rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .department-detail {
            padding: 12px;
        }

        .department-detail .excel-table {
            min-width: 1040px;
        }

        @media (max-width: 991.98px) {
            .entregas-summary {
                grid-template-columns: repeat(2, minmax(140px, 1fr));
            }

            .department-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .department-title-block,
            .department-toggle {
                grid-column: 1 / -1;
            }

            .excel-wrap {
                overflow-x: auto;
            }

            .excel-table {
                min-width: 1040px;
            }
        }

        #entregasDownloadModal .modal-dialog {
            transition: transform .35s cubic-bezier(.2, .8, .2, 1);
        }

        #entregasDownloadModal.fade .modal-dialog {
            transform: translateY(-14px) scale(.96);
        }

        #entregasDownloadModal.show .modal-dialog {
            transform: translateY(0) scale(1);
        }

        .entregas-download-panel {
            position: relative;
            overflow: hidden;
            padding: 27px 30px 24px;
            border-radius: 18px;
            background: radial-gradient(circle at 90% 6%, rgba(56, 189, 248, .32), transparent 34%), linear-gradient(145deg, #102c59, #164c86 62%, #167ba5);
            color: #fff;
            text-align: center;
        }

        .entregas-download-panel::before,
        .entregas-download-panel::after {
            position: absolute;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 50%;
            content: '';
            pointer-events: none;
        }

        .entregas-download-panel::before {
            top: -84px;
            right: -54px;
            width: 190px;
            height: 190px;
        }

        .entregas-download-panel::after {
            bottom: -116px;
            left: -74px;
            width: 220px;
            height: 220px;
        }

        .download-brand,
        .download-animation,
        .download-format,
        .download-title,
        .download-message,
        .download-progress,
        .download-progress-note {
            position: relative;
            z-index: 1;
        }

        .download-brand {
            color: #dbeafe;
            font-size: .72rem;
            font-weight: 900;
            letter-spacing: .16em;
        }

        .download-brand i {
            margin-right: 5px;
            color: #fbbf24;
        }

        .download-brand span {
            margin-left: 4px;
            color: #7dd3fc;
            font-weight: 700;
        }

        .download-animation {
            display: grid;
            width: 118px;
            height: 118px;
            margin: 15px auto 12px;
            place-items: center;
        }

        .download-orbit {
            position: absolute;
            inset: 3px;
            border: 1px dashed rgba(186, 230, 253, .55);
            border-radius: 50%;
            animation: entregas-orbit 9s linear infinite;
        }

        .download-orbit-inner {
            inset: 13px;
            border-style: solid;
            border-color: rgba(255, 255, 255, .14);
            animation-direction: reverse;
            animation-duration: 13s;
        }

        .download-icon-shell {
            display: grid;
            width: 72px;
            height: 72px;
            border: 1px solid rgba(255, 255, 255, .36);
            border-radius: 23px;
            background: linear-gradient(145deg, #fff, #dbeafe);
            box-shadow: 0 12px 30px rgba(2, 15, 40, .3), 0 0 0 9px rgba(255, 255, 255, .08);
            color: #e99b13;
            font-size: 2rem;
            place-items: center;
            animation: entregas-float 2.4s ease-in-out infinite;
        }

        .download-spark {
            position: absolute;
            color: #fde68a;
            font-size: .72rem;
            animation: entregas-twinkle 1.8s ease-in-out infinite;
        }

        .download-spark-one { top: 18px; left: 14px; }
        .download-spark-two { right: 17px; bottom: 17px; animation-delay: .7s; }

        .download-format {
            display: inline-block;
            margin-bottom: 9px;
            padding: 5px 11px;
            border: 1px solid rgba(255, 255, 255, .24);
            border-radius: 999px;
            background: rgba(255, 255, 255, .1);
            color: #e0f2fe;
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .download-title {
            margin: 0 0 7px;
            color: #fff;
            font-size: 1.2rem;
            font-weight: 900;
        }

        .download-message {
            max-width: 350px;
            margin: 0 auto;
            color: #dbeafe;
            font-size: .9rem;
            line-height: 1.5;
        }

        .download-progress {
            height: 7px;
            margin: 20px auto 10px;
            border-radius: 99px;
            background: rgba(255, 255, 255, .2);
            overflow: hidden;
        }

        .download-progress span {
            display: block;
            width: 38%;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #fbbf24, #fde68a, #fff);
            box-shadow: 0 0 14px rgba(253, 230, 138, .75);
            animation: entregas-progress 1.35s ease-in-out infinite;
        }

        .download-progress-note {
            color: rgba(219, 234, 254, .8);
            font-size: .7rem;
        }

        .download-progress-note i { margin-right: 4px; }

        .entregas-download-panel.is-success .download-icon-shell {
            color: #13814b;
            animation: entregas-success .45s ease-out both;
        }

        .entregas-download-panel.is-success .download-progress span {
            width: 100%;
            animation: none;
        }

        .entregas-download-panel.is-error .download-icon-shell {
            color: #d97706;
            animation: none;
        }

        .entregas-download-panel.is-error .download-progress span {
            width: 100%;
            background: linear-gradient(90deg, #fb7185, #fbbf24);
            animation: none;
        }

        @keyframes entregas-orbit {
            to { transform: rotate(360deg); }
        }

        @keyframes entregas-float {
            0%, 100% { transform: translateY(0) rotate(-3deg); }
            50% { transform: translateY(-7px) rotate(3deg); }
        }

        @keyframes entregas-twinkle {
            0%, 100% { opacity: .4; transform: scale(.8); }
            50% { opacity: 1; transform: scale(1.25); }
        }

        @keyframes entregas-progress {
            0% { transform: translateX(-115%); }
            100% { transform: translateX(275%); }
        }

        @keyframes entregas-success {
            0% { transform: scale(.65) rotate(-18deg); }
            75% { transform: scale(1.12) rotate(5deg); }
            100% { transform: scale(1) rotate(0); }
        }

        @media (prefers-reduced-motion: reduce) {
            #entregasDownloadModal .modal-dialog,
            .download-orbit,
            .download-icon-shell,
            .download-spark,
            .download-progress span {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }
    </style>

    @php
        $totalAsignados = (int) $entregadores->sum('total_asignados');
        $totalGeneral = (int) $entregadores->sum('total_entregados');
        $totalCarteroEntregados = (int) $entregadores->sum('total_cartero_entregados');
        $totalVentanilla = (int) $entregadores->sum('total_ventanilla');
        $totalPendientesAsignados = (int) $entregadores->sum('pendientes_asignados');
        $cumplimientoGeneral = \App\Support\DeliveryFulfillment::percentage(
            $totalAsignados,
            $totalCarteroEntregados,
            $totalVentanilla
        );
        $totalCarteros = (int) $entregadores->count();
    @endphp

    <div class="entregas-page">
    <div class="card entregas-card mb-3">
        <div class="card-body entregas-filter">
            <form method="GET" action="{{ route('entregas.index') }}" class="row">
                <div class="col-md-3 mb-3">
                    <label class="mb-1">Rango</label>
                    <select class="form-control" name="range">
                        @php
                            $rangeValue = old('range', request('range', $rangoKey ?? 'all'));
                        @endphp
                        <option value="all" @selected($rangeValue === 'all')>Todo el historial</option>
                        <option value="today" @selected($rangeValue === 'today')>Hoy</option>
                        <option value="7d" @selected($rangeValue === '7d')>Ultimos 7 dias</option>
                        <option value="month" @selected($rangeValue === 'month')>Mes actual</option>
                        <option value="custom" @selected($rangeValue === 'custom')>Personalizado</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="mb-1">Desde</label>
                    <input type="date" class="form-control" name="from" value="{{ request('from', $rangoDesde ?? '') }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="mb-1">Hasta</label>
                    <input type="date" class="form-control" name="to" value="{{ request('to', $rangoHasta ?? '') }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="mb-1">Departamento del cartero</label>
                    <select class="form-control" name="cartero_departamento">
                        <option value="">Todos</option>
                        @foreach($departamentosDisponibles as $dep)
                            <option value="{{ $dep }}" @selected(($departamentoCartero ?? '') === $dep)>{{ $dep }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 mb-3">
                    <label class="mb-2 d-block">Modulos</label>
                    <div class="d-flex flex-wrap" style="gap: .65rem 1rem;">
                        @foreach($modulosDisponibles as $modKey => $modConfig)
                            <label class="mb-0" style="font-weight:600;">
                                <input type="checkbox" name="modules[]" value="{{ $modKey }}" @checked(in_array($modKey, $modulosSeleccionados, true))>
                                {{ $modConfig['label'] }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="col-12 d-flex flex-wrap align-items-center" style="gap: 8px;">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="{{ route('entregas.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                    <a href="{{ route('entregas.export.excel', request()->query()) }}" class="btn btn-success" data-entregas-download="excel">
                        <i class="fas fa-file-excel mr-1"></i> Exportar Excel por departamento
                    </a>
                    <a href="{{ route('entregas.export.pdf', request()->query()) }}" class="btn btn-danger" data-entregas-download="pdf">
                        <i class="fas fa-file-pdf mr-1"></i> Descargar PDF ejecutivo
                    </a>
                    <span class="text-muted ml-md-2">
                        {{ $rangoLabel ?? 'Todo el historial' }}
                        @if(($departamentoCartero ?? '') !== '')
                            | Carteros de {{ $departamentoCartero }}
                        @endif
                    </span>
                </div>
            </form>
        </div>
    </div>

    <div class="entregas-summary mb-3">
        <div class="entregas-kpi"><span>Carteros</span><strong>{{ \App\Support\BolivianNumber::format($totalCarteros) }}</strong></div>
        <div class="entregas-kpi"><span>Entrega física</span><strong>{{ \App\Support\BolivianNumber::format($totalCarteroEntregados) }}</strong></div>
        <div class="entregas-kpi"><span>Ventanilla</span><strong>{{ \App\Support\BolivianNumber::format($totalVentanilla) }}</strong></div>
        <div class="entregas-kpi"><span>Total entregados</span><strong>{{ \App\Support\BolivianNumber::format($totalGeneral) }}</strong></div>
        <div class="entregas-kpi" title="Total entregado dividido entre los días del periodo de lunes a sábado"><span>Promedio diario</span><strong>{{ \App\Support\BolivianNumber::format($promedioDiarioGeneral ?? 0, 2) }}</strong><small>Lun-Sáb · {{ \App\Support\BolivianNumber::format($diasLaborables ?? 0) }} días</small></div>
        <div class="entregas-kpi"><span>Pendientes asignados</span><strong>{{ \App\Support\BolivianNumber::format($totalPendientesAsignados) }}</strong></div>
        <div class="entregas-kpi"><span>Cumplimiento</span><strong>{{ \App\Support\BolivianNumber::format($cumplimientoGeneral, 1) }}%</strong></div>
    </div>

    <div class="card entregas-card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <strong>Resumen por departamento</strong>
                <span class="text-muted ml-2">{{ $rangoLabel ?? 'Todo el historial' }}</span>
            </div>
            <span class="text-muted small">Abre un departamento para ver sus carteros.</span>
        </div>
        <div class="card-body p-3">
            @forelse($resumenDepartamentos as $departamento)
                @php
                    $nombreDepartamento = mb_convert_case(mb_strtolower($departamento['departamento'], 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
                @endphp
                <details class="department-card">
                    <summary class="department-summary">
                        <div class="department-title-block">
                            <div class="department-name">{{ $nombreDepartamento }}</div>
                            <small class="text-muted">{{ \App\Support\BolivianNumber::format($departamento['cantidad_carteros']) }} carteros</small>
                        </div>
                        <div class="department-stat"><small>Entrega física</small><strong>{{ \App\Support\BolivianNumber::format($departamento['total_cartero_entregados']) }}</strong></div>
                        <div class="department-stat"><small>Ventanilla</small><strong>{{ \App\Support\BolivianNumber::format($departamento['total_ventanilla']) }}</strong></div>
                        <div class="department-stat"><small>Total entregados</small><strong>{{ \App\Support\BolivianNumber::format($departamento['total_entregados']) }}</strong></div>
                        <div class="department-stat"><small>Prom. diario</small><strong>{{ \App\Support\BolivianNumber::format($departamento['promedio_diario'], 2) }}</strong></div>
                        <div class="department-stat"><small>Cumplimiento</small><strong>{{ \App\Support\BolivianNumber::format($departamento['cumplimiento'], 1) }}%</strong></div>
                        <span class="department-toggle">Ver carteros <i class="fas fa-chevron-down ml-1"></i></span>
                    </summary>
                    <div class="department-detail">
                        <div class="excel-wrap">
                            <table class="table table-sm excel-table mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Cartero</th>
                                        <th>Asignados</th>
                                        <th>Entrega física</th>
                                        <th>Ventanilla</th>
                                        <th>Total entregados</th>
                                        <th>Promedio diario</th>
                                        <th>Pendientes</th>
                                        <th>Cumplimiento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($departamento['carteros'] as $item)
                                        @php
                                            $cumplimiento = min(100.0, max(0.0, (float) $item->cumplimiento_asignados));
                                        @endphp
                                        <tr>
                                            <td><span class="excel-rank">{{ $loop->iteration }}</span></td>
                                            <td><div class="excel-user">{{ $item->name }}</div></td>
                                            <td class="metric-cell">{{ \App\Support\BolivianNumber::format((int) $item->total_asignados) }}</td>
                                            <td class="metric-cell">{{ \App\Support\BolivianNumber::format((int) $item->total_cartero_entregados) }}</td>
                                            <td class="metric-cell">{{ \App\Support\BolivianNumber::format((int) $item->total_ventanilla) }}</td>
                                            <td class="metric-cell total">{{ \App\Support\BolivianNumber::format((int) $item->total_entregados) }}</td>
                                            <td class="metric-cell">{{ \App\Support\BolivianNumber::format((float) $item->promedio_diario, 2) }}</td>
                                            <td class="metric-cell">{{ \App\Support\BolivianNumber::format((int) $item->pendientes_asignados) }}</td>
                                            <td>
                                                <div class="fulfillment">
                                                    <span class="fulfillment-value">{{ \App\Support\BolivianNumber::format($cumplimiento, 1) }}%</span>
                                                    <span class="fulfillment-track"><span class="fulfillment-bar" style="width: {{ $cumplimiento }}%;"></span></span>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            @empty
                <div class="text-center text-muted py-4">No hay asignaciones ni entregas para el filtro seleccionado.</div>
            @endforelse
            <div class="text-muted small mt-2">El promedio diario usa {{ \App\Support\BolivianNumber::format($diasLaborables ?? 0) }} días de lunes a sábado; no cuenta domingos.</div>
        </div>
    </div>

    <div class="modal fade" id="entregasDownloadModal" tabindex="-1" role="dialog" aria-labelledby="entregasDownloadTitle" aria-describedby="entregasDownloadMessage" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow bg-transparent">
                <div class="entregas-download-panel" id="entregasDownloadPanel">
                    <div class="download-brand"><i class="fas fa-paper-plane" aria-hidden="true"></i> BOLIPOST <span>REPORTES</span></div>
                    <div class="download-animation" aria-hidden="true">
                        <span class="download-orbit"></span>
                        <span class="download-orbit download-orbit-inner"></span>
                        <span class="download-spark download-spark-one"><i class="fas fa-star"></i></span>
                        <span class="download-spark download-spark-two"><i class="fas fa-star"></i></span>
                        <div class="download-icon-shell" id="entregasDownloadIcon"><i class="fas fa-box-open"></i></div>
                    </div>
                    <div class="download-format" id="entregasDownloadFormat">Reporte ejecutivo</div>
                    <h5 class="download-title" id="entregasDownloadTitle">Preparando reporte</h5>
                    <p class="download-message" id="entregasDownloadMessage">Estamos generando el archivo. La descarga comenzará automáticamente.</p>
                    <div class="download-progress" role="presentation"><span></span></div>
                    <div class="download-progress-note"><i class="fas fa-lock" aria-hidden="true"></i> Tus filtros se mantienen en este reporte</div>
                </div>
            </div>
        </div>
    </div>
    </div>
@stop

@section('js')
    <script>
        (function () {
            var links = document.querySelectorAll('[data-entregas-download]');
            var modal = document.getElementById('entregasDownloadModal');
            var panel = document.getElementById('entregasDownloadPanel');
            var icon = document.getElementById('entregasDownloadIcon');
            var formatLabel = document.getElementById('entregasDownloadFormat');
            var title = document.getElementById('entregasDownloadTitle');
            var message = document.getElementById('entregasDownloadMessage');
            var activeDownload = false;

            function showModal(format) {
                panel.classList.remove('is-success', 'is-error');
                icon.innerHTML = '<i class="fas fa-box-open"></i>';
                formatLabel.textContent = format === 'pdf' ? 'PDF ejecutivo · por departamento' : 'Excel · por departamento';
                title.textContent = format === 'pdf' ? 'Preparando PDF ejecutivo' : 'Preparando reporte Excel';
                message.textContent = 'Estamos generando el archivo. La descarga comenzará automáticamente.';
                window.jQuery(modal).modal('show');
            }

            function getFilename(response, fallback) {
                var disposition = response.headers.get('Content-Disposition') || '';
                var utf8Name = disposition.match(/filename\*=UTF-8''([^;]+)/i);
                var regularName = disposition.match(/filename="?([^";]+)"?/i);

                if (utf8Name) {
                    try { return decodeURIComponent(utf8Name[1]); } catch (error) { return utf8Name[1]; }
                }

                return regularName ? regularName[1] : fallback;
            }

            links.forEach(function (link) {
                link.addEventListener('click', async function (event) {
                    event.preventDefault();
                    if (activeDownload) return;

                    activeDownload = true;
                    links.forEach(function (item) { item.setAttribute('aria-disabled', 'true'); });
                    showModal(link.dataset.entregasDownload);

                    try {
                        var response = await fetch(link.href, {
                            credentials: 'same-origin',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });

                        var contentType = (response.headers.get('Content-Type') || '').toLowerCase();
                        if (!response.ok || response.redirected || contentType.indexOf('text/html') === 0) {
                            throw new Error('No se pudo generar el reporte. Verifica tu sesión e inténtalo de nuevo.');
                        }

                        var blob = await response.blob();
                        if (!blob.size) throw new Error('El reporte se generó vacío.');

                        var fallbackName = link.dataset.entregasDownload === 'pdf' ? 'entregas.pdf' : 'entregas.xlsx';
                        var downloadUrl = URL.createObjectURL(blob);
                        var downloadLink = document.createElement('a');
                        downloadLink.href = downloadUrl;
                        downloadLink.download = getFilename(response, fallbackName);
                        document.body.appendChild(downloadLink);
                        downloadLink.click();
                        downloadLink.remove();

                        panel.classList.add('is-success');
                        icon.innerHTML = '<i class="fas fa-check-circle"></i>';
                        title.textContent = 'Reporte listo';
                        message.textContent = 'La descarga del archivo ya comenzó.';
                        window.setTimeout(function () { window.jQuery(modal).modal('hide'); }, 700);
                        window.setTimeout(function () { URL.revokeObjectURL(downloadUrl); }, 60000);
                    } catch (error) {
                        panel.classList.add('is-error');
                        icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                        title.textContent = 'No se pudo generar el reporte';
                        message.textContent = error.message || 'Ocurrió un error al preparar la descarga.';
                        window.setTimeout(function () { window.jQuery(modal).modal('hide'); }, 2200);
                    } finally {
                        window.setTimeout(function () {
                            activeDownload = false;
                            links.forEach(function (item) { item.removeAttribute('aria-disabled'); });
                        }, 700);
                    }
                });
            });
        })();
    </script>
@stop
