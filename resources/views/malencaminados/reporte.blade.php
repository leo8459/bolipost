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
        .mc-kpi { border-radius: 12px; background: linear-gradient(135deg, #1d4d92, #2662b4); color:#fff; padding:12px 14px; }
        .mc-kpi .label { font-size: .8rem; opacity: .9; }
        .mc-kpi .value { font-size: 1.2rem; font-weight: 700; }
        .table thead th { white-space: nowrap; }
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
            </form>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-4">
            <div class="mc-kpi">
                <div class="label">Rango</div>
                <div class="value">{{ $fechaInicio }} al {{ $fechaFin }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mc-kpi">
                <div class="label">Departamento filtro</div>
                <div class="value">{{ $departamento }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mc-kpi">
                <div class="label">Total malencaminados</div>
                <div class="value">{{ (int) $resumen->sum('total_registros') }}</div>
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
                        <th>Total registros</th>
                        <th>Total malencaminamientos</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resumen as $row)
                        <tr>
                            <td>{{ $row->departamento }}</td>
                            <td>{{ (int) $row->total_registros }}</td>
                            <td>{{ (int) $row->total_malencaminamientos }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center">No hay datos para el rango seleccionado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
