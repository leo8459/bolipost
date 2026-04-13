@extends('adminlte::page')

@section('title', 'Nueva solicitud EMS')

@section('content')
    <style>
        :root{
            --azul:#20539A;
            --dorado:#FECC36;
            --bg:#f5f7fb;
            --line:#e5e7eb;
            --muted:#6b7280;
        }

        .solicitud-shell{
            background: var(--bg);
            padding: 18px;
            border-radius: 16px;
        }

        .solicitud-card{
            border:0;
            border-radius:16px;
            box-shadow:0 12px 26px rgba(0,0,0,.08);
            overflow:hidden;
            background:#fff;
        }

        .solicitud-hero{
            background: linear-gradient(90deg, var(--azul), #20539A);
            color:#fff;
            padding:18px 20px;
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:18px;
            flex-wrap:wrap;
        }

        .solicitud-hero h1{
            margin:0;
            font-size:2rem;
            font-weight:800;
        }

        .solicitud-hero p{
            margin:6px 0 0;
            color:rgba(255,255,255,.82);
        }

        .solicitud-actions{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            align-items:center;
        }

        .btn-dorado{
            background: var(--dorado);
            color:#fff;
            font-weight:800;
            border:none;
            border-radius:12px;
            padding:10px 14px;
        }

        .btn-dorado:hover{
            filter:brightness(.95);
            color:#fff;
        }

        .btn-outline-light2{
            border:1px solid rgba(255,255,255,.7);
            color:#fff;
            font-weight:800;
            border-radius:12px;
            padding:10px 14px;
            background:transparent;
        }

        .btn-outline-light2:hover{
            background: rgba(255,255,255,.12);
            color:#fff;
        }

        .solicitud-body{
            padding:16px 20px 20px;
        }

        .solicitud-panel{
            border:1px solid var(--line);
            border-radius:14px;
            overflow:hidden;
            background:#fff;
        }

        .solicitud-panel-head{
            padding:16px 18px;
            border-bottom:1px solid var(--line);
        }

        .solicitud-panel-head h3{
            margin:0;
            font-size:1.05rem;
            font-weight:800;
            color:#163b6c;
        }

        .solicitud-panel-body{
            padding:20px;
        }

        .solicitud-section{
            border:1px solid var(--line);
            border-radius:14px;
            padding:18px;
            margin-bottom:18px;
            background:#fbfcff;
        }

        .solicitud-section:last-child{
            margin-bottom:0;
        }

        .solicitud-section h5{
            margin-bottom:16px;
            font-size:1rem;
            font-weight:800;
            color:#163b6c;
        }

        .solicitud-panel-body label{
            font-weight:800;
            color:#1f2937;
        }

        .solicitud-panel-body .form-control{
            border-radius:10px;
            border:1px solid #d1d5db;
            box-shadow:none;
        }

        .solicitud-panel-body .form-control:focus{
            border-color: var(--azul);
            box-shadow:0 0 0 0.15rem rgba(52,68,124,.15);
        }

        .solicitud-panel-body .form-text{
            color:var(--muted);
        }

        .solicitud-footer{
            padding:0 20px 20px;
            display:flex;
            justify-content:flex-end;
            gap:12px;
        }

        .solicitud-submit{
            min-width:190px;
        }

        .solicitud-cancel{
            min-width:160px;
            border-radius:12px;
            border:1px solid rgba(32, 83, 154, .22);
            background:#fff;
            color:var(--azul);
            font-weight:800;
            padding:10px 18px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            text-decoration:none;
        }

        .solicitud-cancel:hover{
            background:rgba(32, 83, 154, .05);
            color:var(--azul);
            text-decoration:none;
        }

        .solicitud-submit.btn-dorado{
            min-width:190px;
            padding:10px 20px;
        }

        @media (max-width: 767.98px){
            .solicitud-shell{
                padding:12px;
            }

            .solicitud-hero{
                flex-direction:column;
                align-items:flex-start;
            }

            .solicitud-actions{
                width:100%;
            }

            .solicitud-actions > .btn{
                width:100%;
                justify-content:center;
            }

            .solicitud-footer{
                justify-content:stretch;
                flex-direction:column;
            }

            .solicitud-submit,
            .solicitud-cancel{
                width:100%;
            }
        }
    </style>

    <div class="solicitud-shell">
        <div class="solicitud-card">
            <div class="solicitud-hero">
                <div>
                    <h1>Nueva solicitud</h1>
                    <p>Registra una solicitud desde Admisiones con peso incluido.</p>
                </div>
                <div class="solicitud-actions">
                    <a href="{{ route('paquetes-ems.solicitudes.index') }}" class="btn btn-dorado">
                        Ver solicitudes
                    </a>
                    <a href="{{ route('paquetes-ems.index') }}" class="btn btn-outline-light2">
                        Volver a admisiones
                    </a>
                </div>
            </div>

    @if (session('success'))
            <div class="alert alert-success mx-3 mt-3 mb-0">
                {{ session('success') }}
            </div>
    @endif

    @if ($errors->any())
            <div class="alert alert-danger mx-3 mt-3 mb-0">
                Revisa los campos del formulario y vuelve a intentar.
            </div>
    @endif

            <div class="solicitud-body">
                <div class="solicitud-panel">
                    <div class="solicitud-panel-head">
                        <h3>Formulario de solicitud</h3>
                    </div>
        <form method="POST" action="{{ route('paquetes-ems.solicitudes.store') }}">
            @csrf
            <input type="hidden" name="solicitud_id" id="solicitud_id" value="{{ old('solicitud_id') }}">
                    <div class="solicitud-panel-body">
                        <div class="solicitud-section">
                    <h5 class="mb-3">Cargar desde codigo de solicitud</h5>
                    <div class="row align-items-end">
                        <div class="col-md-6 form-group mb-md-0">
                            <label>Codigo de solicitud</label>
                            <input
                                type="text"
                                id="codigo_solicitud_base"
                                class="form-control"
                                placeholder="Ejemplo: SOL00000011"
                            >
                            <small class="form-text text-muted">Pega el codigo y se autollenaran los datos. Solo faltara registrar el peso.</small>
                        </div>
                        <div class="col-md-3 form-group mb-md-0">
                            <button type="button" id="buscar_solicitud_btn" class="btn btn-outline-primary btn-block">
                                Cargar solicitud
                            </button>
                        </div>
                        <div class="col-md-3">
                            <div id="codigo_solicitud_estado" class="small text-muted"></div>
                        </div>
                    </div>
                </div>

                        <div class="solicitud-section">
                    <h5 class="mb-3">Datos del servicio</h5>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Servicio</label>
                            <select name="servicio_extra_id" id="servicio_extra_id" class="form-control">
                                <option value="">Seleccione...</option>
                                @foreach($servicioExtras as $servicioExtra)
                                    <option
                                        value="{{ $servicioExtra->id }}"
                                        data-servicio-nombre="{{ strtolower((string) $servicioExtra->nombre) }}"
                                        @selected((int) old('servicio_extra_id') === (int) $servicioExtra->id)
                                    >
                                        {{ $servicioExtra->descripcion ?: $servicioExtra->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Origen</label>
                            <select name="origen" class="form-control">
                                <option value="">Seleccione...</option>
                                @foreach($ciudades as $ciudad)
                                    <option value="{{ $ciudad }}" @selected(old('origen') === $ciudad)>{{ $ciudad }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Destino</label>
                            <select name="destino_id" class="form-control">
                                <option value="">Seleccione...</option>
                                @foreach($destinos as $destino)
                                    <option value="{{ $destino->id }}" @selected((int) old('destino_id') === (int) $destino->id)>{{ $destino->nombre_destino }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Cantidad</label>
                            <input type="number" min="1" name="cantidad" value="{{ old('cantidad', 1) }}" class="form-control">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Peso</label>
                            <input type="number" step="0.001" min="0.001" name="peso" value="{{ old('peso') }}" class="form-control" placeholder="0.001">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Precio</label>
                            <input
                                type="text"
                                id="precio_preview"
                                value="{{ old('precio') }}"
                                class="form-control"
                                placeholder="Se calcula automaticamente"
                                readonly
                            >
                            <small id="precio_estado" class="form-text text-muted"></small>
                        </div>
                        <div class="col-md-12 form-group mb-0">
                            <label>Contenido</label>
                            <textarea name="contenido" rows="2" class="form-control">{{ old('contenido') }}</textarea>
                        </div>
                    </div>
                </div>

                        <div class="solicitud-section">
                    <h5 class="mb-3">Datos del remitente</h5>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Nombre remitente</label>
                            <input type="text" name="nombre_remitente" value="{{ old('nombre_remitente') }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Carnet</label>
                            <input type="text" name="carnet" value="{{ old('carnet') }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Telefono remitente</label>
                            <input type="text" name="telefono_remitente" value="{{ old('telefono_remitente') }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label>Direccion de recojo</label>
                            <input type="text" name="direccion_recojo" value="{{ old('direccion_recojo') }}" class="form-control">
                        </div>
                    </div>
                </div>

                        <div class="solicitud-section">
                    <h5 class="mb-3">Datos del destinatario</h5>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Nombre destinatario</label>
                            <input type="text" name="nombre_destinatario" value="{{ old('nombre_destinatario') }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Telefono destinatario</label>
                            <input type="text" name="telefono_destinatario" value="{{ old('telefono_destinatario') }}" class="form-control">
                        </div>
                        <div class="col-md-12 form-group mb-0">
                            <label>Direccion de entrega</label>
                            <input
                                type="text"
                                id="direccion_entrega"
                                name="direccion_entrega"
                                value="{{ old('direccion_entrega') }}"
                                class="form-control"
                            >
                        </div>
                        <div class="col-md-12 form-group mt-3 mb-0">
                            <div class="custom-control custom-checkbox">
                                <input
                                    type="checkbox"
                                    class="custom-control-input"
                                    id="pago_destinatario"
                                    name="pago_destinatario"
                                    value="1"
                                    @checked(old('pago_destinatario'))
                                >
                                <label class="custom-control-label" for="pago_destinatario">
                                    Pago en destinatario
                                </label>
                            </div>
                            <small class="form-text text-muted">Si esta marcado, se suma Bs 2.50 al precio.</small>
                        </div>
                    </div>
                </div>
                    </div>
                    <div class="solicitud-footer">
                        <a href="{{ route('paquetes-ems.solicitudes.index') }}" class="solicitud-cancel">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-dorado solicitud-submit">
                    Guardar solicitud
                </button>
                    </div>
        </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const lookupUrl = @json(route('paquetes-ems.solicitudes.find'));
    const quoteUrl = @json(route('paquetes-ems.solicitudes.quote'));
    const buscarSolicitudBtn = document.getElementById('buscar_solicitud_btn');
    const codigoSolicitudInput = document.getElementById('codigo_solicitud_base');
    const solicitudIdInput = document.getElementById('solicitud_id');
    const estadoBusqueda = document.getElementById('codigo_solicitud_estado');
    const servicioSelect = document.getElementById('servicio_extra_id');
    const direccionInput = document.getElementById('direccion_entrega');
    const origenSelect = document.querySelector('select[name="origen"]');
    const destinoSelect = document.querySelector('select[name="destino_id"]');
    const cantidadInput = document.querySelector('input[name="cantidad"]');
    const pesoInput = document.querySelector('input[name="peso"]');
    const precioInput = document.getElementById('precio_preview');
    const precioEstado = document.getElementById('precio_estado');
    const pagoDestinatarioCheckbox = document.getElementById('pago_destinatario');
    const contenidoInput = document.querySelector('textarea[name="contenido"]');
    const nombreRemitenteInput = document.querySelector('input[name="nombre_remitente"]');
    const carnetInput = document.querySelector('input[name="carnet"]');
    const telefonoRemitenteInput = document.querySelector('input[name="telefono_remitente"]');
    const direccionRecojoInput = document.querySelector('input[name="direccion_recojo"]');
    const nombreDestinatarioInput = document.querySelector('input[name="nombre_destinatario"]');
    const telefonoDestinatarioInput = document.querySelector('input[name="telefono_destinatario"]');

    if (!servicioSelect || !direccionInput) {
        return;
    }

    const defaultDireccion = direccionInput.value;

    function syncDireccionEntrega() {
        const selectedOption = servicioSelect.options[servicioSelect.selectedIndex];
        const servicioNombre = (selectedOption?.dataset?.servicioNombre || '').toLowerCase();
        const esVentanillaAVentanilla = servicioNombre.includes('ventanilla a ventanilla');

        if (esVentanillaAVentanilla) {
            direccionInput.value = 'CORREOS DE BOLIVIA';
            direccionInput.setAttribute('readonly', 'readonly');
            return;
        }

        direccionInput.removeAttribute('readonly');

        if (direccionInput.value === 'CORREOS DE BOLIVIA' && defaultDireccion !== 'CORREOS DE BOLIVIA') {
            direccionInput.value = defaultDireccion;
        }
    }

    function setStatus(message, type) {
        if (!estadoBusqueda) {
            return;
        }

        estadoBusqueda.className = 'small';

        if (type === 'success') {
            estadoBusqueda.classList.add('text-success');
        } else if (type === 'error') {
            estadoBusqueda.classList.add('text-danger');
        } else {
            estadoBusqueda.classList.add('text-muted');
        }

        estadoBusqueda.textContent = message;
    }

    function setPrecioEstado(message, type) {
        if (!precioEstado) {
            return;
        }

        precioEstado.className = 'form-text';

        if (type === 'success') {
            precioEstado.classList.add('text-success');
        } else if (type === 'error') {
            precioEstado.classList.add('text-danger');
        } else {
            precioEstado.classList.add('text-muted');
        }

        precioEstado.textContent = message;
    }

    function setSelectValue(select, value) {
        if (!select) {
            return;
        }

        const normalizedValue = String(value ?? '');
        const option = Array.from(select.options).find(function (item) {
            return String(item.value) === normalizedValue;
        });

        select.value = option ? normalizedValue : '';
    }

    async function cargarSolicitudPorCodigo() {
        const codigo = (codigoSolicitudInput?.value || '').trim().toUpperCase();

        if (!codigo) {
            setStatus('Ingresa un codigo de solicitud.', 'error');
            return;
        }

        setStatus('Buscando solicitud...', 'info');
        buscarSolicitudBtn?.setAttribute('disabled', 'disabled');

        try {
            const response = await fetch(lookupUrl + '?codigo_solicitud=' + encodeURIComponent(codigo), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || 'No se pudo cargar la solicitud.');
            }

            setSelectValue(servicioSelect, payload.servicio_extra_id);
            setSelectValue(origenSelect, payload.origen);
            setSelectValue(destinoSelect, payload.destino_id);
            if (solicitudIdInput) solicitudIdInput.value = payload.id ?? '';
            if (cantidadInput) cantidadInput.value = payload.cantidad ?? 1;
            if (contenidoInput) contenidoInput.value = payload.contenido ?? '';
            if (nombreRemitenteInput) nombreRemitenteInput.value = payload.nombre_remitente ?? '';
            if (carnetInput) carnetInput.value = payload.carnet ?? '';
            if (telefonoRemitenteInput) telefonoRemitenteInput.value = payload.telefono_remitente ?? '';
            if (direccionRecojoInput) direccionRecojoInput.value = payload.direccion_recojo ?? '';
            if (nombreDestinatarioInput) nombreDestinatarioInput.value = payload.nombre_destinatario ?? '';
            if (telefonoDestinatarioInput) telefonoDestinatarioInput.value = payload.telefono_destinatario ?? '';
            if (direccionInput) direccionInput.value = payload.direccion_entrega ?? '';
            if (pagoDestinatarioCheckbox) pagoDestinatarioCheckbox.checked = Boolean(payload.pago_destinatario);
            if (pesoInput) pesoInput.focus();

            syncDireccionEntrega();
            cotizarSolicitud();
            setStatus('Solicitud ' + (payload.codigo_solicitud || codigo) + ' cargada correctamente.', 'success');
        } catch (error) {
            if (solicitudIdInput) solicitudIdInput.value = '';
            setStatus(error.message || 'No se pudo cargar la solicitud.', 'error');
        } finally {
            buscarSolicitudBtn?.removeAttribute('disabled');
        }
    }

    async function cotizarSolicitud() {
        const servicioExtraId = servicioSelect.value;
        const origen = origenSelect?.value || '';
        const destinoId = destinoSelect?.value || '';
        const peso = pesoInput?.value || '';
        const pagoDestinatario = pagoDestinatarioCheckbox?.checked ? '1' : '0';

        if (precioInput) {
            precioInput.value = '';
        }

        if (!servicioExtraId || !origen || !destinoId || !peso) {
            setPrecioEstado('Selecciona servicio, origen, destino y peso para calcular el precio.', 'info');
            return;
        }

        setPrecioEstado('Calculando precio...', 'info');

        try {
            const response = await fetch(
                quoteUrl
                + '?servicio_extra_id=' + encodeURIComponent(servicioExtraId)
                + '&origen=' + encodeURIComponent(origen)
                + '&destino_id=' + encodeURIComponent(destinoId)
                + '&peso=' + encodeURIComponent(peso)
                + '&pago_destinatario=' + encodeURIComponent(pagoDestinatario),
                {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                }
            );

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || 'No se pudo calcular el precio.');
            }

            if (precioInput) {
                precioInput.value = payload.precio;
            }

            setPrecioEstado('Precio calculado correctamente. Tiempo de entrega: ' + payload.tiempo_entrega + ' horas.', 'success');
        } catch (error) {
            setPrecioEstado(error.message || 'No se pudo calcular el precio.', 'error');
        }
    }

    servicioSelect.addEventListener('change', syncDireccionEntrega);
    servicioSelect.addEventListener('change', cotizarSolicitud);
    origenSelect?.addEventListener('change', cotizarSolicitud);
    destinoSelect?.addEventListener('change', cotizarSolicitud);
    pesoInput?.addEventListener('input', cotizarSolicitud);
    pagoDestinatarioCheckbox?.addEventListener('change', cotizarSolicitud);
    buscarSolicitudBtn?.addEventListener('click', cargarSolicitudPorCodigo);
    codigoSolicitudInput?.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            cargarSolicitudPorCodigo();
        }
    });
    syncDireccionEntrega();
    cotizarSolicitud();
});
</script>
@endsection
