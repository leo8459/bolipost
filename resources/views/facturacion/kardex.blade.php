@extends('adminlte::page')

@section('title', 'Kardex de facturación')

@section('content_header')
@stop

@section('content')
    @php
        $baseParams = [
            'q' => $filters['q'] ?? '',
            'estado_emision' => $filters['estado_emision'] ?? 'all',
            'from' => $filters['from'] ?? now()->toDateString(),
            'to' => $filters['to'] ?? now()->toDateString(),
        ];
    @endphp

    <div class="kardex-page">
        @if(!empty($kardexError))
            <div class="alert alert-warning">{{ $kardexError }}</div>
        @endif

        <form method="GET" action="{{ route('mis-ventas.kardex') }}" id="kardexReportForm" class="card kardex-filter-card">
            <div class="card-body">
                <input type="hidden" name="scope" value="own">
                <div class="row align-items-end">
                    <div class="col-lg-3 col-md-6 mb-3"><label for="from">Desde</label><input type="date" id="from" name="from" value="{{ $baseParams['from'] }}" class="form-control"></div>
                    <div class="col-lg-3 col-md-6 mb-3"><label for="to">Hasta</label><input type="date" id="to" name="to" value="{{ $baseParams['to'] }}" class="form-control"></div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label for="estado_emision">Estado factura</label>
                        <select id="estado_emision" name="estado_emision" class="form-control">
                            @foreach(['all' => 'Todos', 'FACTURADA' => 'Facturada', 'PENDIENTE' => 'Pendiente', 'RECHAZADA' => 'Rechazada', 'ANULADA' => 'Anulada', 'PENDIENTE_ANULACION' => 'Pendiente de anulación', 'ANULACION_OBSERVADA' => 'Anulación observada', 'NO_APLICA' => 'No aplica QR', 'ERROR' => 'Error'] as $value => $label)
                                <option value="{{ $value }}" @selected($baseParams['estado_emision'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3"><label for="q">Buscar</label><input type="search" id="q" name="q" value="{{ $baseParams['q'] }}" class="form-control" placeholder="Factura, guía o destino"></div>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center mt-2">
                    <div class="kardex-downloads">
                        <button type="submit" formaction="{{ route('mis-ventas.kardex.export.pdf') }}" class="btn btn-danger"><i class="fas fa-file-pdf mr-1"></i> Kardex PDF</button>
                        <button type="submit" formaction="{{ route('mis-ventas.kardex.export.excel') }}" class="btn btn-success"><i class="fas fa-file-excel mr-1"></i> Kardex Excel</button>
                        <button type="submit" formaction="{{ route('mis-ventas.export.pdf') }}" class="btn btn-outline-primary"><i class="fas fa-file-pdf mr-1"></i> PDF Mis ventas</button>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <a href="{{ route('mis-ventas.kardex') }}" class="btn btn-outline-secondary">Limpiar</a>
                        <button type="submit" formaction="{{ route('mis-ventas.kardex') }}" class="btn btn-primary"><i class="fas fa-search mr-1"></i> Filtrar</button>
                    </div>
                </div>
            </div>
        </form>

        <div class="row kardex-summary-row">
            <div class="col-md-4 col-sm-6 mb-3"><div class="kardex-summary-card kardex-summary-card--sales"><span>Total ventas</span><strong>{{ number_format($totalVentas) }}</strong><small>Bs {{ number_format($totalImporte, 2, ',', '.') }} · importe del rango</small></div></div>
            <div class="col-md-4 col-sm-6 mb-3"><div class="kardex-summary-card kardex-summary-card--payments"><span>Efectivo</span><strong>{{ number_format($ventasEfectivo) }}</strong><small>Bs {{ number_format($montoEfectivo, 2, ',', '.') }}</small></div></div>
            <div class="col-md-4 col-sm-6 mb-3"><div class="kardex-summary-card kardex-summary-card--payments"><span>Pago QR</span><strong>{{ number_format($ventasQrPagadas) }}</strong><small>Bs {{ number_format($montoQr, 2, ',', '.') }}</small></div></div>
            <div class="col-md-4 col-sm-6 mb-3"><div class="kardex-summary-card"><span>Filas visibles</span><strong>{{ number_format($visibleRows->count()) }}</strong></div></div>
            <div class="col-md-4 col-sm-6 mb-3"><div class="kardex-summary-card"><span>Peso total</span><strong>{{ number_format($totalPeso, 3, ',', '.') }} kg</strong></div></div>
            <div class="col-md-4 col-sm-6 mb-3"><div class="kardex-summary-card"><span>Anuladas</span><strong class="text-danger">{{ number_format($totalAnuladas) }}</strong></div></div>
        </div>

        <div class="card kardex-report-card">
            <div class="card-header kardex-history-header">
                <div class="kardex-history-header__title">
                    <h3>Historial de Ventas</h3>
                    <p>Detalle de emisiones registradas para tu cuenta.</p>
                </div>
                <div class="kardex-history-header__count">
                    <strong>{{ number_format((int) $totalVentas) }} {{ (int) $totalVentas === 1 ? 'registro' : 'registros' }}</strong>
                    <span>{{ $totalVentas > 0 ? 'Mostrando 1 a ' . number_format((int) $totalVentas) : 'Sin registros visibles' }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive kardex-table-wrap">
                    <table class="table table-sm table-hover kardex-table mb-0">
                        <colgroup>
                            <col style="width:4%"><col style="width:8%"><col style="width:6%"><col style="width:6%"><col style="width:14%"><col style="width:15%"><col style="width:6%"><col style="width:16%"><col style="width:10%"><col style="width:8%"><col style="width:7%">
                        </colgroup>
                        <thead>
                            <tr><th>N°</th><th>FECHA</th><th>CANTIDAD</th><th>REGIONAL</th><th>TIPO DE CORRESPONDENCIA</th><th>CÓDIGO DE ENVÍO</th><th>PESO</th><th>PAÍS/CIUDAD DE DESTINO</th><th>OPERACIÓN</th><th>N° FACTURA</th><th>IMPORTE</th></tr>
                        </thead>
                        <tbody>
                            @forelse($visibleRows as $rowIndex => $row)
                                @php
                                    $saleGroupKey = trim((string) data_get($row, 'venta_group_key', ''));
                                    $previousSaleGroupKey = trim((string) data_get($visibleRows->get($rowIndex - 1), 'venta_group_key', ''));
                                    $nextSaleGroupKey = trim((string) data_get($visibleRows->get($rowIndex + 1), 'venta_group_key', ''));
                                    $isMultiDetailSale = $saleGroupKey !== '' && (int) data_get($row, 'venta_detalles_total', 1) > 1;
                                    $isSaleStart = $saleGroupKey !== '' && $previousSaleGroupKey !== $saleGroupKey;
                                    $isSaleGroupStart = $saleGroupKey !== '' && $previousSaleGroupKey !== $saleGroupKey;
                                    $isSaleGroupEnd = $saleGroupKey !== '' && $nextSaleGroupKey !== $saleGroupKey;

                                    $invoiceStatus = strtoupper(trim((string) data_get($row, 'estado_emision', '')));
                                    $paymentMethod = strtolower(trim((string) data_get($row, 'metodo_pago', '')));
                                    $paymentStatus = strtolower(trim((string) data_get($row, 'estado_pago', '')));
                                    $saleReference = strtoupper(trim((string) data_get($row, 'venta_referencia', '')));
                                    $isQrPayment = $paymentMethod === 'qr'
                                        || trim((string) data_get($row, 'qr_transaction_id', '')) !== ''
                                        || str_starts_with($saleReference, 'VQ-')
                                        || str_starts_with($saleReference, 'VQC-');
                                    $isOfficialSale = strtolower(trim((string) data_get($row, 'canal_emision', ''))) === 'oficial'
                                        || str_starts_with($saleReference, 'OFI-')
                                        || strtoupper(trim((string) data_get($row, 'cliente', ''))) === 'ENVIO OFICIAL';
                                    $isAnnulledSale = (bool) data_get($row, 'es_anulada');
                                    $cartId = (int) data_get($row, 'venta_id', 0);
                                    $pdfUrl = trim((string) data_get($row, 'pdf_url', ''));
                                    $invoiceNumber = trim((string) data_get($row, 'numero_factura', ''));
                                    $isProcessedWithoutInvoice = in_array($invoiceNumber, ['', '-', 'S/N'], true)
                                        && $invoiceStatus !== 'FACTURADA'
                                        && str_contains(strtoupper((string) data_get($row, 'mensaje_emision', '')), 'PROCESADO');
                                    $queryReference = $isQrPayment
                                        ? (trim((string) data_get($row, 'qr_transaction_id', '')) ?: trim((string) data_get($row, 'codigo_seguimiento', '')))
                                        : (trim((string) data_get($row, 'codigo_seguimiento_fiscal', '')) ?: trim((string) data_get($row, 'codigo_seguimiento', '')));
                                    $qrPaidStates = ['pagado', 'success', 'paid', 'completed', 'approved', 'confirmed'];
                                    $isQrPaid = in_array($paymentStatus, $qrPaidStates, true);
                                    $canQuerySale = !$isAnnulledSale
                                        && $invoiceStatus !== 'FACTURADA'
                                        && $cartId > 0
                                        && !($isQrPayment && $paymentStatus === 'cancelado')
                                        && ($queryReference !== '' || $isQrPayment || $invoiceStatus === 'PENDIENTE');
                                    $canRecoverInvoice = !$isAnnulledSale
                                        && $invoiceStatus === 'FACTURADA'
                                        && $pdfUrl === ''
                                        && $cartId > 0
                                        && $queryReference !== '';
                                    $showQueryAction = $canQuerySale || $canRecoverInvoice;
                                    $autoEmitInvoice = $isQrPayment && $isQrPaid && $invoiceStatus !== 'FACTURADA' && !$isAnnulledSale;
                                    $queryActionLabel = $canRecoverInvoice
                                        ? 'Consultar factura'
                                        : ($isQrPayment && $paymentStatus === 'pendiente'
                                            ? 'Ver QR'
                                            : ($autoEmitInvoice
                                                ? 'Emitir factura'
                                                : ($isProcessedWithoutInvoice ? 'Consultar estado' : ($invoiceStatus === 'PENDIENTE' ? 'Actualizar estado' : 'Consultar'))));
                                    $queryActionRoute = $isQrPayment && $paymentStatus === 'pendiente'
                                        ? route('facturacion.cart.ver-qr')
                                        : route('facturacion.cart.consultar');

                                    $paymentLabel = (bool) data_get($row, 'es_cuenta_por_cobrar')
                                        ? 'CUENTA POR COBRAR'
                                        : ($isOfficialSale
                                            ? 'REGISTRO OFICIAL'
                                            : ($isQrPayment ? 'QR' : 'EFECTIVO'));
                                    $paymentNote = (bool) data_get($row, 'es_cuenta_por_cobrar')
                                        ? 'No suma a caja'
                                        : ($isQrPayment
                                            ? ($paymentStatus === 'cancelado' ? 'Cancelado · fuera de caja' : ($isQrPaid ? 'Confirmado · fuera de caja' : 'Pendiente · fuera de caja'))
                                            : ($isOfficialSale ? 'Registro interno' : 'Cobro en caja'));
                                    $billingLabel = match ($invoiceStatus) {
                                        'FACTURADA' => $isQrPayment ? 'QR PAGADO Y FACTURADO' : 'FACTURADA',
                                        'PENDIENTE' => $isQrPayment && $isQrPaid ? 'QR PAGADO · FACTURA EN PROCESO' : 'PENDIENTE',
                                        'RECHAZADA' => 'RECHAZADA',
                                        'ANULADA', 'ANULADO' => 'ANULADA',
                                        'PENDIENTE_ANULACION' => 'PENDIENTE DE ANULACIÓN',
                                        'ANULACION_OBSERVADA' => 'ANULACIÓN OBSERVADA',
                                        'NO_APLICA' => $isQrPayment
                                            ? ($paymentStatus === 'cancelado' ? 'QR ANULADO' : ($isQrPaid ? 'QR PAGADO' : 'QR PENDIENTE'))
                                            : 'NO APLICA',
                                        'ERROR' => 'ERROR',
                                        default => $invoiceStatus !== '' ? str_replace('_', ' ', $invoiceStatus) : 'SIN ESTADO',
                                    };
                                    $billingClass = match (true) {
                                        $invoiceStatus === 'FACTURADA' || ($invoiceStatus === 'NO_APLICA' && $isQrPayment && $isQrPaid) => 'success',
                                        in_array($invoiceStatus, ['PENDIENTE', 'PENDIENTE_ANULACION'], true) => 'warning',
                                        in_array($invoiceStatus, ['RECHAZADA', 'ANULADA', 'ANULADO', 'ANULACION_OBSERVADA'], true) => 'danger',
                                        $invoiceStatus === 'ERROR' => 'dark',
                                        default => 'muted',
                                    };
                                    $saleStateLabel = $isQrPayment && $paymentStatus === 'cancelado'
                                        ? 'ANULADO'
                                        : strtoupper(trim((string) data_get($row, 'estado_venta_label', 'SIN ESTADO')));
                                    $saleStateClass = $isAnnulledSale
                                        ? 'danger'
                                        : (in_array($saleStateLabel, ['ANULADO', 'PENDIENTE DE PAGO', 'BORRADOR'], true)
                                            ? (str_contains($saleStateLabel, 'ANULADO') ? 'danger' : 'warning')
                                            : ($saleStateLabel === 'EMITIDO' ? 'primary' : 'muted'));
                                    $customerName = trim((string) data_get($row, 'cliente', '')) ?: 'SIN NOMBRE';
                                    $customerDocument = trim((string) data_get($row, 'numero_documento', ''));
                                @endphp
                                @if($isSaleStart)
                                    <tr class="kardex-table__sale-label {{ $saleGroupKey !== '' ? 'kardex-table__sale-label--grouped' : '' }}">
                                        <td colspan="11">
                                            <div class="kardex-sale-summary">
                                                <div class="kardex-sale-summary__identity">
                                                    <div class="kardex-sale-summary__customer-line">
                                                        <strong>{{ $customerName }}@if($customerDocument !== '') - {{ $customerDocument }}@endif</strong>
                                                    </div>
                                                </div>
                                                <div class="kardex-sale-summary__statuses">
                                                    <div class="kardex-sale-summary__item">
                                                        <span title="Método de pago" class="kardex-sale-summary__chip kardex-sale-summary__chip--{{ $isQrPayment ? 'qr' : 'neutral' }}"><i class="fas {{ (bool) data_get($row, 'es_cuenta_por_cobrar') ? 'fa-file-invoice-dollar' : ($isOfficialSale ? 'fa-clipboard-list' : ($isQrPayment ? 'fa-qrcode' : 'fa-money-bill-wave')) }}" aria-hidden="true"></i>{{ $paymentLabel }}</span>
                                                        @if(!in_array($paymentNote, ['Cobro en caja', 'Confirmado · fuera de caja'], true))<small>{{ $paymentNote }}</small>@endif
                                                    </div>
                                                    <div class="kardex-sale-summary__item">
                                                        <span title="Estado de facturación" class="kardex-sale-summary__chip kardex-sale-summary__chip--{{ $billingClass }}"><i class="fas fa-file-invoice" aria-hidden="true"></i>{{ $billingLabel }}</span>
                                                    </div>
                                                    <div class="kardex-sale-summary__item">
                                                        <span title="Estado de venta" class="kardex-sale-summary__chip kardex-sale-summary__chip--{{ $saleStateClass }}"><i class="fas {{ $saleStateClass === 'danger' ? 'fa-times-circle' : ($saleStateClass === 'warning' ? 'fa-clock' : 'fa-check-circle') }}" aria-hidden="true"></i>{{ $saleStateLabel }}</span>
                                                    </div>
                                                </div>
                                                <div class="kardex-sale-summary__actions">
                                                    <div class="kardex-sale-summary__buttons">
                                                        @if($showQueryAction)
                                                            <form method="POST" action="{{ $queryActionRoute }}" class="kardex-sale-summary__action-form" @if($invoiceStatus === 'PENDIENTE' || $isQrPayment) data-pending-consult="true" @endif>
                                                                @csrf
                                                                <input type="hidden" name="cart_id" value="{{ $cartId }}">
                                                                <input type="hidden" name="codigo_seguimiento" value="{{ $queryReference }}">
                                                                @if($autoEmitInvoice)<input type="hidden" name="auto_emit_invoice" value="1">@endif
                                                                <button type="submit" class="kardex-sale-summary__button kardex-sale-summary__button--primary">{{ $queryActionLabel }}</button>
                                                            </form>
                                                        @endif
                                                        @if($pdfUrl !== '' && !$isAnnulledSale)
                                                            <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="kardex-sale-summary__button"><i class="fas fa-file-pdf" aria-hidden="true"></i>Factura original</a>
                                                            <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('facturacion.factura-con-firma', now()->addDay(), array_filter(['url' => $pdfUrl, 'cart_id' => $cartId, 'source_user_id' => data_get($row, 'origen_usuario_id')])) }}" target="_blank" rel="noopener" class="kardex-sale-summary__button"><i class="fas fa-receipt" aria-hidden="true"></i>Factura ticket</a>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                                <tr class="kardex-table__row {{ data_get($row, 'es_anulada') ? 'kardex-table__row-annulled' : '' }} {{ $saleGroupKey !== '' ? 'kardex-table__row-sale-group' : '' }} {{ $isSaleGroupStart ? 'kardex-table__row-sale-start' : '' }} {{ $isSaleGroupEnd ? 'kardex-table__row-sale-end' : '' }}">
                                    <td class="kardex-cell-number">{{ data_get($row, 'nro') }}</td>
                                    <td class="kardex-cell-date">{{ data_get($row, 'fecha') }}</td>
                                    <td class="kardex-cell-quantity">{{ number_format((int) data_get($row, 'cantidad', 0)) }}</td>
                                    <td class="kardex-cell-origin"><span>{{ data_get($row, 'origen') }}</span></td>
                                    @if(data_get($row, 'es_casilla'))
                                        <td colspan="4" class="text-center kardex-table__casilla">
                                            <div class="kardex-table__casilla-detail">
                                                <strong>{{ data_get($row, 'tipo_correspondencia') }}</strong>
                                                @if(trim((string) data_get($row, 'detalle_casilla', '')) !== '')
                                                    <span>{{ data_get($row, 'detalle_casilla') }}</span>
                                                @endif
                                            </div>
                                        </td>
                                    @else
                                        <td class="kardex-table__service">{{ data_get($row, 'tipo_correspondencia') }}</td>
                                        <td class="kardex-code {{ trim((string) data_get($row, 'guia_casilla', '')) === '-' ? 'kardex-code--empty' : '' }}"><span>{{ data_get($row, 'guia_casilla') }}</span></td>
                                        <td class="kardex-table__weight">{{ data_get($row, 'peso') !== null && data_get($row, 'peso') !== '' ? number_format((float) data_get($row, 'peso'), 3, ',', '.') : '' }}</td>
                                        <td class="kardex-table__destination">{{ data_get($row, 'pais_ciudad') }}</td>
                                    @endif
                                    <td class="kardex-table__delivery">
                                        @php($deliveryMode = (string) data_get($row, 'modalidad_entrega', '-'))
                                        @php($deliveryClass = match ($deliveryMode) {
                                            'ADMISIÓN' => 'kardex-table__delivery--admission',
                                            'DISTRIBUCIÓN DOMICILIARIA' => 'kardex-table__delivery--domiciliary',
                                            'DISTRIBUCIÓN NO DOMICILIARIA' => 'kardex-table__delivery--non-domiciliary',
                                            default => 'kardex-table__delivery--empty',
                                        })
                                        @if($deliveryMode !== '-')
                                            <span class="{{ $deliveryClass }}">{{ $deliveryMode }}</span>
                                        @else
                                            <span class="kardex-table__delivery--empty">-</span>
                                        @endif
                                    </td>
                                    <td class="kardex-table__invoice {{ data_get($row, 'es_anulada') ? 'kardex-table__annulled' : '' }}">{{ data_get($row, 'numero_factura') }}</td>
                                    <td class="kardex-table__amount">Bs {{ number_format((float) data_get($row, 'importe', 0), 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="text-center text-muted py-4">No se encontraron datos para el Kardex con los filtros aplicados.</td></tr>
                            @endforelse
                        </tbody>
                        @if($visibleRows->isNotEmpty())
                            <tfoot>
                                <tr><th colspan="10" class="text-right">TOTAL</th><th class="text-right kardex-table__amount">Bs {{ number_format($totalImporte, 2, ',', '.') }}</th></tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
    .kardex-page{padding-top:.85rem;--kardex-ink:#18324b;--kardex-muted:#718096;--kardex-line:#e7edf3;--kardex-blue:#1d4f7a;--kardex-gold:#e8b126}
    .kardex-filter-card,.kardex-report-card{overflow:hidden;border:1px solid #dce4ec;border-top:3px solid var(--kardex-gold);border-radius:12px;background:#fff;box-shadow:0 3px 12px rgba(24,50,75,.055)}
    .kardex-report-card{border-top:1px solid #dce4ec}
    .kardex-filter-card .card-body{padding:1.1rem 1.15rem}
    .kardex-filter-card label{display:block;margin-bottom:.38rem;color:#43576b;font-size:.75rem;font-weight:700;letter-spacing:.025em}
    .kardex-filter-card .form-control{height:39px;border:1px solid #d7e0e9;border-radius:7px;color:#263b50;font-size:.84rem;box-shadow:none}
    .kardex-filter-card .form-control:focus{border-color:#83a9c9;box-shadow:0 0 0 3px rgba(29,79,122,.10)}
    .kardex-downloads,.kardex-filter-card .mt-2.mt-md-0{display:flex;flex-wrap:wrap;gap:.45rem}
    .kardex-filter-card .btn{min-height:36px;border-radius:7px;font-size:.79rem;font-weight:700}
    .kardex-summary-row{margin-top:1.6rem}
    .kardex-summary-card{position:relative;min-height:112px;padding:.9rem 1rem;border:1px solid #dce4ec;border-top:3px solid var(--kardex-gold);border-radius:11px;background:#fff;box-shadow:0 2px 7px rgba(24,50,75,.035)}
    .kardex-summary-card span{display:block;color:var(--kardex-muted);font-size:.72rem;font-weight:750;letter-spacing:.065em;text-transform:uppercase}
    .kardex-summary-card strong{display:block;margin-top:.35rem;color:var(--kardex-ink);font-size:1.55rem;font-variant-numeric:tabular-nums;font-weight:750;line-height:1.1}
    .kardex-summary-card strong.text-danger{color:#dc3545!important}
    .kardex-summary-card small{display:block;margin-top:.45rem;color:#718096;font-size:.74rem;line-height:1.3}
    .kardex-summary-card--payments small{color:#496680;font-weight:700;font-variant-numeric:tabular-nums}
    .kardex-history-header{display:flex;align-items:center;justify-content:space-between;gap:1rem;min-height:72px;padding:.85rem 1.15rem;border:0;border-bottom:1px solid #dce4ec;background:linear-gradient(110deg,#fff 0%,#f8fafc 100%)}
    .kardex-history-header__title h3{margin:0;color:#183b59;font-size:.98rem;font-weight:800;letter-spacing:.005em}
    .kardex-history-header__title p{margin:.24rem 0 0;color:#718096;font-size:.76rem}
    .kardex-history-header__count{display:flex;flex-direction:column;align-items:flex-end;gap:.14rem;margin-left:auto;white-space:nowrap;text-align:right}
    .kardex-history-header__count strong{color:#687f98;font-size:.77rem;font-weight:750}
    .kardex-history-header__count span{color:#98a9ba;font-size:.67rem;font-weight:650}
    .kardex-table-wrap{max-height:calc(100vh - 325px);overflow:auto;background:#fff;scrollbar-color:#b8c5d2 #f3f6f8;scrollbar-width:thin}
    .kardex-table{width:100%;min-width:1370px;margin:0;border:0;border-collapse:separate;border-spacing:0;table-layout:fixed;color:#304255;font-size:.79rem}
    .kardex-table thead th{position:sticky;top:0;z-index:4;height:44px;padding:.68rem .55rem;border:0;border-bottom:1px solid #d6e0e9;background:#edf2f6;color:#53677b;font-size:.64rem;font-weight:800;letter-spacing:.045em;text-align:center;white-space:nowrap}
    .kardex-table th,.kardex-table td{vertical-align:middle;line-height:1.4}
    .kardex-table tbody td{padding:.72rem .65rem;border:0;border-bottom:1px solid #edf1f5;background:#fff;text-align:center}
    .kardex-table tbody tr:nth-child(even) td{background:#fbfcfd}
    .kardex-table tbody tr:hover td{background:#f4f8fb}
    .kardex-table tbody tr.kardex-table__row-sale-group td{background:#fff}
    .kardex-table tbody tr.kardex-table__row-sale-group:hover td{background:#f7fafc}
    .kardex-table tbody tr.kardex-table__row-sale-group td:first-child{border-left:1px solid #dce4ec}
    .kardex-table tbody tr.kardex-table__row-sale-group td:last-child{border-right:1px solid #dce4ec}
    .kardex-table tbody tr.kardex-table__row-sale-start td{border-top:0}
    .kardex-table tbody tr.kardex-table__row-sale-end td{border-bottom:1px solid #dce4ec}
    .kardex-table tbody tr.kardex-table__row-sale-end td:first-child{border-bottom-left-radius:8px}
    .kardex-table tbody tr.kardex-table__row-sale-end td:last-child{border-bottom-right-radius:8px}
    .kardex-table tbody tr.kardex-table__sale-label td{padding:.78rem 0 0;border:0;background:transparent;text-align:left}
    .kardex-table tbody tr.kardex-table__sale-label--grouped td{padding-top:.9rem}
    .kardex-sale-summary{display:grid;grid-template-columns:minmax(280px,34%) minmax(0,1fr) auto;align-items:center;gap:1rem;min-height:68px;padding:.72rem 1rem;border:1px solid #dce4ec;border-bottom:0;border-radius:8px 8px 0 0;background:#f7f9fb}
    .kardex-sale-summary__identity{display:flex;align-items:center;flex-wrap:wrap;gap:.55rem .75rem;min-width:0;padding-right:.9rem;border-right:1px solid #dce4ec}
    .kardex-sale-summary__customer-line{display:flex;align-items:baseline;gap:.38rem;max-width:100%;min-width:0;white-space:nowrap}
    .kardex-sale-summary__customer-line strong{overflow:hidden;color:#29445d;font-size:.75rem;font-weight:750;text-overflow:ellipsis}
    .kardex-sale-summary__statuses{display:flex;align-items:center;justify-content:flex-start;flex-wrap:wrap;gap:.45rem;min-width:0}
    .kardex-sale-summary__item{display:flex;flex-direction:column;align-items:flex-start;justify-content:center;gap:.12rem;min-width:0;min-height:34px;padding:0 .2rem}
    .kardex-sale-summary__item strong{max-width:100%;overflow:hidden;color:#29445d;font-size:.73rem;font-weight:750;text-overflow:ellipsis;white-space:nowrap}
    .kardex-sale-summary__item small{color:#73869a;font-size:.68rem;line-height:1.15}
    .kardex-sale-summary__chip{display:inline-block;max-width:100%;padding:.24rem .46rem;border:1px solid transparent;border-radius:999px;font-size:.64rem;font-weight:800;line-height:1.2;text-align:left;white-space:normal;overflow-wrap:anywhere}
    .kardex-sale-summary__chip i,.kardex-sale-summary__button i{margin-right:.32rem;opacity:.82}
    .kardex-sale-summary__chip--success{border-color:#b9e2c6;background:#e6f4ea;color:#1d773c}
    .kardex-sale-summary__chip--warning{border-color:#f2dda7;background:#fff6df;color:#8a6500}
    .kardex-sale-summary__chip--danger{border-color:#efc1c5;background:#fff0f1;color:#a33e49}
    .kardex-sale-summary__chip--dark{border-color:#d5d9de;background:#eceff2;color:#48515a}
    .kardex-sale-summary__chip--muted,.kardex-sale-summary__chip--neutral{border-color:#dce4eb;background:#f5f7f9;color:#52677b}
    .kardex-sale-summary__chip--primary{border-color:#bfd7eb;background:#eaf3fa;color:#245d87}
    .kardex-sale-summary__chip--qr{border-color:#dce4eb;background:#f5f7f9;color:#52677b}
    .kardex-sale-summary__actions{display:flex;align-items:center;justify-content:flex-end;min-width:0;min-height:38px;padding-left:.9rem;border-left:1px solid #dce4ec}
    .kardex-sale-summary__buttons{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:.3rem;max-width:100%}
    .kardex-sale-summary__action-form{margin:0}
    .kardex-sale-summary__button{display:inline-flex;align-items:center;justify-content:center;min-height:31px;padding:.32rem .55rem;border:1px solid #d3dde6;border-radius:6px;background:#fff;color:#315773;font-size:.66rem;font-weight:750;line-height:1.2;text-align:center;text-decoration:none;white-space:nowrap;cursor:pointer}
    .kardex-sale-summary__button:hover{border-color:#7598b5;background:#f7fafc;color:#173e5e;text-decoration:none}
    .kardex-sale-summary__button--primary{border-color:#2d6590;background:#2d6590;color:#fff}
    .kardex-sale-summary__button--primary:hover{border-color:#214f73;background:#214f73;color:#fff}
    @media(max-width:1100px){.kardex-sale-summary{grid-template-columns:minmax(280px,34%) minmax(0,1fr)}.kardex-sale-summary__actions{grid-column:1/-1;justify-content:flex-start;min-height:0;padding:.55rem 0 0;border-top:1px solid #e1e7ed;border-left:0}.kardex-sale-summary__buttons{justify-content:flex-start}}
    .kardex-cell-number{color:#8997a5;font-size:.72rem;font-variant-numeric:tabular-nums;text-align:center}
    .kardex-cell-date{color:#52677b;font-size:.76rem;font-variant-numeric:tabular-nums;text-align:center;white-space:nowrap}
    .kardex-cell-quantity{color:#2d4359;font-variant-numeric:tabular-nums;font-weight:700;text-align:center}
    .kardex-cell-origin{text-align:center}
    .kardex-cell-origin span{display:inline-block;min-width:39px;padding:.23rem .42rem;border:1px solid #e1e8ef;border-radius:5px;background:#f5f7f9;color:#586b7e;font-size:.68rem;font-weight:700;letter-spacing:.025em}
    .kardex-table__service{color:#344a60;font-weight:600;text-align:center;overflow-wrap:anywhere}
    .kardex-code{color:#194c77;font-family:Consolas,Monaco,monospace;font-size:.71rem;font-variant-numeric:tabular-nums;text-align:center;text-transform:uppercase;overflow-wrap:anywhere}
    .kardex-code span{display:inline-block;padding:.24rem .4rem;border:1px solid #e1eaf2;border-radius:5px;background:#f3f7fa}.kardex-code--empty span{padding:0;border:0;background:transparent;color:#a1acb7;font-family:inherit}
    .kardex-code--empty{text-align:center}
    .kardex-table__weight{color:#52677b;font-size:.75rem;font-variant-numeric:tabular-nums;text-align:center;white-space:nowrap}
    .kardex-table__destination{color:#3e5368;font-size:.76rem;text-align:center;overflow-wrap:anywhere}
    .kardex-table__delivery{padding:.52rem .36rem!important;text-align:center}
    .kardex-table__delivery span{display:inline-block;padding:.28rem .42rem;border:1px solid transparent;border-radius:6px;font-size:.61rem;font-weight:750;line-height:1.25;overflow-wrap:anywhere}
    .kardex-table__delivery--admission{border-color:#d8dff1!important;background:#f2f4fb;color:#4e5f8f}
    .kardex-table__delivery--domiciliary{border-color:#cfe0ef!important;background:#f0f6fb;color:#315f83}
    .kardex-table__delivery--non-domiciliary{border-color:#cce8dc!important;background:#eff9f4;color:#28704e}
    .kardex-table__delivery--empty{padding:0!important;border:0!important;background:transparent!important;color:#9aa7b3;font-weight:500!important}
    .kardex-table__invoice{color:#198754;font-size:.75rem;font-variant-numeric:tabular-nums;font-weight:750;text-align:center;white-space:nowrap}
    .kardex-table__amount{color:#173b5d;font-size:.8rem;font-variant-numeric:tabular-nums;font-weight:750;text-align:center;white-space:nowrap}
    .kardex-table__casilla{background:#fcf8ef!important;color:#735f35;font-weight:650;letter-spacing:.015em;text-transform:uppercase;white-space:normal}
    .kardex-table__casilla-detail{display:flex;flex-direction:column;align-items:center;gap:.18rem;line-height:1.3}
    .kardex-table__casilla-detail strong{font-size:.72rem;font-weight:750}
    .kardex-table__casilla-detail span{color:#806f50;font-size:.68rem;font-weight:500;letter-spacing:0;overflow-wrap:anywhere}
    .kardex-table tbody tr.kardex-table__row-annulled td{background:#fff7f7!important;color:#853c46}
    .kardex-table tbody tr.kardex-table__row-annulled td:first-child{border-left:3px solid #cf727a}
    .kardex-table tbody tr.kardex-table__row-annulled .kardex-table__invoice{color:#a33e49;font-weight:800}
    .kardex-table tfoot th{padding:.74rem .65rem;border:0;border-top:1px solid #dce4ec;background:#f4f7f9;color:#53677b;font-size:.69rem;font-weight:800;letter-spacing:.035em}
    .kardex-table tfoot th:last-child{color:#173b5d;font-size:.8rem;font-variant-numeric:tabular-nums}
    @media(max-width:767.98px){
        .kardex-filter-card .card-body{padding:.85rem}.kardex-filter-card .mt-2.mt-md-0{width:100%;margin-top:.8rem!important}
        .kardex-filter-card .mt-2.mt-md-0 .btn-primary{flex:1}.kardex-downloads .btn{flex:1}
        .kardex-summary-card{min-height:100px;padding:.75rem .8rem}.kardex-summary-card strong{font-size:1.25rem}.kardex-summary-card small{font-size:.68rem}
        .kardex-history-header{padding:.75rem .85rem}.kardex-history-header__title h3{font-size:.9rem}.kardex-history-header__count strong{font-size:.7rem}
        .kardex-table-wrap{max-height:calc(100vh - 355px)}.kardex-table{min-width:1370px}
    }
</style>
@stop
