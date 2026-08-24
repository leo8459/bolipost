@extends('adminlte::page')

@section('title', 'Guías Empresa')

@section('content_header')
    <div class="d-flex align-items-center justify-content-between flex-wrap">
        <div>
            <h1 class="mb-1">Guías Empresa</h1>
            <p class="text-muted mb-0">Consulta todos los paquetes registrados por empresas.</p>
        </div>
    </div>
@endsection

@section('content')
    <div class="guias-empresa-page">
        <div class="card guias-card mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-filter mr-2"></i>Filtros</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('empresa.guias.index') }}" class="row align-items-end">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <label for="q">Buscar guía</label>
                        <input id="q" name="q" type="text" class="form-control"
                            value="{{ $search }}" placeholder="Código, destinatario, origen, estado...">
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <label for="empresa_id">Empresa</label>
                        <select id="empresa_id" name="empresa_id" class="form-control @error('empresa_id') is-invalid @enderror">
                            <option value="">Todas las empresas</option>
                            @foreach($empresas as $empresa)
                                <option value="{{ $empresa->id }}" @selected($empresaId === (int) $empresa->id)>
                                    @if(filled($empresa->codigo_cliente)){{ $empresa->codigo_cliente }} - @endif{{ $empresa->nombre }}@if(filled($empresa->sigla)) ({{ $empresa->sigla }})@endif
                                </option>
                            @endforeach
                        </select>
                        @error('empresa_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-xl-2 col-md-4 mb-3">
                        <label for="fecha_desde">Fecha desde</label>
                        <input id="fecha_desde" name="fecha_desde" type="date"
                            class="form-control @error('fecha_desde') is-invalid @enderror" value="{{ $fechaDesde }}">
                        @error('fecha_desde')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-xl-2 col-md-4 mb-3">
                        <label for="fecha_hasta">Fecha hasta</label>
                        <input id="fecha_hasta" name="fecha_hasta" type="date"
                            class="form-control @error('fecha_hasta') is-invalid @enderror" value="{{ $fechaHasta }}">
                        @error('fecha_hasta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-xl-2 col-md-4 mb-3">
                        @aclcan('search', null, 'empresa.guias.index')
                            <div class="d-flex">
                                <button type="submit" class="btn btn-primary flex-fill mr-2">
                                    <i class="fas fa-search mr-1"></i> Filtrar
                                </button>
                                <a href="{{ route('empresa.guias.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros">
                                    <i class="fas fa-eraser"></i>
                                </a>
                            </div>
                        @endaclcan
                    </div>
                </form>
                <small class="text-muted">El rango se aplica sobre la fecha de registro de cada paquete.</small>
            </div>
        </div>

        <div class="card guias-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title"><i class="fas fa-boxes mr-2"></i>Paquetes de empresas</h3>
                <div class="d-flex align-items-center">
                    <span class="badge badge-primary guias-total mr-2">{{ number_format($guias->total()) }} registros</span>
                    @aclcan('export', null, 'empresa.guias.index')
                        <a href="{{ route('empresa.guias.excel', request()->query()) }}" class="btn btn-success btn-sm">
                            <i class="fas fa-file-excel mr-1"></i> Descargar reporte Excel
                        </a>
                    @endaclcan
                </div>
            </div>
            <div class="px-3 py-2 bg-light border-bottom text-muted small">
                El reporte aplica los filtros seleccionados y excluye las guías sin estado o con estado 0.
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Guía</th>
                                <th>Empresa</th>
                                <th>Trayecto</th>
                                <th>Remitente</th>
                                <th>Destinatario</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($guias as $guia)
                                @php($empresaGuia = $guia->empresa ?? $guia->user?->empresa)
                                <tr>
                                    <td>
                                        <strong>{{ $guia->codigo ?: 'SIN CÓDIGO' }}</strong>
                                        @if(filled($guia->codigo_madre))
                                            <small class="d-block text-muted">Madre: {{ $guia->codigo_madre }}</small>
                                        @endif
                                        @if(filled($guia->cod_especial))
                                            <small class="d-block text-muted">CN-33: {{ $guia->cod_especial }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $empresaGuia?->nombre ?? '-' }}</strong>
                                        @if(filled($empresaGuia?->sigla))
                                            <small class="d-block text-muted">{{ $empresaGuia->sigla }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $guia->origen ?: '-' }} <i class="fas fa-long-arrow-alt-right text-muted mx-1"></i> {{ $guia->destino ?: '-' }}</td>
                                    <td>{{ $guia->nombre_r ?: '-' }}</td>
                                    <td>{{ $guia->nombre_d ?: '-' }}</td>
                                    <td><span class="badge badge-light border">{{ $guia->estadoRegistro?->nombre_estado ?? 'SIN ESTADO' }}</span></td>
                                    <td class="text-nowrap">{{ $guia->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td class="text-center text-nowrap">
                                        @aclcan('view', null, 'empresa.guias.index')
                                            @include('partials.rastreo-eventos-button', [
                                                'tipo' => 'contrato',
                                                'codigo' => $guia->codigo,
                                                'routeName' => 'empresa.guias.rastreo',
                                                'class' => 'btn btn-sm btn-outline-primary',
                                            ])
                                        @endaclcan
                                        @aclcan('print', null, 'empresa.guias.index')
                                            <a href="{{ route('paquetes-contrato.reporte', $guia) }}" target="_blank" rel="noopener"
                                                class="btn btn-sm btn-warning" title="Ver o imprimir guía">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        @endaclcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="fas fa-search fa-2x d-block mb-2"></i>
                                        No se encontraron paquetes con los filtros seleccionados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($guias->hasPages())
                <div class="card-footer d-flex justify-content-end pb-0">
                    {{ $guias->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

@section('css')
    <style>
        .guias-empresa-page .guias-card { border: 0; border-radius: 12px; box-shadow: 0 8px 24px rgba(32, 83, 154, .10); overflow: hidden; }
        .guias-empresa-page .card-header { background: #fff; border-bottom: 1px solid #e8edf5; padding: 1rem 1.25rem; }
        .guias-empresa-page .card-title { color: #173d73; font-weight: 700; }
        .guias-empresa-page label { color: #34445d; font-size: .86rem; font-weight: 700; }
        .guias-empresa-page thead th { background: #edf2f9; color: #20539a; border: 0; font-size: .78rem; letter-spacing: .3px; text-transform: uppercase; white-space: nowrap; }
        .guias-empresa-page tbody td { vertical-align: middle; }
        .guias-total { background: #20539a; border-radius: 999px; font-size: .82rem; padding: .45rem .75rem; }
    </style>
@endsection
