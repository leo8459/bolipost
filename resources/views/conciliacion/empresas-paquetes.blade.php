@extends('adminlte::page')

@section('title', 'Empresas paquetes')

@section('content_header')
    <div class="d-flex align-items-center justify-content-between flex-wrap">
        <div>
            <h1 class="mb-1">Empresas paquetes</h1>
            <p class="text-muted mb-0">Resumen de contratos registrados y entregados por empresa.</p>
        </div>
    </div>
@endsection

@section('content')
    <div class="empresas-paquetes-page">
        <div class="card filtros-card mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-filter mr-2"></i>Filtrar por meses</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('dashboard.conciliacion.empresas-paquetes') }}">
                    <div class="row align-items-end">
                        <div class="col-xl-2 col-md-4 mb-3">
                            <label for="anio">Año</label>
                            <input id="anio" name="anio" type="number" min="2000" max="{{ now()->year + 1 }}"
                                class="form-control @error('anio') is-invalid @enderror" value="{{ $anio }}">
                            @error('anio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-xl-10 col-md-8 mb-3 d-flex justify-content-md-end">
                            <button id="seleccionarTodosMeses" type="button" class="btn btn-outline-primary btn-sm mr-2">
                                <i class="fas fa-check-double mr-1"></i> Seleccionar todos
                            </button>
                            <button id="limpiarMeses" type="button" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times mr-1"></i> Quitar selección
                            </button>
                        </div>
                    </div>

                    <label>Meses <small class="text-muted font-weight-normal">(selecciona uno o varios)</small></label>
                    <div class="month-grid mb-3">
                        @foreach($nombresMeses as $numeroMes => $nombreMes)
                            <label class="month-option @if(in_array($numeroMes, $mesesSeleccionados, true)) selected @endif">
                                <input type="checkbox" name="meses[]" value="{{ $numeroMes }}"
                                    @checked(in_array($numeroMes, $mesesSeleccionados, true))>
                                <span>{{ $nombreMes }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('meses')<div class="text-danger small mb-3">{{ $message }}</div>@enderror
                    @error('meses.*')<div class="text-danger small mb-3">{{ $message }}</div>@enderror

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search mr-1"></i> Aplicar filtro
                    </button>
                    <a href="{{ route('dashboard.conciliacion.empresas-paquetes') }}" class="btn btn-outline-secondary ml-2">
                        <i class="fas fa-undo mr-1"></i> Mes actual
                    </a>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 col-md-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3>{{ number_format($empresas->count()) }}</h3>
                        <p>Empresas</p>
                    </div>
                    <div class="icon"><i class="fas fa-building"></i></div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ number_format($totalRegistrados) }}</h3>
                        <p>Contratos registrados</p>
                    </div>
                    <div class="icon"><i class="fas fa-boxes"></i></div>
                </div>
            </div>
            <div class="col-lg-4 col-md-12">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ number_format($totalEntregados) }}</h3>
                        <p>Contratos entregados</p>
                    </div>
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
        </div>

        <div class="card resumen-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title">
                    <i class="fas fa-table mr-2"></i>Resumen por empresa
                    <small class="text-muted ml-2">
                        {{ collect($mesesSeleccionados)->map(fn ($mes) => $nombresMeses[$mes])->implode(', ') }} {{ $anio }}
                    </small>
                </h3>
                <span class="badge badge-primary resumen-total">{{ number_format($empresas->count()) }} empresas</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th class="text-center">Contratos registrados</th>
                                <th class="text-center">Entregados</th>
                                <th class="text-right">Presupuesto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($empresas as $empresa)
                                <tr>
                                    <td>
                                        <strong>{{ $empresa->nombre }}</strong>
                                        <small class="d-block text-muted">
                                            @if(filled($empresa->codigo_cliente)){{ $empresa->codigo_cliente }}@endif
                                            @if(filled($empresa->codigo_cliente) && filled($empresa->sigla)) &middot; @endif
                                            @if(filled($empresa->sigla)){{ $empresa->sigla }}@endif
                                        </small>
                                    </td>
                                    <td class="text-center"><span class="count-value">{{ number_format($empresa->contratos_registrados) }}</span></td>
                                    <td class="text-center"><span class="count-value delivered">{{ number_format($empresa->contratos_entregados) }}</span></td>
                                    <td class="text-right text-nowrap font-weight-bold">
                                        {{ $empresa->presupuesto !== null ? 'Bs '.number_format((float) $empresa->presupuesto, 2) : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-5">No existen empresas registradas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($empresas->isNotEmpty())
                            <tfoot>
                                <tr>
                                    <th>Total</th>
                                    <th class="text-center">{{ number_format($totalRegistrados) }}</th>
                                    <th class="text-center">{{ number_format($totalEntregados) }}</th>
                                    <th class="text-right text-nowrap">Bs {{ number_format($totalPresupuesto, 2) }}</th>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .empresas-paquetes-page .small-box { border-radius: 12px; box-shadow: 0 8px 24px rgba(32, 83, 154, .12); }
        .empresas-paquetes-page .resumen-card, .empresas-paquetes-page .filtros-card { border: 0; border-radius: 12px; box-shadow: 0 8px 24px rgba(32, 83, 154, .10); overflow: hidden; }
        .empresas-paquetes-page .card-header { background: #fff; border-bottom: 1px solid #e8edf5; padding: 1rem 1.25rem; }
        .empresas-paquetes-page .card-title { color: #173d73; font-weight: 700; }
        .empresas-paquetes-page label { color: #34445d; font-size: .86rem; font-weight: 700; }
        .empresas-paquetes-page .month-grid { display: grid; grid-template-columns: repeat(6, minmax(110px, 1fr)); gap: .6rem; }
        .empresas-paquetes-page .month-option { display: flex; align-items: center; justify-content: center; min-height: 42px; margin: 0; padding: .55rem .75rem; border: 1px solid #d7dfeb; border-radius: 9px; background: #fff; cursor: pointer; transition: .15s ease; }
        .empresas-paquetes-page .month-option input { position: absolute; opacity: 0; pointer-events: none; }
        .empresas-paquetes-page .month-option.selected { border-color: #20539a; background: #e8f1ff; color: #20539a; box-shadow: inset 0 0 0 1px #20539a; }
        .empresas-paquetes-page thead th { background: #edf2f9; color: #20539a; border: 0; font-size: .78rem; letter-spacing: .3px; text-transform: uppercase; white-space: nowrap; }
        .empresas-paquetes-page tbody td { vertical-align: middle; }
        .empresas-paquetes-page tfoot th { background: #f7f9fc; border-top: 2px solid #dce4ef; color: #173d73; }
        .empresas-paquetes-page .resumen-total { border-radius: 999px; font-size: .82rem; padding: .45rem .75rem; }
        .empresas-paquetes-page .count-value { display: inline-block; min-width: 48px; padding: .3rem .65rem; border-radius: 999px; background: #e8f1ff; color: #20539a; font-weight: 700; }
        .empresas-paquetes-page .count-value.delivered { background: #e6f7ed; color: #198754; }
        @media (max-width: 991.98px) { .empresas-paquetes-page .month-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 575.98px) { .empresas-paquetes-page .month-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const options = Array.from(document.querySelectorAll('.month-option'));
            const updateOption = option => option.classList.toggle('selected', option.querySelector('input').checked);

            options.forEach(option => option.querySelector('input').addEventListener('change', () => updateOption(option)));
            document.getElementById('seleccionarTodosMeses')?.addEventListener('click', function () {
                options.forEach(option => { option.querySelector('input').checked = true; updateOption(option); });
            });
            document.getElementById('limpiarMeses')?.addEventListener('click', function () {
                options.forEach(option => { option.querySelector('input').checked = false; updateOption(option); });
            });
        });
    </script>
@endsection
