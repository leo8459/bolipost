@extends('layouts.cliente-adminlte')

@section('title', 'Nuevo envío Delivery Express')

@section('content_header')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div>
            <h1 class="m-0 text-dark">Nuevo envío Delivery Express</h1>
            <small class="text-muted">Cotiza y completa los datos de tu envío en pasos sencillos.</small>
        </div>
        <div class="d-flex flex-column flex-md-row">
            <a href="{{ route('clientes.solicitudes.history') }}" class="btn btn-outline-warning mt-3 mt-md-0 mr-md-2">
                <i class="fas fa-list-alt mr-1" aria-hidden="true"></i> Historial de envíos
            </a>
            <a href="{{ route('clientes.dashboard') }}" class="btn btn-outline-primary mt-3 mt-md-0">
                <i class="fas fa-arrow-left mr-1" aria-hidden="true"></i> Volver al panel
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

    <div class="card solicitud-card">
        <div class="card-header solicitud-card-header">
            <div>
                <div class="solicitud-card-header__eyebrow"><i class="fas fa-shipping-fast" aria-hidden="true"></i> Delivery Express</div>
                <h3 class="card-title">Prepara tu envío</h3>
                <p class="solicitud-card-header__description">Completa la información de tu paquete y confirma los datos antes de continuar.</p>
            </div>
            <span class="solicitud-card-header__badge"><i class="fas fa-list-ol mr-1" aria-hidden="true"></i> 3 pasos</span>
        </div>
        <form id="solicitud-form" method="POST" action="{{ route('clientes.solicitudes.store') }}" novalidate>
            @csrf
            <input type="hidden" name="qr_attempt_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="precio_cotizado" id="precio_cotizado" value="">
            <div class="card-body">
                <div class="solicitud-wizard-progress" aria-label="Progreso de la solicitud">
                    <div class="solicitud-wizard-steps">
                        <button type="button" class="solicitud-wizard-step is-active" data-wizard-nav="0" aria-current="step">
                            <span class="solicitud-wizard-step-number">1</span><span><strong>Servicio</strong><small>Env&iacute;o y pago</small></span>
                        </button>
                        <button type="button" class="solicitud-wizard-step" data-wizard-nav="1">
                            <span class="solicitud-wizard-step-number">2</span><span><strong>Remitente</strong><small>Datos de recojo</small></span>
                        </button>
                        <button type="button" class="solicitud-wizard-step" data-wizard-nav="2">
                            <span class="solicitud-wizard-step-number">3</span><span><strong>Destinatario</strong><small>Datos de entrega</small></span>
                        </button>
                    </div>
                    <div class="solicitud-wizard-track" role="progressbar" aria-label="Avance del formulario" aria-valuemin="1" aria-valuemax="3" aria-valuenow="1">
                        <span id="solicitud-wizard-bar"></span>
                    </div>
                    <div class="solicitud-wizard-caption" aria-live="polite"><span id="solicitud-wizard-current">Datos del servicio</span><span>Paso <strong id="solicitud-wizard-number">1</strong> de 3</span></div>
                </div>
                <p class="text-muted mb-4"><span class="text-danger">*</span> Los campos con asterisco son obligatorios.</p>
                <div class="solicitud-section mb-4" data-wizard-panel="0">
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
                            <input type="hidden" id="paquete_muy_grande" name="paquete_muy_grande" value="{{ old('paquete_muy_grande') ? '1' : '0' }}">
                            <button type="button" id="recargo-tamano-button" class="btn btn-outline-warning btn-sm mt-2" aria-pressed="false" disabled>
                                <i class="fas fa-plus-circle mr-1" aria-hidden="true"></i>Aumentar Bs 10 por tama&ntilde;o
                            </button>
                            <small class="form-text text-muted">
                                El tama&ntilde;o del paquete se verificar&aacute; al momento del recojo. Si corresponde, se cobrar&aacute; un recargo de Bs 10.
                            </small>
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label for="forma_pago">Forma de pago <span class="text-danger" aria-hidden="true">*</span></label>
                            <select name="forma_pago" id="forma_pago" required class="form-control">
                                <option value="">Selecciona una opci&oacute;n...</option>
                                <option value="origen" @selected(old('forma_pago') === 'origen')>Pagar en origen</option>
                                <option value="destino" @selected(old('forma_pago') === 'destino')>Pagar en destino</option>
                            </select>
                            <small id="forma-pago-ayuda" class="form-text text-muted" aria-live="polite">Elige d&oacute;nde se realizar&aacute; el pago.</small>
                        </div>
                        <div id="tipo-pago-container" class="col-md-6 form-group mb-0" @if(old('forma_pago') !== 'origen') hidden @endif>
                            <label for="tipo_pago">Tipo de pago <span id="tipo-pago-obligatorio" class="text-danger" aria-hidden="true" @if(old('forma_pago') !== 'origen') hidden @endif>*</span></label>
                            <select name="tipo_pago" id="tipo_pago" @required(old('forma_pago') === 'origen') @disabled(old('forma_pago') !== 'origen') class="form-control">
                                <option value="">Selecciona una opci&oacute;n...</option>
                                <option value="qr" @selected(old('tipo_pago') === 'qr')>QR</option>
                                <option value="efectivo" @selected(old('tipo_pago') === 'efectivo')>Efectivo</option>
                            </select>
                            <small id="tipo-pago-ayuda" class="form-text text-muted" aria-live="polite">Elige si pagar&aacute;s con QR o en efectivo.</small>
                        </div>
                        <div id="datos-facturacion-qr" class="col-12 mt-4" hidden>
                            <div class="solicitud-billing-card">
                                <div class="solicitud-billing-heading">
                                    <span class="solicitud-billing-icon"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i></span>
                                    <div>
                                        <h6 class="mb-1">Datos para generar tu QR</h6>
                                        <p class="mb-0 text-muted small">Completa los datos de facturaci&oacute;n antes de continuar. La documentaci&oacute;n se detecta mientras escribes el n&uacute;mero.</p>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6 form-group">
                                        <label for="numero_documento">N&uacute;mero de documento <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            id="numero_documento"
                                            name="numero_documento"
                                            value="{{ old('numero_documento', $cliente->numero_carnet) }}"
                                            placeholder="Ej. 1003579028"
                                            maxlength="80"
                                            autocomplete="off"
                                            class="form-control"
                                        >
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label for="complemento_documento">Complemento <small class="text-muted font-weight-normal">(opcional)</small></label>
                                        <input
                                            type="text"
                                            id="complemento_documento"
                                            name="complemento_documento"
                                            value="{{ old('complemento_documento', $cliente->complemento) }}"
                                            maxlength="30"
                                            class="form-control"
                                        >
                                    </div>
                                    <div class="col-md-12 form-group">
                                        <label for="razon_social">Raz&oacute;n social <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            id="razon_social"
                                            name="razon_social"
                                            value="{{ old('razon_social', $cliente->razon_social ?: $cliente->name) }}"
                                            placeholder="NOMBRE O RAZ&Oacute;N SOCIAL"
                                            maxlength="255"
                                            class="form-control text-uppercase"
                                        >
                                    </div>
                                    <div class="col-md-12 form-group">
                                        <div class="solicitud-email-heading">
                                            <label for="correo_facturacion" class="mb-0">Correo factura <span class="text-danger">*</span></label>
                                            <label class="solicitud-email-toggle mb-0" for="editar-correo-facturacion">
                                                <input type="checkbox" id="editar-correo-facturacion" @checked(old('editar_correo_facturacion'))>
                                                <span>Editar correo cliente</span>
                                            </label>
                                        </div>
                                        <input
                                            type="email"
                                            id="correo_facturacion"
                                            name="correo_facturacion"
                                            value="{{ old('correo_facturacion', $cliente->email) }}"
                                            data-email-perfil="{{ strtolower(trim((string) $cliente->email)) }}"
                                            maxlength="50"
                                            autocomplete="email"
                                            class="form-control"
                                        >
                                        <small class="form-text text-muted">Se carga autom&aacute;ticamente desde tu perfil.</small>
                                    </div>
                                    <div class="col-md-12 form-group mb-0">
                                        <label for="tipo_documento">Documentaci&oacute;n <span class="text-danger">*</span></label>
                                        <input type="hidden" id="tipo_documento_valor" name="tipo_documento" value="{{ old('tipo_documento', $cliente->tipodocumentoidentidad) }}">
                                        <select id="tipo_documento" class="form-control" disabled aria-disabled="true">
                                            <option value="">Selecciona</option>
                                            @foreach ($tiposDocumento as $codigo => $etiqueta)
                                                <option value="{{ $codigo }}" @selected((string) old('tipo_documento', $cliente->tipodocumentoidentidad) === (string) $codigo)>{{ $etiqueta }}</option>
                                            @endforeach
                                        </select>
                                        <small id="tipo-documento-ayuda" class="form-text text-muted" aria-live="polite">Escribe el n&uacute;mero para detectar el tipo de documento.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="solicitud-section mb-4" data-wizard-panel="1" hidden>
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
                            <label for="telefono_remitente">Tel&eacute;fono <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" id="telefono_remitente" name="telefono_remitente" required value="{{ old('telefono_remitente', $cliente->telefono) }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label for="direccion_recojo">Direcci&oacute;n de recojo <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" id="direccion_recojo" name="direccion_recojo" required value="{{ old('direccion_recojo', $cliente->direccion) }}" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="solicitud-section" data-wizard-panel="2" hidden>
                    <h5 class="solicitud-section-title"><span class="solicitud-step">3</span>Datos del destinatario</h5><p class="text-muted small mb-3">Indica qui&eacute;n recibir&aacute; el paquete y su direcci&oacute;n.</p>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="nombre_destinatario">Nombre completo <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" id="nombre_destinatario" name="nombre_destinatario" required value="{{ old('nombre_destinatario') }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="telefono_destinatario">Tel&eacute;fono <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" id="telefono_destinatario" name="telefono_destinatario" required value="{{ old('telefono_destinatario') }}" class="form-control">
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
            <div class="card-footer solicitud-wizard-footer">
                <button type="button" id="solicitud-wizard-back" class="btn btn-outline-secondary" hidden>
                    <i class="fas fa-arrow-left mr-1" aria-hidden="true"></i>Anterior
                </button>
                <div class="solicitud-wizard-footer-actions">
                    <span id="solicitud-wizard-hint" class="text-muted small d-none d-md-inline">Completa este paso para continuar.</span>
                    <button type="button" id="solicitud-wizard-next" class="btn btn-primary">
                        Continuar <i class="fas fa-arrow-right ml-1" aria-hidden="true"></i>
                    </button>
                    <button type="submit" id="solicitud-review-button" class="btn btn-primary" hidden>
                    Revisar solicitud <i class="fas fa-arrow-right ml-1" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="modal fade" id="resumen-solicitud-modal" tabindex="-1" aria-labelledby="resumen-solicitud-title" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div><h5 class="modal-title" id="resumen-solicitud-title">Revisa y confirma tu env&iacute;o</h5><small class="text-muted">Ya casi terminas. Comprueba que los datos sean correctos.</small></div>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p id="resumen-solicitud-instruccion" class="text-muted">Confirma los datos del env&iacute;o.</p>
                            <div id="resumen-solicitud-pago" class="alert alert-info" role="status" aria-live="polite">
                                <strong id="resumen-solicitud-pago-titulo">Forma de pago</strong>
                                <div id="resumen-solicitud-pago-detalle" class="small mt-1"></div>
                            </div>
                            <div id="qr-generacion-error" class="alert alert-danger d-none" role="alert"></div>
                            <div id="resumen-solicitud-datos" class="solicitud-summary"></div>
                            <div class="solicitud-total mt-3">
                                <strong id="resumen-solicitud-precio"></strong>
                                <small class="d-block">El tama&ntilde;o del paquete se verificar&aacute; al momento del recojo. Si corresponde, se cobrar&aacute; un recargo de Bs 10.</small>
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Volver a editar</button>
                            <button type="button" id="confirmar-solicitud-button" class="btn btn-primary"><i class="fas fa-qrcode mr-1" aria-hidden="true"></i>Generar QR y pagar</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="modal fade" id="qr-pago-modal" tabindex="-1" aria-labelledby="qr-pago-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content solicitud-qr-modal">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="qr-pago-title">QR de pago</h5>
                            <small id="qr-pago-instruccion" class="text-muted">Escanea este c&oacute;digo para pagar tu env&iacute;o.</small>
                        </div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body text-center">
                        <div class="qr-payment-amount">Bs <span id="qr-pago-importe">0.00</span></div>
                        <div id="qr-pago-estado" class="alert alert-info" role="status" aria-live="polite">Esperando la confirmacion del pago QR...</div>
                        <div id="qr-pago-factura" class="small text-muted mb-2"></div>
                        <div id="qr-pago-registro" class="alert d-none" role="status" aria-live="polite"></div>
                        <div id="qr-pago-imagen-wrap" class="qr-payment-image-wrap d-none">
                            <img id="qr-pago-imagen" alt="C&oacute;digo QR para pagar el env&iacute;o">
                            <a id="qr-pago-descargar" class="btn btn-outline-primary btn-sm qr-payment-download" href="#" download="QR-de-pago.png" target="_blank" rel="noopener noreferrer">
                                <i class="fas fa-download mr-1" aria-hidden="true"></i>Descargar imagen
                            </a>
                            <small class="d-block text-muted mt-2">En celular, si la imagen se abre aparte, mantenla presionada para guardarla.</small>
                        </div>
                        <div id="qr-pago-sin-imagen" class="alert alert-warning d-none" role="status">Facturaci&oacute;n no devolvi&oacute; la imagen del QR.</div>
                        <small class="text-muted">C&oacute;digo: <span id="qr-pago-codigo"></span></small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('css')
<style>
.solicitud-card { overflow: hidden; border: 1px solid #e3eaf3; border-radius: 16px; box-shadow: 0 10px 28px rgba(25, 48, 78, .08); animation: solicitud-panel-in .35s ease-out; }
.solicitud-card-header { display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 22px 24px; border-bottom: 0; color: #fff; background: linear-gradient(115deg, #172c4d, #205a91); }
.solicitud-card-header .card-title { float: none; display: block; margin: 0; color: #fff; font-size: 1.35rem; font-weight: 800; }
.solicitud-card-header__eyebrow { margin-bottom: 5px; color: #ffe28b; font-size: .73rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
.solicitud-card-header__description { margin: 6px 0 0; color: rgba(255, 255, 255, .78); font-size: .88rem; }
.solicitud-card-header__badge { flex: 0 0 auto; padding: 8px 11px; border: 1px solid rgba(255, 255, 255, .22); border-radius: 999px; color: #fff; background: rgba(255, 255, 255, .1); font-size: .78rem; font-weight: 700; }
.solicitud-card .card-body { padding: 24px; }
.solicitud-wizard-progress { margin-bottom: 24px; padding: 20px 22px 16px; border: 1px solid #e5eaf1; border-radius: 14px; background: linear-gradient(135deg, #fbfcfe, #f5f8fc); }
.solicitud-wizard-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.solicitud-wizard-step { display: flex; align-items: center; gap: 11px; min-width: 0; padding: 4px; border: 0; background: transparent; color: #667085; text-align: left; cursor: pointer; }
.solicitud-wizard-step:not(:disabled):hover .solicitud-wizard-step-number, .solicitud-wizard-step:focus-visible .solicitud-wizard-step-number { transform: translateY(-2px); border-color: #164b8d; box-shadow: 0 4px 12px rgba(22, 75, 141, .14); }
.solicitud-wizard-step:focus-visible { outline: 2px solid #164b8d; outline-offset: 3px; border-radius: 8px; }
.solicitud-wizard-step:disabled { cursor: default; opacity: .72; }
.solicitud-wizard-step-number { display: inline-flex; flex: 0 0 36px; width: 36px; height: 36px; align-items: center; justify-content: center; border: 2px solid #d7dee8; border-radius: 50%; background: #fff; color: #667085; font-weight: 700; transition: color .2s ease, background .2s ease, border-color .2s ease, transform .2s ease; }
.solicitud-wizard-step strong, .solicitud-wizard-step small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.solicitud-wizard-step strong { color: #475467; font-size: .93rem; }
.solicitud-wizard-step small { margin-top: 2px; color: #98a2b3; font-size: .77rem; }
.solicitud-wizard-step.is-active .solicitud-wizard-step-number { transform: scale(1.06); border-color: #164b8d; background: #164b8d; color: #fff; box-shadow: 0 4px 12px rgba(22, 75, 141, .2); }
.solicitud-wizard-step.is-active strong { color: #164b8d; }
.solicitud-wizard-step.is-complete .solicitud-wizard-step-number { border-color: #25845b; background: #25845b; color: #fff; }
.solicitud-wizard-track { height: 5px; margin: 16px 4px 10px; overflow: hidden; border-radius: 99px; background: #e6ebf2; }
.solicitud-wizard-track span { display: block; width: 0; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #164b8d, #ffca2c); transition: width .3s ease; }
.solicitud-wizard-caption { display: flex; justify-content: space-between; color: #667085; font-size: .82rem; }
.solicitud-wizard-caption strong { color: #164b8d; }
.solicitud-wizard-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.solicitud-wizard-footer-actions { display: flex; flex: 1; align-items: center; justify-content: flex-end; gap: 14px; }
.solicitud-section[hidden] { display: none !important; }
.solicitud-section[data-wizard-panel]:not([hidden]) { animation: solicitud-panel-in .22s ease-out; }
#datos-facturacion-qr[hidden] { display: none !important; }
.solicitud-billing-card { padding: 20px; border: 1px solid #dfe7f1; border-radius: 13px; background: #f8fbff; animation: solicitud-panel-in .2s ease-out; }
.solicitud-billing-heading { display: flex; align-items: center; gap: 12px; }
.solicitud-billing-heading h6 { color: #174a82; font-size: 1rem; font-weight: 700; }
.solicitud-billing-icon { display: inline-flex; flex: 0 0 40px; width: 40px; height: 40px; align-items: center; justify-content: center; border-radius: 12px; background: #e5effc; color: #164b8d; font-size: 1rem; }
.solicitud-billing-card .form-control { border-color: #d9e2ed; background-color: #fff; }
.solicitud-billing-card .form-control:focus { border-color: #6d9ed5; box-shadow: 0 0 0 .18rem rgba(22, 75, 141, .12); }
.solicitud-billing-card .form-group { margin-bottom: 16px; }
.solicitud-email-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
.solicitud-email-toggle { display: inline-flex; align-items: center; gap: 7px; color: #164b8d; font-size: .8rem !important; cursor: pointer; }
.solicitud-email-toggle input { accent-color: #164b8d; }
.solicitud-billing-card input[readonly] { background: #eef4fb; color: #52647a; }
@keyframes solicitud-panel-in { from { opacity: .55; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
.solicitud-section { padding: 22px; border: 1px solid #e4e8ee; border-radius: 12px; background: #fff; transition: border-color .2s ease, box-shadow .2s ease; }
.solicitud-section:hover { border-color: #d1dfef; box-shadow: 0 6px 18px rgba(25, 48, 78, .045); }
.solicitud-section-title { display: flex; align-items: center; gap: 10px; color: #203957; font-size: 1.1rem; font-weight: 700; }
.solicitud-step { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 50%; background: #fff3cd; color: #735500; font-size: .9rem; }
.solicitud-card .form-control { border: 1px solid #d9e2ed; border-radius: 8px; transition: border-color .18s ease, box-shadow .18s ease; }
.solicitud-card .form-control:focus { border-color: #6d9ed5; box-shadow: 0 0 0 .18rem rgba(22, 75, 141, .12); }
.solicitud-card label { font-size: .93rem; }
.solicitud-wizard-footer .btn { min-height: 40px; padding: 9px 15px; border-radius: 9px; font-weight: 700; transition: transform .18s ease, box-shadow .18s ease; }
.solicitud-wizard-footer .btn:hover, .solicitud-wizard-footer .btn:focus-visible { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(25, 48, 78, .14); }
#resumen-solicitud-modal .modal-content { border-radius: 14px; overflow: hidden; }
#resumen-solicitud-modal .modal-header { background: #fff9e8; padding: 20px 24px; }
#resumen-solicitud-modal .modal-body { padding: 20px 24px; background: #f8fafc; }
#resumen-solicitud-modal .modal-footer { background: #fff; }
.solicitud-qr-modal { border-radius: 14px; overflow: hidden; }
.solicitud-qr-modal .modal-header { background: #fff9e8; }
.qr-payment-amount { color: #1f4f8f; font-size: 1.8rem; font-weight: 700; margin-bottom: 14px; }
.qr-payment-image-wrap { margin: 0 auto 16px; width: min(100%, 300px); padding: 12px; background: white; border: 1px solid #e4e8ee; border-radius: 12px; }
.qr-payment-image-wrap img { display: block; width: 100%; height: auto; }
.qr-payment-download { display: block; width: 100%; margin-top: 12px; }
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
    .solicitud-card-header { align-items: flex-start; padding: 19px 17px; }
    .solicitud-card-header__badge { padding: 6px 9px; font-size: .72rem; }
    .solicitud-card .card-body { padding: 16px; }
    .solicitud-wizard-progress { padding: 14px 12px 12px; }
    .solicitud-wizard-steps { gap: 4px; }
    .solicitud-wizard-step { flex-direction: column; gap: 5px; padding: 2px; text-align: center; }
    .solicitud-wizard-step-number { flex-basis: 32px; width: 32px; height: 32px; }
    .solicitud-wizard-step strong { font-size: .74rem; }
    .solicitud-wizard-step small { display: none; }
    .solicitud-wizard-track { margin-top: 12px; }
    .solicitud-wizard-footer { align-items: stretch; }
    .solicitud-wizard-footer-actions { width: auto; }
    .solicitud-section { padding: 16px; }
    .solicitud-billing-card { padding: 15px; }
    .solicitud-summary { grid-template-columns: 1fr; }
    #resumen-solicitud-modal .modal-body, #qr-pago-modal .modal-body { padding: 16px; }
    #resumen-solicitud-modal .modal-footer, #qr-pago-modal .modal-footer { flex-direction: column-reverse; align-items: stretch; }
    #resumen-solicitud-modal .modal-footer .btn, #qr-pago-modal .modal-footer .btn { margin: 4px 0; }
}
@media (prefers-reduced-motion: reduce) {
    .solicitud-section[data-wizard-panel]:not([hidden]) { animation: none; }
    .solicitud-wizard-step-number, .solicitud-wizard-track span, .solicitud-section, .solicitud-card .form-control, .solicitud-wizard-footer .btn { transition: none; }
}
</style>
@endpush

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const servicioSelect = document.getElementById('servicio_extra_id');
    const origenSelect = document.getElementById('origen');
    const destinoSelect = document.getElementById('destino_id');
    const formaPagoSelect = document.getElementById('forma_pago');
    const tipoPagoSelect = document.getElementById('tipo_pago');
    const tipoPagoContainer = document.getElementById('tipo-pago-container');
    const tipoPagoObligatorio = document.getElementById('tipo-pago-obligatorio');
    const billingPanel = document.getElementById('datos-facturacion-qr');
    const billingDocumentNumber = document.getElementById('numero_documento');
    const billingDocumentComplement = document.getElementById('complemento_documento');
    const billingDocumentType = document.getElementById('tipo_documento');
    const billingDocumentTypeValue = document.getElementById('tipo_documento_valor');
    const billingCompanyName = document.getElementById('razon_social');
    const billingEmail = document.getElementById('correo_facturacion');
    const editBillingEmail = document.getElementById('editar-correo-facturacion');
    const direccionInput = document.getElementById('direccion_entrega');
    const precioSolicitud = document.getElementById('precio_solicitud');
    const paqueteMuyGrandeInput = document.getElementById('paquete_muy_grande');
    const recargoTamanoButton = document.getElementById('recargo-tamano-button');
    const quoteUrl = @json(route('clientes.solicitudes.quote'));
    const qrGenerateUrl = @json(route('clientes.solicitudes.qr.generate'));
    const qrStatusUrl = @json(route('clientes.solicitudes.qr.status'));
    const form = document.getElementById('solicitud-form');
    const modal = $('#resumen-solicitud-modal');
    const qrModal = $('#qr-pago-modal');
    const confirmarSolicitudButton = document.getElementById('confirmar-solicitud-button');
    const wizardPanels = Array.from(document.querySelectorAll('[data-wizard-panel]'));
    const wizardNavButtons = Array.from(document.querySelectorAll('[data-wizard-nav]'));
    const wizardBackButton = document.getElementById('solicitud-wizard-back');
    const wizardNextButton = document.getElementById('solicitud-wizard-next');
    const reviewButton = document.getElementById('solicitud-review-button');
    const wizardTitles = ['Datos del servicio', 'Datos del remitente', 'Datos del destinatario'];
    let generandoQr = false;
    let solicitudRegistrada = false;
    let qrPendienteActivo = false;
    let qrAttemptActivo = '';
    let consultaPagoTimer = null;
    let consultaPagoEnCurso = false;
    let qrConsultaDetenida = false;
    let wizardStep = 0;

    function mostrarPaso(index) {
        wizardStep = Math.max(0, Math.min(index, wizardPanels.length - 1));
        wizardPanels.forEach((panel, panelIndex) => {
            panel.hidden = panelIndex !== wizardStep;
        });
        wizardNavButtons.forEach((button, buttonIndex) => {
            const isActive = buttonIndex === wizardStep;
            button.disabled = buttonIndex > wizardStep;
            button.classList.toggle('is-active', isActive);
            button.classList.toggle('is-complete', buttonIndex < wizardStep);
            if (isActive) {
                button.setAttribute('aria-current', 'step');
            } else {
                button.removeAttribute('aria-current');
            }
        });

        const progress = wizardPanels.length > 1 ? (wizardStep / (wizardPanels.length - 1)) * 100 : 100;
        document.getElementById('solicitud-wizard-bar').style.width = progress + '%';
        document.querySelector('.solicitud-wizard-track').setAttribute('aria-valuenow', String(wizardStep + 1));
        document.getElementById('solicitud-wizard-current').textContent = wizardTitles[wizardStep];
        document.getElementById('solicitud-wizard-number').textContent = String(wizardStep + 1);
        wizardBackButton.hidden = wizardStep === 0;
        wizardNextButton.hidden = wizardStep === wizardPanels.length - 1;
        reviewButton.hidden = wizardStep !== wizardPanels.length - 1;
    }

    function validarPaso(index) {
        const invalidField = wizardPanels[index].querySelector('input:invalid, select:invalid, textarea:invalid');
        if (!invalidField) return true;

        invalidField.reportValidity();
        return false;
    }

    function validarTodosLosPasos() {
        for (let index = 0; index < wizardPanels.length; index++) {
            const invalidField = wizardPanels[index].querySelector('input:invalid, select:invalid, textarea:invalid');
            if (invalidField) {
                mostrarPaso(index);
                invalidField.reportValidity();
                return false;
            }
        }

        return true;
    }

    wizardBackButton.addEventListener('click', function () {
        mostrarPaso(wizardStep - 1);
    });
    wizardNextButton.addEventListener('click', function () {
        if (validarPaso(wizardStep)) mostrarPaso(wizardStep + 1);
    });
    wizardNavButtons.forEach(button => {
        button.addEventListener('click', function () {
            const targetStep = Number(button.dataset.wizardNav);
            if (targetStep < wizardStep) {
                mostrarPaso(targetStep);
            } else if (targetStep === wizardStep + 1 && validarPaso(wizardStep)) {
                mostrarPaso(targetStep);
            }
        });
    });
    mostrarPaso(0);

    ['servicio_extra_id', 'origen', 'destino_id', 'cantidad', 'contenido',
        'nombre_remitente', 'carnet', 'direccion_recojo', 'nombre_destinatario',
        'telefono_destinatario', 'direccion_entrega'].forEach(name => {
        form.elements.namedItem(name).required = true;
    });

    function mostrarResumen() {
        const container = document.getElementById('resumen-solicitud-datos');
        container.replaceChildren();
        const camposServicio = [['Servicio', 'servicio_extra_id'], ['Origen', 'origen'],
            ['Destino', 'destino_id'], ['Cantidad', 'cantidad'], ['Contenido', 'contenido'],
            ['Forma de pago', 'forma_pago']];
        if (formaPagoSelect.value === 'origen') camposServicio.push(['Tipo de pago', 'tipo_pago']);
        const sections = [
            ['Datos del servicio', camposServicio],
            ['Datos del remitente', [['Nombre', 'nombre_remitente'], ['Carnet', 'carnet'],
                ['Teléfono', 'telefono_remitente'], ['Dirección de recojo', 'direccion_recojo']]],
            ['Datos del destinatario', [['Nombre', 'nombre_destinatario'],
                ['Teléfono', 'telefono_destinatario'], ['Dirección de entrega', 'direccion_entrega']]],
        ];
        if (tipoPagoSelect.value === 'qr') {
            sections.push(['Datos de facturacion', [
                ['Documentacion', 'tipo_documento'],
                ['Numero de documento', 'numero_documento'],
                ['Complemento', 'complemento_documento'],
                ['Razon social', 'razon_social'],
                ['Correo factura', 'correo_facturacion']
            ]]);
        }

        sections.forEach(([title, fields]) => {
            const section = document.createElement('section');
            section.className = 'solicitud-summary-section';
            const heading = document.createElement('h6');
            heading.className = 'font-weight-bold';
            heading.textContent = title;
            const list = document.createElement('dl');
            list.className = 'row mb-0';
            fields.forEach(([label, name]) => {
                const field = name === 'tipo_documento' ? billingDocumentType : form.elements.namedItem(name);
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

        const pagaEnDestino = formaPagoSelect.value === 'destino';
        const pagaConQr = !pagaEnDestino && tipoPagoSelect.value === 'qr';
        const pagoTitulo = document.getElementById('resumen-solicitud-pago-titulo');
        const pagoDetalle = document.getElementById('resumen-solicitud-pago-detalle');
        const pagoResumen = document.getElementById('resumen-solicitud-pago');
        pagoResumen.className = `alert ${pagaConQr ? 'alert-primary' : (pagaEnDestino ? 'alert-info' : 'alert-warning')}`;
        pagoTitulo.textContent = pagaEnDestino ? 'Pagar en destino' : (pagaConQr ? 'Pago por QR' : 'Pago en efectivo');
        pagoDetalle.textContent = pagaConQr
            ? 'Se generará un QR. La solicitud y la guía se registrarán cuando se confirme el pago.'
            : (pagaEnDestino
                ? 'La solicitud se registrará ahora para que el pago se realice en destino.'
                : 'La solicitud se registrará ahora y la guía indicará cobrar en origen.');
        document.getElementById('resumen-solicitud-instruccion').textContent = pagaConQr
            ? 'Revisa el importe y los datos antes de generar el QR.'
            : 'Revisa los datos antes de registrar la solicitud.';
        confirmarSolicitudButton.innerHTML = pagaConQr
            ? '<i class="fas fa-qrcode mr-1" aria-hidden="true"></i>Generar QR y pagar'
            : '<i class="fas fa-check mr-1" aria-hidden="true"></i>Registrar solicitud';
    }

    function actualizarAyudaFormaPago() {
        const ayuda = document.getElementById('forma-pago-ayuda');
        const tipoAyuda = document.getElementById('tipo-pago-ayuda');
        const pagaEnOrigen = formaPagoSelect.value === 'origen';
        const pagaEnDestino = formaPagoSelect.value === 'destino';
        if (!pagaEnOrigen) tipoPagoSelect.value = '';
        tipoPagoContainer.hidden = !pagaEnOrigen;
        tipoPagoObligatorio.hidden = !pagaEnOrigen;
        tipoPagoSelect.required = pagaEnOrigen;
        tipoPagoSelect.disabled = !pagaEnOrigen;
        const pagaConQr = pagaEnOrigen && tipoPagoSelect.value === 'qr';
        const pagaEnEfectivo = pagaEnOrigen && tipoPagoSelect.value === 'efectivo';

        billingPanel.hidden = !pagaConQr;
        [billingDocumentNumber, billingCompanyName, billingEmail].forEach(field => {
            field.required = pagaConQr;
        });
        ayuda.textContent = pagaEnDestino
            ? 'El envío se pagará en destino.'
            : pagaEnOrigen
                ? (pagaEnEfectivo ? 'El efectivo se cobrará en origen.' : 'Elige cómo realizarás el pago en origen.')
                : 'Elige dónde se realizará el pago.';
        tipoAyuda.textContent = pagaConQr
            ? 'Se generará un QR y la guía se creará al confirmarse el pago.'
            : pagaEnEfectivo
                ? 'La guía se generará ahora y mostrará cobrar en origen.'
                : 'Elige si pagarás con QR o en efectivo.';
    }
    function actualizarComplementoDocumento() {
        const permiteComplemento = ['1', '2'].includes(billingDocumentType.value);
        billingDocumentComplement.disabled = !permiteComplemento;
        billingDocumentComplement.setAttribute('aria-disabled', permiteComplemento ? 'false' : 'true');
        if (!permiteComplemento) billingDocumentComplement.value = '';
    }

    // Misma clasificacion por formato que utiliza el carrito de los cajeros.
    function inferDocumentTypeFromNumber(rawValue) {
        const value = String(rawValue || '').trim().toUpperCase();
        if (value === '') return '';

        const compact = value.replace(/\s+/g, '');
        const digitsOnly = compact.replace(/\D+/g, '');
        const hasLetters = /[A-Z]/.test(compact);

        if (digitsOnly === '99002' || digitsOnly === '99001') return '5';
        if (/^\d{9,15}$/.test(compact)) return '5';
        if (/^(CEX|EXT|E-)/.test(compact)) return '2';
        if (/^\d{5,8}$/.test(compact)) return '1';
        if (/^[A-Z0-9]{6,12}$/.test(compact) && hasLetters) return '3';

        return '4';
    }

    function detectarDocumentacion() {
        billingDocumentType.value = inferDocumentTypeFromNumber(billingDocumentNumber.value);
        billingDocumentTypeValue.value = billingDocumentType.value;
        const tipoAyuda = document.getElementById('tipo-documento-ayuda');
        const etiqueta = billingDocumentType.selectedOptions[0]?.textContent.trim() || '';
        tipoAyuda.textContent = billingDocumentType.value
            ? 'Tipo detectado por formato: ' + etiqueta + '. Se actualiza automaticamente al escribir el numero.'
            : 'Escribe el numero para detectar el tipo de documento.';

        actualizarComplementoDocumento();
    }

    function sincronizarCorreoFactura(focus = false) {
        const emailPerfil = String(billingEmail.dataset.emailPerfil || '').trim().toLowerCase();
        const permiteEdicion = editBillingEmail.checked;
        billingEmail.readOnly = !permiteEdicion;
        billingEmail.setAttribute('aria-readonly', permiteEdicion ? 'false' : 'true');

        if (!permiteEdicion) billingEmail.value = emailPerfil;
        if (focus && permiteEdicion) billingEmail.focus();
    }

    formaPagoSelect.addEventListener('change', actualizarAyudaFormaPago);
    tipoPagoSelect.addEventListener('change', actualizarAyudaFormaPago);
    billingDocumentNumber.addEventListener('input', detectarDocumentacion);
    editBillingEmail.addEventListener('change', function () {
        sincronizarCorreoFactura(true);
    });
    detectarDocumentacion();
    sincronizarCorreoFactura();
    actualizarAyudaFormaPago();

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (solicitudRegistrada) return;
        if (!validarTodosLosPasos()) return;
        mostrarResumen();
        document.getElementById('qr-generacion-error').classList.add('d-none');
        modal.modal('show');
    });

    function nuevoQrAttemptId() {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => {
            const random = Math.floor(Math.random() * 16);
            return (character === 'x' ? random : (random & 0x3) | 0x8).toString(16);
        });
    }

    function mostrarEstadoPago(message, type = 'info') {
        const state = document.getElementById('qr-pago-estado');
        state.textContent = message;
        state.className = 'alert alert-' + type;
    }

    function mostrarInstruccionPago(message) {
        document.getElementById('qr-pago-instruccion').textContent = message;
    }

    function mostrarFactura(payload) {
        const invoice = document.getElementById('qr-pago-factura');
        const status = String(payload.invoice_status || '').toUpperCase();
        const number = String(payload.invoice_number || '').trim();
        const labels = {
            FACTURADA: 'Factura emitida' + (number ? ' N.º ' + number + '.' : '.'),
            PENDIENTE: 'La factura está pendiente de respuesta fiscal.',
            PROCESADA: 'La factura está en proceso de emisión.',
            EMITIENDO: 'La factura se está emitiendo.',
            EMISION_INCIERTA: 'La emisión de la factura está siendo verificada.',
            RECHAZADA: 'El pago fue confirmado, pero la factura requiere revisión.',
            ERROR: 'El pago fue confirmado, pero la factura requiere revisión.',
            ANULADA: 'El pago fue confirmado, pero la factura figura anulada.',
        };
        invoice.textContent = labels[status] || (payload.paid ? 'La factura se está verificando.' : '');
        if (payload.invoice_error) invoice.textContent += ' ' + payload.invoice_error;
    }

    function mostrarRegistroSolicitud(payload) {
        const registro = document.getElementById('qr-pago-registro');
        registro.replaceChildren();
        registro.className = payload.solicitud_codigo ? 'alert alert-success' : 'alert alert-info';

        if (payload.solicitud_codigo) {
            registro.append(document.createTextNode('Envío registrado con código ' + payload.solicitud_codigo + '. '));
        } else {
            registro.append(document.createTextNode('Pago confirmado. Registrando los datos del envío…'));
        }

        if (payload.ticket_url) {
            const ticket = document.createElement('a');
            ticket.href = payload.ticket_url;
            ticket.target = '_blank';
            ticket.rel = 'noopener noreferrer';
            ticket.className = 'alert-link';
            ticket.textContent = 'Descargar ticket';
            registro.append(document.createTextNode(' '), ticket);
        }

        if (payload.mail_warning) {
            registro.append(document.createElement('br'), document.createTextNode(payload.mail_warning));
        }
    }

    function programarConsultaPago() {
        if (consultaPagoTimer) window.clearTimeout(consultaPagoTimer);
        if (!qrPendienteActivo || !qrAttemptActivo || qrConsultaDetenida) return;
        consultaPagoTimer = window.setTimeout(consultarEstadoPago, 3000);
    }

    async function consultarEstadoPago() {
        if (!qrPendienteActivo || !qrAttemptActivo || consultaPagoEnCurso || qrConsultaDetenida) return;
        const attemptId = qrAttemptActivo;
        consultaPagoEnCurso = true;

        try {
            const response = await fetch(qrStatusUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({
                    _token: form.elements.namedItem('_token').value,
                    qr_attempt_id: attemptId,
                }),
            });
            const payload = await response.json();
            if (qrAttemptActivo !== attemptId) return;
            if (payload.qr?.amount != null) document.getElementById('qr-pago-importe').textContent = payload.qr.amount;
            if (payload.qr?.internal_code) document.getElementById('qr-pago-codigo').textContent = payload.qr.internal_code;
            if (!response.ok && (payload.terminal || payload.retry === false)) {
                qrConsultaDetenida = true;
                mostrarEstadoPago(payload.message || 'La solicitud requiere revisión.', 'warning');
                mostrarFactura(payload);
                if (payload.registration_error === 'qr_amount_mismatch') {
                    document.getElementById('qr-pago-imagen-wrap').classList.add('d-none');
                    document.getElementById('qr-pago-sin-imagen').classList.add('d-none');
                    mostrarInstruccionPago(payload.paid
                        ? 'Pago detectado con monto inconsistente. No vuelvas a pagar; contacta soporte.'
                        : 'No pagues este QR. Contacta soporte antes de iniciar otro intento.');
                }
                if (payload.paid || payload.payment_status === 'pagado') {
                    mostrarInstruccionPago('Pago confirmado. Conserva el código de pago; no vuelvas a pagar.');
                    document.getElementById('qr-pago-imagen-wrap').classList.add('d-none');
                    document.getElementById('qr-pago-sin-imagen').classList.add('d-none');
                }
                return;
            }
            if (!response.ok) throw new Error(payload.message || 'No se pudo consultar el pago.');

            if (payload.payment_status === 'cancelado') {
                qrPendienteActivo = false;
                qrAttemptActivo = '';
                form.elements.namedItem('qr_attempt_id').value = nuevoQrAttemptId();
                mostrarEstadoPago(payload.message || 'El QR venció o fue cancelado. Puedes generar uno nuevo.', 'warning');
                mostrarInstruccionPago('Este QR ya no está activo. Puedes cerrar esta ventana y generar otro.');
                document.getElementById('qr-pago-factura').textContent = '';
                document.getElementById('qr-pago-imagen-wrap').classList.add('d-none');
                document.getElementById('qr-pago-sin-imagen').classList.add('d-none');
                return;
            }

            if (payload.paid || payload.payment_status === 'pagado') {
                mostrarEstadoPago(payload.message || 'Pago confirmado. Registrando el envío y la factura.', 'success');
                mostrarInstruccionPago('Pago confirmado. El QR ya no necesita escanearse.');
                mostrarFactura(payload);
                mostrarRegistroSolicitud(payload);
                solicitudRegistrada = Boolean(payload.solicitud_codigo);
                document.getElementById('qr-pago-imagen-wrap').classList.add('d-none');
                document.getElementById('qr-pago-sin-imagen').classList.add('d-none');
                if (payload.terminal) {
                    qrPendienteActivo = false;
                    if (consultaPagoTimer) window.clearTimeout(consultaPagoTimer);
                    return;
                }
            } else {
                mostrarEstadoPago(payload.message || 'Esperando la confirmación del pago QR.');
            }
        } catch (error) {
            if (qrAttemptActivo === attemptId) {
                mostrarEstadoPago(error.message || 'No pudimos consultar el pago ahora. Reintentaremos automáticamente en 3 segundos.', 'warning');
            }
        } finally {
            consultaPagoEnCurso = false;
            programarConsultaPago();
        }
    }

    function iniciarSeguimientoQr(payload, abrirModal = true) {
        qrConsultaDetenida = false;
        qrAttemptActivo = String(payload.qr_attempt_id || '');
        qrPendienteActivo = Boolean(qrAttemptActivo);
        if (qrAttemptActivo) form.elements.namedItem('qr_attempt_id').value = nuevoQrAttemptId();
        document.getElementById('qr-pago-importe').textContent = payload.amount || '0.00';
        document.getElementById('qr-pago-codigo').textContent = payload.internal_code || '';
        document.getElementById('qr-pago-registro').replaceChildren();
        document.getElementById('qr-pago-registro').className = 'alert d-none';
        document.getElementById('qr-pago-factura').textContent = '';
        mostrarInstruccionPago('Escanea este código para pagar tu envío. Revisaremos el pago cada 3 segundos.');
        mostrarEstadoPago('Esperando la confirmación del pago QR. Consultaremos el estado cada 3 segundos.');

        const image = String(payload.qr_image || '');
        const imageElement = document.getElementById('qr-pago-imagen');
        const imageWrap = document.getElementById('qr-pago-imagen-wrap');
        const downloadLink = document.getElementById('qr-pago-descargar');
        const noImage = document.getElementById('qr-pago-sin-imagen');
        if (image) {
            const imageSource = image.startsWith('data:image/') || image.startsWith('http://') || image.startsWith('https://')
                ? image
                : 'data:image/png;base64,' + image;
            imageElement.src = imageSource;
            const imageExtension = imageSource.match(/^data:image\/(png|jpe?g|webp)/i)?.[1]?.replace('jpeg', 'jpg')
                || imageSource.match(/\.(png|jpe?g|webp)(?:[?#]|$)/i)?.[1]?.replace('jpeg', 'jpg')
                || 'png';
            const safeCode = String(document.getElementById('qr-pago-codigo').textContent || 'pago')
                .replace(/[^a-z0-9_-]/gi, '-');
            downloadLink.href = imageSource;
            downloadLink.download = 'QR-' + safeCode + '.' + imageExtension;
            imageWrap.classList.remove('d-none');
            noImage.classList.add('d-none');
        } else {
            imageElement.removeAttribute('src');
            imageWrap.classList.add('d-none');
            noImage.classList.remove('d-none');
        }

        if (abrirModal) qrModal.modal('show');
        programarConsultaPago();
    }

    // El QR activo se consulta en esta ventana; los intentos anteriores quedan en Mis solicitudes.
    form.addEventListener('submit', function (event) {
        if (!solicitudRegistrada) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        qrModal.modal('show');
    }, true);

    // El cliente online registra el envío desde el servidor al confirmarse el pago.
    confirmarSolicitudButton.addEventListener('click', async function (event) {
        if (tipoPagoSelect.value !== 'qr') return;
        event.preventDefault();
        event.stopImmediatePropagation();

        if (solicitudRegistrada) {
            qrModal.modal('show');
            return;
        }
        if (generandoQr || !validarTodosLosPasos()) return;
        if (!precioCotizado.value) {
            const errorBox = document.getElementById('qr-generacion-error');
            errorBox.textContent = 'Espera a que se calcule el precio antes de generar el QR.';
            errorBox.classList.remove('d-none');
            return;
        }

        generandoQr = true;
        confirmarSolicitudButton.disabled = true;
        confirmarSolicitudButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1" aria-hidden="true"></i>Generando...';
        const errorBox = document.getElementById('qr-generacion-error');
        errorBox.classList.add('d-none');

        try {
            const response = await fetch(qrGenerateUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
            });
            const payload = await response.json();
            if (!response.ok) {
                if (payload.precio_actual) {
                    const actual = Number(payload.precio_actual);
                    tarifaBase = Math.max(0, actual - (paqueteMuyGrande ? 10 : 0));
                    sincronizarBotonRecargo();
                    setPrecio('La tarifa cambió. Nuevo precio: Bs ' + actual.toFixed(2) + '. Revísalo antes de confirmar.', 'warning', actual);
                    mostrarResumen();
                }
                const validationErrors = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
                throw new Error(validationErrors || payload.message || 'No se pudo generar el QR.');
            }

            modal.one('hidden.bs.modal', function () {
                iniciarSeguimientoQr(payload);
            });
            modal.modal('hide');
        } catch (error) {
            errorBox.textContent = error.message || 'No se pudo generar el QR. Intenta nuevamente.';
            errorBox.classList.remove('d-none');
        } finally {
            generandoQr = false;
            confirmarSolicitudButton.disabled = false;
            confirmarSolicitudButton.innerHTML = '<i class="fas fa-qrcode mr-1" aria-hidden="true"></i>Generar QR y pagar';
        }
    }, true);

    confirmarSolicitudButton.addEventListener('click', function () {
        const registrarDirectamente = formaPagoSelect.value === 'destino'
            || (formaPagoSelect.value === 'origen' && tipoPagoSelect.value === 'efectivo');
        if (!registrarDirectamente || solicitudRegistrada || generandoQr) return;
        if (!validarTodosLosPasos()) return;
        HTMLFormElement.prototype.submit.call(form);
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

    const precioCotizado = document.getElementById('precio_cotizado');
    let quoteSequence = 0;
    let tarifaBase = null;
    let paqueteMuyGrande = paqueteMuyGrandeInput.value === '1';

    function setPrecio(message, type, amount = '') {
        if (!precioSolicitud) return;

        precioCotizado.value = amount === '' ? '' : Number(amount).toFixed(2);
        precioSolicitud.textContent = message;
        precioSolicitud.className = 'alert border mb-0 py-2 alert-' + type;
        document.getElementById('resumen-solicitud-precio').textContent = message;
    }

    function mostrarPrecioCotizado(type = 'success') {
        if (!Number.isFinite(tarifaBase)) return;

        const recargo = paqueteMuyGrande ? 10 : 0;
        const total = tarifaBase + recargo;
        const detalleRecargo = recargo > 0 ? ' (incluye Bs 10 por tama\u00f1o).' : '.';
        setPrecio('Precio: Bs ' + total.toFixed(2) + detalleRecargo, type, total);
    }

    function sincronizarBotonRecargo() {
        if (!recargoTamanoButton) return;

        recargoTamanoButton.disabled = !Number.isFinite(tarifaBase);
        recargoTamanoButton.setAttribute('aria-pressed', paqueteMuyGrande ? 'true' : 'false');
        recargoTamanoButton.classList.toggle('btn-warning', paqueteMuyGrande);
        recargoTamanoButton.classList.toggle('btn-outline-warning', !paqueteMuyGrande);
        recargoTamanoButton.innerHTML = paqueteMuyGrande
            ? '<i class="fas fa-minus-circle mr-1" aria-hidden="true"></i>Quitar aumento de Bs 10'
            : '<i class="fas fa-plus-circle mr-1" aria-hidden="true"></i>Aumentar Bs 10 por tama&ntilde;o';
    }

    recargoTamanoButton?.addEventListener('click', function () {
        if (!Number.isFinite(tarifaBase)) return;

        paqueteMuyGrande = !paqueteMuyGrande;
        paqueteMuyGrandeInput.value = paqueteMuyGrande ? '1' : '0';
        sincronizarBotonRecargo();
        mostrarPrecioCotizado();
    });
    sincronizarBotonRecargo();

    async function cotizarSolicitud() {
        const requestSequence = ++quoteSequence;
        const servicioId = servicioSelect.value;
        const origen = origenSelect?.value || '';
        const destinoId = destinoSelect?.value || '';
        const selectionKey = [servicioId, origen, destinoId].join('|');

        if (!servicioId || !origen || !destinoId) {
            tarifaBase = null;
            sincronizarBotonRecargo();
            setPrecio('Seleccione el servicio, origen y destino para conocer el precio.', 'light');
            return;
        }

        tarifaBase = null;
        sincronizarBotonRecargo();
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

            if (requestSequence !== quoteSequence
                || selectionKey !== [servicioSelect.value, origenSelect?.value || '', destinoSelect?.value || ''].join('|')) {
                return;
            }

            if (!response.ok) {
                throw new Error(payload.message || 'No se pudo calcular el precio.');
            }

            const amount = Number(payload.precio);
            if (!Number.isFinite(amount) || amount <= 0) {
                throw new Error('La tarifa recibida no es válida.');
            }

            tarifaBase = amount;
            sincronizarBotonRecargo();
            mostrarPrecioCotizado();
        } catch (error) {
            if (requestSequence !== quoteSequence) return;
            tarifaBase = null;
            sincronizarBotonRecargo();
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
