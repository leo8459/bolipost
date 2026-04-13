@extends('adminlte::page')
@section('title', 'Contratos Entregados')
@section('template_title')
    Contratos Entregados
@endsection

@section('content')
    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                <div>
                    <h4 class="fw-bold mb-1">Contratos Entregados</h4>
                    <small class="text-white-50">
                        Empresa aplicada: <strong>{{ optional(auth()->user()->empresa)->nombre ?? 'SIN EMPRESA' }}</strong>
                    </small>
                    <div class="header-meta">Total en registros: <strong>{{ $contratos->total() }}</strong></div>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Cantidad</th>
                                <th>Receptor</th>
                                <th>Fecha registro</th>
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
                                    <td>{{ optional($c->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        @if (!empty($c->imagen) && ($canContratoEntregadoExport ?? false))
                                            <a href="{{ asset('storage/' . $c->imagen) }}"
                                               class="btn btn-sm btn-outline-azul"
                                               download>
                                                Descargar
                                            </a>
                                        @else
                                            <span class="muted">-</span>
                                        @endif
                                    </td>
                                    <td class="action-col">
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
                                    <td colspan="8" class="text-center py-5">
                                        <div class="fw-bold text-color-main">No hay contratos entregados</div>
                                        <div class="muted">No existen registros en estado ENTREGADO para tu empresa.</div>
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
            --bg: #f5f7fb;
            --muted: #6b7280;
        }

        .plantilla-wrap {
            background: var(--bg);
            padding: 18px;
            border-radius: 16px;
        }

        .card-app {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 12px 26px rgba(0, 0, 0, .08);
            overflow: hidden;
        }

        .header-app {
            background: linear-gradient(90deg, var(--azul), #20539A);
            color: #fff;
            padding: 18px 20px;
        }

        .header-meta {
            margin-top: .35rem;
            color: rgba(255, 255, 255, .82);
            font-size: .88rem;
        }

        .table thead th {
            background: rgba(52, 68, 124, .08);
            color: var(--azul);
            font-weight: 900;
            border-bottom: 2px solid rgba(52, 68, 124, .2);
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .pill-id {
            background: rgba(52, 68, 124, .12);
            color: var(--azul);
            font-weight: 900;
            padding: 4px 10px;
            border-radius: 999px;
            display: inline-block;
        }

        .btn-outline-azul {
            border: 1px solid rgba(52, 68, 124, .35);
            color: var(--azul);
            font-weight: 800;
            border-radius: 12px;
            padding: 8px 12px;
            background: #fff;
        }

        .btn-outline-azul:hover {
            background: rgba(52, 68, 124, .06);
            color: var(--azul);
        }

        .action-col {
            width: 92px;
            min-width: 92px;
            text-align: center;
        }

        .action-btn {
            width: 48px;
            height: 48px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            box-shadow: 0 8px 18px rgba(32, 83, 154, .10);
        }

        .action-btn i {
            font-size: 16px;
        }

        .muted {
            color: var(--muted);
        }

        .text-color-main {
            color: var(--azul);
        }
    </style>
@endsection

