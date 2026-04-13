@extends('adminlte::page')

@section('title', 'Mis ventas')

@section('content_header')
    <div class="ventas-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
        <div>
            <h1 class="mb-1">Mis ventas</h1>
            <p class="text-muted mb-0">Consulta tu historial de emisiones, revisa estados y accede a los comprobantes sin salir del flujo operativo.</p>
        </div>
        <div class="ventas-header-badges mt-3 mt-lg-0">
            <span class="ventas-mini-badge">Ventas: {{ number_format($summary['totalVentas']) }}</span>
            <span class="ventas-mini-badge ventas-mini-badge--success">Facturadas: {{ number_format($summary['facturadas']) }}</span>
            <span class="ventas-mini-badge ventas-mini-badge--warning">Pendientes: {{ number_format($summary['pendientes']) }}</span>
        </div>
    </div>
@stop

@section('content')
    <div class="ventas-summary-grid">
        <section class="ventas-stat-card">
            <div class="ventas-stat-card__label">Ventas emitidas</div>
            <div class="ventas-stat-card__value">{{ number_format($summary['totalVentas']) }}</div>
            <div class="ventas-stat-card__meta">Registros cerrados y enviados a facturación</div>
        </section>
        <section class="ventas-stat-card">
            <div class="ventas-stat-card__label">Facturadas</div>
            <div class="ventas-stat-card__value">{{ number_format($summary['facturadas']) }}</div>
            <div class="ventas-stat-card__meta">Ventas con comprobante listo para entrega</div>
        </section>
        <section class="ventas-stat-card">
            <div class="ventas-stat-card__label">Pendientes</div>
            <div class="ventas-stat-card__value">{{ number_format($summary['pendientes']) }}</div>
            <div class="ventas-stat-card__meta">Emisiones en proceso o esperando actualización</div>
        </section>
        <section class="ventas-stat-card">
            <div class="ventas-stat-card__label">Rechazadas</div>
            <div class="ventas-stat-card__value">{{ number_format($summary['rechazadas']) }}</div>
            <div class="ventas-stat-card__meta">Ventas que requieren revisión antes de reenviar</div>
        </section>
        <section class="ventas-stat-card">
            <div class="ventas-stat-card__label">Borradores</div>
            <div class="ventas-stat-card__value">{{ number_format($summary['totalBorradores']) }}</div>
            <div class="ventas-stat-card__meta">Carritos guardados y aún no emitidos</div>
        </section>
        <section class="ventas-stat-card ventas-stat-card--accent">
            <div class="ventas-stat-card__label">Total vendido</div>
            <div class="ventas-stat-card__value">{{ number_format($summary['montoTotal'], 2) }}</div>
            <div class="ventas-stat-card__meta">Monto acumulado de las ventas emitidas</div>
        </section>
    </div>

    <div class="card ventas-panel mb-4">
        <div class="card-header ventas-panel__header">
            <div>
                <strong>Filtros de consulta</strong>
                <div class="text-muted small">Ajusta criterios para encontrar ventas por código, cliente, fecha o estado.</div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('mis-ventas.index') }}">
                <div class="row">
                    <div class="col-xl-4 col-lg-6 mb-3">
                        <label class="ventas-label">Buscar</label>
                        <input type="text" class="form-control ventas-control" name="q" value="{{ $filters['q'] }}" placeholder="Código, seguimiento, cliente o mensaje">
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 mb-3">
                        <label class="ventas-label">Estado</label>
                        <select class="form-control ventas-control" name="estado">
                            <option value="all" {{ $filters['estado'] === 'all' ? 'selected' : '' }}>Todos</option>
                            <option value="emitido" {{ $filters['estado'] === 'emitido' ? 'selected' : '' }}>Emitido</option>
                            <option value="borrador" {{ $filters['estado'] === 'borrador' ? 'selected' : '' }}>Borrador</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 mb-3">
                        <label class="ventas-label">Estado emisión</label>
                        <select class="form-control ventas-control" name="estado_emision">
                            <option value="all" {{ $filters['estado_emision'] === 'all' ? 'selected' : '' }}>Todos</option>
                            <option value="FACTURADA" {{ $filters['estado_emision'] === 'FACTURADA' ? 'selected' : '' }}>Facturada</option>
                            <option value="PENDIENTE" {{ $filters['estado_emision'] === 'PENDIENTE' ? 'selected' : '' }}>Pendiente</option>
                            <option value="RECHAZADA" {{ $filters['estado_emision'] === 'RECHAZADA' ? 'selected' : '' }}>Rechazada</option>
                            <option value="ERROR" {{ $filters['estado_emision'] === 'ERROR' ? 'selected' : '' }}>Error</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 mb-3">
                        <label class="ventas-label">Desde</label>
                        <input type="date" class="form-control ventas-control" name="from" value="{{ $filters['from'] }}">
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 mb-3">
                        <label class="ventas-label">Hasta</label>
                        <input type="date" class="form-control ventas-control" name="to" value="{{ $filters['to'] }}">
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 mb-3">
                        <label class="ventas-label">Registros</label>
                        <select class="form-control ventas-control" name="per_page">
                            <option value="10" {{ (int) $filters['per_page'] === 10 ? 'selected' : '' }}>10</option>
                            <option value="20" {{ (int) $filters['per_page'] === 20 ? 'selected' : '' }}>20</option>
                            <option value="50" {{ (int) $filters['per_page'] === 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ (int) $filters['per_page'] === 100 ? 'selected' : '' }}>100</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex flex-wrap">
                    <button type="submit" class="btn btn-primary mr-2 mb-2">
                        <i class="fas fa-search mr-1"></i> Filtrar
                    </button>
                    <a href="{{ route('mis-ventas.index') }}" class="btn btn-outline-secondary mb-2">
                        <i class="fas fa-undo mr-1"></i> Reiniciar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card ventas-panel">
        <div class="card-header ventas-panel__header d-flex justify-content-between align-items-center">
            <div>
                <strong>Historial de ventas</strong>
                <div class="text-muted small">Detalle de emisiones registradas para tu cuenta.</div>
            </div>
            <span class="ventas-table-count">{{ $carts->total() }} registros</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table ventas-table mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th class="text-center">Items</th>
                            <th class="text-right">Total</th>
                            <th>Estado</th>
                            <th>Facturación</th>
                            <th>Seguimiento</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($carts as $cart)
                            @php
                                $respuesta = (array) ($cart->respuesta_emision ?? []);
                                $pdfUrl = trim((string) data_get($respuesta, 'factura.pdfUrl', ''));
                                $facturaEstado = strtoupper((string) ($cart->estado_emision ?? ''));
                                $mensajeEmision = trim((string) ($cart->mensaje_emision ?? ''));
                            @endphp
                            <tr>
                                <td>
                                    <div class="ventas-table__primary">{{ optional($cart->emitido_en ?? $cart->created_at)->format('d/m/Y') }}</div>
                                    <div class="ventas-table__secondary">{{ optional($cart->emitido_en ?? $cart->created_at)->format('H:i') }}</div>
                                </td>
                                <td>
                                    <div class="ventas-table__primary">{{ $cart->codigo_orden ?: 'Sin código' }}</div>
                                    <div class="ventas-table__secondary">Doc: {{ $cart->numero_documento ?: 'S/N' }}</div>
                                </td>
                                <td>
                                    <div class="ventas-table__primary">{{ $cart->razon_social ?: 'SIN NOMBRE' }}</div>
                                    <div class="ventas-table__secondary">{{ ucfirst(str_replace('_', ' ', $cart->modalidad_facturacion ?? 'con_datos')) }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="ventas-count-pill">{{ $cart->items->count() }}</span>
                                </td>
                                <td class="text-right">
                                    <div class="ventas-table__amount">{{ number_format((float) $cart->total, 2) }}</div>
                                </td>
                                <td>
                                    <span class="ventas-status-chip {{ $cart->estado === 'emitido' ? 'ventas-status-chip--primary' : 'ventas-status-chip--muted' }}">
                                        {{ strtoupper($cart->estado) }}
                                    </span>
                                </td>
                                <td>
                                    @if($facturaEstado === 'FACTURADA')
                                        <span class="ventas-status-chip ventas-status-chip--success">FACTURADA</span>
                                    @elseif($facturaEstado === 'PENDIENTE')
                                        <span class="ventas-status-chip ventas-status-chip--warning">PENDIENTE</span>
                                    @elseif($facturaEstado === 'RECHAZADA')
                                        <span class="ventas-status-chip ventas-status-chip--danger">RECHAZADA</span>
                                    @elseif($facturaEstado === 'ERROR')
                                        <span class="ventas-status-chip ventas-status-chip--dark">ERROR</span>
                                    @else
                                        <span class="ventas-status-chip ventas-status-chip--muted">{{ $facturaEstado !== '' ? $facturaEstado : 'SIN ESTADO' }}</span>
                                    @endif
                                    <div class="ventas-table__secondary mt-1">
                                        {{ $mensajeEmision !== '' ? \Illuminate\Support\Str::limit($mensajeEmision, 65) : 'Sin observaciones registradas.' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="ventas-table__primary">{{ $cart->codigo_seguimiento ?: 'Sin seguimiento' }}</div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex flex-wrap justify-content-center">
                                        @if($cart->codigo_seguimiento)
                                            <form method="POST" action="{{ route('facturacion.cart.consultar') }}" class="mr-2 mb-2">
                                                @csrf
                                                <input type="hidden" name="cart_id" value="{{ $cart->id }}">
                                                <button type="submit" class="btn btn-xs btn-outline-secondary">Consultar</button>
                                            </form>
                                        @endif
                                        @if($pdfUrl !== '')
                                            <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary mb-2">PDF</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">No se encontraron ventas con los filtros aplicados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($carts->hasPages())
            <div class="card-footer clearfix">
                {{ $carts->links() }}
            </div>
        @endif
    </div>
@stop

@section('css')
    <style>
        .ventas-header {
            gap: 1rem;
        }

        .ventas-header h1 {
            font-weight: 700;
            color: #173b73;
        }

        .ventas-header-badges {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .ventas-mini-badge {
            display: inline-flex;
            align-items: center;
            padding: .45rem .8rem;
            border-radius: 999px;
            background: rgba(254, 204, 54, 0.18);
            color: #173b73;
            font-size: .82rem;
            font-weight: 700;
        }

        .ventas-mini-badge--success {
            background: rgba(40, 167, 69, 0.14);
            color: #1f7a35;
        }

        .ventas-mini-badge--warning {
            background: rgba(255, 193, 7, 0.18);
            color: #9a6b00;
        }

        .ventas-summary-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .ventas-stat-card {
            background: #fff;
            border: 1px solid rgba(32, 83, 154, 0.12);
            border-top: 4px solid #fecc36;
            border-radius: 12px;
            padding: 1rem 1.05rem;
            box-shadow: 0 8px 24px rgba(16, 43, 84, 0.06);
            min-height: 142px;
        }

        .ventas-stat-card--accent {
            border-top-color: #20539a;
            background: linear-gradient(180deg, #ffffff 0%, #f6f9ff 100%);
        }

        .ventas-stat-card__label {
            color: #5f7290;
            font-size: .82rem;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
            margin-bottom: .65rem;
        }

        .ventas-stat-card__value {
            color: #173b73;
            font-size: 2rem;
            line-height: 1;
            font-weight: 800;
            margin-bottom: .7rem;
        }

        .ventas-stat-card__meta {
            color: #74839b;
            font-size: .82rem;
            line-height: 1.35;
        }

        .ventas-panel {
            border: 1px solid rgba(32, 83, 154, 0.12);
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(16, 43, 84, 0.05);
            overflow: hidden;
        }

        .ventas-panel__header {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-bottom: 1px solid rgba(32, 83, 154, 0.1);
            padding: 1rem 1.25rem;
        }

        .ventas-label {
            color: #294b7c;
            font-size: .82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .02em;
        }

        .ventas-control {
            min-height: 44px;
            border-radius: 10px;
            border-color: rgba(32, 83, 154, 0.18);
            box-shadow: none;
        }

        .ventas-control:focus {
            border-color: rgba(32, 83, 154, 0.42);
            box-shadow: 0 0 0 .18rem rgba(32, 83, 154, 0.09);
        }

        .ventas-table-count {
            color: #6f819d;
            font-size: .86rem;
            font-weight: 600;
        }

        .ventas-table thead th {
            background: #f7f9fc;
            border-bottom: 1px solid rgba(32, 83, 154, 0.1);
            color: #38557f;
            font-size: .79rem;
            font-weight: 800;
            letter-spacing: .02em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .ventas-table td {
            vertical-align: top;
            border-top: 1px solid rgba(32, 83, 154, 0.08);
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        .ventas-table__primary {
            color: #173b73;
            font-weight: 700;
        }

        .ventas-table__secondary {
            color: #74839b;
            font-size: .83rem;
            margin-top: .25rem;
        }

        .ventas-table__amount {
            color: #0f2f61;
            font-weight: 800;
            font-size: 1.05rem;
        }

        .ventas-count-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 .6rem;
            border-radius: 999px;
            background: rgba(32, 83, 154, 0.08);
            color: #20539a;
            font-weight: 800;
        }

        .ventas-status-chip {
            display: inline-flex;
            align-items: center;
            padding: .28rem .58rem;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .02em;
            border: 1px solid transparent;
        }

        .ventas-status-chip--primary {
            background: rgba(254, 204, 54, 0.18);
            color: #7c5b00;
            border-color: rgba(254, 204, 54, 0.45);
        }

        .ventas-status-chip--success {
            background: rgba(40, 167, 69, 0.14);
            color: #1f7a35;
            border-color: rgba(40, 167, 69, 0.28);
        }

        .ventas-status-chip--warning {
            background: rgba(255, 193, 7, 0.18);
            color: #9a6b00;
            border-color: rgba(255, 193, 7, 0.3);
        }

        .ventas-status-chip--danger {
            background: rgba(220, 53, 69, 0.12);
            color: #b02a37;
            border-color: rgba(220, 53, 69, 0.24);
        }

        .ventas-status-chip--dark {
            background: rgba(52, 58, 64, 0.1);
            color: #343a40;
            border-color: rgba(52, 58, 64, 0.2);
        }

        .ventas-status-chip--muted {
            background: rgba(108, 117, 125, 0.1);
            color: #66707a;
            border-color: rgba(108, 117, 125, 0.22);
        }

        @media (max-width: 1399.98px) {
            .ventas-summary-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .ventas-summary-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr));
            }

            .ventas-stat-card {
                min-height: auto;
            }

            .ventas-header-badges {
                width: 100%;
            }
        }
    </style>
@stop
