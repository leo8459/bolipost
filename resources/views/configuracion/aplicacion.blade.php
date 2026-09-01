@extends('adminlte::page')

@section('title', 'Configuracion de Aplicacion')

@section('content_header')
    <h1>Configuracion de Aplicacion</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('configuracion.aplicacion.update') }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label>Ultima version (latestVersion)</label>
                    <input type="text" name="latestVersion" class="form-control" value="{{ old('latestVersion', $settings['latestVersion']) }}" required>
                </div>

                <div class="form-group">
                    <label>Version minima (minimumVersion)</label>
                    <input type="text" name="minimumVersion" class="form-control" value="{{ old('minimumVersion', $settings['minimumVersion']) }}" required>
                </div>

                <div class="form-group">
                    <label>URL de descarga (downloadUrl)</label>
                    <input type="url" name="downloadUrl" class="form-control" value="{{ old('downloadUrl', $settings['downloadUrl']) }}">
                </div>

                <div class="form-group">
                    <label>Titulo del aviso</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $settings['title']) }}">
                </div>

                <div class="form-group">
                    <label>Mensaje del aviso</label>
                    <textarea name="message" class="form-control" rows="3">{{ old('message', $settings['message']) }}</textarea>
                </div>

                <div class="form-group form-check">
                    <input type="hidden" name="forceUpdate" value="0">
                    <input type="checkbox" class="form-check-input" id="forceUpdate" name="forceUpdate" value="1" {{ old('forceUpdate', $settings['forceUpdate']) ? 'checked' : '' }}>
                    <label class="form-check-label" for="forceUpdate">Forzar actualizacion</label>
                </div>

                <hr>

                <h5 class="mb-3">Notificaciones de ChasquiApp</h5>
                <p class="text-muted">
                    Configura cada cuanto tiempo la aplicacion consulta si el cartero tiene paquetes pendientes.
                    Android permite un minimo fiable de 15 minutos para esta tarea en segundo plano.
                </p>

                <div class="form-group form-check">
                    <input type="hidden" name="carteroNotificationEnabled" value="0">
                    <input
                        type="checkbox"
                        class="form-check-input"
                        id="carteroNotificationEnabled"
                        name="carteroNotificationEnabled"
                        value="1"
                        {{ old('carteroNotificationEnabled', $settings['carteroNotificationEnabled']) ? 'checked' : '' }}>
                    <label class="form-check-label" for="carteroNotificationEnabled">Activar avisos de paquetes pendientes</label>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="carteroNotificationIntervalMinutes">Avisar cada cuantos minutos</label>
                        <input
                            type="number"
                            min="15"
                            max="1440"
                            step="1"
                            id="carteroNotificationIntervalMinutes"
                            name="carteroNotificationIntervalMinutes"
                            class="form-control @error('carteroNotificationIntervalMinutes') is-invalid @enderror"
                            value="{{ old('carteroNotificationIntervalMinutes', $settings['carteroNotificationIntervalMinutes']) }}"
                            required>
                        @error('carteroNotificationIntervalMinutes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-8">
                        <label for="carteroNotificationTitle">Titulo de la notificacion</label>
                        <input
                            type="text"
                            id="carteroNotificationTitle"
                            name="carteroNotificationTitle"
                            class="form-control"
                            maxlength="80"
                            value="{{ old('carteroNotificationTitle', $settings['carteroNotificationTitle']) }}"
                            required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="carteroNotificationMessage">Mensaje de la notificacion</label>
                    <input
                        type="text"
                        id="carteroNotificationMessage"
                        name="carteroNotificationMessage"
                        class="form-control"
                        maxlength="180"
                        value="{{ old('carteroNotificationMessage', $settings['carteroNotificationMessage']) }}"
                        required>
                    <small class="form-text text-muted">Solo se mostrara cuando el cartero tenga uno o mas paquetes pendientes.</small>
                </div>

                <hr>

                <h5 class="mb-3">Facturacion</h5>
                <p class="text-muted">Controla si los botones de emision aparecen o no dentro del modal de facturacion.</p>

                <div class="form-group form-check">
                    <input type="hidden" name="facturacionShowFacturaElectronica" value="0">
                    <input type="checkbox" class="form-check-input" id="facturacionShowFacturaElectronica" name="facturacionShowFacturaElectronica" value="1" {{ old('facturacionShowFacturaElectronica', $settings['facturacionShowFacturaElectronica']) ? 'checked' : '' }}>
                    <label class="form-check-label" for="facturacionShowFacturaElectronica">Mostrar boton Factura electronica</label>
                </div>

                <div class="form-group form-check">
                    <input type="hidden" name="facturacionShowQrFactura" value="0">
                    <input type="checkbox" class="form-check-input" id="facturacionShowQrFactura" name="facturacionShowQrFactura" value="1" {{ old('facturacionShowQrFactura', $settings['facturacionShowQrFactura']) ? 'checked' : '' }}>
                    <label class="form-check-label" for="facturacionShowQrFactura">Mostrar boton QR + factura</label>
                </div>

                <div class="form-group form-check">
                    <input type="hidden" name="facturacionShowQrSolo" value="0">
                    <input type="checkbox" class="form-check-input" id="facturacionShowQrSolo" name="facturacionShowQrSolo" value="1" {{ old('facturacionShowQrSolo', $settings['facturacionShowQrSolo']) ? 'checked' : '' }}>
                    <label class="form-check-label" for="facturacionShowQrSolo">Mostrar boton QR solo pago</label>
                </div>

                <div class="form-group">
                    <label>URL por defecto del monitor QR</label>
                    <input
                        type="url"
                        name="facturacionMonitorDefaultUrl"
                        class="form-control"
                        value="{{ old('facturacionMonitorDefaultUrl', $settings['facturacionMonitorDefaultUrl']) }}"
                        placeholder="http://127.0.0.1:8000/facturacion/monitor/display/monitor-...">
                    <small class="form-text text-muted">
                        Se usa como valor inicial para la pestaña externa del monitor. Cada caja puede activarla o desactivarla en su propio navegador.
                    </small>
                </div>

                <button type="submit" class="btn btn-primary">Guardar</button>
            </form>
        </div>
    </div>
@stop
