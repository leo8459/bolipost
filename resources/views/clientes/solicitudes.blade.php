@extends('layouts.cliente-adminlte')

@section('title', 'Solicitudes')

@section('content_header')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div>
            <h1 class="m-0 text-dark">Nueva solicitud</h1>
            <small class="text-muted">Registra una solicitud tipo preregistro desde tu panel de cliente.</small>
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
            Revisa los campos del formulario y vuelve a intentar.
        </div>
    @endif

    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title">Formulario de solicitud</h3>
        </div>
        <form method="POST" action="{{ route('clientes.solicitudes.store') }}">
            @csrf
            <div class="card-body">
                <div class="border rounded p-3 mb-4">
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
                                        data-servicio-descripcion="{{ strtolower((string) $servicioExtra->descripcion) }}"
                                        @selected((int) old('servicio_extra_id') === (int) $servicioExtra->id)
                                    >
                                        {{ $servicioExtra->descripcion ?: $servicioExtra->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Origen</label>
                            <select name="origen" id="origen" class="form-control">
                                <option value="">Seleccione...</option>
                                @foreach($ciudades as $ciudad)
                                    <option value="{{ $ciudad }}" @selected(old('origen') === $ciudad)>{{ $ciudad }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Destino</label>
                            <select name="destino_id" id="destino_id" class="form-control">
                                <option value="">Seleccione...</option>
                                @foreach($destinos as $destino)
                                    <option value="{{ $destino->id }}" @selected((int) old('destino_id') === (int) $destino->id)>{{ $destino->nombre_destino }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group mb-md-0">
                            <label>Cantidad</label>
                            <input type="number" min="1" name="cantidad" value="{{ old('cantidad', 1) }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label>Contenido</label>
                            <textarea name="contenido" rows="2" class="form-control">{{ old('contenido') }}</textarea>
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

                <div class="border rounded p-3 mb-4">
                    <h5 class="mb-3">Datos del remitente</h5>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Nombre remitente</label>
                            <input type="text" name="nombre_remitente" value="{{ old('nombre_remitente', $cliente->name) }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Carnet</label>
                            <input
                                type="text"
                                name="carnet"
                                value="{{ old('carnet', trim($cliente->numero_carnet . ' ' . ($cliente->complemento ?: ''))) }}"
                                class="form-control"
                            >
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Telefono remitente</label>
                            <input type="text" name="telefono_remitente" value="{{ old('telefono_remitente', $cliente->telefono) }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label>Direccion de recojo</label>
                            <input type="text" name="direccion_recojo" value="{{ old('direccion_recojo', $cliente->direccion) }}" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="border rounded p-3">
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
                    </div>
                </div>
            </div>
            <div class="card-footer text-right">
                <button type="submit" class="btn btn-primary">
                    Guardar solicitud
                </button>
            </div>
        </form>
    </div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const servicioSelect = document.getElementById('servicio_extra_id');
    const origenSelect = document.getElementById('origen');
    const destinoSelect = document.getElementById('destino_id');
    const direccionInput = document.getElementById('direccion_entrega');
    const precioSolicitud = document.getElementById('precio_solicitud');
    const quoteUrl = @json(route('clientes.solicitudes.quote'));

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
