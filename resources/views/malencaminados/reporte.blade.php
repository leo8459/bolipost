@extends('adminlte::page')

@section('title', 'Reporte Malencaminados')
@section('template_title')
    Reporte de Malencaminados
@endsection

@section('content')
<div class="container-fluid">
    <style>
        .mc-card { border: 0; border-radius: 14px; box-shadow: 0 8px 22px rgba(0,0,0,.08); }
        .mc-head { border-top-left-radius: 14px; border-top-right-radius: 14px; }
        .mc-kpi { border-radius: 12px; background: linear-gradient(135deg, #1d4d92, #2662b4); color:#fff; padding:12px 14px; height:100%; }
        .mc-kpi .label { font-size: .8rem; opacity: .9; }
        .mc-kpi .value { font-size: 1.2rem; font-weight: 700; }
        .table thead th { white-space: nowrap; }
        .mc-danger { color: #b91c1c; font-weight: 800; }
        .mc-ok { color: #166534; font-weight: 800; }
    </style>

    <div class="card">
        <div class="card-header bg-primary mc-head">
            <h3 class="card-title text-white mb-0">Filtro por fechas y departamento</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('malencaminados.reporte') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Fecha inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="{{ $fechaInicio }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha fin</label>
                    <input type="date" name="fecha_fin" class="form-control" value="{{ $fechaFin }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Departamento (origen)</label>
                    <select name="departamento" class="form-control">
                        @foreach($departamentos as $dep)
                            <option value="{{ $dep }}" {{ $departamento === $dep ? 'selected' : '' }}>{{ $dep }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    @aclcan('report', null, 'malencaminados')
                        <button type="submit" class="btn btn-primary w-100">Generar reporte</button>
                    @endaclcan
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    @aclcan('export', null, 'malencaminados')
                        <a
                            href="{{ route('malencaminados.reporte.pdf', ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin, 'departamento' => $departamento]) }}"
                            class="btn btn-danger w-100"
                            target="_blank"
                        >
                            Exportar PDF
                        </a>
                    @endaclcan
                </div>
                <div class="col-md-3 d-flex align-items-end mt-2">
                    @aclcan('export', null, 'malencaminados')
                        <a
                            href="{{ route('malencaminados.reporte.excel', ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin, 'departamento' => $departamento]) }}"
                            class="btn btn-success w-100"
                        >
                            <i class="fas fa-file-excel mr-1"></i> Exportar Excel analitico
                        </a>
                    @endaclcan
                </div>
            </form>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-3">
            <div class="mc-kpi">
                <div class="label">Rango</div>
                <div class="value">{{ $fechaInicio }} al {{ $fechaFin }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="mc-kpi">
                <div class="label">Departamento filtro</div>
                <div class="value">{{ $departamento }}</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="mc-kpi">
                <div class="label">Total envios</div>
                <div class="value">{{ number_format($totalEnvios) }}</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="mc-kpi">
                <div class="label">Total malencaminados</div>
                <div class="value">{{ number_format($totalMalencaminados) }}</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="mc-kpi">
                <div class="label">% error general</div>
                <div class="value">{{ number_format($porcentajeErrorGeneral, 2) }}%</div>
            </div>
        </div>
    </div>

    <div class="card mc-card mt-3">
        <div class="card-header bg-info mc-head">
            <h3 class="card-title text-white mb-0">Resumen por departamento (origen)</h3>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>Departamento</th>
                        <th>Total envios</th>
                        <th>Malencaminados</th>
                        <th>% error</th>
                        <th>Total registros</th>
                        <th>Total malencaminamientos</th>
                        <th>EMS</th>
                        <th>Contratos</th>
                        <th>Certificados</th>
                        <th>Ordinarios</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resumen as $row)
                        <tr>
                            <td>{{ $row->departamento }}</td>
                            <td>{{ number_format((int) $row->total_envios) }}</td>
                            <td>{{ number_format((int) $row->total_registros) }}</td>
                            <td class="{{ (float) $row->porcentaje_error > 0 ? 'mc-danger' : 'mc-ok' }}">
                                {{ number_format((float) $row->porcentaje_error, 2) }}%
                            </td>
                            <td>{{ number_format((int) $row->total_registros) }}</td>
                            <td>{{ number_format((int) $row->total_malencaminamientos) }}</td>
                            <td>{{ number_format((int) $row->ems) }}</td>
                            <td>{{ number_format((int) $row->contratos) }}</td>
                            <td>{{ number_format((int) $row->certificados) }}</td>
                            <td>{{ number_format((int) $row->ordinarios) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center">No hay datos para el rango seleccionado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mc-card mt-3 mb-4">
        <div class="card-header bg-secondary mc-head">
            <h3 class="card-title text-white mb-0">Detalle con personal que creo la guia o reporto el envio</h3>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Codigo</th>
                        <th>Tipo</th>
                        <th>Departamento origen</th>
                        <th>Destino anterior</th>
                        <th>Destino nuevo</th>
                        <th>Creo guia</th>
                        <th>Reporto / mando</th>
                        <th>Obs.</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($detalle as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i') }}</td>
                            <td class="font-weight-bold">{{ $row->codigo }}</td>
                            <td>{{ $row->tipo }}</td>
                            <td>{{ $row->departamento_origen }}</td>
                            <td>{{ $row->destino_anterior }}</td>
                            <td>{{ $row->destino_nuevo }}</td>
                            <td>
                                {{ $row->usuario_creador_guia }}
                                <div class="text-muted small">{{ $row->departamento_usuario_creador }}</div>
                            </td>
                            <td>{{ $row->usuario_reporto_malencaminado }}</td>
                            <td>{{ $row->observacion }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center">No hay detalle para el rango seleccionado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if(method_exists($detalle, 'links'))
                {{ $detalle->links() }}
            @endif
        </div>
    </div>
</div>
@endsection
