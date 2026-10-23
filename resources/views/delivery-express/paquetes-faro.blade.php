@extends('adminlte::page')

@section('title', 'Paquetes Delivery Express por Faro')

@section('content_header')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between">
        <div>
            <h1 class="mb-1">Paquetes Delivery Express por Faro</h1>
            <p class="text-muted mb-0">Control general de solicitudes, pagos y facturación.</p>
        </div>
        <span class="delivery-control-updated"><i class="far fa-clock mr-1"></i>Actualizado {{ now()->timezone('America/La_Paz')->format('d/m/Y H:i') }}</span>
    </div>
@stop

@section('content')
    <style>
        .delivery-control-wrap { --delivery-ink: #17324d; --delivery-muted: #718096; --delivery-line: #e6edf3; }
        .delivery-control-wrap .delivery-control-kpi { border: 1px solid var(--delivery-line); border-radius: 14px; background: #fff; padding: 17px 19px; height: 100%; box-shadow: 0 5px 18px rgba(22, 50, 77, .045); }
        .delivery-control-wrap .delivery-control-kpi-label { color: var(--delivery-muted); font-size: .75rem; font-weight: 700; letter-spacing: .055em; text-transform: uppercase; }
        .delivery-control-wrap .delivery-control-kpi-value { color: var(--delivery-ink); font-size: 1.7rem; line-height: 1.2; font-weight: 800; margin-top: 5px; }
        .delivery-control-wrap .delivery-control-kpi-icon { float: right; color: #b6c9d9; font-size: 1.2rem; margin-top: 2px; }
        .delivery-control-wrap .delivery-control-filters { border: 1px solid var(--delivery-line); border-radius: 14px; background: #fff; padding: 18px; box-shadow: 0 5px 18px rgba(22, 50, 77, .035); }
        .delivery-control-wrap .delivery-control-filters label { color: #526579; font-size: .75rem; font-weight: 700; margin-bottom: 5px; }
        .delivery-control-wrap .delivery-control-table-card { border: 1px solid var(--delivery-line); border-radius: 14px; overflow: hidden; background: #fff; box-shadow: 0 5px 18px rgba(22, 50, 77, .04); }
        .delivery-control-wrap .delivery-control-table { margin: 0; min-width: 1280px; }
        .delivery-control-wrap .delivery-control-table thead th { background: #f5f8fb; border-top: 0; border-bottom: 1px solid var(--delivery-line); color: #53677b; font-size: .69rem; letter-spacing: .045em; text-transform: uppercase; white-space: nowrap; padding: 12px 11px; }
        .delivery-control-wrap .delivery-control-table td { border-top: 1px solid #edf2f6; color: #31465a; font-size: .82rem; padding: 12px 11px; vertical-align: middle; }
        .delivery-control-wrap .delivery-control-table tbody tr:hover { background: #fbfdff; }
        .delivery-control-wrap .delivery-control-code { color: #17324d; font-weight: 800; white-space: nowrap; }
        .delivery-control-wrap .delivery-control-secondary { display: block; color: var(--delivery-muted); font-size: .72rem; margin-top: 3px; }
        .delivery-control-wrap .delivery-control-route { min-width: 150px; font-weight: 700; white-space: nowrap; }
        .delivery-control-wrap .delivery-control-route i { color: #8aa0b5; margin: 0 5px; }
        .delivery-control-wrap .delivery-control-badge { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 5px 9px; font-size: .7rem; line-height: 1.2; font-weight: 800; white-space: nowrap; }
        .delivery-control-wrap .badge-paid { background: #e8f7ef; color: #167348; }
        .delivery-control-wrap .badge-destination { background: #fff4dc; color: #986600; }
        .delivery-control-wrap .badge-process { background: #eaf2fc; color: #285d91; }
        .delivery-control-wrap .badge-error { background: #fff0ef; color: #a33830; }
        .delivery-control-wrap .badge-muted { background: #eef2f5; color: #627488; }
        .delivery-control-wrap .delivery-control-empty { padding: 46px 20px; text-align: center; color: var(--delivery-muted); }
        .delivery-control-wrap .delivery-control-empty i { color: #afc0cf; font-size: 2rem; margin-bottom: 10px; }
        .delivery-control-updated { color: #718096; font-size: .78rem; padding-top: 6px; }
        @media (max-width: 767.98px) {
            .delivery-control-wrap .delivery-control-kpi { padding: 13px 15px; }
            .delivery-control-wrap .delivery-control-kpi-value { font-size: 1.4rem; }
        }
    </style>

    @php
        $paymentData = static function ($solicitud) {
            $payment = strtolower(trim((string) ($solicitud->forma_pago ?? '')));
            if ($payment === 'pagado') return ['Pagado por QR', 'badge-paid', 'fas fa-qrcode'];
            if ($payment === 'destino' || $solicitud->pago_destinatario) return ['Pagar en destino', 'badge-destination', 'fas fa-map-marker-alt'];
            if ($payment === 'origen') return ['Pagar en origen', 'badge-destination', 'fas fa-store'];
            return ['Sin clasificar', 'badge-muted', 'fas fa-question-circle'];
        };
        $invoiceData = static function ($source) {
            $invoice = is_array($source)
                ? $source
                : (array) ($source->getAttribute('_control_invoice') ?? []);
            $status = strtoupper((string) ($invoice['status'] ?? 'SIN_DATO'));
            $number = trim((string) ($invoice['number'] ?? ''));
            return match ($status) {
                'FACTURADA', 'PROCESADA' => ['Facturada' . ($number !== '' ? ' · ' . $number : ''), 'badge-paid', 'fas fa-file-invoice-dollar'],
                'PENDIENTE', 'EMITIENDO', 'EMISION_INCIERTA' => ['En proceso', 'badge-process', 'fas fa-hourglass-half'],
                'ERROR', 'RECHAZADA' => ['Requiere revisión', 'badge-error', 'fas fa-exclamation-circle'],
                'ANULADA', 'ANULADO' => ['Anulada', 'badge-muted', 'fas fa-ban'],
                'NO_VERIFICADA' => ['No verificada', 'badge-muted', 'fas fa-question-circle'],
                'NO_APLICA' => ['No facturada', 'badge-destination', 'fas fa-file-invoice'],
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
            <div class="col-6 col-lg-3 mb-2">
                <div class="delivery-control-kpi">
                    <span class="delivery-control-kpi-icon"><i class="fas fa-boxes"></i></span>
                    <div class="delivery-control-kpi-label">Solicitudes encontradas</div>
                    <div class="delivery-control-kpi-value">{{ number_format($summary['total']) }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-2">
                <div class="delivery-control-kpi">
                    <span class="delivery-control-kpi-icon"><i class="fas fa-qrcode"></i></span>
                    <div class="delivery-control-kpi-label">Pagadas por QR</div>
                    <div class="delivery-control-kpi-value">{{ number_format($summary['qr']) }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-2">
                <div class="delivery-control-kpi">
                    <span class="delivery-control-kpi-icon"><i class="fas fa-map-marker-alt"></i></span>
                    <div class="delivery-control-kpi-label">Pago en destino</div>
                    <div class="delivery-control-kpi-value">{{ number_format($summary['destino']) }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-2">
                <div class="delivery-control-kpi">
                    <span class="delivery-control-kpi-icon"><i class="fas fa-qrcode"></i></span>
                    <div class="delivery-control-kpi-label">QR sin envío vinculado</div>
                    <div class="delivery-control-kpi-value">{{ number_format(count($qrSinSolicitud)) }}</div>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('delivery-express.paquetes-faro.index') }}" class="delivery-control-filters mb-3">
            <div class="form-row align-items-end">
                <div class="form-group col-12 col-lg-4 mb-2">
                    <label for="delivery-control-q">Buscar</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span></div>
                        <input id="delivery-control-q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Código, cliente, remitente, destinatario, ciudad...">
                    </div>
                </div>
                <div class="form-group col-6 col-lg-2 mb-2">
                    <label for="delivery-control-pago">Forma de pago</label>
                    <select id="delivery-control-pago" name="pago" class="form-control">
                        <option value="todos" @selected($filters['pago'] === 'todos')>Todos</option>
                        <option value="qr" @selected($filters['pago'] === 'qr')>Pagado por QR</option>
                        <option value="destino" @selected($filters['pago'] === 'destino')>Pagar en destino</option>
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
                    <button type="submit" class="btn btn-primary flex-fill mr-2"><i class="fas fa-filter mr-1"></i>Filtrar</button>
                    <a href="{{ route('delivery-express.paquetes-faro.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros"><i class="fas fa-undo"></i></a>
                </div>
            </div>
        </form>

        @if (count($qrSinSolicitud) > 0)
            <div class="delivery-control-table-card mb-3">
                <div class="px-3 py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <strong class="text-dark"><i class="fas fa-qrcode text-primary mr-2"></i>Pagos QR sin envío vinculado</strong>
                        <div class="small text-muted mt-1">Intentos visibles en el historial del cliente que todavía no tienen una solicitud registrada.</div>
                    </div>
                    <span class="badge badge-light border">{{ count($qrSinSolicitud) }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table delivery-control-table">
                        <thead>
                            <tr>
                                <th>Referencia QR</th>
                                <th>Cliente</th>
                                <th>Servicio</th>
                                <th>Ruta</th>
                                <th>Paquetes / importe</th>
                                <th>Pago</th>
                                <th>Factura</th>
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
                                    <td>{{ $qrRow['service'] }}</td>
                                    <td class="delivery-control-route">{{ $qrRow['origin'] }}<i class="fas fa-long-arrow-alt-right"></i>{{ $qrRow['destination'] }}</td>
                                    <td>
                                        <strong>{{ number_format($qrRow['quantity']) }} {{ $qrRow['quantity'] === 1 ? 'paquete' : 'paquetes' }}</strong>
                                        <span class="delivery-control-secondary">Bs {{ \App\Support\BolivianNumber::format((float) $qrRow['amount'], 2, '.', '') }}</span>
                                    </td>
                                    <td>
                                        <span class="delivery-control-badge {{ $qrPaid ? 'badge-paid' : 'badge-process' }}">
                                            <i class="{{ $qrPaid ? 'fas fa-check-circle' : 'fas fa-hourglass-half' }}"></i>
                                            {{ $qrPaid ? 'Pagado por QR · falta registrar envío' : 'QR pendiente de pago' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="delivery-control-badge {{ $qrInvoiceClass }}"><i class="{{ $qrInvoiceIcon }}"></i>{{ $qrInvoiceLabel }}</span>
                                        @if (!empty($qrRow['invoice']['message']))
                                            <span class="delivery-control-secondary">{{ $qrRow['invoice']['message'] }}</span>
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
                                <th>Servicio</th>
                                <th>Ruta</th>
                                <th>Remitente / destinatario</th>
                                <th>Paquetes</th>
                                <th>Estado del envío</th>
                                <th>Pago</th>
                                <th>Factura</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($solicitudes as $solicitud)
                                @php
                                    [$paymentLabel, $paymentClass, $paymentIcon] = $paymentData($solicitud);
                                    [$invoiceLabel, $invoiceClass, $invoiceIcon] = $invoiceData($solicitud);
                                    $serviceName = $solicitud->servicioExtra?->descripcion ?: ($solicitud->servicioExtra?->nombre ?? 'Delivery Express');
                                    $stateName = $solicitud->estadoRegistro?->nombre_estado ?: 'Sin estado';
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
                                    <td>{{ $serviceName }}</td>
                                    <td class="delivery-control-route">{{ $solicitud->origen ?: '—' }}<i class="fas fa-long-arrow-alt-right"></i>{{ $solicitud->destino?->nombre_destino ?: $solicitud->ciudad ?: '—' }}</td>
                                    <td>
                                        {{ $solicitud->nombre_remitente ?: '—' }}
                                        <span class="delivery-control-secondary">{{ $solicitud->nombre_destinatario ?: '—' }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ number_format((int) $solicitud->cantidad) }}</strong>
                                        <span class="delivery-control-secondary">Bs {{ \App\Support\BolivianNumber::format((float) ($solicitud->precio ?? 0), 2, '.', '') }}</span>
                                    </td>
                                    <td><span class="delivery-control-badge badge-muted">{{ $stateName }}</span></td>
                                    <td><span class="delivery-control-badge {{ $paymentClass }}"><i class="{{ $paymentIcon }}"></i>{{ $paymentLabel }}</span></td>
                                    <td>
                                        <span class="delivery-control-badge {{ $invoiceClass }}"><i class="{{ $invoiceIcon }}"></i>{{ $invoiceLabel }}</span>
                                        @if (!empty($solicitud->getAttribute('_control_invoice')['message']))
                                            <span class="delivery-control-secondary">{{ $solicitud->getAttribute('_control_invoice')['message'] }}</span>
                                        @endif
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
    </div>
@stop
