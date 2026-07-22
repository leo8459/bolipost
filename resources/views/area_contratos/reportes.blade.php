@extends('adminlte::page')
@section('title', 'Area Contratos - Reportes')
@section('template_title')
    Area Contratos - Reportes
@endsection

@section('content')
    <div class="area-contratos-wrap">
        <div class="card area-contratos-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h3 class="card-title mb-1">Reportes de contratos</h3>
                    <div class="area-header-meta">Total filtrado: <strong>{{ $totalReportes }}</strong></div>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('area-contratos.reportes') }}" class="row align-items-end mb-3" id="reportesContratosForm">
                    <div class="col-md-3 mb-2">
                        <label class="small font-weight-bold mb-1">Empresa</label>
                        <select name="empresa_id" class="form-control">
                            <option value="0">Todas las empresas</option>
                            @foreach ($empresas as $empresa)
                                <option value="{{ $empresa->id }}" {{ (int) $empresaId === (int) $empresa->id ? 'selected' : '' }}>
                                    {{ $empresa->nombre }}@if(!empty($empresa->sigla)) ({{ $empresa->sigla }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="small font-weight-bold mb-1">Fecha envio desde</label>
                        <input type="date" name="from" value="{{ $from }}" class="form-control">
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="small font-weight-bold mb-1">Fecha envio hasta</label>
                        <input type="date" name="to" value="{{ $to }}" class="form-control">
                    </div>

                    <div class="col-md-3 mb-2">
                        <label class="small font-weight-bold mb-1">Buscar</label>
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            class="form-control"
                            placeholder="Codigo, remitente, destinatario, origen, destino..."
                        >
                    </div>

                    <div class="col-md-2 mb-2">
                        <div class="area-filter-actions">
                            <button type="submit" class="btn area-btn-primary">Filtrar</button>
                            <a href="{{ route('area-contratos.reportes') }}" class="btn area-btn-secondary">Limpiar</a>
                        </div>
                    </div>
                </form>

                <div class="d-flex flex-wrap align-items-center justify-content-between report-toolbar">
                    <div class="text-muted mb-2 mb-md-0">
                        El Excel se genera con todos los contratos que tengan estado distinto de 0, excepto CANCELADO, separados por hoja segun el departamento de origen.
                    </div>
                    <a
                        href="{{ route('area-contratos.reportes.excel', request()->query()) }}"
                        class="btn btn-success"
                        id="exportExcelContratosBtn"
                    >
                        <i class="fas fa-file-excel mr-1"></i> Exportar Excel
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 mb-3">
                <div class="card area-contratos-card h-100">
                    <div class="card-header">
                        <h3 class="card-title">Hojas que se generaran</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Origen</th>
                                        <th class="text-right">Registros</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($groupedSummary as $group)
                                        <tr>
                                            <td>{{ $group['origen'] }}</td>
                                            <td class="text-right">{{ $group['total'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="text-center py-4 text-muted">
                                                No hay datos para generar hojas.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8 mb-3">
                <div class="card area-contratos-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h3 class="card-title mb-1">Vista previa de contratos</h3>
                            <div class="area-header-meta">Mostrando: <strong>{{ $contratos->count() }}</strong></div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Codigo</th>
                                        <th>Origen</th>
                                        <th>Provincia</th>
                                        <th>Destino</th>
                                        <th>Remitente</th>
                                        <th>Destinatario</th>
                                        <th>Empresa</th>
                                        <th>Cantidad</th>
                                        <th>Estado</th>
                                        <th>Fecha envio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($contratos as $contrato)
                                        <tr>
                                            <td>{{ $contrato->codigo }}</td>
                                            <td>{{ $contrato->origen ?: '-' }}</td>
                                            <td>{{ $contrato->provincia ?: '-' }}</td>
                                            <td>{{ $contrato->destino ?: '-' }}</td>
                                            <td>{{ $contrato->nombre_r ?: '-' }}</td>
                                            <td>{{ $contrato->nombre_d ?: '-' }}</td>
                                            <td>
                                                {{ optional($contrato->empresa)->nombre ?? '-' }}
                                                @if(!empty(optional($contrato->empresa)->sigla))
                                                    ({{ optional($contrato->empresa)->sigla }})
                                                @endif
                                            </td>
                                            <td>{{ $contrato->cantidad ?: '-' }}</td>
                                            <td>{{ optional($contrato->estadoRegistro)->nombre_estado ?: '-' }}</td>
                                            <td>{{ optional($contrato->created_at)->format('d/m/Y H:i') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center py-4 text-muted">
                                                No hay contratos con los filtros seleccionados.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        {{ $contratos->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .area-contratos-wrap {
            background: linear-gradient(180deg, #f8faff 0%, #f1f5fe 100%);
            border: 1px solid #e2e8f6;
            border-radius: 14px;
            padding: 14px;
        }

        .area-contratos-card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 12px 26px rgba(28, 45, 94, 0.1);
            overflow: hidden;
        }

        .area-contratos-card .card-header {
            background: linear-gradient(95deg, #20539A 0%, #43538f 100%);
            color: #fff;
            border-bottom: 0;
            padding: 0.95rem 1.1rem;
        }

        .area-header-meta {
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.88rem;
        }

        .area-btn-primary {
            background: #FECC36;
            color: #fff;
            font-weight: 800;
            border: none;
            border-radius: 12px;
            padding: 10px 14px;
        }

        .area-btn-primary:hover {
            filter: brightness(.96);
            color: #fff;
        }

        .area-btn-secondary {
            border: 1px solid rgba(32, 83, 154, .22);
            background: #fff;
            color: #20539A;
            font-weight: 800;
            border-radius: 12px;
            padding: 10px 14px;
        }

        .area-btn-secondary:hover {
            background: rgba(32, 83, 154, .05);
            color: #20539A;
        }

        .area-filter-actions {
            display: flex;
            gap: 10px;
        }

        .area-filter-actions .btn {
            flex: 1 1 0;
        }

        .area-contratos-card .table thead th {
            background: #edf1fb;
            color: #20539A;
            border-bottom: 1px solid #dbe2f2;
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            white-space: nowrap;
        }

        .report-toolbar {
            gap: 12px;
            padding: 12px 14px;
            border: 1px dashed #d8e0f2;
            border-radius: 12px;
            background: #f8fbff;
        }

        @media (max-width: 767.98px) {
            .area-header-meta {
                margin-bottom: .35rem;
            }

            .area-filter-actions {
                flex-direction: column;
            }
        }
    </style>
@endsection
