@extends('layouts.cliente-adminlte')

@section('title', 'Solicitudes')

@section('content_header')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div>
            <h1 class="m-0 text-dark">Nueva solicitud</h1>
            <small class="text-muted">Completa los datos de tu env&iacute;o. Podr&aacute;s revisarlos antes de confirmar.</small>
        </div>
        <div class="d-flex flex-column flex-md-row">
            <a href="{{ route('clientes.solicitudes.history') }}" class="btn btn-outline-warning mt-3 mt-md-0 mr-md-2">
                Ver mis solicitudes
            </a>
            <a href="{{ route('clientes.dashboard') }}" class="btn btn-outline-primary mt-3 mt-md-0">
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
        <div class="alert alert-warning">
            {{ session('warning') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            Revisa los siguientes datos para continuar:
            <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card card-outline card-primary solicitud-card">
        <div class="card-header">
            <h3 class="card-title">Prepara tu env&iacute;o</h3>
        </div>
        <form id="solicitud-form" method="POST" action="{{ route('clientes.solicitudes.store') }}">
            @csrf
            <div class="card-body">
                <p class="text-muted mb-4"><span class="text-danger">*</span> Los campos con asterisco son obligatorios.</p>
                <div class="solicitud-section mb-4">
                    <h5 class="solicitud-section-title"><span class="solicitud-step">1</span>Datos del servicio</h5><p class="text-muted small mb-3">Elige el servicio y las ciudades de tu env&iacute;o.</p>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="servicio_extra_id">Servicio <span class="text-danger" aria-hidden="true">*</span></label>
                            <select name="servicio_extra_id" required id="servicio_extra_id" class="form-control">
                                <option value="">Selecciona una opci&oacute;n...</option>
                                @foreach($servicioExtras as $servicioExtra)
                                    <option
                                        value="{{ $servicioExtra->id }}"
                                        data-servicio-nombre="{{ strtolower((string) $servicioExtra->nombre) }}"
                                        data-servicio-descripcion="{{ strtolower((string) $servicioExtra->descripcion) }}"
                                        @selected((int) old('servicio_extra_id') === (int) $servicioExtra->id)
                                    >
                                        {{ $servicioExtra->descripcion ?: $servicioExtra->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="origen">Origen <span class="text-danger" aria-hidden="true">*</span></label>
                            <select name="origen" required id="origen" class="form-control">
                                <option value="">Selecciona una opci&oacute;n...</option>
                                @foreach($ciudades as $ciudad)
                                    <option value="{{ $ciudad }}" @selected(old('origen') === $ciudad)>{{ $ciudad }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="destino_id">Destino <span class="text-danger" aria-hidden="true">*</span></label>
                            <select name="destino_id" required id="destino_id" class="form-control">
                                <option value="">Selecciona una opci&oacute;n...</option>
                                @foreach($destinos as $destino)
                                    <option value="{{ $destino->id }}" @selected((int) old('destino_id') === (int) $destino->id)>{{ $destino->nombre_destino }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group mb-md-0">
                            <label for="cantidad">Cantidad de paquetes <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="number" min="1" id="cantidad" name="cantidad" required value="{{ old('cantidad', 1) }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label for="contenido">Contenido <span class="text-danger" aria-hidden="true">*</span></label>
                            <textarea id="contenido" name="contenido" required placeholder="Ej.: documentos, ropa o accesorios" rows="2" class="form-control">{{ old('contenido') }}</textarea>
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label>Precio de la solicitud</label>
                            <div id="precio_solicitud" class="alert alert-light border mb-0 py-2" role="status" aria-live="polite">
                                Seleccione el servicio, origen y destino para conocer el precio.
                            </div>
                            <small class="form-text text-muted">
                                Si el volumen del paquete es muy grande, se a&ntilde;adir&aacute; un recargo de Bs 10 al precio indicado.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="solicitud-section mb-4">
                    <h5 class="solicitud-section-title"><span class="solicitud-step">2</span>Datos del remitente</h5><p class="text-muted small mb-3">Indica qui&eacute;n env&iacute;a el paquete y d&oacute;nde lo recogemos.</p>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="nombre_remitente">Nombre completo <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" id="nombre_remitente" name="nombre_remitente" required value="{{ old('nombre_remitente', $cliente->name) }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="carnet">Carnet de identidad <span class="text-danger" aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                id="carnet" name="carnet" required
                                value="{{ old('carnet', trim($cliente->numero_carnet . ' ' . ($cliente->complemento ?: ''))) }}"
                                class="form-control"
                            >
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="telefono_remitente">Tel&eacute;fono <small class="text-muted font-weight-normal">(opcional)</small></label>
                            <input type="text" id="telefono_remitente" name="telefono_remitente" value="{{ old('telefono_remitente', $cliente->telefono) }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label for="direccion_recojo">Direcci&oacute;n de recojo <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" id="direccion_recojo" name="direccion_recojo" required value="{{ old('direccion_recojo', $cliente->direccion) }}" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="solicitud-section">
                    <h5 class="solicitud-section-title"><span class="solicitud-step">3</span>Datos del destinatario</h5><p class="text-muted small mb-3">Indica qui&eacute;n recibir&aacute; el paquete y su direcci&oacute;n.</p>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="nombre_destinatario">Nombre completo <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" id="nombre_destinatario" name="nombre_destinatario" required value="{{ old('nombre_destinatario') }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="telefono_destinatario">Tel&eacute;fono <small class="text-muted font-weight-normal">(opcional)</small></label>
                            <input type="text" id="telefono_destinatario" name="telefono_destinatario" value="{{ old('telefono_destinatario') }}" class="form-control">
                        </div>
                        <div class="col-md-12 form-group mb-0">
                            <label for="direccion_entrega">Direcci&oacute;n de entrega <span class="text-danger" aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                id="direccion_entrega"
                                name="direccion_entrega" required
                                value="{{ old('direccion_entrega') }}"
                                class="form-control"
                            >
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-right">
                <button type="submit" class="btn btn-primary">
                    Revisar solicitud <i class="fas fa-arrow-right ml-1" aria-hidden="true"></i>
                </button>
            </div>
            <div class="modal fade" id="resumen-solicitud-modal" tabindex="-1" aria-labelledby="resumen-solicitud-title" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div><h5 class="modal-title" id="resumen-solicitud-title">Revisa y confirma tu env&iacute;o</h5><small class="text-muted">Ya casi terminas. Comprueba que los datos sean correctos.</small></div>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small"><span class="text-danger">*</span> Selecciona la forma de pago para guardar tu solicitud.</p>
                            <div class="solicitud-payment mb-3">
                                <label for="pago_destinatario">Forma de pago <span class="text-danger">*</span></label>
                                <select id="pago_destinatario" name="pago_destinatario" class="form-control @error('pago_destinatario') is-invalid @enderror" aria-describedby="pago-ayuda" required disabled>
                                    <option value="">Selecciona una opci&oacute;n...</option>
                                    <option value="0" @selected((string) old('pago_destinatario') === '0')>Pagado</option>
                                    <option value="1" @selected((string) old('pago_destinatario') === '1')>Pagar en destino</option>
                                </select>
                                <small class="form-text text-muted" id="pago-ayuda">Pagado: el env&iacute;o ya fue pagado. Pagar en destino: el destinatario paga al recibir.</small>
                                @error('pago_destinatario')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div id="resumen-solicitud-datos" class="solicitud-summary"></div>
                            <div class="solicitud-total mt-3">
                                <strong id="resumen-solicitud-precio"></strong>
                                <small class="d-block">Si el volumen del paquete es muy grande, se añadir&aacute; un recargo de Bs 10 al precio indicado.</small>
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Volver a editar</button>
                            <button type="button" id="confirmar-solicitud" class="btn btn-primary">Confirmar y guardar</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('css')
<style>
.solicitud-card { border-radius: 12px; }
.solicitud-section { padding: 22px; border: 1px solid #e4e8ee; border-radius: 12px; background: #fff; }
.solicitud-section-title { display: flex; align-items: center; gap: 10px; font-size: 1.1rem; font-weight: 600; }
.solicitud-step { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 50%; background: #fff3cd; color: #735500; font-size: .9rem; }
.solicitud-card .form-control { border-radius: 7px; }
.solicitud-card label { font-size: .93rem; }
#resumen-solicitud-modal .modal-content { border-radius: 14px; overflow: hidden; }
#resumen-solicitud-modal .modal-header { background: #fff9e8; padding: 20px 24px; }
#resumen-solicitud-modal .modal-body { padding: 20px 24px; background: #f8fafc; }
#resumen-solicitud-modal .modal-footer { background: #fff; }
.solicitud-payment { padding: 16px; border: 1px solid #e8cc79; border-radius: 10px; background: #fff; }
.solicitud-summary { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.solicitud-summary-section { padding: 16px; background: #fff; border: 1px solid #e4e8ee; border-radius: 10px; min-width: 0; }
.solicitud-summary-section:first-child { grid-column: 1 / -1; }
.solicitud-summary-section h6 { padding-bottom: 10px; margin-bottom: 12px; border-bottom: 1px solid #edf0f4; color: #344054; }
.solicitud-summary-section dt { font-size: .85rem; font-weight: 500; color: #667085; }
.solicitud-summary-section dd { font-size: .9rem; color: #1d2939; }
.solicitud-total { padding: 16px; border-radius: 10px; background: #eaf5ef; color: #21583b; }
.solicitud-total strong { font-size: 1.2rem; }
.solicitud-total small { margin-top: 4px; }
@media (max-width: 575.98px) {
    .solicitud-section { padding: 16px; }
    .solicitud-summary { grid-template-columns: 1fr; }
    #resumen-solicitud-modal .modal-body { padding: 16px; }
    #resumen-solicitud-modal .modal-footer { flex-direction: column-reverse; align-items: stretch; }
    #resumen-solicitud-modal .modal-footer .btn { margin: 4px 0; }
}
</style>
@endpush

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const servicioSelect = document.getElementById('servicio_extra_id');
    const origenSelect = document.getElementById('origen');
    const destinoSelect = document.getElementById('destino_id');
    const direccionInput = document.getElementById('direccion_entrega');
    const precioSolicitud = document.getElementById('precio_solicitud');
    const quoteUrl = @json(route('clientes.solicitudes.quote'));
    const form = document.getElementById('solicitud-form');
    const pagoSelect = document.getElementById('pago_destinatario');
    const modal = $('#resumen-solicitud-modal');
    const confirmarButton = document.getElementById('confirmar-solicitud');
    let confirmado = false;

    ['servicio_extra_id', 'origen', 'destino_id', 'cantidad', 'contenido',
        'nombre_remitente', 'carnet', 'direccion_recojo', 'nombre_destinatario',
        'direccion_entrega'].forEach(name => {
        form.elements.namedItem(name).required = true;
    });

    function mostrarResumen() {
        const container = document.getElementById('resumen-solicitud-datos');
        container.replaceChildren();
        const sections = [
            ['Datos del servicio', [['Servicio', 'servicio_extra_id'], ['Origen', 'origen'],
                ['Destino', 'destino_id'], ['Cantidad', 'cantidad'], ['Contenido', 'contenido']]],
            ['Datos del remitente', [['Nombre', 'nombre_remitente'], ['Carnet', 'carnet'],
                ['Teléfono', 'telefono_remitente'], ['Dirección de recojo', 'direccion_recojo']]],
            ['Datos del destinatario', [['Nombre', 'nombre_destinatario'],
                ['Teléfono', 'telefono_destinatario'], ['Dirección de entrega', 'direccion_entrega']]],
        ];
        sections.forEach(([title, fields]) => {
            const section = document.createElement('section');
            section.className = 'solicitud-summary-section';
            const heading = document.createElement('h6');
            heading.className = 'font-weight-bold';
            heading.textContent = title;
            const list = document.createElement('dl');
            list.className = 'row mb-0';
            fields.forEach(([label, name]) => {
                const field = form.elements.namedItem(name);
                const term = document.createElement('dt');
                term.className = 'col-sm-4';
                term.textContent = label;
                const value = document.createElement('dd');
                value.className = 'col-sm-8 text-break';
                value.style.whiteSpace = 'pre-wrap';
                value.textContent = (field.tagName === 'SELECT'
                    ? field.selectedOptions[0]?.textContent.trim() : field.value.trim()) || 'No especificado';
                list.append(term, value);
            });
            section.append(heading, list);
            container.append(section);
        });
        document.getElementById('resumen-solicitud-precio').textContent = precioSolicitud.textContent;
    }

    form.addEventListener('submit', function (event) {
        if (confirmado) return;
        event.preventDefault();
        mostrarResumen();
        pagoSelect.disabled = false;
        modal.modal('show');
    });
    modal.on('hidden.bs.modal', function () {
        if (!confirmado) pagoSelect.disabled = true;
    });
    confirmarButton.addEventListener('click', function () {
        if (!form.reportValidity()) return;
        confirmado = true;
        confirmarButton.disabled = true;
        confirmarButton.textContent = 'Guardando...';
        form.requestSubmit();
    });

    if (!servicioSelect || !direccionInput) {
        return;
    }

    const defaultDireccion = direccionInput.value;
    const direccionVentanilla = 'CORREOS DE BOLIVIA';

    function normalizeText(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function syncDireccionEntrega() {
        const selectedOption = servicioSelect.options[servicioSelect.selectedIndex];
        const servicioTexto = normalizeText([
            selectedOption?.dataset?.servicioNombre,
            selectedOption?.dataset?.servicioDescripcion,
            selectedOption?.textContent,
        ].join(' '));
        const esPuertaAVentanilla = servicioTexto.includes('puerta a ventanilla');

        if (esPuertaAVentanilla) {
            direccionInput.value = direccionVentanilla;
            direccionInput.setAttribute('readonly', 'readonly');
            direccionInput.classList.add('bg-light');
            return;
        }

        direccionInput.removeAttribute('readonly');
        direccionInput.classList.remove('bg-light');

        if (direccionInput.value === direccionVentanilla && defaultDireccion !== direccionVentanilla) {
            direccionInput.value = defaultDireccion;
        }
    }

    function setPrecio(message, type) {
        if (!precioSolicitud) return;

        precioSolicitud.textContent = message;
        precioSolicitud.className = 'alert border mb-0 py-2 alert-' + type;
        document.getElementById('resumen-solicitud-precio').textContent = message;
    }

    async function cotizarSolicitud() {
        const servicioId = servicioSelect.value;
        const origen = origenSelect?.value || '';
        const destinoId = destinoSelect?.value || '';

        if (!servicioId || !origen || !destinoId) {
            setPrecio('Seleccione el servicio, origen y destino para conocer el precio.', 'light');
            return;
        }

        setPrecio('Calculando precio...', 'info');

        try {
            const params = new URLSearchParams({
                servicio_extra_id: servicioId,
                origen: origen,
                destino_id: destinoId,
            });
            const response = await fetch(quoteUrl + '?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || 'No se pudo calcular el precio.');
            }

            setPrecio('Precio: Bs ' + payload.precio + '.', 'success');
        } catch (error) {
            setPrecio(error.message || 'No se pudo calcular el precio.', 'warning');
        }
    }

    servicioSelect.addEventListener('change', syncDireccionEntrega);
    servicioSelect.addEventListener('change', cotizarSolicitud);
    origenSelect?.addEventListener('change', cotizarSolicitud);
    destinoSelect?.addEventListener('change', cotizarSolicitud);
    syncDireccionEntrega();
    cotizarSolicitud();
});
</script>
@endpush
