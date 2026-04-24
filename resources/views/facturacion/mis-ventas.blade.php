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
    @php
        $facturacionFeedback = session('facturacion_feedback');
        $cajaEstado = strtoupper(trim((string) data_get($cajaContext ?? [], 'estado', 'SIN_APERTURA')));
        $isCajaAbierta = in_array($cajaEstado, ['ABIERTA', 'ABIERTO'], true);
        $cajaMensaje = trim((string) data_get($cajaContext ?? [], 'mensaje', ''));
        $arqueosMes = (string) data_get($arqueosContext ?? [], 'mes', ($filters['arqueo_mes'] ?? now()->format('Y-m')));
        $arqueosError = trim((string) data_get($arqueosContext ?? [], 'error', ''));
        $arqueosResumen = (array) data_get($arqueosContext ?? [], 'resumen', []);
        $arqueosDias = data_get($arqueosContext ?? [], 'dias', collect());
        $arqueosDias = $arqueosDias instanceof \Illuminate\Support\Collection
            ? $arqueosDias
            : collect(is_array($arqueosDias) ? $arqueosDias : []);
        $baseFilterParams = [
            'q' => $filters['q'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'per_page' => $filters['per_page'],
            'arqueo_mes' => $filters['arqueo_mes'] ?? now()->format('Y-m'),
        ];

        $summaryCards = [
            [
                'label' => 'Ventas emitidas',
                'value' => number_format($summary['totalVentas']),
                'meta' => 'Registros cerrados y enviados a facturación',
                'params' => array_merge($baseFilterParams, ['estado' => 'emitido', 'estado_emision' => 'all']),
                'active' => $filters['estado'] === 'emitido' && $filters['estado_emision'] === 'all',
                'accent' => false,
            ],
            [
                'label' => 'Facturadas',
                'value' => number_format($summary['facturadas']),
                'meta' => 'Ventas con comprobante listo para entrega',
                'params' => array_merge($baseFilterParams, ['estado' => 'emitido', 'estado_emision' => 'FACTURADA']),
                'active' => $filters['estado'] === 'emitido' && $filters['estado_emision'] === 'FACTURADA',
                'accent' => false,
            ],
            [
                'label' => 'Pendientes',
                'value' => number_format($summary['pendientes']),
                'meta' => 'Emisiones en proceso o esperando actualización',
                'params' => array_merge($baseFilterParams, ['estado' => 'emitido', 'estado_emision' => 'PENDIENTE']),
                'active' => $filters['estado'] === 'emitido' && $filters['estado_emision'] === 'PENDIENTE',
                'accent' => false,
            ],
            [
                'label' => 'Rechazadas',
                'value' => number_format($summary['rechazadas']),
                'meta' => 'Ventas que requieren revisión antes de reenviar',
                'params' => array_merge($baseFilterParams, ['estado' => 'emitido', 'estado_emision' => 'RECHAZADA']),
                'active' => $filters['estado'] === 'emitido' && $filters['estado_emision'] === 'RECHAZADA',
                'accent' => false,
            ],
            [
                'label' => 'Borradores',
                'value' => number_format($summary['totalBorradores']),
                'meta' => 'Carritos guardados y aún no emitidos',
                'params' => array_merge($baseFilterParams, ['estado' => 'borrador', 'estado_emision' => 'all']),
                'active' => $filters['estado'] === 'borrador' && $filters['estado_emision'] === 'all',
                'accent' => false,
            ],
            [
                'label' => 'Total vendido',
                'value' => 'Bs ' . number_format($summary['montoTotal'], 2),
                'meta' => 'Monto acumulado de las ventas emitidas',
                'params' => array_merge($baseFilterParams, ['estado' => 'emitido', 'estado_emision' => 'all']),
                'active' => $filters['estado'] === 'emitido' && $filters['estado_emision'] === 'all',
                'accent' => true,
            ],
        ];
    @endphp

    @if (is_array($facturacionFeedback) && in_array((string) ($facturacionFeedback['action'] ?? ''), ['caja_abrir', 'caja_cerrar'], true))
        <div class="alert ventas-feedback-alert ventas-feedback-alert--{{ $facturacionFeedback['type'] ?? 'info' }}">
            <strong>{{ $facturacionFeedback['title'] ?? 'Caja diaria' }}</strong>
            <div>{{ $facturacionFeedback['message'] ?? '' }}</div>
            @if (!empty($facturacionFeedback['detail']))
                <small>{{ $facturacionFeedback['detail'] }}</small>
            @endif
        </div>
    @endif

    <div class="card ventas-panel mb-4">
        <div class="card-header ventas-panel__header">
            <div>
                <strong>Filtros de consulta</strong>
                <div class="text-muted small">Ajusta criterios para encontrar ventas por código, cliente, fecha o estado.</div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('mis-ventas.index') }}" id="ventasFiltersForm">
                <div class="row">
                    <div class="col-xl-4 col-lg-6 mb-3">
                        <label class="ventas-label">Buscar</label>
                        <input type="text" class="form-control ventas-control" name="q" value="{{ $filters['q'] }}" placeholder="Código, seguimiento, cliente o mensaje" data-auto-submit="search">
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 mb-3">
                        <label class="ventas-label">Estado</label>
                        <select class="form-control ventas-control" name="estado" data-auto-submit="change">
                            <option value="all" {{ $filters['estado'] === 'all' ? 'selected' : '' }}>Todos</option>
                            <option value="emitido" {{ $filters['estado'] === 'emitido' ? 'selected' : '' }}>Emitido</option>
                            <option value="borrador" {{ $filters['estado'] === 'borrador' ? 'selected' : '' }}>Borrador</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 mb-3">
                        <label class="ventas-label">Estado emisión</label>
                        <select class="form-control ventas-control" name="estado_emision" data-auto-submit="change">
                            <option value="all" {{ $filters['estado_emision'] === 'all' ? 'selected' : '' }}>Todos</option>
                            <option value="FACTURADA" {{ $filters['estado_emision'] === 'FACTURADA' ? 'selected' : '' }}>Facturada</option>
                            <option value="PENDIENTE" {{ $filters['estado_emision'] === 'PENDIENTE' ? 'selected' : '' }}>Pendiente</option>
                            <option value="RECHAZADA" {{ $filters['estado_emision'] === 'RECHAZADA' ? 'selected' : '' }}>Rechazada</option>
                            <option value="ERROR" {{ $filters['estado_emision'] === 'ERROR' ? 'selected' : '' }}>Error</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 mb-3">
                        <label class="ventas-label">Desde</label>
                        <input type="date" class="form-control ventas-control" name="from" value="{{ $filters['from'] }}" data-auto-submit="change">
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 mb-3">
                        <label class="ventas-label">Hasta</label>
                        <input type="date" class="form-control ventas-control" name="to" value="{{ $filters['to'] }}" data-auto-submit="change">
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 mb-3">
                        <label class="ventas-label">Registros</label>
                        <select class="form-control ventas-control" name="per_page" data-auto-submit="change">
                            <option value="10" {{ (int) $filters['per_page'] === 10 ? 'selected' : '' }}>10</option>
                            <option value="20" {{ (int) $filters['per_page'] === 20 ? 'selected' : '' }}>20</option>
                            <option value="50" {{ (int) $filters['per_page'] === 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ (int) $filters['per_page'] === 100 ? 'selected' : '' }}>100</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex flex-wrap">
                    <a href="{{ route('mis-ventas.export.pdf', request()->query()) }}" target="_blank" rel="noopener" class="btn btn-outline-primary mr-2 mb-2">
                        <i class="fas fa-file-pdf mr-1"></i> Reporte PDF
                    </a>
                    <a href="{{ route('mis-ventas.index') }}" class="btn btn-outline-secondary mb-2">
                        <i class="fas fa-undo mr-1"></i> Reiniciar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="ventas-caja-card">
        <div class="ventas-caja-card__left">
            <div class="ventas-caja-card__label">Caja diaria</div>
            <div class="ventas-caja-card__state">
                Estado:
                <span class="ventas-caja-badge {{ $isCajaAbierta ? 'is-open' : 'is-closed' }}">
                    {{ $isCajaAbierta ? 'Caja abierta' : 'Sin apertura' }}
                </span>
            </div>
            <div class="ventas-caja-card__hint">
                {{ $cajaMensaje !== '' ? $cajaMensaje : ($isCajaAbierta ? 'Cierra caja al finalizar tu jornada.' : 'Abre caja para habilitar emisión en facturación.') }}
            </div>
        </div>
        <div class="ventas-caja-card__right">
            @if($isCajaAbierta)
                <form method="POST" action="{{ route('facturacion.cart.caja.cerrar') }}" onsubmit="return confirm('Se cerrará la caja diaria. ¿Deseas continuar?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="fas fa-door-closed mr-1"></i> Cerrar caja
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('facturacion.cart.caja.abrir') }}" onsubmit="return confirm('Se abrirá una nueva caja diaria. ¿Deseas continuar?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-lock-open mr-1"></i> Abrir caja
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="card ventas-panel ventas-arqueo-card mb-4">
        <div class="card-header ventas-panel__header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <strong>Arqueos del mes</strong>
                <div class="text-muted small">Control diario de cierres de caja y conciliación de ventas.</div>
            </div>
            <form method="GET" action="{{ route('mis-ventas.index') }}" class="ventas-arqueo-filter">
                <input type="hidden" name="q" value="{{ $filters['q'] }}">
                <input type="hidden" name="estado" value="{{ $filters['estado'] }}">
                <input type="hidden" name="estado_emision" value="{{ $filters['estado_emision'] }}">
                <input type="hidden" name="from" value="{{ $filters['from'] }}">
                <input type="hidden" name="to" value="{{ $filters['to'] }}">
                <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}">
                <input type="month" name="arqueo_mes" class="form-control ventas-control" value="{{ $arqueosMes }}" onchange="this.form.submit()">
            </form>
        </div>
        <div class="card-body">
            @if ($arqueosError !== '')
                <div class="alert ventas-feedback-alert ventas-feedback-alert--warning mb-3">
                    {{ $arqueosError }}
                </div>
            @endif

            <div class="ventas-arqueo-resumen">
                <div class="ventas-arqueo-pill">
                    <span>Días arqueados</span>
                    <strong>{{ number_format((int) ($arqueosResumen['dias'] ?? 0)) }}</strong>
                </div>
                <div class="ventas-arqueo-pill">
                    <span>Ventas conciliadas</span>
                    <strong>{{ number_format((int) ($arqueosResumen['cantidadVentas'] ?? 0)) }}</strong>
                </div>
                <div class="ventas-arqueo-pill">
                    <span>Total arqueado</span>
                    <strong>Bs {{ number_format((float) ($arqueosResumen['montoTotal'] ?? 0), 2) }}</strong>
                </div>
                <div class="ventas-arqueo-pill {{ ((float) ($arqueosResumen['diferencia'] ?? 0)) == 0.0 ? 'is-ok' : 'is-alert' }}">
                    <span>Diferencia</span>
                    <strong>Bs {{ number_format((float) ($arqueosResumen['diferencia'] ?? 0), 2) }}</strong>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table ventas-table ventas-arqueo-table mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th class="text-center">Arqueos</th>
                            <th class="text-center">Ventas</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Declarado</th>
                            <th class="text-right">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($arqueosDias as $dia)
                            @php
                                $dia = is_array($dia) ? (object) $dia : $dia;
                                $fechaDia = trim((string) data_get($dia, 'fecha', ''));
                                $fechaDiaLabel = '-';
                                if ($fechaDia !== '') {
                                    try {
                                        $fechaDiaLabel = \Carbon\Carbon::parse($fechaDia)->format('d/m/Y');
                                    } catch (\Throwable $e) {
                                        $fechaDiaLabel = $fechaDia;
                                    }
                                }
                                $cantidadArqueos = is_countable(data_get($dia, 'arqueos', [])) ? count(data_get($dia, 'arqueos', [])) : 0;
                                $cantidadVentasDia = (int) data_get($dia, 'cantidadVentas', 0);
                                $montoTotalDia = (float) data_get($dia, 'montoTotal', 0);
                                $montoDeclaradoDia = (float) data_get($dia, 'montoDeclarado', 0);
                                $diferenciaDia = (float) data_get($dia, 'diferencia', 0);
                            @endphp
                            <tr>
                                <td>
                                    <div class="ventas-table__primary">{{ $fechaDiaLabel }}</div>
                                    <div class="ventas-table__secondary">{{ $fechaDia !== '' ? $fechaDia : 'Sin fecha' }}</div>
                                </td>
                                <td class="text-center">{{ $cantidadArqueos }}</td>
                                <td class="text-center">{{ $cantidadVentasDia }}</td>
                                <td class="text-right">Bs {{ number_format($montoTotalDia, 2) }}</td>
                                <td class="text-right">Bs {{ number_format($montoDeclaradoDia, 2) }}</td>
                                <td class="text-right">
                                    <span class="ventas-status-chip {{ $diferenciaDia == 0.0 ? 'ventas-status-chip--success' : 'ventas-status-chip--danger' }}">
                                        Bs {{ number_format($diferenciaDia, 2) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No hay cierres arqueados para este mes.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="ventas-summary-grid">
        @foreach($summaryCards as $card)
            <a
                href="{{ route('mis-ventas.index', $card['params']) }}"
                class="ventas-stat-card {{ $card['accent'] ? 'ventas-stat-card--accent' : '' }} {{ $card['active'] ? 'ventas-stat-card--active' : '' }}"
            >
                <div class="ventas-stat-card__label">{{ $card['label'] }}</div>
                <div class="ventas-stat-card__value">{{ $card['value'] }}</div>
                <div class="ventas-stat-card__meta">{{ $card['meta'] }}</div>
            </a>
        @endforeach
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
                            <th>Código orden</th>
                            <th>Cliente</th>
                            <th>Facturación</th>
                            <th>Estado</th>
                            <th class="text-center">Items</th>
                            <th class="text-right">Total</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($carts as $cart)
                            @php
                                $cart = is_array($cart) ? (object) $cart : $cart;
                                $rawCartItems = data_get($cart, 'items', []);
                                $cartItems = ($rawCartItems instanceof \Illuminate\Support\Collection
                                    ? $rawCartItems
                                    : (is_array($rawCartItems) ? collect($rawCartItems) : collect()))
                                    ->map(fn ($item) => is_array($item) ? (object) $item : $item)
                                    ->filter(fn ($item) => is_object($item))
                                    ->values();

                                $respuesta = (array) data_get($cart, 'respuesta_emision', []);
                                $pdfUrl = trim((string) data_get($respuesta, 'factura.pdfUrl', ''));
                                $numeroFactura = trim((string) (
                                    data_get($respuesta, 'factura.nroFactura')
                                    ?? data_get($respuesta, 'factura.numeroFactura')
                                    ?? data_get($respuesta, 'nroFactura')
                                    ?? data_get($respuesta, 'numeroFactura')
                                    ?? data_get($respuesta, 'consultaSefe.nroFactura')
                                ));
                                $facturaEstado = strtoupper((string) data_get($cart, 'estado_emision', ''));
                                $mensajeEmision = trim((string) data_get($cart, 'mensaje_emision', ''));
                                $cartId = (int) data_get($cart, 'id', 0);
                                $codigoOrden = trim((string) data_get($cart, 'codigo_orden', ''));
                                $numeroDocumento = trim((string) data_get($cart, 'numero_documento', ''));
                                $razonSocial = trim((string) data_get($cart, 'razon_social', ''));
                                $modalidadFacturacion = (string) data_get($cart, 'modalidad_facturacion', 'con_datos');
                                $estadoCart = (string) data_get($cart, 'estado', '');
                                $codigoSeguimiento = trim((string) data_get($cart, 'codigo_seguimiento', ''));
                                $totalCart = (float) data_get($cart, 'total', 0);
                                $itemsCountApi = (int) data_get($cart, 'items_count', 0);
                                $fechaRaw = data_get($cart, 'emitido_en') ?: data_get($cart, 'created_at');
                                $fecha = null;

                                if ($fechaRaw instanceof \Carbon\CarbonInterface) {
                                    $fecha = $fechaRaw;
                                } elseif (!empty($fechaRaw)) {
                                    try {
                                        $fecha = \Carbon\Carbon::parse((string) $fechaRaw);
                                    } catch (\Throwable $e) {
                                        $fecha = null;
                                    }
                                }
                            @endphp
                            <tr>
                                <td>
                                    <div class="ventas-table__primary">{{ $fecha ? $fecha->format('d/m/Y') : '-' }}</div>
                                    <div class="ventas-table__secondary">{{ $fecha ? $fecha->format('H:i') : '-' }}</div>
                                </td>
                                <td>
                                    <div class="ventas-table__primary">{{ $codigoOrden !== '' ? $codigoOrden : 'Sin código' }}</div>
                                    <div class="ventas-table__secondary">Doc: {{ $numeroDocumento !== '' ? $numeroDocumento : 'S/N' }} · Fact: {{ $numeroFactura !== '' ? $numeroFactura : 'S/N' }}</div>
                                </td>
                                <td>
                                    <div class="ventas-table__primary">{{ $razonSocial !== '' ? $razonSocial : 'SIN NOMBRE' }}</div>
                                    <div class="ventas-table__secondary">{{ ucfirst(str_replace('_', ' ', $modalidadFacturacion)) }}</div>
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
                                    @if($facturaEstado === 'PENDIENTE')
                                        <div class="ventas-table__secondary ventas-table__secondary--hint">
                                            Si fue por contingencia, usa actualizar estado hasta que llegue la factura.
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="ventas-status-chip {{ $estadoCart === 'emitido' ? 'ventas-status-chip--primary' : 'ventas-status-chip--muted' }}">
                                        {{ strtoupper($estadoCart !== '' ? $estadoCart : 'sin estado') }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($cartItems->isNotEmpty())
                                        <button
                                            type="button"
                                            class="ventas-count-pill ventas-count-pill--button"
                                            data-toggle="modal"
                                            data-target="#ventasItemsModal-{{ $cartId }}"
                                            aria-label="Ver {{ $cartItems->count() }} items de la venta {{ $codigoOrden !== '' ? $codigoOrden : $cartId }}"
                                        >
                                            {{ $cartItems->count() }}
                                        </button>
                                    @else
                                        <span class="ventas-count-pill">{{ $itemsCountApi > 0 ? $itemsCountApi : 0 }}</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="ventas-table__amount">Bs {{ number_format($totalCart, 2) }}</div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex flex-wrap justify-content-center">
                                        @if($codigoSeguimiento !== '')
                                            <form
                                                method="POST"
                                                action="{{ route('facturacion.cart.consultar') }}"
                                                class="mr-2 mb-2"
                                                @if($facturaEstado === 'PENDIENTE')
                                                    data-pending-consult="true"
                                                @endif
                                            >
                                                @csrf
                                                <input type="hidden" name="cart_id" value="{{ $cartId }}">
                                                <input type="hidden" name="codigo_seguimiento" value="{{ $codigoSeguimiento }}">
                                                <button type="submit" class="btn btn-xs btn-outline-secondary">
                                                    {{ $facturaEstado === 'PENDIENTE' ? 'Actualizar estado' : 'Consultar' }}
                                                </button>
                                            </form>
                                        @endif
                                        @if($pdfUrl !== '')
                                            <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary mr-2 mb-2">PDF original</a>
                                        @endif
                                        <a href="{{ route('mis-ventas.ticket', $cartId) }}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-dark mb-2">Ticket</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">No se encontraron ventas con los filtros aplicados.</td>
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

    @foreach($carts as $cart)
        @php
            $cart = is_array($cart) ? (object) $cart : $cart;
            $rawCartItems = data_get($cart, 'items', []);
            $cartItems = ($rawCartItems instanceof \Illuminate\Support\Collection
                ? $rawCartItems
                : (is_array($rawCartItems) ? collect($rawCartItems) : collect()))
                ->map(fn ($item) => is_array($item) ? (object) $item : $item)
                ->filter(fn ($item) => is_object($item))
                ->values();
            $cartId = (int) data_get($cart, 'id', 0);
            $codigoOrden = trim((string) data_get($cart, 'codigo_orden', ''));
            $totalCart = (float) data_get($cart, 'total', 0);
        @endphp
        @if($cartItems->isNotEmpty())
            <div class="modal fade ventas-items-modal" id="ventasItemsModal-{{ $cartId }}" tabindex="-1" role="dialog" aria-labelledby="ventasItemsModalLabel-{{ $cartId }}" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                    <div class="modal-content">
                        <div class="modal-header ventas-items-modal__header">
                            <div>
                                <h5 class="modal-title" id="ventasItemsModalLabel-{{ $cartId }}">Detalle de items</h5>
                                <div class="ventas-items-modal__subtitle">
                                    Venta {{ $codigoOrden !== '' ? $codigoOrden : ('#' . $cartId) }} · {{ $cartItems->count() }} item(s)
                                </div>
                            </div>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body p-0">
                            <div class="table-responsive">
                                <table class="table ventas-items-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Detalle</th>
                                            <th class="text-center">Cant.</th>
                                            <th class="text-right">Base</th>
                                            <th class="text-right">Extras</th>
                                            <th class="text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($cartItems as $item)
                                            @php
                                                $item = is_array($item) ? (object) $item : $item;
                                                $itemId = (int) data_get($item, 'id', 0);
                                                $itemCodigo = trim((string) data_get($item, 'codigo', ''));
                                                $itemOrigenTipo = trim((string) data_get($item, 'origen_tipo', ''));
                                                $itemTitulo = trim((string) data_get($item, 'titulo', ''));
                                                $itemServicio = trim((string) data_get($item, 'nombre_servicio', ''));
                                                $itemDestinatario = trim((string) data_get($item, 'nombre_destinatario', ''));
                                                $itemCantidad = (int) data_get($item, 'cantidad', 0);
                                                $itemMontoBase = (float) data_get($item, 'monto_base', 0);
                                                $itemMontoExtras = (float) data_get($item, 'monto_extras', 0);
                                                $itemTotalLinea = (float) data_get($item, 'total_linea', 0);
                                            @endphp
                                            <tr>
                                                <td>
                                                    <div class="ventas-table__primary">{{ $itemCodigo !== '' ? $itemCodigo : ('Item #' . $itemId) }}</div>
                                                    <div class="ventas-table__secondary">{{ $itemOrigenTipo !== '' ? $itemOrigenTipo : 'Sin origen' }}</div>
                                                </td>
                                                <td>
                                                    <div class="ventas-table__primary">{{ $itemTitulo !== '' ? $itemTitulo : 'Sin título' }}</div>
                                                    <div class="ventas-table__secondary">{{ $itemServicio !== '' ? $itemServicio : 'Sin servicio registrado' }}</div>
                                                    <div class="ventas-table__secondary">{{ $itemDestinatario !== '' ? $itemDestinatario : 'Sin destinatario' }}</div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="ventas-items-table__qty">{{ $itemCantidad }}</span>
                                                </td>
                                                <td class="text-right">Bs {{ number_format($itemMontoBase, 2) }}</td>
                                                <td class="text-right">Bs {{ number_format($itemMontoExtras, 2) }}</td>
                                                <td class="text-right ventas-items-table__total">Bs {{ number_format($itemTotalLinea, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer ventas-items-modal__footer">
                            <div class="ventas-items-modal__summary">
                                Total venta: Bs {{ number_format($totalCart, 2) }}
                            </div>
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
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

        .ventas-feedback-alert {
            border-radius: 12px;
            border: 1px solid transparent;
        }

        .ventas-feedback-alert--success {
            background: #edf9f1;
            border-color: #cfead8;
            color: #1f6a3e;
        }

        .ventas-feedback-alert--error {
            background: #fff1f0;
            border-color: #f2cfca;
            color: #a33e34;
        }

        .ventas-feedback-alert--warning {
            background: #fff7ec;
            border-color: #f6dec0;
            color: #9a5a00;
        }

        .ventas-feedback-alert--info {
            background: #eef5ff;
            border-color: #d5e4fb;
            color: #20539a;
        }

        .ventas-caja-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.1rem;
            border-radius: 12px;
            border: 1px solid rgba(32, 83, 154, 0.14);
            background: #fff;
            box-shadow: 0 8px 20px rgba(16, 43, 84, 0.05);
            margin-bottom: 1rem;
        }

        .ventas-caja-card__label {
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #6a7f9e;
            margin-bottom: .2rem;
        }

        .ventas-caja-card__state {
            font-weight: 700;
            color: #173b73;
            margin-bottom: .2rem;
        }

        .ventas-caja-card__hint {
            color: #70839f;
            font-size: .86rem;
        }

        .ventas-caja-badge {
            display: inline-flex;
            align-items: center;
            padding: .25rem .6rem;
            border-radius: 999px;
            font-size: .76rem;
            font-weight: 700;
            margin-left: .35rem;
        }

        .ventas-caja-badge.is-open {
            background: rgba(40, 167, 69, 0.14);
            color: #1f7a35;
        }

        .ventas-caja-badge.is-closed {
            background: rgba(255, 193, 7, 0.22);
            color: #9a6b00;
        }

        .ventas-arqueo-card .card-body {
            padding: 1rem 1.25rem 1.25rem;
        }

        .ventas-arqueo-filter {
            min-width: 170px;
        }

        .ventas-arqueo-resumen {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .75rem;
            margin-bottom: 1rem;
        }

        .ventas-arqueo-pill {
            border: 1px solid rgba(32, 83, 154, 0.15);
            border-radius: 10px;
            background: #f9fbff;
            padding: .65rem .75rem;
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: .6rem;
            color: #466084;
            font-size: .82rem;
        }

        .ventas-arqueo-pill strong {
            color: #173b73;
            font-size: .95rem;
        }

        .ventas-arqueo-pill.is-ok {
            border-color: rgba(40, 167, 69, 0.25);
            background: #eff9f2;
        }

        .ventas-arqueo-pill.is-alert {
            border-color: rgba(220, 53, 69, 0.25);
            background: #fff3f2;
        }

        .ventas-arqueo-table td,
        .ventas-arqueo-table th {
            vertical-align: middle;
        }

        .ventas-summary-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .ventas-stat-card {
            display: block;
            background: #fff;
            border: 1px solid rgba(32, 83, 154, 0.12);
            border-top: 4px solid #fecc36;
            border-radius: 12px;
            padding: 1rem 1.05rem;
            box-shadow: 0 8px 24px rgba(16, 43, 84, 0.06);
            min-height: 142px;
            text-decoration: none;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background-color .18s ease;
        }

        .ventas-stat-card:hover,
        .ventas-stat-card:focus {
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(16, 43, 84, 0.12);
            border-color: rgba(32, 83, 154, 0.24);
        }

        .ventas-stat-card--active {
            background: linear-gradient(180deg, #fffdf5 0%, #fff6d8 100%);
            border-color: rgba(254, 204, 54, 0.42);
            box-shadow: 0 16px 30px rgba(254, 204, 54, 0.22);
        }

        .ventas-stat-card--accent {
            border-top-color: #20539a;
            background: linear-gradient(180deg, #ffffff 0%, #f6f9ff 100%);
        }

        .ventas-stat-card--accent.ventas-stat-card--active {
            background: linear-gradient(180deg, #f7fbff 0%, #e8f1ff 100%);
            border-color: rgba(32, 83, 154, 0.32);
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
        .ventas-table__secondary--hint {
            color: #9a6b00;
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

        .ventas-count-pill--button {
            border: 1px solid transparent;
            transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease;
            cursor: pointer;
        }

        .ventas-count-pill--button:hover,
        .ventas-count-pill--button:focus {
            background: rgba(32, 83, 154, 0.14);
            box-shadow: 0 8px 20px rgba(32, 83, 154, 0.16);
            transform: translateY(-1px);
            outline: none;
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

        .ventas-items-modal__header {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-bottom: 1px solid rgba(32, 83, 154, 0.1);
            align-items: flex-start;
        }

        .ventas-items-modal__subtitle {
            margin-top: .25rem;
            color: #74839b;
            font-size: .85rem;
        }

        .ventas-items-table thead th {
            background: #f7f9fc;
            color: #38557f;
            border-bottom: 1px solid rgba(32, 83, 154, 0.1);
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .02em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .ventas-items-table td {
            vertical-align: top;
            border-top: 1px solid rgba(32, 83, 154, 0.08);
            padding-top: .9rem;
            padding-bottom: .9rem;
        }

        .ventas-items-table__qty {
            display: inline-flex;
            min-width: 30px;
            height: 30px;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: rgba(254, 204, 54, 0.22);
            color: #7c5b00;
            font-weight: 800;
        }

        .ventas-items-table__total {
            color: #173b73;
            font-weight: 800;
        }

        .ventas-items-modal__footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .ventas-items-modal__summary {
            color: #173b73;
            font-weight: 800;
        }

        @media (max-width: 1399.98px) {
            .ventas-summary-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .ventas-arqueo-resumen {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .ventas-summary-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr));
            }

            .ventas-arqueo-resumen {
                grid-template-columns: repeat(1, minmax(0, 1fr));
            }

            .ventas-caja-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .ventas-stat-card {
                min-height: auto;
            }

            .ventas-header-badges {
                width: 100%;
            }

            .ventas-items-modal__footer {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
@stop

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('ventasFiltersForm');
            const pendingConsultForms = Array.from(document.querySelectorAll('form[data-pending-consult="true"]'));

            if (!form) {
                return;
            }

            let searchTimer = null;
            let isSubmitting = false;

            const submitFilters = () => {
                if (isSubmitting) {
                    return;
                }

                isSubmitting = true;
                form.submit();
            };

            form.querySelectorAll('[data-auto-submit="change"]').forEach(function (field) {
                field.addEventListener('change', submitFilters);
            });

            form.querySelectorAll('[data-auto-submit="search"]').forEach(function (field) {
                field.addEventListener('input', function () {
                    window.clearTimeout(searchTimer);
                    searchTimer = window.setTimeout(submitFilters, 450);
                });

                field.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        window.clearTimeout(searchTimer);
                        submitFilters();
                    }
                });
            });

            if (pendingConsultForms.length > 0) {
                let pendingConsultInFlight = false;
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                const refreshPendingStatuses = async function () {
                    if (pendingConsultInFlight) {
                        return;
                    }

                    pendingConsultInFlight = true;

                    try {
                        for (const pendingForm of pendingConsultForms) {
                            const formData = new FormData(pendingForm);
                            await fetch(pendingForm.action, {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'text/html,application/xhtml+xml',
                                },
                                body: formData,
                                credentials: 'same-origin',
                            });
                        }

                        window.location.reload();
                    } catch (error) {
                        pendingConsultInFlight = false;
                    }
                };

                window.setInterval(refreshPendingStatuses, 30000);
            }
        });
    </script>
@stop



