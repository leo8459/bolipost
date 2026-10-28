@extends('adminlte::page')

@section('title', 'Paquetes Delivery Express por Faro')

@section('content_header')
    <div class="delivery-control-heading d-flex flex-column flex-lg-row align-items-lg-center justify-content-between">
        <div class="d-flex align-items-center">
            <span class="delivery-control-heading-icon"><i class="fas fa-shipping-fast"></i></span>
            <div>
                <h1 class="mb-1">Paquetes de mi servicio</h1>
                <p class="text-muted mb-0">
                    Consulta solicitudes y verifica pagos · Delivery Express por Faro ·
                    @if ($departmentScope === null)
                        Todos los departamentos
                    @else
                        Departamento: {{ $departmentScope !== [] ? implode(', ', $departmentScope) : 'sin asignar' }}
                    @endif
                </p>
            </div>
        </div>
        <div class="delivery-control-heading-actions">
            <span class="delivery-control-updated"><i class="far fa-clock mr-1"></i>Actualizado {{ now()->timezone('America/La_Paz')->format('d/m/Y H:i') }}</span>
            @can('feature.dashboard.facturacion')
                <a href="{{ route('mis-ventas.index') }}" class="btn delivery-control-billing-link"><i class="fas fa-file-invoice mr-2"></i>Facturación</a>
            @endcan
        </div>
    </div>
@stop

@section('content')
    <style>
        .delivery-control-wrap { --delivery-ink: #112f61; --delivery-muted: #617a9e; --delivery-line: #dfe8f3; color: var(--delivery-ink); }
        .content-header { padding-top: 18px; padding-bottom: 17px; }
        .delivery-control-heading-icon { width: 60px; height: 60px; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 60px; margin-right: 17px; border-radius: 17px; color: #fff; background: linear-gradient(145deg, #1c5799, #123b78); font-size: 1.45rem; box-shadow: 0 8px 18px rgba(24, 74, 136, .2); }
        .delivery-control-heading h1 { color: #102e5e; font-size: 1.65rem; font-weight: 800; letter-spacing: -.035em; }
        .delivery-control-heading p { color: #4d6992 !important; font-size: .92rem; font-weight: 600; }
        .delivery-control-heading-actions { display: flex; align-items: center; gap: 20px; }
        .delivery-control-billing-link { border: 1px solid #b7c9e3; border-radius: 13px; padding: 10px 18px; color: #143d78; background: rgba(255,255,255,.7); font-weight: 800; white-space: nowrap; }
        .delivery-control-billing-link:hover { border-color: #6b91c4; background: #fff; color: #0d2e62; }
        .delivery-control-wrap .delivery-control-kpi { position: relative; display: flex; align-items: center; gap: 15px; min-height: 100px; border: 1px solid var(--delivery-line); border-radius: 15px; padding: 15px 18px; height: 100%; box-shadow: 0 7px 20px rgba(32, 68, 112, .045); overflow: hidden; }
        .delivery-control-wrap .delivery-control-kpi--blue { border-color: #c9defb; background: linear-gradient(115deg, #f0f6ff, #fbfdff); color: #1557a8; }
        .delivery-control-wrap .delivery-control-kpi--green { border-color: #c7ebd8; background: linear-gradient(115deg, #effbf4, #fbfefc); color: #147744; }
        .delivery-control-wrap .delivery-control-kpi--amber { border-color: #ffe2a4; background: linear-gradient(115deg, #fff8e9, #fffdf8); color: #8c5200; }
        .delivery-control-wrap .delivery-control-kpi--red { border-color: #ffcbd0; background: linear-gradient(115deg, #fff0f1, #fffafa); color: #a71f27; }
        .delivery-control-wrap .delivery-control-kpi-icon { width: 64px; height: 64px; display: inline-flex; flex: 0 0 64px; align-items: center; justify-content: center; border-radius: 15px; background: rgba(255,255,255,.68); font-size: 1.6rem; }
        .delivery-control-wrap .delivery-control-kpi--blue .delivery-control-kpi-icon { background: #dceaff; }
        .delivery-control-wrap .delivery-control-kpi--green .delivery-control-kpi-icon { background: #d8f5e5; }
        .delivery-control-wrap .delivery-control-kpi--amber .delivery-control-kpi-icon { background: #ffedbf; }
        .delivery-control-wrap .delivery-control-kpi--red .delivery-control-kpi-icon { background: #ffdcdf; }
        .delivery-control-wrap .delivery-control-kpi-label { color: inherit; font-size: .88rem; font-weight: 750; }
        .delivery-control-wrap .delivery-control-kpi-value { color: #112f61; font-size: 1.85rem; line-height: 1.12; font-weight: 850; margin-top: 3px; }
        .delivery-control-wrap .delivery-control-kpi-arrow { margin-left: auto; color: #2b66b2; font-size: 1.1rem; }
        .delivery-control-wrap .delivery-control-filters { border: 1px solid var(--delivery-line); border-radius: 15px; background: #fff; padding: 17px 18px 10px; box-shadow: 0 7px 20px rgba(32, 68, 112, .035); }
        .delivery-control-wrap .delivery-control-filters label { color: #193b70; font-size: .79rem; font-weight: 750; margin-bottom: 6px; }
        .delivery-control-wrap .delivery-control-filters .form-control { height: 43px; border-radius: 9px; border-color: #d4e0ee; color: #15345f; box-shadow: inset 0 1px 2px rgba(18, 53, 98, .025); }
        .delivery-control-wrap .delivery-control-filters .form-control:focus { border-color: #80a8da; box-shadow: 0 0 0 .16rem rgba(36, 103, 183, .12); }
        .delivery-control-wrap .delivery-control-filters .input-group-text { min-width: 43px; justify-content: center; border-color: #d4e0ee; border-radius: 9px 0 0 9px; color: #194b8d; }
        .delivery-control-wrap .delivery-control-filters .input-group .form-control { border-radius: 0 9px 9px 0; }
        .delivery-control-wrap .delivery-control-filters .btn { height: 43px; border-radius: 9px; font-weight: 800; }
        .delivery-control-wrap .delivery-control-filters .btn-warning { color: #102f60; background: #ffc928; border-color: #ffc928; }
        .delivery-control-wrap .delivery-control-filters .btn-warning:hover { background: #f5b900; border-color: #f5b900; }
        .delivery-control-wrap .delivery-control-clear { width: 46px; border: 1px solid #5382bf; color: #174b8c; background: #fff; }
        .delivery-control-wrap .delivery-control-table-card { border: 1px solid var(--delivery-line); border-radius: 15px; overflow: hidden; background: #fff; box-shadow: 0 7px 20px rgba(32, 68, 112, .045); }
        .delivery-control-wrap .delivery-control-table { margin: 0; min-width: 1020px; }
        .delivery-control-wrap .delivery-control-table thead th { background: #f3f7fc; border-top: 0; border-bottom: 1px solid var(--delivery-line); color: #536d93; font-size: .71rem; letter-spacing: .025em; text-transform: uppercase; white-space: nowrap; padding: 12px 13px; }
        .delivery-control-wrap .delivery-control-table td { border-top: 1px solid #e8eef6; color: #112f61; font-size: .82rem; padding: 10px 13px; vertical-align: middle; }
        .delivery-control-wrap .delivery-control-table tbody tr { transition: background-color .15s ease; }
        .delivery-control-wrap .delivery-control-table tbody tr:hover { background: #f8fbff; }
        .delivery-control-wrap .delivery-control-card-heading { padding: 16px 20px; border-bottom: 1px solid var(--delivery-line); display: flex; align-items: center; justify-content: space-between; }
        .delivery-control-wrap .delivery-control-card-heading strong { color: var(--delivery-ink); font-size: 1.03rem; font-weight: 800; }
        .delivery-control-wrap .delivery-control-card-heading p { color: var(--delivery-muted); font-size: .77rem; margin: 3px 0 0; }
        .delivery-control-wrap .delivery-control-card-heading .badge { color: #193b70; background: #f3f7fc; border: 1px solid #e1e9f3 !important; border-radius: 8px; padding: 7px 10px; font-weight: 750; }
        .delivery-control-wrap .delivery-control-code { color: #102f61; font-weight: 850; white-space: nowrap; }
        .delivery-control-wrap .delivery-control-secondary { display: block; color: var(--delivery-muted); font-size: .72rem; margin-top: 3px; }
        .delivery-control-wrap .delivery-control-route { min-width: 165px; font-size: .77rem !important; font-weight: 800; white-space: nowrap; text-transform: uppercase; }
        .delivery-control-wrap .delivery-control-route i { color: #4779b7; margin: 0 7px; }
        .delivery-control-wrap .delivery-control-badge { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 7px 11px; font-size: .72rem; line-height: 1.2; font-weight: 800; white-space: nowrap; }
        .delivery-control-wrap .badge-paid { background: #dff7ea; color: #08733d; }
        .delivery-control-wrap .badge-destination { background: #fff0c9; color: #885000; }
        .delivery-control-wrap .badge-process { background: #e3efff; color: #155cad; }
        .delivery-control-wrap .badge-error { background: #ffe2e4; color: #ab171e; }
        .delivery-control-wrap .badge-muted { background: #edf1f6; color: #566e91; }
        .delivery-control-wrap .badge-transit { background: #e0efff; color: #155cad; }
        .delivery-control-wrap .badge-preparing { background: #edf1f6; color: #566e91; }
        .delivery-control-wrap .delivery-control-actions { display: flex; justify-content: flex-end; align-items: center; gap: 6px; white-space: nowrap; }
        .delivery-control-wrap .delivery-control-invoice-action, .delivery-control-wrap .delivery-control-view-action { border-radius: 8px; font-size: .75rem; font-weight: 750; }
        .delivery-control-wrap .delivery-control-view-action { min-width: 77px; border-color: #6e9bd3; color: #174b8c; }
        .delivery-control-wrap .delivery-control-empty { padding: 46px 20px; text-align: center; color: var(--delivery-muted); }
        .delivery-control-wrap .delivery-control-empty i { color: #9eb4d0; font-size: 2rem; margin-bottom: 10px; }
        .delivery-control-wrap .pagination .page-link { color: #174b8c; border-color: #e0e9f4; }
        .delivery-control-wrap .pagination .page-item.active .page-link { background: #174b8c; border-color: #174b8c; }
        .delivery-control-updated { color: #547097; font-size: .8rem; white-space: nowrap; }
        .delivery-control-modal .modal-content { border: 0; border-radius: 16px; box-shadow: 0 18px 50px rgba(14, 39, 78, .2); }
        .delivery-control-modal .modal-header { border-bottom-color: #e5edf7; }
        .delivery-control-modal .modal-title { color: #102f61; font-weight: 800; }
        .delivery-control-modal .modal-body { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 13px; }
        .delivery-control-detail { padding: 11px 13px; border: 1px solid #e4ebf4; border-radius: 10px; background: #f8fafd; }
        .delivery-control-detail span { display: block; color: #667f9f; font-size: .7rem; font-weight: 750; text-transform: uppercase; }
        .delivery-control-detail strong { display: block; color: #163965; margin-top: 3px; overflow-wrap: anywhere; }
        @media (max-width: 991.98px) {
            .delivery-control-heading-actions { margin-top: 13px; width: 100%; justify-content: space-between; }
        }
        @media (max-width: 767.98px) {
            .delivery-control-heading { align-items: flex-start !important; }
            .delivery-control-heading h1 { font-size: 1.25rem; }
            .delivery-control-heading p { font-size: .8rem; }
            .delivery-control-heading-icon { width: 48px; height: 48px; flex-basis: 48px; border-radius: 14px; margin-right: 11px; }
            .delivery-control-heading-actions { align-items: flex-start; gap: 10px; flex-direction: column; }
            .delivery-control-billing-link { padding: 8px 13px; }
            .delivery-control-wrap .delivery-control-kpi { min-height: 82px; padding: 11px; gap: 9px; border-radius: 12px; }
            .delivery-control-wrap .delivery-control-kpi-icon { width: 43px; height: 43px; flex-basis: 43px; border-radius: 11px; font-size: 1.15rem; }
            .delivery-control-wrap .delivery-control-kpi-label { font-size: .67rem; }
            .delivery-control-wrap .delivery-control-kpi-value { font-size: 1.45rem; }
            .delivery-control-wrap .delivery-control-kpi-arrow { display: none; }
            .delivery-control-wrap .delivery-control-filters { padding: 14px 14px 5px; }
            .delivery-control-wrap .delivery-control-table-card { border-radius: 12px; }
            .delivery-control-wrap .delivery-control-card-heading { padding: 13px 14px; gap: 8px; }
            .delivery-control-wrap .delivery-control-card-heading strong { font-size: .91rem; }
            .delivery-control-wrap .delivery-control-card-heading p { max-width: 220px; }
            .delivery-control-modal .modal-body { grid-template-columns: 1fr; }
        }
    </style>

    @php
        $paymentData = static function ($solicitud) {
            $payment = strtolower(trim((string) ($solicitud->forma_pago ?? '')));
            if ($payment === 'pagado') return ['Pagado', 'badge-paid', 'fas fa-check-circle'];
            if ($payment === 'destino' || $solicitud->pago_destinatario) return ['Por cobrar', 'badge-destination', 'fas fa-map-marker-alt'];
            if ($payment === 'origen') return ['Pendiente de pago', 'badge-destination', 'fas fa-credit-card'];
            return ['Pendiente de pago', 'badge-destination', 'fas fa-credit-card'];
        };
        $stateData = static function (string $state): array {
            $normalized = mb_strtolower(\Illuminate\Support\Str::ascii($state));
            if (str_contains($normalized, 'entregado') || str_contains($normalized, 'entrega completada')) return ['badge-paid', 'fas fa-check-circle'];
            if (str_contains($normalized, 'transit') || str_contains($normalized, 'distrib') || str_contains($normalized, 'en entrega')) return ['badge-transit', 'fas fa-truck'];
            if (str_contains($normalized, 'prepar') || str_contains($normalized, 'almacen')) return ['badge-preparing', 'fas fa-cube'];
            if (str_contains($normalized, 'solicitud') || str_contains($normalized, 'registro')) return ['badge-process', 'far fa-clock'];
            if (str_contains($normalized, 'devuelt') || str_contains($normalized, 'rechaz') || str_contains($normalized, 'anulad')) return ['badge-error', 'fas fa-exclamation-triangle'];
            return ['badge-muted', 'fas fa-box'];
        };
        $invoiceData = static function ($source) {
            $invoice = is_array($source)
                ? $source
                : (array) ($source->getAttribute('_control_invoice') ?? []);
            $status = strtoupper((string) ($invoice['status'] ?? 'SIN_DATO'));
            return match ($status) {
                'FACTURADA', 'PROCESADA' => ['Facturada', 'badge-paid', 'fas fa-file-invoice-dollar'],
                'PENDIENTE', 'EMITIENDO', 'EMISION_INCIERTA' => ['En proceso', 'badge-process', 'fas fa-hourglass-half'],
                'ERROR', 'RECHAZADA' => ['Requiere revisión', 'badge-error', 'fas fa-exclamation-circle'],
                'ANULADA', 'ANULADO' => ['Anulada', 'badge-muted', 'fas fa-ban'],
                'NO_VERIFICADA' => ['No verificada', 'badge-muted', 'fas fa-question-circle'],
                'NO_APLICA' => ['No facturada', 'badge-muted', 'fas fa-file-invoice'],
                default => ['Sin dato', 'badge-muted', 'fas fa-file-alt'],
            };
        };
    @endphp

    <div class="delivery-control-wrap">
        @if ($hasInvoiceLookupError)
            <div class="alert alert-warning border-0 shadow-sm">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Facturación no devolvió el estado de algunas facturas. Esas filas aparecen como “Sin dato”.
            </div>
        @endif

        <div class="row mb-3">
            <div class="col-6 col-xl-3 mb-2">
                <div class="delivery-control-kpi delivery-control-kpi--blue">
                    <span class="delivery-control-kpi-icon"><i class="fas fa-file-alt"></i></span>
                    <div><div class="delivery-control-kpi-label">Solicitudes</div><div class="delivery-control-kpi-value">{{ number_format($summary['total']) }}</div></div>
                    <i class="fas fa-chevron-right delivery-control-kpi-arrow"></i>
                </div>
            </div>
            <div class="col-6 col-xl-3 mb-2">
                <div class="delivery-control-kpi delivery-control-kpi--green">
                    <span class="delivery-control-kpi-icon"><i class="fas fa-qrcode"></i></span>
                    <div><div class="delivery-control-kpi-label">Pagados por QR</div><div class="delivery-control-kpi-value">{{ number_format($summary['qr']) }}</div></div>
                    <i class="fas fa-chevron-right delivery-control-kpi-arrow"></i>
                </div>
            </div>
            <div class="col-6 col-xl-3 mb-2">
                <div class="delivery-control-kpi delivery-control-kpi--amber">
                    <span class="delivery-control-kpi-icon"><i class="fas fa-credit-card"></i></span>
                    <div><div class="delivery-control-kpi-label">Pendientes de pago</div><div class="delivery-control-kpi-value">{{ number_format($summary['pending']) }}</div></div>
                    <i class="fas fa-chevron-right delivery-control-kpi-arrow"></i>
                </div>
            </div>
            <div class="col-6 col-xl-3 mb-2">
                <div class="delivery-control-kpi delivery-control-kpi--red">
                    <span class="delivery-control-kpi-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <div><div class="delivery-control-kpi-label">QR sin envío vinculado</div><div class="delivery-control-kpi-value">{{ number_format(count($qrSinSolicitud)) }}</div></div>
                    <i class="fas fa-chevron-right delivery-control-kpi-arrow"></i>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('delivery-express.paquetes-faro.index') }}" class="delivery-control-filters mb-3">
            <div class="form-row align-items-end">
                <div class="form-group col-12 col-lg-4 mb-2">
                    <label for="delivery-control-q">Buscar</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span></div>
                        <input id="delivery-control-q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Código, cliente, remitente o destinatario">
                    </div>
                </div>
                <div class="form-group col-6 col-lg-2 mb-2">
                    <label for="delivery-control-pago">Estado de pago</label>
                    <select id="delivery-control-pago" name="pago" class="form-control">
                        <option value="todos" @selected($filters['pago'] === 'todos')>Todos</option>
                        <option value="qr" @selected($filters['pago'] === 'qr')>Pagado por QR</option>
                        <option value="pendiente" @selected($filters['pago'] === 'pendiente')>Pendiente de pago</option>
                        <option value="destino" @selected($filters['pago'] === 'destino')>Por cobrar</option>
                    </select>
                </div>
                <div class="form-group col-6 col-lg-2 mb-2">
                    <label for="delivery-control-estado">Estado del envío</label>
                    <select id="delivery-control-estado" name="estado" class="form-control">
                        <option value="">Todos</option>
                        @foreach ($estados as $estado)
                            <option value="{{ $estado }}" @selected(mb_strtoupper($filters['estado']) === mb_strtoupper($estado))>{{ $estado }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-6 col-lg-1 mb-2">
                    <label for="delivery-control-desde">Desde</label>
                    <input id="delivery-control-desde" type="date" name="fecha_desde" value="{{ $filters['fecha_desde'] }}" class="form-control">
                </div>
                <div class="form-group col-6 col-lg-1 mb-2">
                    <label for="delivery-control-hasta">Hasta</label>
                    <input id="delivery-control-hasta" type="date" name="fecha_hasta" value="{{ $filters['fecha_hasta'] }}" class="form-control">
                </div>
                <div class="form-group col-12 col-lg-2 mb-2 d-flex">
                    <button type="submit" class="btn btn-warning flex-fill mr-2"><i class="fas fa-filter mr-1"></i>Buscar</button>
                    <a href="{{ route('delivery-express.paquetes-faro.index') }}" class="btn delivery-control-clear" title="Limpiar búsqueda y filtros" aria-label="Limpiar búsqueda y filtros"><i class="fas fa-undo"></i></a>
                </div>
            </div>
        </form>

        @if (count($qrSinSolicitud) > 0)
            <div class="delivery-control-table-card mb-3">
                <div class="delivery-control-card-heading">
                    <div>
                        <strong><i class="fas fa-qrcode text-primary mr-2"></i>Pagos QR sin envío vinculado</strong>
                        <p>Pagos confirmados o pendientes que aún no aparecen como solicitud.</p>
                    </div>
                    <span class="badge badge-light border">{{ count($qrSinSolicitud) }} registros</span>
                </div>
                <div class="table-responsive">
                    <table class="table delivery-control-table">
                        <thead>
                            <tr>
                                <th>Referencia QR</th>
                                <th>Cliente</th>
                                <th>Ruta</th>
                                <th>Paquetes</th>
                                <th>Pago</th>
                                <th>Factura</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($qrSinSolicitud as $qrRow)
                                @php
                                    $qrPaid = $qrRow['payment_status'] === 'pagado';
                                    [$qrInvoiceLabel, $qrInvoiceClass, $qrInvoiceIcon] = $invoiceData($qrRow['invoice']);
                                @endphp
                                <tr>
                                    <td>
                                        <span class="delivery-control-code">{{ $qrRow['code'] }}</span>
                                        <span class="delivery-control-secondary">{{ $qrRow['created_at']?->format('d/m/Y H:i') ?: '—' }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ $qrRow['client_name'] }}</strong>
                                        <span class="delivery-control-secondary">{{ $qrRow['client_code'] }}</span>
                                    </td>
                                    <td class="delivery-control-route"><span>{{ $qrRow['origin'] }}</span><i class="fas fa-long-arrow-alt-right"></i><span>{{ $qrRow['destination'] }}</span></td>
                                    <td>
                                        <strong>{{ number_format($qrRow['quantity']) }} {{ $qrRow['quantity'] === 1 ? 'paquete' : 'paquetes' }}</strong>
                                        <span class="delivery-control-secondary">Bs {{ \App\Support\BolivianNumber::format((float) $qrRow['amount'], 2, '.', '') }}</span>
                                    </td>
                                    <td>
                                        <span class="delivery-control-badge {{ $qrPaid ? 'badge-paid' : 'badge-process' }}">
                                            <i class="{{ $qrPaid ? 'fas fa-check-circle' : 'fas fa-credit-card' }}"></i>
                                            {{ $qrPaid ? 'Pagado por QR' : 'Pendiente de pago' }}
                                        </span>
                                        @if ($qrPaid && $qrRow['created_at'])
                                            <span class="delivery-control-secondary">{{ $qrRow['created_at']->format('d/m/Y H:i') }} · falta registrar envío</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="delivery-control-badge {{ $qrInvoiceClass }}"><i class="{{ $qrInvoiceIcon }}"></i>{{ $qrInvoiceLabel }}</span>
                                        @if (!empty($qrRow['invoice']['number']))
                                            <span class="delivery-control-secondary">N.º {{ $qrRow['invoice']['number'] }}</span>
                                        @endif
                                        @if (!empty($qrRow['invoice']['message']))
                                            <span class="delivery-control-secondary">{{ $qrRow['invoice']['message'] }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if (($qrRow['invoice']['status'] ?? '') === 'FACTURADA' && (int) ($qrRow['invoice']['cart_id'] ?? 0) > 0)
                                            <a class="btn btn-sm btn-outline-primary delivery-control-invoice-action" href="{{ route('delivery-express.paquetes-faro.invoice', ['cliente' => $qrRow['client_id'], 'cart' => $qrRow['invoice']['cart_id']]) }}" target="_blank" rel="noopener" aria-label="Reimprimir factura {{ $qrRow['invoice']['number'] ?? '' }}">
                                                <i class="fas fa-print mr-1"></i>Reimprimir
                                            </a>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="delivery-control-table-card">
            <div class="delivery-control-card-heading">
                <div>
                    <strong><i class="fas fa-boxes text-primary mr-2"></i>Solicitudes de envío</strong>
                    <p>Consulta el estado de cada paquete y sus datos de pago y factura.</p>
                </div>
                <span class="badge badge-light border">{{ number_format($solicitudes->total()) }} registros</span>
            </div>
            @if ($solicitudes->isEmpty())
                <div class="delivery-control-empty">
                    <div><i class="fas fa-box-open"></i></div>
                    <strong>No hay solicitudes que coincidan con estos filtros.</strong>
                    <div class="small mt-1">Prueba con otro texto o limpia los filtros.</div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table delivery-control-table">
                        <thead>
                            <tr>
                                <th>Solicitud</th>
                                <th>Cliente</th>
                                <th>Ruta</th>
                                <th>Paquetes</th>
                                <th>Estado del envío</th>
                                <th>Pago</th>
                                <th>Factura</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($solicitudes as $solicitud)
                                @php
                                    [$paymentLabel, $paymentClass, $paymentIcon] = $paymentData($solicitud);
                                    [$invoiceLabel, $invoiceClass, $invoiceIcon] = $invoiceData($solicitud);
                                    $serviceName = $solicitud->servicioExtra?->descripcion ?: ($solicitud->servicioExtra?->nombre ?? 'Delivery Express');
                                    $stateName = $solicitud->estadoRegistro?->nombre_estado ?: 'Sin estado';
                                    [$stateClass, $stateIcon] = $stateData($stateName);
                                    $invoice = (array) $solicitud->getAttribute('_control_invoice');
                                    $routeLabel = ($solicitud->origen ?: '—').' → '.($solicitud->destino?->nombre_destino ?: ($solicitud->ciudad ?: '—'));
                                    $invoiceDownloadUrl = ($invoice['status'] ?? '') === 'FACTURADA' && (int) ($invoice['cart_id'] ?? 0) > 0 && $solicitud->cliente_id
                                        ? route('delivery-express.paquetes-faro.invoice', ['cliente' => $solicitud->cliente_id, 'cart' => $invoice['cart_id']])
                                        : '';
                                @endphp
                                <tr>
                                    <td>
                                        <span class="delivery-control-code">{{ $solicitud->codigo_solicitud ?: $solicitud->barcode ?: 'Sin código' }}</span>
                                        <span class="delivery-control-secondary">{{ optional($solicitud->created_at)->format('d/m/Y H:i') ?: '—' }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ $solicitud->cliente?->name ?: 'Cliente no identificado' }}</strong>
                                        <span class="delivery-control-secondary">{{ $solicitud->cliente?->codigo_cliente ?: $solicitud->cliente?->email ?: '—' }}</span>
                                    </td>
                                    <td class="delivery-control-route"><span>{{ $solicitud->origen ?: '—' }}</span><i class="fas fa-long-arrow-alt-right"></i><span>{{ $solicitud->destino?->nombre_destino ?: $solicitud->ciudad ?: '—' }}</span></td>
                                    <td>
                                        <strong>{{ number_format((int) $solicitud->cantidad) }}</strong>
                                        <span class="delivery-control-secondary">Bs {{ \App\Support\BolivianNumber::format((float) ($solicitud->precio ?? 0), 2, '.', '') }}</span>
                                    </td>
                                    <td><span class="delivery-control-badge {{ $stateClass }}"><i class="{{ $stateIcon }}"></i>{{ $stateName }}</span></td>
                                    <td>
                                        <span class="delivery-control-badge {{ $paymentClass }}"><i class="{{ $paymentIcon }}"></i>{{ $paymentLabel }}</span>
                                    </td>
                                    <td>
                                        <span class="delivery-control-badge {{ $invoiceClass }}"><i class="{{ $invoiceIcon }}"></i>{{ $invoiceLabel }}</span>
                                        @if (!empty($invoice['number']))
                                            <span class="delivery-control-secondary">N.º {{ $invoice['number'] }}</span>
                                        @endif
                                        @if (!empty($invoice['message']))
                                            <span class="delivery-control-secondary">{{ $invoice['message'] }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="delivery-control-actions">
                                            <button type="button" class="btn btn-sm btn-outline-primary delivery-control-view-action"
                                                data-toggle="modal" data-target="#deliveryRequestDetails"
                                                data-code="{{ $solicitud->codigo_solicitud ?: $solicitud->barcode ?: 'Sin código' }}"
                                                data-created="{{ optional($solicitud->created_at)->format('d/m/Y H:i') ?: '—' }}"
                                                data-client="{{ $solicitud->cliente?->name ?: 'Cliente no identificado' }}"
                                                data-client-code="{{ $solicitud->cliente?->codigo_cliente ?: $solicitud->cliente?->email ?: '—' }}"
                                                data-service="{{ $serviceName }}"
                                                data-route="{{ $routeLabel }}"
                                                data-sender="{{ $solicitud->nombre_remitente ?: '—' }}"
                                                data-sender-phone="{{ $solicitud->telefono_remitente ?: '—' }}"
                                                data-recipient="{{ $solicitud->nombre_destinatario ?: '—' }}"
                                                data-recipient-phone="{{ $solicitud->telefono_destinatario ?: '—' }}"
                                                data-content="{{ $solicitud->contenido ?: '—' }}"
                                                data-quantity="{{ number_format((int) $solicitud->cantidad) }}"
                                                data-amount="Bs {{ \App\Support\BolivianNumber::format((float) ($solicitud->precio ?? 0), 2, '.', '') }}"
                                                data-state="{{ $stateName }}"
                                                data-payment="{{ $paymentLabel }}"
                                                data-invoice="{{ $invoiceLabel }}"
                                                data-invoice-url="{{ $invoiceDownloadUrl }}">
                                                <i class="fas fa-eye mr-1"></i>Ver
                                            </button>
                                            @if ($invoiceDownloadUrl !== '')
                                            <a class="btn btn-sm btn-outline-secondary delivery-control-invoice-action" href="{{ $invoiceDownloadUrl }}" target="_blank" rel="noopener" aria-label="Reimprimir factura {{ $invoice['number'] ?? '' }}" title="Reimprimir factura">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-3 py-2 border-top d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                    <small class="text-muted mb-2 mb-md-0">Mostrando {{ $solicitudes->firstItem() }}–{{ $solicitudes->lastItem() }} de {{ $solicitudes->total() }} solicitudes.</small>
                    {{ $solicitudes->links() }}
                </div>
            @endif
        </div>

        <div class="modal fade delivery-control-modal" id="deliveryRequestDetails" tabindex="-1" role="dialog" aria-labelledby="deliveryRequestDetailsTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="deliveryRequestDetailsTitle">Detalle de solicitud</h5>
                            <div class="delivery-control-secondary" id="deliveryRequestDetailsCode">—</div>
                        </div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="delivery-control-detail"><span>Cliente</span><strong data-detail="client">—</strong><small data-detail="client-code"></small></div>
                        <div class="delivery-control-detail"><span>Fecha de solicitud</span><strong data-detail="created">—</strong></div>
                        <div class="delivery-control-detail"><span>Servicio</span><strong data-detail="service">—</strong></div>
                        <div class="delivery-control-detail"><span>Ruta</span><strong data-detail="route">—</strong></div>
                        <div class="delivery-control-detail"><span>Remitente</span><strong data-detail="sender">—</strong><small data-detail="sender-phone"></small></div>
                        <div class="delivery-control-detail"><span>Destinatario</span><strong data-detail="recipient">—</strong><small data-detail="recipient-phone"></small></div>
                        <div class="delivery-control-detail"><span>Contenido</span><strong data-detail="content">—</strong></div>
                        <div class="delivery-control-detail"><span>Paquetes e importe</span><strong><span data-detail="quantity">—</span> · <span data-detail="amount">—</span></strong></div>
                        <div class="delivery-control-detail"><span>Estado del envío</span><strong data-detail="state">—</strong></div>
                        <div class="delivery-control-detail"><span>Pago</span><strong data-detail="payment">—</strong></div>
                        <div class="delivery-control-detail"><span>Factura</span><strong data-detail="invoice">—</strong></div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <a href="#" id="deliveryRequestDetailsInvoice" class="btn btn-outline-primary d-none" target="_blank" rel="noopener"><i class="fas fa-print mr-1"></i>Reimprimir factura</a>
                        <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

@stop

@section('js')
    <script>
        (function ($) {
            $('#deliveryRequestDetails').on('show.bs.modal', function (event) {
                var trigger = $(event.relatedTarget);
                var modal = $(this);
                modal.find('#deliveryRequestDetailsCode').text(trigger.attr('data-code') || '—');
                modal.find('[data-detail]').each(function () {
                    var field = $(this).attr('data-detail');
                    $(this).text(trigger.attr('data-' + field) || '—');
                });
                var invoiceUrl = trigger.attr('data-invoice-url') || '';
                var invoiceButton = modal.find('#deliveryRequestDetailsInvoice');
                if (invoiceUrl) {
                    invoiceButton.attr('href', invoiceUrl).removeClass('d-none');
                } else {
                    invoiceButton.attr('href', '#').addClass('d-none');
                }
            });
        })(jQuery);
    </script>
@stop
