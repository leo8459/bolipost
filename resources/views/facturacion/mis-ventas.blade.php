@extends('adminlte::page')

@section('title', 'Mis ventas')

@section('content')
    @php
        $facturacionFeedback = session('facturacion_feedback');
        $cajaEstado = strtoupper(trim((string) data_get($cajaContext ?? [], 'estado', 'SIN_APERTURA')));
        $isCajaAbierta = in_array($cajaEstado, ['ABIERTA', 'ABIERTO'], true);
        $cajaMensaje = trim((string) data_get($cajaContext ?? [], 'mensaje', ''));
        $baseFilterParams = [
            'q' => $filters['q'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'per_page' => $filters['per_page'],
        ];
        $firstVisible = $carts->total() > 0 ? (($carts->currentPage() - 1) * $carts->perPage()) + 1 : 0;
        $lastVisible = $carts->total() > 0 ? min($carts->currentPage() * $carts->perPage(), $carts->total()) : 0;

        $summaryCards = [
            [
                'label' => 'Facturadas',
                'value' => number_format($summary['facturadas']),
                'meta' => '',
                'params' => array_merge($baseFilterParams, ['estado' => 'all', 'estado_emision' => 'FACTURADA']),
                'active' => $filters['estado'] === 'all' && $filters['estado_emision'] === 'FACTURADA',
                'accent' => false,
            ],
            [
                'label' => 'Pendientes',
                'value' => number_format($summary['pendientes']),
                'meta' => '',
                'params' => array_merge($baseFilterParams, ['estado' => 'all', 'estado_emision' => 'PENDIENTE']),
                'active' => $filters['estado'] === 'all' && $filters['estado_emision'] === 'PENDIENTE',
                'accent' => false,
            ],
            [
                'label' => 'QR pendientes',
                'value' => number_format($summary['qrPendientes'] ?? 0),
                'meta' => '',
                'params' => array_merge($baseFilterParams, ['estado' => 'pendiente_pago', 'estado_emision' => 'NO_APLICA']),
                'active' => $filters['estado'] === 'pendiente_pago' && $filters['estado_emision'] === 'NO_APLICA',
                'accent' => false,
            ],
            [
                'label' => 'Rechazadas',
                'value' => number_format($summary['rechazadas']),
                'meta' => '',
                'params' => array_merge($baseFilterParams, ['estado' => 'all', 'estado_emision' => 'RECHAZADA']),
                'active' => $filters['estado'] === 'all' && $filters['estado_emision'] === 'RECHAZADA',
                'accent' => false,
            ],
            [
                'label' => 'Borradores',
                'value' => number_format($summary['totalBorradores']),
                'meta' => '',
                'params' => array_merge($baseFilterParams, ['estado' => 'borrador', 'estado_emision' => 'all']),
                'active' => $filters['estado'] === 'borrador' && $filters['estado_emision'] === 'all',
                'accent' => false,
            ],
            [
                'label' => 'Total vendido',
                'value' => 'Bs ' . number_format($summary['montoTotal'], 2),
                'meta' => '',
                'params' => array_merge($baseFilterParams, ['estado' => 'emitido', 'estado_emision' => 'all']),
                'active' => $filters['estado'] === 'emitido' && $filters['estado_emision'] === 'all',
                'accent' => true,
            ],
        ];
    @endphp

    <div class="ventas-page">
    @if (is_array($facturacionFeedback) && in_array((string) ($facturacionFeedback['action'] ?? ''), ['caja_abrir', 'caja_cerrar'], true))
        <div class="alert ventas-feedback-alert ventas-feedback-alert--{{ $facturacionFeedback['type'] ?? 'info' }}">
            <strong>{{ $facturacionFeedback['title'] ?? 'Caja diaria' }}</strong>
            <div>{{ $facturacionFeedback['message'] ?? '' }}</div>
            @if (!empty($facturacionFeedback['detail']))
                <small>{{ $facturacionFeedback['detail'] }}</small>
            @endif
        </div>
    @endif


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
                {{ $cajaMensaje !== '' ? $cajaMensaje : ($isCajaAbierta ? 'Cierra caja al finalizar tu jornada.' : 'Abre caja para habilitar emision en facturacion.') }}
            </div>
        </div>
        <div class="ventas-caja-card__right">
            @if($isCajaAbierta)
                <form
                    method="POST"
                    action="{{ route('facturacion.cart.caja.cerrar') }}"
                    class="ventas-caja-confirm-form"
                    data-confirm-title="Cerrar caja"
                    data-confirm-message="Se cerrara la caja diaria de esta sesion."
                    data-confirm-detail="Asegurate de haber terminado tus operaciones antes de continuar."
                    data-confirm-cta="Si, cerrar caja"
                    data-processing-title="Cerrando caja"
                    data-processing-text="Estamos cerrando la caja diaria, espera un momento..."
                >
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="fas fa-door-closed mr-1"></i> Cerrar caja
                    </button>
                </form>
            @else
                <form
                    method="POST"
                    action="{{ route('facturacion.cart.caja.abrir') }}"
                    class="ventas-caja-confirm-form"
                    data-confirm-title="Abrir caja"
                    data-confirm-message="Se abrira una nueva caja diaria para esta sesion."
                    data-confirm-detail="Despues de abrirla ya podras emitir y consultar facturas."
                    data-confirm-cta="Si, abrir caja"
                    data-processing-title="Abriendo caja"
                    data-processing-text="Estamos preparando la caja diaria, espera un momento..."
                >
                    @csrf
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-lock-open mr-1"></i> Abrir caja
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="card ventas-panel mb-4">
        <div class="card-header ventas-panel__header">
            <div>
                <strong>Filtros de consulta</strong>
                <div class="text-muted small">Ajusta criterios para encontrar ventas por codigo, cliente, fecha o estado.</div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('mis-ventas.index') }}" id="ventasFiltersForm">
                <div class="row">
                    <div class="col-xl-4 col-lg-6 mb-3">
                        <label class="ventas-label">Buscar</label>
                        <input type="text" class="form-control ventas-control" name="q" value="{{ $filters['q'] }}" placeholder="Codigo, seguimiento, cliente o mensaje" data-auto-submit="search">
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 mb-3">
                        <label class="ventas-label">Estado</label>
                        <select class="form-control ventas-control" name="estado" data-auto-submit="change">
                            <option value="all" {{ $filters['estado'] === 'all' ? 'selected' : '' }}>Todos</option>
                            <option value="emitido" {{ $filters['estado'] === 'emitido' ? 'selected' : '' }}>Emitido</option>
                            <option value="pendiente_pago" {{ $filters['estado'] === 'pendiente_pago' ? 'selected' : '' }}>Pendiente QR</option>
                            <option value="borrador" {{ $filters['estado'] === 'borrador' ? 'selected' : '' }}>Borrador</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4 mb-3">
                        <label class="ventas-label">Estado emision</label>
                        <select class="form-control ventas-control" name="estado_emision" data-auto-submit="change">
                            <option value="all" {{ $filters['estado_emision'] === 'all' ? 'selected' : '' }}>Todos</option>
                            <option value="FACTURADA" {{ $filters['estado_emision'] === 'FACTURADA' ? 'selected' : '' }}>Facturada</option>
                            <option value="PENDIENTE" {{ $filters['estado_emision'] === 'PENDIENTE' ? 'selected' : '' }}>Pendiente</option>
                            <option value="RECHAZADA" {{ $filters['estado_emision'] === 'RECHAZADA' ? 'selected' : '' }}>Rechazada</option>
                            <option value="NO_APLICA" {{ $filters['estado_emision'] === 'NO_APLICA' ? 'selected' : '' }}>No aplica QR</option>
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

    <div class="ventas-summary-grid">
        @foreach($summaryCards as $card)
            <a
                href="{{ route('mis-ventas.index', $card['params']) }}"
                class="ventas-stat-card {{ $card['accent'] ? 'ventas-stat-card--accent' : '' }} {{ $card['active'] ? 'ventas-stat-card--active' : '' }}"
            >
                <div class="ventas-stat-card__label">{{ $card['label'] }}</div>
                <div class="ventas-stat-card__value">{{ $card['value'] }}</div>
                @if ($card['meta'] !== '')
                    <div class="ventas-stat-card__meta">{{ $card['meta'] }}</div>
                @endif
            </a>
        @endforeach
    </div>

    <div class="card ventas-panel">
        <div class="card-header ventas-panel__header d-flex justify-content-between align-items-center">
            <div>
                <strong>Historial de ventas</strong>
                <div class="text-muted small">Detalle de emisiones registradas para tu cuenta.</div>
            </div>
            <div class="text-right">
                <div class="ventas-table-count">{{ $carts->total() }} registros</div>
                @if($carts->total() > 0)
                    <div class="ventas-table-range">Mostrando {{ $firstVisible }} a {{ $lastVisible }}</div>
                @endif
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table ventas-table mb-0">
                    <thead>
                        <tr>
                            <th class="text-center">N°</th>
                            <th>Fecha</th>
                            <th>Codigo orden</th>
                            <th>Cliente</th>
                            <th>Facturacion</th>
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
                                $estadoPago = strtolower(trim((string) data_get($cart, 'estado_pago', 'pendiente')));
                                $mensajeEmision = trim((string) data_get($cart, 'mensaje_emision', ''));
                                $cartId = (int) data_get($cart, 'id', 0);
                                $codigoOrden = trim((string) data_get($cart, 'codigo_orden', ''));
                                $numeroDocumento = trim((string) data_get($cart, 'numero_documento', ''));
                                $razonSocial = trim((string) data_get($cart, 'razon_social', ''));
                                $modalidadFacturacion = (string) data_get($cart, 'modalidad_facturacion', 'con_datos');
                                $esOficialMeta = (bool) data_get($cart, 'es_oficial', false);
                                $estadoSufeRaw = strtoupper(trim((string) data_get($cart, 'respuesta_emision.estadoSufe', data_get($cart, 'estado_sufe', ''))));
                                $canalEmision = strtolower(trim((string) data_get($cart, 'canal_emision', '')));
                                $metodoPago = strtolower(trim((string) data_get($cart, 'metodo_pago', $canalEmision === 'qr' ? 'qr' : 'efectivo')));
                                $isOficial = $esOficialMeta
                                    || str_starts_with($codigoOrden, 'OFI-')
                                    || strtoupper($razonSocial ?? '') === 'ENVIO OFICIAL'
                                    || $estadoSufeRaw === 'REGISTRADA_OFICIAL';
                                if ($canalEmision === '') {
                                    if ($isOficial) {
                                        $canalEmision = 'oficial';
                                    } elseif (str_starts_with($codigoOrden, 'VQ-')) {
                                        $canalEmision = 'qr';
                                    } elseif (str_starts_with($codigoOrden, 'VF-')) {
                                        $canalEmision = 'factura_electronica';
                                    }
                                }
                                $isQrPayment = $metodoPago === 'qr' || trim((string) data_get($cart, 'qr_transaction_id', '')) !== '';

                                $canalBadgeLabel = $isQrPayment
                                    ? 'Pago QR'
                                    : ($canalEmision === 'oficial' ? 'Registro oficial' : 'Factura electronica');
                                $canalBadgeClass = $isQrPayment
                                    ? 'ventas-channel-chip--qr'
                                    : 'ventas-channel-chip--factura';
                                $estadoCart = (string) data_get($cart, 'estado', '');
                                $codigoSeguimiento = trim((string) data_get($cart, 'codigo_seguimiento', ''));
                                $codigoSeguimientoFiscal = trim((string) data_get($cart, 'codigo_seguimiento_fiscal', $codigoSeguimiento));
                                $qrTransactionId = trim((string) data_get($cart, 'qr_transaction_id', ''));
                                $referenciaConsulta = $canalEmision === 'qr'
                                    ? ($qrTransactionId !== '' ? $qrTransactionId : $codigoSeguimiento)
                                    : ($codigoSeguimientoFiscal !== '' ? $codigoSeguimientoFiscal : $codigoSeguimiento);
                                $referenciaLabel = $canalEmision === 'qr' ? 'QR ref' : 'Fiscal';
                                $totalCart = (float) data_get($cart, 'total', 0);
                                $itemsCountApi = (int) data_get($cart, 'items_count', 0);
                                $fechaRaw = data_get($cart, 'emitido_en') ?: data_get($cart, 'created_at');
                                $fecha = null;
                                $showConsultAction = $cartId > 0 && ($referenciaConsulta !== '' || $isQrPayment || $facturaEstado === 'PENDIENTE');
                                $consultActionLabel = 'Consultar';

                                if ($fechaRaw instanceof \Carbon\CarbonInterface) {
                                    $fecha = $fechaRaw;
                                } elseif (!empty($fechaRaw)) {
                                    try {
                                        $fecha = \Carbon\Carbon::parse((string) $fechaRaw);
                                    } catch (\Throwable $e) {
                                        $fecha = null;
                                    }
                                }

                                if ($isQrPayment) {
                                    if ($estadoPago === 'pendiente') {
                                        $consultActionLabel = 'Actualizar pago';
                                    } elseif ($estadoPago === 'cancelado') {
                                        $consultActionLabel = 'Revisar pago';
                                    } elseif ($facturaEstado === 'FACTURADA') {
                                        $consultActionLabel = 'Consultar';
                                    } elseif (in_array($facturaEstado, ['PENDIENTE', 'NO_APLICA', 'ERROR', 'RECHAZADA'], true)) {
                                        $consultActionLabel = 'Actualizar factura';
                                    } else {
                                        $consultActionLabel = 'Consultar';
                                    }
                                } elseif ($facturaEstado === 'PENDIENTE') {
                                    $consultActionLabel = 'Actualizar estado';
                                }
                            @endphp
                            <tr>
                                <td class="text-center">
                                    <span class="ventas-row-number">{{ $firstVisible + $loop->index }}</span>
                                </td>
                                <td>
                                    <div class="ventas-table__primary">{{ $fecha ? $fecha->format('d/m/Y') : '-' }}</div>
                                    <div class="ventas-table__secondary">{{ $fecha ? $fecha->format('H:i') : '-' }}</div>
                                </td>
                                <td>
                                    <div class="ventas-table__primary">{{ $codigoOrden !== '' ? $codigoOrden : 'Sin codigo' }}</div>
                                    @if ($isOficial)
                                        <div class="ventas-table__secondary">Fact: {{ $numeroFactura !== '' ? $numeroFactura : 'S/N' }}</div>
                                    @else
                                        <div class="ventas-table__secondary">Doc: {{ $numeroDocumento !== '' ? $numeroDocumento : 'S/N' }} · Fact: {{ $numeroFactura !== '' ? $numeroFactura : 'S/N' }}</div>
                                    @endif
                                    @if($referenciaConsulta !== '')
                                        <div class="ventas-table__secondary">{{ $referenciaLabel }}: {{ $referenciaConsulta }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="ventas-table__primary">{{ $razonSocial !== '' ? $razonSocial : 'SIN NOMBRE' }}</div>
                                    <div class="ventas-table__secondary">{{ $isOficial ? 'Registro interno' : ucfirst(str_replace('_', ' ', $modalidadFacturacion)) }}</div>
                                    <div class="ventas-table__secondary">
                                        <span class="ventas-channel-chip {{ $canalBadgeClass }}">{{ $canalBadgeLabel }}</span>
                                    </div>
                                </td>
                                <td>
                                    @if($isQrPayment && $facturaEstado === 'FACTURADA')
                                        <span class="ventas-status-chip ventas-status-chip--success">QR PAGADO Y FACTURADO</span>
                                    @elseif($isQrPayment && $facturaEstado === 'PENDIENTE')
                                        <span class="ventas-status-chip ventas-status-chip--warning">QR PAGADO - FACTURA EN PROCESO</span>
                                    @elseif($isQrPayment && $facturaEstado === 'RECHAZADA')
                                        <span class="ventas-status-chip ventas-status-chip--danger">FACTURA RECHAZADA</span>
                                    @elseif($isQrPayment && $facturaEstado === 'ERROR')
                                        <span class="ventas-status-chip ventas-status-chip--dark">ERROR DE FACTURA</span>
                                    @elseif($isQrPayment && $facturaEstado === 'NO_APLICA' && $estadoPago === 'pagado')
                                        <span class="ventas-status-chip ventas-status-chip--success">QR PAGADO</span>
                                    @elseif($isQrPayment && $facturaEstado === 'NO_APLICA' && $estadoPago === 'cancelado')
                                        <span class="ventas-status-chip ventas-status-chip--danger">QR NO PAGADO / CANCELADO</span>
                                    @elseif($isQrPayment && $facturaEstado === 'NO_APLICA')
                                        <span class="ventas-status-chip ventas-status-chip--warning">QR PENDIENTE DE PAGO</span>
                                    @elseif($facturaEstado === 'FACTURADA')
                                        <span class="ventas-status-chip ventas-status-chip--success">FACTURADA</span>
                                    @elseif($facturaEstado === 'PENDIENTE')
                                        <span class="ventas-status-chip ventas-status-chip--warning">PENDIENTE</span>
                                    @elseif($facturaEstado === 'RECHAZADA')
                                        <span class="ventas-status-chip ventas-status-chip--danger">RECHAZADA</span>
                                    @elseif($facturaEstado === 'NO_APLICA')
                                        <span class="ventas-status-chip ventas-status-chip--muted">NO APLICA</span>
                                    @elseif($facturaEstado === 'ERROR')
                                        <span class="ventas-status-chip ventas-status-chip--dark">ERROR</span>
                                    @else
                                        <span class="ventas-status-chip ventas-status-chip--muted">{{ $facturaEstado !== '' ? $facturaEstado : 'SIN ESTADO' }}</span>
                                    @endif
                                    <div class="ventas-table__secondary mt-1">
                                        {{ $mensajeEmision !== '' ? \Illuminate\Support\Str::limit($mensajeEmision, 65) : 'Sin observaciones registradas.' }}
                                    </div>
                                    @if($facturaEstado === 'PENDIENTE' && $canalEmision !== 'qr')
                                        <div class="ventas-table__secondary ventas-table__secondary--hint">
                                            Si fue por contingencia, usa actualizar estado hasta que llegue la factura.
                                        </div>
                                    @elseif($isQrPayment && $facturaEstado === 'NO_APLICA' && $estadoPago === 'pendiente')
                                        <div class="ventas-table__secondary ventas-table__secondary--hint">
                                            Si el cliente cerró la ventana o no completó el pago, la venta seguirá pendiente hasta volver a consultar.
                                        </div>
                                    @elseif($isQrPayment && $facturaEstado === 'NO_APLICA' && $estadoPago === 'cancelado')
                                        <div class="ventas-table__secondary ventas-table__secondary--hint">
                                            El intento de pago QR no se concretó. Puedes generar un nuevo QR o dejar la venta sin cobrar.
                                        </div>
                                    @elseif($isQrPayment && $facturaEstado === 'PENDIENTE')
                                        <div class="ventas-table__secondary ventas-table__secondary--hint">
                                            El pago QR ya fue confirmado y la factura se encuentra en proceso ante SEFE.
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="ventas-status-chip {{ $estadoCart === 'emitido' ? 'ventas-status-chip--primary' : ($estadoCart === 'pendiente_pago' ? 'ventas-status-chip--warning' : 'ventas-status-chip--muted') }}">
                                        {{ strtoupper($estadoCart !== '' ? $estadoCart : 'sin estado') }}
                                    </span>
                                    @if($estadoCart === 'pendiente_pago' && $isQrPayment && $estadoPago === 'pendiente')
                                        <div class="ventas-table__secondary mt-1">Pago QR pendiente de confirmacion.</div>
                                    @elseif($estadoCart === 'pendiente_pago' && $isQrPayment && $estadoPago === 'cancelado')
                                        <div class="ventas-table__secondary mt-1">El QR fue cerrado, cancelado o no completado.</div>
                                    @endif
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
                                        @if($showConsultAction)
                                            <form
                                                method="POST"
                                                action="{{ route('facturacion.cart.consultar') }}"
                                                class="mr-2 mb-2"
                                                @if($facturaEstado === 'PENDIENTE' || $isQrPayment)
                                                    data-pending-consult="true"
                                                @endif
                                            >
                                                @csrf
                                                <input type="hidden" name="cart_id" value="{{ $cartId }}">
                                                <input type="hidden" name="codigo_seguimiento" value="{{ $referenciaConsulta }}">
                                                <button type="submit" class="btn btn-xs btn-outline-secondary">
                                                    {{ $consultActionLabel }}
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
                                <td colspan="9" class="text-center py-5 text-muted">No se encontraron ventas con los filtros aplicados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($carts->hasPages() || $carts->total() > 0)
            <div class="card-footer ventas-table-footer">
                <div class="ventas-table-footer__meta">
                    @if($carts->total() > 0)
                        Mostrando {{ $firstVisible }} a {{ $lastVisible }} de {{ $carts->total() }} registros
                    @else
                        Sin resultados para los filtros actuales
                    @endif
                </div>
                @if($carts->hasPages())
                    <div class="ventas-table-footer__pagination">
                        {{ $carts->appends(request()->except('page'))->links('pagination::bootstrap-4') }}
                    </div>
                @endif
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
                                            <th>Codigo</th>
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
                                                    <div class="ventas-table__primary">{{ $itemTitulo !== '' ? $itemTitulo : 'Sin titulo' }}</div>
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
    </div>

    <div class="ventas-confirm-modal" id="ventasCajaConfirmModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ventasCajaConfirmTitle">
        <div class="ventas-confirm-modal__backdrop" data-close-ventas-caja-confirm="true"></div>
        <div class="ventas-confirm-modal__panel" role="document">
            <div class="ventas-confirm-modal__eyebrow">Caja diaria</div>
            <h4 id="ventasCajaConfirmTitle" class="ventas-confirm-modal__title">Confirmar accion</h4>
            <p class="ventas-confirm-modal__message" id="ventasCajaConfirmMessage">Esta accion actualizara el estado de la caja diaria.</p>
            <div class="ventas-confirm-modal__detail" id="ventasCajaConfirmDetail" hidden></div>
            <div class="ventas-confirm-modal__actions">
                <button type="button" class="btn btn-light" id="ventasCajaConfirmCancel">Cancelar</button>
                <button type="button" class="btn btn-primary" id="ventasCajaConfirmAccept">Confirmar</button>
            </div>
        </div>
    </div>

    <div class="ventas-processing-overlay" id="ventasCajaProcessingOverlay" aria-hidden="true" hidden>
        <div class="ventas-processing-overlay__card" role="status" aria-live="polite">
            <div class="ventas-processing-overlay__spinner" aria-hidden="true"></div>
            <strong id="ventasCajaProcessingTitle">Procesando caja</strong>
            <span id="ventasCajaProcessingText">Espera un momento...</span>
        </div>
    </div>
@stop

@section('css')
    <style>
        .ventas-page {
            padding-top: 1rem;
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

        .ventas-caja-confirm-form.is-submitting button {
            pointer-events: none;
            opacity: .72;
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

        .ventas-table-range {
            color: #97a7bf;
            font-size: .78rem;
            margin-top: .15rem;
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

        .ventas-row-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 .55rem;
            border-radius: 999px;
            background: #eef4ff;
            color: #1f4f96;
            font-weight: 800;
            font-size: .8rem;
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
        .ventas-channel-chip {
            display: inline-flex;
            align-items: center;
            padding: .24rem .52rem;
            border-radius: 999px;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .03em;
            text-transform: uppercase;
            border: 1px solid transparent;
        }
        .ventas-channel-chip--factura {
            background: #eaf2ff;
            border-color: #cfe0ff;
            color: #1f4f96;
        }
        .ventas-channel-chip--qr {
            background: #fff4dd;
            border-color: #f4d694;
            color: #9a6400;
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

        .ventas-table-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            background: #fbfcfe;
        }

        .ventas-table-footer__meta {
            color: #70839f;
            font-size: .86rem;
            font-weight: 600;
        }

        .ventas-table-footer__pagination .pagination {
            margin-bottom: 0;
        }

        .ventas-confirm-modal,
        .ventas-processing-overlay {
            position: fixed;
            inset: 0;
            z-index: 2055;
            opacity: 1;
            visibility: visible;
            transition: opacity .18s ease, visibility .18s ease;
        }

        .ventas-confirm-modal[aria-hidden="true"],
        .ventas-processing-overlay[aria-hidden="true"] {
            pointer-events: none;
            opacity: 0;
            visibility: hidden;
        }

        .ventas-confirm-modal__backdrop,
        .ventas-processing-overlay {
            background: rgba(15, 28, 52, 0.46);
            backdrop-filter: blur(6px);
        }

        .ventas-confirm-modal {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }

        .ventas-confirm-modal__panel {
            width: min(100%, 460px);
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 24px 70px rgba(15, 28, 52, 0.24);
            padding: 1.4rem;
            position: relative;
            z-index: 1;
        }

        .ventas-confirm-modal__eyebrow {
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #6a7f9e;
            margin-bottom: .55rem;
        }

        .ventas-confirm-modal__title {
            margin: 0 0 .55rem;
            color: #173b73;
            font-weight: 800;
        }

        .ventas-confirm-modal__message {
            margin: 0;
            color: #4c6285;
        }

        .ventas-confirm-modal__detail {
            margin-top: .85rem;
            padding: .9rem 1rem;
            border-radius: 14px;
            background: #f7f9fc;
            color: #667c9e;
            font-size: .9rem;
        }

        .ventas-confirm-modal__actions {
            display: flex;
            justify-content: flex-end;
            gap: .75rem;
            margin-top: 1.2rem;
        }

        .ventas-processing-overlay {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }

        .ventas-processing-overlay__card {
            width: min(100%, 360px);
            background: rgba(255, 255, 255, 0.98);
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(15, 28, 52, 0.24);
            padding: 1.5rem 1.3rem;
            text-align: center;
            display: grid;
            gap: .55rem;
            justify-items: center;
        }

        .ventas-processing-overlay__spinner {
            width: 54px;
            height: 54px;
            border-radius: 999px;
            border: 4px solid rgba(32, 83, 154, 0.14);
            border-top-color: #20539a;
            border-right-color: #fecc36;
            animation: ventasCajaSpin .9s linear infinite;
        }

        .ventas-processing-overlay__card strong {
            color: #173b73;
            font-size: 1.15rem;
        }

        .ventas-processing-overlay__card span {
            color: #5d7396;
        }

        @keyframes ventasCajaSpin {
            to {
                transform: rotate(360deg);
            }
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

            .ventas-caja-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .ventas-stat-card {
                min-height: auto;
            }

            .ventas-items-modal__footer {
                flex-direction: column;
                align-items: stretch;
            }

            .ventas-confirm-modal__actions {
                flex-direction: column-reverse;
            }

            .ventas-table-footer {
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
            const cajaForms = Array.from(document.querySelectorAll('.ventas-caja-confirm-form'));
            const cajaConfirmModal = document.getElementById('ventasCajaConfirmModal');
            const cajaConfirmTitle = document.getElementById('ventasCajaConfirmTitle');
            const cajaConfirmMessage = document.getElementById('ventasCajaConfirmMessage');
            const cajaConfirmDetail = document.getElementById('ventasCajaConfirmDetail');
            const cajaConfirmCancel = document.getElementById('ventasCajaConfirmCancel');
            const cajaConfirmAccept = document.getElementById('ventasCajaConfirmAccept');
            const cajaProcessingOverlay = document.getElementById('ventasCajaProcessingOverlay');
            const cajaProcessingTitle = document.getElementById('ventasCajaProcessingTitle');
            const cajaProcessingText = document.getElementById('ventasCajaProcessingText');
            let pendingCajaForm = null;
            let isCajaSubmitting = false;

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

            const openCajaConfirm = (targetForm) => {
                if (!(targetForm instanceof HTMLFormElement) || !cajaConfirmModal) {
                    targetForm?.submit();
                    return;
                }

                const confirmTitle = String(
                    targetForm.getAttribute('data-confirm-title')
                    || targetForm.dataset.confirmTitle
                    || 'Confirmar accion'
                );
                const confirmMessage = String(
                    targetForm.getAttribute('data-confirm-message')
                    || targetForm.dataset.confirmMessage
                    || 'Esta accion actualizara el estado de la caja diaria.'
                );
                const confirmDetail = String(
                    targetForm.getAttribute('data-confirm-detail')
                    || targetForm.dataset.confirmDetail
                    || ''
                ).trim();
                const confirmCta = String(
                    targetForm.getAttribute('data-confirm-cta')
                    || targetForm.dataset.confirmCta
                    || 'Confirmar'
                );

                pendingCajaForm = targetForm;
                if (cajaConfirmTitle) {
                    cajaConfirmTitle.textContent = confirmTitle;
                }
                if (cajaConfirmMessage) {
                    cajaConfirmMessage.textContent = confirmMessage;
                }
                if (cajaConfirmDetail) {
                    cajaConfirmDetail.textContent = confirmDetail;
                    cajaConfirmDetail.hidden = confirmDetail === '';
                }
                if (cajaConfirmAccept) {
                    cajaConfirmAccept.textContent = confirmCta;
                }

                cajaConfirmModal.setAttribute('aria-hidden', 'false');
                window.setTimeout(() => cajaConfirmAccept?.focus(), 30);
            };

            const closeCajaConfirm = () => {
                pendingCajaForm = null;
                if (!cajaConfirmModal) {
                    return;
                }
                cajaConfirmModal.setAttribute('aria-hidden', 'true');
            };

            const setCajaProcessing = (active, targetForm = null) => {
                isCajaSubmitting = active;

                cajaForms.forEach((candidate) => {
                    const button = candidate.querySelector('button[type="submit"]');
                    candidate.classList.toggle('is-submitting', active && candidate === targetForm);
                    if (button instanceof HTMLButtonElement) {
                        button.dataset.originalText = button.dataset.originalText || button.textContent.trim();
                        button.disabled = active;
                        button.textContent = active && candidate === targetForm
                            ? 'Procesando...'
                            : (button.dataset.originalText || button.textContent);
                    }
                });

                if (cajaConfirmAccept instanceof HTMLButtonElement) {
                    cajaConfirmAccept.disabled = active;
                }
                if (cajaConfirmCancel instanceof HTMLButtonElement) {
                    cajaConfirmCancel.disabled = active;
                }

                if (cajaProcessingOverlay) {
                    cajaProcessingOverlay.hidden = !active;
                    cajaProcessingOverlay.setAttribute('aria-hidden', active ? 'false' : 'true');
                }

                if (active && targetForm instanceof HTMLFormElement) {
                    const processingTitle = String(
                        targetForm.getAttribute('data-processing-title')
                        || targetForm.dataset.processingTitle
                        || 'Procesando caja'
                    );
                    const processingText = String(
                        targetForm.getAttribute('data-processing-text')
                        || targetForm.dataset.processingText
                        || 'Espera un momento...'
                    );

                    if (cajaProcessingTitle) {
                        cajaProcessingTitle.textContent = processingTitle;
                    }
                    if (cajaProcessingText) {
                        cajaProcessingText.textContent = processingText;
                    }
                } else {
                    if (cajaProcessingTitle) {
                        cajaProcessingTitle.textContent = 'Procesando caja';
                    }
                    if (cajaProcessingText) {
                        cajaProcessingText.textContent = 'Espera un momento...';
                    }
                }
            };

            cajaForms.forEach((targetForm) => {
                targetForm.addEventListener('submit', (event) => {
                    if (isCajaSubmitting) {
                        event.preventDefault();
                        return;
                    }

                    event.preventDefault();
                    openCajaConfirm(targetForm);
                });
            });

            cajaConfirmCancel?.addEventListener('click', closeCajaConfirm);
            cajaConfirmAccept?.addEventListener('click', () => {
                if (!(pendingCajaForm instanceof HTMLFormElement)) {
                    closeCajaConfirm();
                    return;
                }

                const targetForm = pendingCajaForm;
                closeCajaConfirm();
                setCajaProcessing(true, targetForm);
                targetForm.submit();
            });

            cajaConfirmModal?.addEventListener('click', (event) => {
                const target = event.target;
                if (target instanceof HTMLElement && target.dataset.closeVentasCajaConfirm === 'true' && !isCajaSubmitting) {
                    closeCajaConfirm();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && cajaConfirmModal?.getAttribute('aria-hidden') === 'false' && !isCajaSubmitting) {
                    closeCajaConfirm();
                }
            });

            window.addEventListener('pageshow', () => {
                setCajaProcessing(false);
            });

        });
    </script>
@stop



