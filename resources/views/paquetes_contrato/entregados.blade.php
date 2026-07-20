@extends('adminlte::page')
@section('title', 'Contratos Entregados')
@section('template_title')
    Contratos Entregados
@endsection

@section('content')
    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app">
                <div class="header-shell">
                    <div>
                        <h4 class="fw-bold mb-1">Contratos Entregados</h4>
                        <small class="text-white-50">
                            Empresa aplicada: <strong>{{ optional(auth()->user()->empresa)->nombre ?? 'SIN EMPRESA' }}</strong>
                        </small>
                        <div class="header-meta">Total filtrado: <strong>{{ $contratos->total() }}</strong></div>
                    </div>

                    <div class="header-actions">
                        <form method="GET" action="{{ route('paquetes-contrato.entregados') }}" class="filters-form">
                            <div class="filter-field">
                                <label>Desde</label>
                                <input type="date" name="fecha_desde" value="{{ $fechaDesde }}" class="form-control">
                            </div>
                            <div class="filter-field">
                                <label>Hasta</label>
                                <input type="date" name="fecha_hasta" value="{{ $fechaHasta }}" class="form-control">
                            </div>
                            <button class="btn btn-outline-light2" type="submit">Filtrar</button>
                            <a class="btn btn-outline-light2" href="{{ route('paquetes-contrato.entregados', [], false) }}">Limpiar</a>
                            @if ($canContratoEntregadoExport ?? false)
                                <a class="btn btn-dorado" href="{{ route('paquetes-contrato.entregados.pdf', request()->query(), false) }}" target="_blank">
                                    Descargar PDF
                                </a>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            @if (session()->has('success'))
                <div class="alert alert-success m-3 mb-0">{{ session('success') }}</div>
            @endif
            @if (session()->has('error'))
                <div class="alert alert-danger m-3 mb-0">{{ session('error') }}</div>
            @endif

            <div class="card-body">
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-label">Entregados</div>
                        <div class="summary-value">{{ number_format($stats['total'] ?? 0) }}</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Peso total</div>
                        <div class="summary-value">{{ number_format((float) ($stats['peso_total'] ?? 0), 3) }} kg</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Dias cubiertos</div>
                        <div class="summary-value">{{ number_format($stats['dias_cubiertos'] ?? 0) }}</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Promedio diario</div>
                        <div class="summary-value">{{ number_format((float) ($stats['promedio_diario'] ?? 0), 2) }}</div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Cantidad</th>
                                <th>Remitente</th>
                                <th>Destinatario</th>
                                <th>Fecha de entrega</th>
                                <th>Peso</th>
                                <th>Imagen</th>
                                <th class="action-col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($contratos as $c)
                                <tr>
                                    <td><span class="pill-id">{{ $c->codigo }}</span></td>
                                    <td>{{ $c->origen }}</td>
                                    <td>{{ $c->destino }}</td>
                                    <td>{{ $c->cantidad ?: '-' }}</td>
                                    <td>{{ $c->nombre_r }}</td>
                                    <td>{{ $c->nombre_d }}</td>
                                    <td>{{ optional($c->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td>{{ number_format((float) ($c->peso ?? 0), 3) }}</td>
                                    <td>
                                        @if (!empty($c->imagen) && ($canContratoEntregadoExport ?? false))
                                            <a href="{{ route('delivery-images.show', ['source' => 'contrato', 'id' => $c->id], false) }}"
                                               class="btn btn-sm btn-outline-azul"
                                               target="_blank"
                                               rel="noopener">
                                                Ver imagen
                                            </a>
                                        @else
                                            <span class="muted">-</span>
                                        @endif
                                    </td>
                                    <td class="action-col">
                                        @include('partials.rastreo-eventos-button', [
                                            'tipo' => 'contrato',
                                            'codigo' => $c->codigo,
                                        ])
                                        @if ($canContratoEntregadoPrint ?? false)
                                            <a href="{{ route('paquetes-contrato.reporte', $c->id, false) }}"
                                               class="btn btn-sm btn-outline-azul action-btn"
                                               target="_blank"
                                               title="Reimprimir rotulo">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <div class="fw-bold text-color-main">No hay contratos entregados</div>
                                        <div class="muted">No existen registros en estado ENTREGADO para tu empresa y rango de fechas.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    {{ $contratos->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        :root {
            --azul: #20539A;
            --dorado: #FECC36;
            --bg: #f5f7fb;
            --muted: #6b7280;
        }

        .plantilla-wrap { background: var(--bg); padding: 18px; border-radius: 16px; }
        .card-app { border: 0; border-radius: 16px; box-shadow: 0 12px 26px rgba(0, 0, 0, .08); overflow: hidden; }
        .header-app { background: linear-gradient(90deg, var(--azul), #20539A); color: #fff; padding: 18px 20px; }
        .header-shell { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; flex-wrap: wrap; }
        .header-meta { margin-top: .35rem; color: rgba(255, 255, 255, .82); font-size: .88rem; }
        .header-actions { flex: 1 1 640px; }
        .filters-form { display: flex; gap: 10px; justify-content: flex-end; align-items: end; flex-wrap: wrap; }
        .filter-field label { display: block; margin-bottom: 6px; font-size: .8rem; font-weight: 800; color: rgba(255,255,255,.92); }
        .filter-field .form-control { min-width: 160px; border-radius: 10px; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
        .summary-card { background: linear-gradient(180deg, #fff 0%, #f7faff 100%); border: 1px solid #dbe4f2; border-radius: 14px; padding: 14px 16px; box-shadow: 0 8px 18px rgba(32,83,154,.06); }
        .summary-label { color: #64748b; font-size: .78rem; text-transform: uppercase; font-weight: 800; margin-bottom: 4px; }
        .summary-value { color: #1e3a8a; font-size: 1.45rem; font-weight: 900; }
        .table thead th { background: rgba(52, 68, 124, .08); color: var(--azul); font-weight: 900; border-bottom: 2px solid rgba(52, 68, 124, .2); white-space: nowrap; }
        .table td { vertical-align: middle; }
        .pill-id { background: rgba(52, 68, 124, .12); color: var(--azul); font-weight: 900; padding: 4px 10px; border-radius: 999px; display: inline-block; }
        .btn-dorado { background: var(--dorado); color: #fff; font-weight: 800; border: none; border-radius: 12px; padding: 10px 14px; }
        .btn-outline-light2 { border: 1px solid rgba(255,255,255,.7); color: #fff; font-weight: 800; border-radius: 12px; padding: 10px 14px; background: transparent; }
        .btn-outline-azul { border: 1px solid rgba(52, 68, 124, .35); color: var(--azul); font-weight: 800; border-radius: 12px; padding: 8px 12px; background: #fff; }
        .btn-outline-azul:hover, .btn-outline-light2:hover, .btn-dorado:hover { color: inherit; filter: brightness(.98); }
        .action-col { width: 128px; min-width: 128px; text-align: center; }
        .action-btn { width: 48px; height: 48px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 14px; box-shadow: 0 8px 18px rgba(32, 83, 154, .10); }
        .action-btn i { font-size: 16px; }
        .muted { color: var(--muted); }
        .text-color-main { color: var(--azul); }

        @media (max-width: 991.98px) {
            .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filters-form { justify-content: flex-start; }
        }

        @media (max-width: 575.98px) {
            .summary-grid { grid-template-columns: 1fr; }
            .filters-form { flex-direction: column; align-items: stretch; }
            .filter-field .form-control,
            .filters-form .btn { width: 100%; }
        }
    </style>
@endsection
