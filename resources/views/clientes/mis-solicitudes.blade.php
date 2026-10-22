@extends('layouts.cliente-adminlte')

@section('title', 'Mis Solicitudes')

@section('css')
    <style>
        .order-success-modal .modal-content {
            overflow: hidden;
            border: 0;
            border-radius: 22px;
            box-shadow: 0 24px 70px rgba(16, 49, 95, .28);
        }

        .order-success-modal .modal-body {
            padding: 34px 30px 30px;
            text-align: center;
        }

        .order-success-modal__icon {
            display: flex;
            width: 78px;
            height: 78px;
            margin: 0 auto 20px;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #103f81;
            background: #ffcc29;
            font-size: 36px;
            box-shadow: 0 10px 24px rgba(255, 190, 0, .35);
        }

        .order-success-modal__title {
            margin: 0;
            color: #103f81;
            font-size: 28px;
            font-weight: 800;
        }

        .order-success-modal__message {
            margin: 12px 0 0;
            color: #26384f;
            font-size: 18px;
            line-height: 1.5;
        }

        .order-success-modal__notice {
            margin-top: 20px;
            padding: 14px 16px;
            border-radius: 12px;
            color: #103f81;
            background: #fff7d6;
            font-size: 14px;
            line-height: 1.55;
        }

        .order-success-modal__button {
            min-width: 150px;
            margin-top: 22px;
            padding: 10px 24px;
            border: 0;
            border-radius: 999px;
            color: #fff;
            background: #103f81;
            font-weight: 700;
        }
    </style>
@endsection

@section('content_header')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div>
            <h1 class="m-0 text-dark">Mis solicitudes</h1>
            <small class="text-muted">Revisa todas las solicitudes que registraste con tu cuenta cliente.</small>
        </div>
        <div class="d-flex flex-column flex-md-row">
            <a href="{{ route('clientes.solicitudes.create') }}" class="btn btn-outline-primary mt-3 mt-md-0 mr-md-2">
                Nueva solicitud
            </a>
            <a href="{{ route('clientes.dashboard') }}" class="btn btn-outline-warning mt-3 mt-md-0">
                Volver al panel
            </a>
        </div>
    </div>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning" role="alert">
            {{ session('warning') }}
        </div>
    @endif

    @if ($qrHistoryWarning)
        <div class="alert alert-warning" role="status">{{ $qrHistoryWarning }}</div>
    @endif

    @if ($pagosQrSinSolicitud->isNotEmpty())
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title">Pagos QR sin env&iacute;o registrado</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>C&oacute;digo de pago</th>
                                <th>Importe</th>
                                <th>Estado guardado</th>
                                <th>Fecha</th>
                                <th>Acci&oacute;n</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pagosQrSinSolicitud as $pagoQr)
                                <tr>
                                    <td><strong>{{ $pagoQr->internal_code ?: 'Pago QR' }}</strong></td>
                                    <td>Bs {{ \App\Support\BolivianNumber::format((float) $pagoQr->importe, 2, '.', '') }}</td>
                                    <td>
                                        @php
                                            $estadoPagoQr = strtolower((string) $pagoQr->estado_pago);
                                            $estadoPagoQrLabel = in_array($estadoPagoQr, ['pagado', 'paid', 'success', 'completed', 'approved', 'confirmed'], true)
                                                ? 'Pagado'
                                                : (in_array($estadoPagoQr, ['cancelado', 'cancelled', 'rejected', 'failed', 'expired'], true)
                                                    ? 'Cancelado'
                                                    : 'Pendiente');
                                        @endphp
                                        <span class="badge badge-{{ $estadoPagoQrLabel === 'Pagado' ? 'success' : ($estadoPagoQrLabel === 'Cancelado' ? 'secondary' : 'warning') }}">
                                            {{ $estadoPagoQrLabel }}
                                        </span>
                                    </td>
                                    <td>{{ optional($pagoQr->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary js-consultar-pago-qr" data-qr-attempt="{{ $pagoQr->qr_attempt_id }}">
                                            <i class="fas fa-qrcode mr-1" aria-hidden="true"></i>Ver QR
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="card card-outline card-warning">
        <div class="card-header">
            <h3 class="card-title">Historial de solicitudes</h3>
        </div>
        <div class="card-body p-0">
            @if ($solicitudes->isEmpty())
                <div class="p-3 text-muted">Todavia no registraste solicitudes.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Servicio</th>
                                <th>Tarifario</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Comprobante / pago</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($solicitudes as $solicitud)
                                <tr>
                                    <td><strong>{{ $solicitud->codigo_solicitud }}</strong></td>
                                    <td>{{ $solicitud->servicioExtra?->descripcion ?: ($solicitud->servicioExtra?->nombre ?? '-') }}</td>
                                    <td>
                                        @if($solicitud->tarifarioTiktoker)
                                            <span class="d-block">#{{ $solicitud->tarifarioTiktoker->id }}</span>
                                            <small class="text-muted">
                                                {{ $solicitud->tarifarioTiktoker->origen?->nombre_origen }}
                                                /
                                                {{ $solicitud->tarifarioTiktoker->destino?->nombre_destino }}
                                                @if($solicitud->tarifarioTiktoker->servicioExtra)
                                                    / {{ $solicitud->tarifarioTiktoker->servicioExtra->nombre }}
                                                @endif
                                            </small>
                                        @else
                                            <span class="text-muted">Sin tarifa</span>
                                        @endif
                                        <span class="d-block mt-1 font-weight-bold text-primary">
                                            Precio: {{ $solicitud->precio !== null ? 'Bs ' . \App\Support\BolivianNumber::format((float) $solicitud->precio, 2, '.', '') : 'No disponible' }}
                                        </span>
                                    </td>
                                    <td>{{ $solicitud->origen }}</td>
                                    <td>{{ $solicitud->destino?->nombre_destino }}</td>
                                    <td>
                                        @php($estadoNombre = (string) optional($solicitud->estadoRegistro)->nombre_estado)
                                        <span class="badge badge-{{ strtoupper($estadoNombre) === 'SOLICITUD' ? 'warning' : 'success' }}">
                                            {{ $estadoNombre !== '' ? $estadoNombre : '-' }}
                                        </span>
                                    </td>
                                    <td>{{ optional($solicitud->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <a href="{{ route('clientes.solicitudes.ticket', $solicitud) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-file-pdf mr-1"></i> Descargar PDF
                                        </a>
                                        @if (!empty($qrAttemptsBySolicitudId[$solicitud->id]))
                                            <button type="button" class="btn btn-sm btn-outline-success mt-1 js-consultar-pago-qr" data-qr-attempt="{{ $qrAttemptsBySolicitudId[$solicitud->id] }}">
                                                <i class="fas fa-qrcode mr-1" aria-hidden="true"></i>Ver estado QR
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="mis-solicitudes-qr-modal" tabindex="-1" role="dialog" aria-labelledby="mis-solicitudes-qr-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <div>
                        <h5 class="modal-title" id="mis-solicitudes-qr-title">Estado del pago QR</h5>
                        <small class="text-muted">Consulta actualizada directamente con Facturaci&oacute;n.</small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body text-center">
                    <div class="h4 text-primary mb-3">Bs <span id="mis-solicitudes-qr-importe">0.00</span></div>
                    <div id="mis-solicitudes-qr-estado" class="alert alert-info" role="status" aria-live="polite">Consultando el estado del pago...</div>
                    <div id="mis-solicitudes-qr-factura" class="small text-muted mb-2"></div>
                    <div id="mis-solicitudes-qr-registro" class="alert d-none" role="status" aria-live="polite"></div>
                    <div id="mis-solicitudes-qr-imagen-wrap" class="mx-auto mb-3 p-2 border rounded d-none" style="max-width: 300px; background: #fff;">
                        <img id="mis-solicitudes-qr-imagen" class="img-fluid" alt="QR pendiente de pago">
                    </div>
                    <small class="text-muted">C&oacute;digo: <span id="mis-solicitudes-qr-codigo"></span></small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    @if (session('pickup_notice'))
        <div class="modal fade order-success-modal" id="orderSuccessModal" tabindex="-1" role="dialog" aria-labelledby="orderSuccessModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="order-success-modal__icon" aria-hidden="true">
                            <i class="fas fa-check"></i>
                        </div>
                        <h2 class="order-success-modal__title" id="orderSuccessModalTitle">¡Muchas gracias por su pedido!</h2>
                        <p class="order-success-modal__message">Enseguida nos comunicaremos con usted.</p>
                        <div class="order-success-modal__notice">
                            <strong>Alerta:</strong><br>
                            {{ session('pickup_notice') }}
                        </div>
                        <button type="button" class="order-success-modal__button" data-dismiss="modal">Entendido</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('js')
    @if (session('pickup_notice'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.jQuery) {
                    window.jQuery('#orderSuccessModal').modal('show');
                }
            });
        </script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const jquery = window.jQuery;
            if (!jquery) return;

            const modal = jquery('#mis-solicitudes-qr-modal');
            if (!modal.length) return;

            const statusUrl = @json(route('clientes.solicitudes.qr.status'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const stateElement = document.getElementById('mis-solicitudes-qr-estado');
            const invoiceElement = document.getElementById('mis-solicitudes-qr-factura');
            const requestElement = document.getElementById('mis-solicitudes-qr-registro');
            const imageWrap = document.getElementById('mis-solicitudes-qr-imagen-wrap');
            const imageElement = document.getElementById('mis-solicitudes-qr-imagen');
            let activeAttempt = '';
            let pollTimer = null;
            let requestInProgress = false;

            function setStatus(message, type = 'info') {
                stateElement.textContent = message;
                stateElement.className = 'alert alert-' + type;
            }

            function schedulePoll() {
                if (pollTimer) window.clearTimeout(pollTimer);
                if (activeAttempt) pollTimer = window.setTimeout(() => consultStatus(activeAttempt), 3000);
            }

            function showInvoice(payload) {
                const status = String(payload.invoice_status || '').toUpperCase();
                const labels = {
                    FACTURADA: 'Factura emitida' + (payload.invoice_number ? ' N.º ' + payload.invoice_number : '') + '.',
                    PENDIENTE: 'La factura está pendiente de respuesta fiscal.',
                    PROCESADA: 'La factura está en proceso de emisión.',
                    EMITIENDO: 'La factura se está emitiendo.',
                    EMISION_INCIERTA: 'La emisión de la factura está siendo verificada.',
                    RECHAZADA: 'La factura requiere revisión.',
                    ERROR: 'La factura requiere revisión.',
                    ANULADA: 'La factura figura anulada.',
                };
                invoiceElement.textContent = labels[status] || '';
                if (payload.invoice_error) invoiceElement.textContent += ' ' + payload.invoice_error;
            }

            function showRequest(payload) {
                requestElement.replaceChildren();
                if (!payload.solicitud_codigo && !payload.ticket_url) {
                    requestElement.className = 'alert d-none';
                    return;
                }

                requestElement.className = 'alert alert-success';
                const requestCode = payload.solicitud_codigo ? ' con c\u00f3digo ' + payload.solicitud_codigo : '';
                requestElement.append(document.createTextNode('Env\u00edo registrado' + requestCode + '. '));
                if (payload.ticket_url) {
                    const link = document.createElement('a');
                    link.href = payload.ticket_url;
                    link.className = 'alert-link';
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.textContent = 'Descargar ticket';
                    requestElement.append(link);
                }
            }

            async function consultStatus(attemptId) {
                if (!activeAttempt || requestInProgress || activeAttempt !== attemptId) return;
                requestInProgress = true;

                try {
                    const response = await fetch(statusUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new URLSearchParams({ _token: csrfToken, qr_attempt_id: attemptId }),
                    });
                    const payload = await response.json();
                    if (!response.ok) throw new Error(payload.message || 'No se pudo consultar el pago.');

                    document.getElementById('mis-solicitudes-qr-importe').textContent = payload.qr?.amount || '0.00';
                    document.getElementById('mis-solicitudes-qr-codigo').textContent = payload.qr?.internal_code || '';
                    showInvoice(payload);
                    showRequest(payload);

                    if (payload.payment_status === 'pendiente' && payload.qr?.qr_image) {
                        const image = String(payload.qr.qr_image);
                        imageElement.src = image.startsWith('data:image/') || image.startsWith('http://') || image.startsWith('https://')
                            ? image
                            : 'data:image/png;base64,' + image;
                        imageWrap.classList.remove('d-none');
                        setStatus('El pago sigue pendiente. Actualizaremos el estado cada 3 segundos.');
                    } else {
                        imageWrap.classList.add('d-none');
                        imageElement.removeAttribute('src');
                        setStatus(payload.message || 'Estado del pago actualizado.', payload.payment_status === 'cancelado' ? 'warning' : 'success');
                    }

                    if (payload.terminal) {
                        activeAttempt = '';
                        if (pollTimer) window.clearTimeout(pollTimer);
                    }
                } catch (error) {
                    setStatus(error.message || 'No se pudo consultar el pago. Reintentaremos en 3 segundos.', 'warning');
                } finally {
                    requestInProgress = false;
                    schedulePoll();
                }
            }

            document.querySelectorAll('.js-consultar-pago-qr').forEach(button => {
                button.addEventListener('click', function () {
                    activeAttempt = button.dataset.qrAttempt || '';
                    if (pollTimer) window.clearTimeout(pollTimer);
                    document.getElementById('mis-solicitudes-qr-importe').textContent = '0.00';
                    document.getElementById('mis-solicitudes-qr-codigo').textContent = '';
                    invoiceElement.textContent = '';
                    requestElement.replaceChildren();
                    requestElement.className = 'alert d-none';
                    imageWrap.classList.add('d-none');
                    imageElement.removeAttribute('src');
                    setStatus('Consultando el estado del pago...', 'info');
                    modal.modal('show');
                    if (activeAttempt) consultStatus(activeAttempt);
                });
            });

            modal.on('hidden.bs.modal', function () {
                activeAttempt = '';
                if (pollTimer) window.clearTimeout(pollTimer);
            });
        });
    </script>
@endsection
