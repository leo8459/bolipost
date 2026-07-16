@extends('adminlte::page')
@section('title', 'Bitacoras')
@section('template_title')
    Bitacoras
@endsection

@section('css')
    <style>
        :root {
            --bitacora-primary: #20539A;
            --bitacora-secondary: #FECC36;
            --bitacora-bg: #f3f6fc;
            --bitacora-border: #e4e8f2;
            --bitacora-text: #1f2937;
        }

        .bitacoras-wrap {
            background: linear-gradient(180deg, #f8faff 0%, var(--bitacora-bg) 100%);
            padding: 14px 0 0;
        }

        .card-bitacoras {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 12px 28px rgba(21, 36, 75, 0.1);
            overflow: hidden;
        }

        .card-bitacoras .card-header {
            background: linear-gradient(95deg, var(--bitacora-primary) 0%, #43538f 100%);
            color: #fff;
            border-bottom: 0;
            padding: 1rem 1.2rem;
        }

        .card-bitacoras .card-title {
            float: none;
            display: block;
            font-weight: 800;
            font-size: 1.35rem;
            margin: 0;
        }

        .bitacoras-subtitle {
            margin-top: 4px;
            color: rgba(255, 255, 255, 0.76);
            font-size: 0.92rem;
            line-height: 1.45;
            max-width: 640px;
        }

        .bitacoras-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn-dorado {
            background: var(--bitacora-secondary);
            border-color: var(--bitacora-secondary);
            color: #fff;
            min-height: 42px;
            border-radius: 12px;
            font-weight: 800;
            padding-inline: 1rem;
            border: none;
        }

        .btn-dorado:hover {
            filter: brightness(.95);
            color: #fff;
        }

        .card-bitacoras .card-body {
            padding: 1.25rem;
            color: var(--bitacora-text);
        }

        .bitacoras-panel {
            border: 1px solid var(--bitacora-border);
            border-radius: 14px;
            background: #fff;
            overflow: hidden;
        }

        .bitacoras-filters {
            padding: 18px;
            border-bottom: 1px solid var(--bitacora-border);
            background: linear-gradient(180deg, #fbfcff 0%, #f7faff 100%);
        }

        .bitacoras-filters-title {
            color: var(--bitacora-primary);
            font-size: 1rem;
            font-weight: 800;
            margin-bottom: 0.2rem;
        }

        .bitacoras-filters-subtitle {
            color: #5e6b86;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .bitacoras-filters label {
            color: #0f172a;
            font-weight: 800;
            margin-bottom: 0.45rem;
        }

        .bitacoras-filters .form-control {
            min-height: 44px;
            border-radius: 10px;
            border-color: #cbd5e1;
            box-shadow: none;
        }

        .bitacoras-filters .form-control:focus {
            border-color: var(--bitacora-primary);
            box-shadow: 0 0 0 0.15rem rgba(32, 83, 154, 0.12);
        }

        .bitacoras-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 0.25rem;
        }

        .bitacoras-report {
            padding: 18px;
            border-bottom: 1px solid var(--bitacora-border);
            background:
                radial-gradient(circle at top right, rgba(254, 204, 54, 0.18), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #f7faff 100%);
        }

        .bitacoras-report-title {
            color: var(--bitacora-primary);
            font-size: 1rem;
            font-weight: 800;
            margin-bottom: 0.2rem;
        }

        .bitacoras-report-subtitle {
            color: #5e6b86;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .bitacoras-report-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            flex-wrap: wrap;
        }

        .bitacoras-report-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-bitacora-export {
            min-height: 42px;
            border-radius: 12px;
            font-weight: 800;
            padding-inline: 1rem;
            border: 1px solid rgba(32, 83, 154, 0.18);
            background: #fff;
            color: var(--bitacora-primary);
        }

        .btn-bitacora-export:hover {
            color: #173d72;
            background: #eff6ff;
        }

        .bitacoras-metric-card {
            border: 1px solid rgba(32, 83, 154, 0.12);
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            padding: 1rem;
            height: 100%;
        }

        .bitacoras-metric-label {
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-size: 0.76rem;
            font-weight: 800;
            margin-bottom: 0.35rem;
        }

        .bitacoras-metric-value {
            color: var(--bitacora-primary);
            font-size: 1.45rem;
            line-height: 1.1;
            font-weight: 900;
        }

        .bitacoras-metric-note {
            color: #64748b;
            font-size: 0.82rem;
            margin-top: 0.3rem;
        }

        .bitacoras-report-table {
            border: 1px solid var(--bitacora-border);
            border-radius: 14px;
            background: #fff;
            overflow: hidden;
            height: 100%;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }

        .bitacoras-report-table .table {
            margin-bottom: 0;
        }

        .bitacoras-report-table .table thead th {
            background: #eef3ff;
            color: var(--bitacora-primary);
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            border-bottom: 1px solid #d8e2f5;
        }

        .bitacoras-report-table .table td {
            vertical-align: middle;
            border-top: 1px solid rgba(32, 83, 154, 0.08);
        }

        .bitacoras-report-table-title {
            color: #0f172a;
            font-size: 0.95rem;
            font-weight: 800;
            margin: 0;
            padding: 1rem 1rem 0.4rem;
        }

        .bitacoras-report-table-subtitle {
            color: #64748b;
            font-size: 0.8rem;
            padding: 0 1rem 0.9rem;
        }

        .btn-bitacoras-filter,
        .btn-bitacoras-clear {
            min-height: 44px;
            border-radius: 12px;
            font-weight: 800;
            padding-inline: 1rem;
        }

        .btn-bitacoras-filter {
            background: var(--bitacora-primary);
            border-color: var(--bitacora-primary);
            color: #fff;
        }

        .btn-bitacoras-filter:hover {
            background: #1a4682;
            border-color: #1a4682;
            color: #fff;
        }

        .btn-bitacoras-clear {
            background: #fff;
            border: 1px solid rgba(32, 83, 154, 0.28);
            color: var(--bitacora-primary);
        }

        .btn-bitacoras-clear:hover {
            background: rgba(32, 83, 154, 0.05);
            color: var(--bitacora-primary);
        }

        .bitacoras-table-wrap {
            padding: 0 18px 18px;
        }

        .bitacoras-table-wrap .table {
            margin-bottom: 0;
        }

        .bitacoras-table-wrap .table thead th {
            background: #edf1fb;
            color: var(--bitacora-primary);
            border-bottom: 1px solid #dbe2f2;
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            vertical-align: middle;
        }

        .bitacoras-table-wrap .table td {
            vertical-align: middle;
            border-top: 1px solid rgba(32, 83, 154, 0.08);
        }

        .bitacoras-table-wrap .btn {
            border-radius: 10px;
            font-weight: 700;
        }

        .bitacora-code-btn {
            border: 0;
            background: rgba(32, 83, 154, 0.08);
            color: var(--bitacora-primary);
            border-radius: 999px;
            font-weight: 900;
            padding: 0.32rem 0.7rem;
            white-space: nowrap;
        }

        .bitacora-code-btn:hover {
            background: rgba(32, 83, 154, 0.16);
            color: #173d72;
        }

        .bitacora-detail-modal .modal-header {
            background: var(--bitacora-primary);
            color: #fff;
        }

        .bitacora-detail-modal .modal-title {
            font-weight: 800;
        }

        .bitacora-summary-pill {
            border: 1px solid var(--bitacora-border);
            border-radius: 10px;
            background: #fff;
            padding: 0.65rem 0.8rem;
        }

        .bitacora-summary-pill .label {
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .bitacora-summary-pill .value {
            color: var(--bitacora-primary);
            font-size: 1.1rem;
            font-weight: 900;
        }

        .bitacoras-footer {
            padding: 16px 18px 0;
        }

        .bitacoras-footer .pagination {
            margin-bottom: 0;
        }

        @media (max-width: 991.98px) {
            .bitacoras-table-wrap {
                padding-inline: 0;
            }
        }
    </style>
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="bitacoras-wrap">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card card-bitacoras">
                    <div class="card-header">
                        <div class="bitacoras-header-top">
                            <div>
                                <h3 class="card-title" id="card_title">Administracion de Bitacoras</h3>
                                <div class="bitacoras-subtitle">Gestiona bitacoras, filtros operativos y evidencias asociadas a paquetes provinciales.</div>
                            </div>
                            @can('feature.bitacoras.index.create')
                                <a href="{{ route('bitacoras.create') }}" class="btn btn-dorado">Crear Nuevo</a>
                            @endcan
                        </div>
                    </div>

                    @if ($message = Session::get('success'))
                        <div class="alert alert-success m-3 mb-0">
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    @endif

                    @if(((int) data_get($pendingCn33Alert ?? [], 'count', 0)) > 0)
                        @php
                            $pendingCn33Rows = collect(data_get($pendingCn33Alert, 'rows', []));
                            $pendingCn33Departments = $pendingCn33Rows
                                ->groupBy(function ($row) {
                                    $regional = trim((string) ($row->regional ?? ''));
                                    return $regional !== '' ? $regional : 'SIN DEPARTAMENTO';
                                })
                                ->map(function ($rows, $department) {
                                    return (object) [
                                        'department' => $department,
                                        'total_cn33' => $rows->count(),
                                        'max_days_delay' => (int) $rows->max('days_delay'),
                                        'rows' => $rows->sortByDesc('days_delay')->values(),
                                    ];
                                })
                                ->sortByDesc(function ($item) {
                                    return ((int) ($item->total_cn33 ?? 0) * 100000) + (int) ($item->max_days_delay ?? 0);
                                })
                                ->values();
                        @endphp
                        <div class="alert alert-danger m-3 mb-0">
                            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
                                <div>
                                    <strong>Registrar bitacora de envio nacional.</strong>
                                    Hay {{ number_format((int) data_get($pendingCn33Alert, 'count', 0)) }} CN-33 sin registrar en bitacora por mas de {{ (int) data_get($pendingCn33Alert, 'grace_hours', 24) }} horas.
                                    @if((string) data_get($pendingCn33Alert, 'regional', '') !== '')
                                        Solo se muestran registros de {{ data_get($pendingCn33Alert, 'regional') }}.
                                    @else
                                        Se muestran registros a nivel nacional.
                                    @endif
                                    Retraso maximo: {{ number_format((int) data_get($pendingCn33Alert, 'max_days_delay', 0)) }} dia(s).
                                    @if($pendingCn33Departments->isNotEmpty())
                                        <div class="mt-2 d-flex flex-wrap">
                                            @foreach($pendingCn33Departments as $index => $department)
                                                <button
                                                    type="button"
                                                    class="btn btn-light border mr-2 mb-2"
                                                    data-toggle="modal"
                                                    data-target="#bitacoraPendingCn33DepartmentModal{{ $index }}"
                                                >
                                                    {{ $department->department }}: {{ number_format((int) ($department->total_cn33 ?? 0)) }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                @can('feature.bitacoras.index.create')
                                    <div class="mt-3 mt-lg-0">
                                        <a href="{{ route('bitacoras.create') }}" class="btn btn-sm btn-outline-light border">
                                            Registrar ahora
                                        </a>
                                    </div>
                                @endcan
                            </div>
                        </div>

                        @foreach($pendingCn33Departments as $index => $department)
                            <div class="modal fade" id="bitacoraPendingCn33DepartmentModal{{ $index }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title">
                                                {{ $department->department }} - CN-33 que no tienen bitacora
                                            </h5>
                                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="alert alert-light border">
                                                <strong>Total CN-33 sin bitacora:</strong> {{ number_format((int) ($department->total_cn33 ?? 0)) }}
                                                |
                                                <strong>Retraso maximo:</strong> {{ number_format((int) ($department->max_days_delay ?? 0)) }} dia(s)
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>CN-33 sin bitacora</th>
                                                            <th class="text-right">Dias de retraso</th>
                                                            <th class="text-right">Peso</th>
                                                            <th class="text-right">Registros</th>
                                                            <th>Primer registro</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach(($department->rows ?? collect()) as $row)
                                                            <tr>
                                                                <td>{{ $row->numero_despacho ?? $row->cod_especial }}</td>
                                                                <td class="text-right">{{ number_format((int) ($row->days_delay ?? 0)) }}</td>
                                                                <td class="text-right">{{ number_format((float) ($row->peso_total ?? 0), 3) }}</td>
                                                                <td class="text-right">{{ number_format((int) ($row->total_registros ?? 0)) }}</td>
                                                                <td>{{ optional($row->first_created_at)->format('d/m/Y H:i') }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            @can('feature.bitacoras.index.create')
                                                <a href="{{ route('bitacoras.create') }}" class="btn btn-danger">Registrar bitacora</a>
                                            @endcan
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    <div class="card-body">
                        <div class="bitacoras-panel">
                            <div class="bitacoras-filters">
                                <div class="bitacoras-filters-title">Busqueda y filtros</div>
                                <div class="bitacoras-filters-subtitle">Refina la lista por regional del usuario, usuario, codigo especial, origen CN-33 o provincia.</div>

                                <form method="GET" action="{{ route('bitacoras.index') }}">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Busqueda general</label>
                                                <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cod especial, factura, usuario, codigo...">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Regional usuario</label>
                                                <select name="regional" class="form-control" onchange="this.form.submit()">
                                                    <option value="">Todas</option>
                                                    @foreach($regionales as $regionalItem)
                                                        <option value="{{ $regionalItem }}" {{ strtoupper((string) $regional) === strtoupper((string) $regionalItem) ? 'selected' : '' }}>
                                                            {{ $regionalItem }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Usuario</label>
                                                <select name="user_id" class="form-control">
                                                    <option value="">Todos</option>
                                                    @foreach($users as $user)
                                                        <option value="{{ $user->id }}" {{ (int) $userId === (int) $user->id ? 'selected' : '' }}>
                                                            {{ $user->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Cod especial</label>
                                                <input type="text" name="cod_especial" value="{{ $codEspecial }}" class="form-control" placeholder="Ej: LPZ00001">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Origen CN-33</label>
                                                <select name="origen_cn33" class="form-control">
                                                    <option value="">Todos</option>
                                                    @foreach($origenesCn33 as $origenCn33Item)
                                                        <option value="{{ $origenCn33Item }}" {{ strtoupper((string) $origenCn33) === strtoupper((string) $origenCn33Item) ? 'selected' : '' }}>
                                                            {{ $origenCn33Item }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Provincia</label>
                                                <select name="provincia" class="form-control">
                                                    <option value="">Todas</option>
                                                    @foreach($provincias as $provinciaItem)
                                                        <option value="{{ $provinciaItem }}" {{ strtoupper((string) $provincia) === strtoupper((string) $provinciaItem) ? 'selected' : '' }}>
                                                            {{ $provinciaItem }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bitacoras-actions">
                                        <button type="submit" class="btn btn-bitacoras-filter">Filtrar</button>
                                        <a href="{{ route('bitacoras.index') }}" class="btn btn-bitacoras-clear">Limpiar</a>
                                    </div>
                                </form>
                            </div>

                            <div class="bitacoras-report">
                                <div class="bitacoras-report-head">
                                    <div>
                                        <div class="bitacoras-report-title">Reporte profesional por departamento</div>
                                        <div class="bitacoras-report-subtitle">El resumen usa las mismas bitacoras filtradas actualmente y acumula el `precio_total` por origen, destino y ruta.</div>
                                    </div>
                                    <div class="bitacoras-report-actions">
                                        <a href="{{ route('bitacoras.export-pdf', request()->query()) }}" class="btn btn-bitacora-export" target="_blank">
                                            Exportar PDF
                                        </a>
                                        <a href="{{ route('bitacoras.export-excel', request()->query()) }}" class="btn btn-bitacora-export">
                                            Exportar Excel
                                        </a>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="bitacoras-metric-card">
                                            <div class="bitacoras-metric-label">Precio total acumulado</div>
                                            <div class="bitacoras-metric-value">Bs {{ number_format((float) data_get($reportTotals, 'total_precio', 0), 2) }}</div>
                                            <div class="bitacoras-metric-note">Total consolidado del reporte actual</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="bitacoras-metric-card">
                                            <div class="bitacoras-metric-label">Registros agrupados</div>
                                            <div class="bitacoras-metric-value">{{ number_format((int) data_get($reportTotals, 'total_registros', 0)) }}</div>
                                            <div class="bitacoras-metric-note">Bitacoras finales consideradas</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="bitacoras-metric-card">
                                            <div class="bitacoras-metric-label">Departamentos origen</div>
                                            <div class="bitacoras-metric-value">{{ number_format((int) data_get($reportTotals, 'origenes', 0)) }}</div>
                                            <div class="bitacoras-metric-note">Orígenes únicos detectados</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="bitacoras-metric-card">
                                            <div class="bitacoras-metric-label">Departamentos destino</div>
                                            <div class="bitacoras-metric-value">{{ number_format((int) data_get($reportTotals, 'destinos', 0)) }}</div>
                                            <div class="bitacoras-metric-note">Destinos únicos detectados</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="bitacoras-metric-card">
                                            <div class="bitacoras-metric-label">Transportadoras</div>
                                            <div class="bitacoras-metric-value">{{ number_format((int) data_get($reportTotals, 'transportadoras', 0)) }}</div>
                                            <div class="bitacoras-metric-note">Transportadoras únicas en el reporte</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-xl-3 mb-3">
                                        <div class="bitacoras-report-table">
                                            <h4 class="bitacoras-report-table-title">Ranking de transportadoras</h4>
                                            <div class="bitacoras-report-table-subtitle">Muestra a cuál transportadora se envía más, ordenado por cantidad de registros.</div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Transportadora</th>
                                                            <th class="text-right">Envíos</th>
                                                            <th class="text-right">Precio Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($reportByTransportadora as $row)
                                                            <tr>
                                                                <td>{{ strtoupper((string) $row->transportadora) }}</td>
                                                                <td class="text-right">{{ number_format((int) $row->total_registros) }}</td>
                                                                <td class="text-right">Bs {{ number_format((float) $row->total_precio, 2) }}</td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="3" class="text-center text-muted py-3">No hay datos para mostrar.</td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-xl-3 mb-3">
                                        <div class="bitacoras-report-table">
                                            <h4 class="bitacoras-report-table-title">Totales por origen</h4>
                                            <div class="bitacoras-report-table-subtitle">Precio total y peso acumulado por departamento de salida.</div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Origen</th>
                                                            <th class="text-right">Registros</th>
                                                            <th class="text-right">Precio Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($reportByOrigin as $row)
                                                            <tr>
                                                                <td>{{ $row->departamento }}</td>
                                                                <td class="text-right">{{ number_format((int) $row->total_registros) }}</td>
                                                                <td class="text-right">Bs {{ number_format((float) $row->total_precio, 2) }}</td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="3" class="text-center text-muted py-3">No hay datos para mostrar.</td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-xl-3 mb-3">
                                        <div class="bitacoras-report-table">
                                            <h4 class="bitacoras-report-table-title">Totales por destino</h4>
                                            <div class="bitacoras-report-table-subtitle">Resumen del importe acumulado según departamento de llegada.</div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Destino</th>
                                                            <th class="text-right">Registros</th>
                                                            <th class="text-right">Precio Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($reportByDestination as $row)
                                                            <tr>
                                                                <td>{{ $row->departamento }}</td>
                                                                <td class="text-right">{{ number_format((int) $row->total_registros) }}</td>
                                                                <td class="text-right">Bs {{ number_format((float) $row->total_precio, 2) }}</td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="3" class="text-center text-muted py-3">No hay datos para mostrar.</td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-xl-3 mb-3">
                                        <div class="bitacoras-report-table">
                                            <h4 class="bitacoras-report-table-title">Cruce origen y destino</h4>
                                            <div class="bitacoras-report-table-subtitle">Vista ejecutiva para identificar las rutas con mayor facturación.</div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Ruta</th>
                                                            <th class="text-right">Registros</th>
                                                            <th class="text-right">Precio Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($reportRows as $row)
                                                            <tr>
                                                                <td>{{ $row->origen_departamento }} <span class="text-muted">→</span> {{ $row->destino_departamento }}</td>
                                                                <td class="text-right">{{ number_format((int) $row->total_registros) }}</td>
                                                                <td class="text-right">Bs {{ number_format((float) $row->total_precio, 2) }}</td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="3" class="text-center text-muted py-3">No hay datos para mostrar.</td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bitacoras-table-wrap">
                                <div class="small text-muted mb-2">
                                    Se muestra una sola fila por <strong>cod_especial</strong>. Haz click en el codigo para ver la lista completa de registros relacionados.
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="thead">
                                            <tr>
                                                <th>ID</th>
                                                <th>Cod Especial</th>
                                                <th>Registros</th>
                                                <th>Usuario</th>
                                                <th>Transportadora</th>
                                                <th>Provincia</th>
                                                <th>Factura</th>
                                                <th>Precio Total</th>
                                                <th>Peso</th>
                                                <th>Imagen</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($bitacoras as $bitacora)
                                                @php
                                                    $detalleCodigo = strtoupper(trim((string) $bitacora->cod_especial));
                                                    $detallesCodigo = ($detallesPorCodEspecial ?? collect())->get($detalleCodigo, collect());
                                                @endphp
                                                <tr>
                                                    <td>{{ $bitacora->id }}</td>
                                                    <td>
                                                        <button
                                                            type="button"
                                                            class="bitacora-code-btn"
                                                            data-toggle="modal"
                                                            data-target="#bitacoraDetalleModal{{ $bitacora->id }}"
                                                            title="Ver todo el detalle de {{ $bitacora->cod_especial }}"
                                                        >
                                                            {{ $bitacora->cod_especial }}
                                                        </button>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-light border">
                                                            {{ number_format($detallesCodigo->count()) }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $bitacora->user->name ?? '-' }}</td>
                                                    <td>{{ $bitacora->transportadora ? strtoupper((string) $bitacora->transportadora) : '-' }}</td>
                                                    <td>{{ $bitacora->provincia ?: '-' }}</td>
                                                    <td>{{ $bitacora->factura ?: '-' }}</td>
                                                    <td>{{ $bitacora->precio_total !== null ? number_format((float) $bitacora->precio_total, 2) : '-' }}</td>
                                                    <td>{{ $bitacora->peso !== null ? number_format((float) $bitacora->peso, 3) : '-' }}</td>
                                                    <td>
                                                        @if($bitacora->imagen_factura)
                                                            @php
                                                                $bitacoraImagenExtension = strtolower(pathinfo((string) $bitacora->imagen_factura, PATHINFO_EXTENSION));
                                                                $bitacoraEsImagen = in_array($bitacoraImagenExtension, ['jpg', 'jpeg', 'png', 'webp'], true);
                                                            @endphp
                                                            @if($bitacoraEsImagen)
                                                                <a href="{{ asset('storage/' . $bitacora->imagen_factura) }}" target="_blank" class="d-inline-block">
                                                                    <img
                                                                        src="{{ asset('storage/' . $bitacora->imagen_factura) }}"
                                                                        alt="Factura bitacora {{ $bitacora->id }}"
                                                                        style="width:60px; height:60px; object-fit:cover; border-radius:10px; border:1px solid #dbe3f0; background:#fff; padding:2px;"
                                                                    >
                                                                </a>
                                                            @else
                                                                <a href="{{ asset('storage/' . $bitacora->imagen_factura) }}" target="_blank" class="btn btn-sm btn-outline-info">
                                                                    Ver PDF
                                                                </a>
                                                            @endif
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <form action="{{ route('bitacoras.destroy', $bitacora) }}" method="POST">
                                                            <a class="btn btn-sm btn-success" href="{{ route('bitacoras.edit', $bitacora) }}" title="Editar">
                                                                <i class="fa fa-fw fa-edit"></i>
                                                            </a>
                                                            @csrf
                                                            @method('DELETE')
                                                            <button
                                                                type="submit"
                                                                class="btn btn-danger btn-sm"
                                                                title="Eliminar"
                                                                onclick="return confirm('Seguro que deseas eliminar esta bitacora?')"
                                                            >
                                                                <i class="fa fa-fw fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="10" class="text-center py-4">No hay registros</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                @foreach($bitacoras as $bitacora)
                                    @php
                                        $detalleCodigo = strtoupper(trim((string) $bitacora->cod_especial));
                                        $detallesCodigo = ($detallesPorCodEspecial ?? collect())->get($detalleCodigo, collect());
                                        $cn33LocationResumen = ($cn33LocationsPorCodEspecial ?? collect())->get($detalleCodigo);
                                        $cn33PackagesResumen = ($cn33PackagesPorCodEspecial ?? collect())->get($detalleCodigo, collect());
                                        $totalPaquetesCn33 = $cn33PackagesResumen->count();
                                        $pesoTotalDetalle = $bitacora->peso ?? optional($cn33LocationResumen)->peso_total;
                                    @endphp
                                    <div class="modal fade bitacora-detail-modal" id="bitacoraDetalleModal{{ $bitacora->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-xl">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title mb-0">Detalle completo - {{ $bitacora->cod_especial }}</h5>
                                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-3 col-6 mb-2">
                                                            <div class="bitacora-summary-pill">
                                                                <div class="label">Registros</div>
                                                                <div class="value">{{ number_format($detallesCodigo->count()) }}</div>
                                                                <small class="text-muted">{{ number_format($totalPaquetesCn33) }} paquete(s) CN-33</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3 col-6 mb-2">
                                                            <div class="bitacora-summary-pill">
                                                                <div class="label">Precio total</div>
                                                                <div class="value">Bs {{ $bitacora->precio_total !== null ? number_format((float) $bitacora->precio_total, 2) : '-' }}</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3 col-6 mb-2">
                                                            <div class="bitacora-summary-pill">
                                                                <div class="label">Peso total</div>
                                                                <div class="value">{{ $pesoTotalDetalle !== null ? number_format((float) $pesoTotalDetalle, 3) : '-' }}</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3 col-6 mb-2">
                                                            <div class="bitacora-summary-pill">
                                                                <div class="label">Provincia</div>
                                                                <div class="value">{{ $bitacora->provincia ?: '-' }}</div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="table-responsive" style="max-height: 460px; overflow:auto;">
                                                        <table class="table table-sm table-striped table-hover mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th>ID</th>
                                                                    <th>Usuario</th>
                                                                    <th>Paquete EMS</th>
                                                                    <th>Paquete Contrato</th>
                                                                    <th>Paquete Ordinario</th>
                                                                    <th>Paquete Certificado</th>
                                                                    <th>Origen CN-33</th>
                                                                    <th>Transportadora</th>
                                                                    <th>Factura</th>
                                                                    <th>Precio</th>
                                                                    <th>Peso</th>
                                                                    <th>Imagen</th>
                                                                    <th>Creado</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @forelse($detallesCodigo as $detalle)
                                                                    @php
                                                                        $detalleCodigoNormalizado = strtoupper(trim((string) $detalle->cod_especial));
                                                                        $cn33Location = ($cn33LocationsPorCodEspecial ?? collect())->get($detalleCodigoNormalizado);
                                                                        $cn33Packages = ($cn33PackagesPorCodEspecial ?? collect())->get($detalleCodigoNormalizado, collect());
                                                                        $cn33EmsPackages = $cn33Packages->where('tipo', 'EMS')->values();
                                                                        $cn33ContratoPackages = $cn33Packages->where('tipo', 'CONTRATO')->values();
                                                                        $cn33OrdinarioPackages = $cn33Packages->where('tipo', 'ORDINARIO')->values();
                                                                        $cn33CertificadoPackages = $cn33Packages->where('tipo', 'CERTIFICADO')->values();
                                                                        $detalleOrigenCn33 = $detalle->paqueteEms->origen
                                                                            ?? $detalle->paqueteContrato->origen
                                                                            ?? optional($cn33Location)->origen_departamento
                                                                            ?? '-';
                                                                        $detallePeso = $detalle->peso ?? optional($cn33Location)->peso_total;
                                                                    @endphp
                                                                    <tr>
                                                                        <td>{{ $detalle->id }}</td>
                                                                        <td>{{ $detalle->user->name ?? '-' }}</td>
                                                                        <td>
                                                                            @if($detalle->paqueteEms)
                                                                                #{{ $detalle->paqueteEms->id }} - {{ $detalle->paqueteEms->codigo }}
                                                                            @elseif($cn33EmsPackages->isNotEmpty())
                                                                                @foreach($cn33EmsPackages as $paqueteCn33)
                                                                                    <div>#{{ $paqueteCn33->id }} - {{ $paqueteCn33->codigo }} <small class="text-muted">({{ number_format((float) $paqueteCn33->peso, 3) }} kg)</small></div>
                                                                                @endforeach
                                                                            @else
                                                                                -
                                                                            @endif
                                                                        </td>
                                                                        <td>
                                                                            @if($detalle->paqueteContrato)
                                                                                #{{ $detalle->paqueteContrato->id }} - {{ $detalle->paqueteContrato->codigo }}
                                                                            @elseif($cn33ContratoPackages->isNotEmpty())
                                                                                @foreach($cn33ContratoPackages as $paqueteCn33)
                                                                                    <div>#{{ $paqueteCn33->id }} - {{ $paqueteCn33->codigo }} <small class="text-muted">({{ number_format((float) $paqueteCn33->peso, 3) }} kg)</small></div>
                                                                                @endforeach
                                                                            @else
                                                                                -
                                                                            @endif
                                                                        </td>
                                                                        <td>
                                                                            @if($detalle->paqueteOrdi)
                                                                                #{{ $detalle->paqueteOrdi->id }} - {{ $detalle->paqueteOrdi->codigo }}
                                                                            @elseif($cn33OrdinarioPackages->isNotEmpty())
                                                                                @foreach($cn33OrdinarioPackages as $paqueteCn33)
                                                                                    <div>#{{ $paqueteCn33->id }} - {{ $paqueteCn33->codigo }} <small class="text-muted">({{ number_format((float) $paqueteCn33->peso, 3) }} kg)</small></div>
                                                                                @endforeach
                                                                            @else
                                                                                -
                                                                            @endif
                                                                        </td>
                                                                        <td>
                                                                            @if($detalle->paqueteCerti)
                                                                                #{{ $detalle->paqueteCerti->id }} - {{ $detalle->paqueteCerti->codigo }}
                                                                            @elseif($cn33CertificadoPackages->isNotEmpty())
                                                                                @foreach($cn33CertificadoPackages as $paqueteCn33)
                                                                                    <div>#{{ $paqueteCn33->id }} - {{ $paqueteCn33->codigo }} <small class="text-muted">({{ number_format((float) $paqueteCn33->peso, 3) }} kg)</small></div>
                                                                                @endforeach
                                                                            @else
                                                                                -
                                                                            @endif
                                                                        </td>
                                                                        <td>{{ $detalleOrigenCn33 }}</td>
                                                                        <td>{{ $detalle->transportadora ? strtoupper((string) $detalle->transportadora) : '-' }}</td>
                                                                        <td>{{ $detalle->factura ?: '-' }}</td>
                                                                        <td>{{ $detalle->precio_total !== null ? number_format((float) $detalle->precio_total, 2) : '-' }}</td>
                                                                        <td>{{ $detallePeso !== null ? number_format((float) $detallePeso, 3) : '-' }}</td>
                                                                        <td>
                                                                            @if($detalle->imagen_factura)
                                                                                @php
                                                                                    $detalleImagenExtension = strtolower(pathinfo((string) $detalle->imagen_factura, PATHINFO_EXTENSION));
                                                                                    $detalleEsImagen = in_array($detalleImagenExtension, ['jpg', 'jpeg', 'png', 'webp'], true);
                                                                                @endphp
                                                                                @if($detalleEsImagen)
                                                                                    <a href="{{ asset('storage/' . $detalle->imagen_factura) }}" target="_blank" class="d-inline-block">
                                                                                        <img
                                                                                            src="{{ asset('storage/' . $detalle->imagen_factura) }}"
                                                                                            alt="Factura bitacora {{ $detalle->id }}"
                                                                                            style="width:56px; height:56px; object-fit:cover; border-radius:10px; border:1px solid #dbe3f0; background:#fff; padding:2px;"
                                                                                        >
                                                                                    </a>
                                                                                @else
                                                                                    <a href="{{ asset('storage/' . $detalle->imagen_factura) }}" target="_blank" class="btn btn-xs btn-outline-info">
                                                                                        Ver PDF
                                                                                    </a>
                                                                                @endif
                                                                            @else
                                                                                -
                                                                            @endif
                                                                        </td>
                                                                        <td>{{ optional($detalle->created_at)->format('d/m/Y H:i') }}</td>
                                                                    </tr>
                                                                @empty
                                                                    <tr>
                                                                        <td colspan="13" class="text-center text-muted py-4">No hay registros para este codigo especial.</td>
                                                                    </tr>
                                                                @endforelse
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                <div class="bitacoras-footer">
                                    {!! $bitacoras->links() !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
