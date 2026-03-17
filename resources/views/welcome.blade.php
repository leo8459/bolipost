<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<<<<<<< HEAD
    <title>TrackingBO | Plataforma Empresarial Postal</title>
    <meta name="description" content="TrackingBO ofrece rastreo postal y gesti&#xF3;n operativa con enfoque empresarial e institucional.">
=======
    <title>TrackingBo | Plataforma Empresarial Postal</title>
    <meta name="description" content="TrackingBo ofrece rastreo postal y gesti&#xF3;n operativa con enfoque empresarial e institucional.">
>>>>>>> a41ccfb (Uchazara)
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing-shared.css') }}">
    <link rel="stylesheet" href="{{ asset('css/welcome.css') }}">
</head>
<body>
    @include('partials.landing-header')

    <main>
        <section class="hero" id="inicio">
            <div class="container hero-grid">
                <div>
                    <p class="kicker reveal">&#xBF;Est&#xE1;s buscando tu paquete?</p>
                    <h1 class="reveal">RASTREA TU C&#xD3;DIGO</h1>
<<<<<<< HEAD
                    <p class="reveal">Este es un servicio de seguimiento de c&#xF3;digo de rastreo postal a nivel internacional y nacional de Correos de Bolivia.</p>
=======
                    <p class="reveal">Este es un servicio de seguimiento de c&#xF3;digo de rastreo postal a nivel internacional y nacional de la Agencia Boliviana de Correos.</p>
>>>>>>> a41ccfb (Uchazara)
                    <div class="hero-track reveal">
                        <div class="hero-track-title">Rastrea tu c&#xF3;digo</div>
                        <form class="hero-track-form" id="trackForm" action="{{ route('tracking.demo') }}" method="GET">
                            <div class="hero-track-main">
                                <input
                                    class="track-input"
                                    type="text"
                                    name="codigo"
                                    placeholder="INSERTE C&#xD3;DIGO"
                                    value="{{ old('codigo', session('tracking_codigo', '')) }}"
                                    aria-label="C&#xF3;digo de rastreo"
                                    required
                                >
                            </div>
                            <div class="hero-captcha-block">
                                <label class="hero-captcha-label" for="captchaAnswer">Verificaci&#xF3;n de seguridad</label>
                                <div class="hero-captcha-row">
                                    <div class="hero-captcha-code" id="captchaQuestion">{{ $captchaPregunta }}</div>
                                </div>
                                <div class="hero-captcha-entry">
                                    <input
                                        id="captchaAnswer"
                                        class="track-captcha-input"
                                        type="text"
                                        name="captcha_answer"
                                        inputmode="text"
                                        autocomplete="off"
                                        autocapitalize="characters"
                                        placeholder="ESCRIBE EL CAPTCHA"
                                        aria-label="Respuesta del captcha"
                                        required
                                    >
                                    <button class="btn btn-light hero-track-btn" type="submit">Buscar</button>
                                </div>
                                <p class="hero-captcha-help">Escribe los caracteres que ves en la imagen para continuar.</p>
                            </div>
                        </form>
                        <p class="hero-track-feedback {{ session('tracking_error') ? 'is-error' : '' }}" id="trackFeedback" role="status" aria-live="polite">
                            {{ session('tracking_error', '') }}
                        </p>
                      
                        
                    </div>
                </div>

                <div class="hero-figure" id="heroFigure">
<<<<<<< HEAD
                    <img class="hero-img reveal" src="{{ asset('images/MONITO.png') }}" alt="Operador TrackingBO">
=======
                    <img class="hero-img reveal" src="{{ asset('images/MONITO.png') }}" alt="Operador TrackingBo">
>>>>>>> a41ccfb (Uchazara)
                </div>
            </div>
        </section>

        <section class="band">
            <div class="container band-grid">
                <a class="band-item" href="#inicio">
                    <span class="band-item-title">Rastreo</span>
                    <span class="band-item-sub">Buscar c&#xF3;digo</span>
                </a>
                <a class="band-item" href="#servicios">
                    <span class="band-item-title">Servicios</span>
                    <span class="band-item-sub">Env&#xED;o y recojo nacional</span>
                </a>
                <a class="band-item" href="#proceso">
                    <span class="band-item-title">Proceso</span>
                    <span class="band-item-sub">Flujo operativo est&#xE1;ndar</span>
                </a>
                <a class="band-item" href="#cumplimiento">
                    <span class="band-item-title">Cumplimiento</span>
                    <span class="band-item-sub">Objetos permitidos y restringidos</span>
                </a>
                <a class="band-item" href="#contacto">
                    <span class="band-item-title">Contacto</span>
                    <span class="band-item-sub">Canales y atenci&#xF3;n oficial</span>
                </a>
            </div>
        </section>

        <section class="section services-section" id="servicios">
            <div class="container">
                <div class="reveal">
                    <div class="service-title">
<<<<<<< HEAD
                        <h3>Enviar y Recoger Paquetes con Correos de Bolivia: Simple y R&#xE1;pido</h3>
=======
                        <h3>Enviar y Recoger Paquetes con la Agencia Boliviana de Correos: Simple y R&#xE1;pido</h3>
>>>>>>> a41ccfb (Uchazara)
                        <div class="service-divider"></div>
                    </div>
                    <div class="service-journey">
                        <article class="service-row">
                            <div class="service-copy">
                                <span class="service-pill">Env&#xED;o de paquetes</span>
                                <h4>Env&#xED;a tus paquetes en nuestras oficinas</h4>
<<<<<<< HEAD
                                <p>Env&#xED;a tus paquetes en Correos de Bolivia de manera sencilla. Visita nuestras oficinas cercanas, empaca bien tu paquete y proporciona la direcci&#xF3;n del destinatario.</p>
=======
                                <p>Env&#xED;a tus paquetes en la Agencia Boliviana de Correos de manera sencilla. Visita nuestras oficinas cercanas, empaca bien tu paquete y proporciona la direcci&#xF3;n del destinatario.</p>
>>>>>>> a41ccfb (Uchazara)
                                <h5>Requerimientos para el env&#xED;o de paquetes:</h5>
                                <ul class="service-list">
                                    <li>Fotocopia de C.I.</li>
                                    <li>Paquete abierto con el embalaje necesario.</li>
                                    <li>Paquete rotulado con datos personales y destino.</li>
                                    <li>Fotocopia de pasaporte (personas extranjeras).</li>
                                </ul>
                            </div>
                            <div class="service-art" aria-hidden="true">
                                <img src="{{ asset('images/carta.png') }}" alt="Ilustraci&#xF3;n de carta para envio">
                            </div>
                        </article>

                        <article class="service-row">
                            <div class="service-art" aria-hidden="true">
                                <img src="{{ asset('images/mano.png') }}" alt="Ilustraci&#xF3;n de mano recibiendo carta">
                            </div>
                            <div class="service-copy">
                                <span class="service-pill">Recojo de paquetes</span>
                                <h4>Recoge tus paquetes en nuestras oficinas</h4>
<<<<<<< HEAD
                                <p>Para recoger paquetes en Correos de Bolivia, sigue pasos simples: ve a nuestras oficinas cuando recibas un aviso, lleva una identificaci&#xF3;n v&#xE1;lida y el c&#xF3;digo de rastreo.</p>
=======
                                <p>Para recoger paquetes en la Agencia Boliviana de Correos, sigue pasos simples: ve a nuestras oficinas cuando recibas un aviso, lleva una identificaci&#xF3;n v&#xE1;lida y el c&#xF3;digo de rastreo.</p>
>>>>>>> a41ccfb (Uchazara)
                                <h5>Requerimientos para recoger tus paquetes:</h5>
                                <ul class="service-list">
                                    <li>C.I. o pasaporte vigente.</li>
                                    <li>C&#xF3;digo de rastreo del env&#xED;o.</li>
                                    <li>Verificaci&#xF3;n de datos antes de la recepci&#xF3;n final.</li>
                                </ul>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="section alt" id="proceso">
            <div class="container">
                <div class="heading reveal">
                    <h2>Flujo operativo est&#xE1;ndarizado</h2>
                    <p>Proceso claro para fortalecer eficiencia, control y confianza del cliente.</p>
                </div>

                <div class="flow">
                    <article class="step reveal">
                        <span class="step-badge">Paso 1</span>
                        <h4>Recepci&#xF3;n</h4>
                        <p>Registro y validaci&#xF3;n inicial del env&#xED;o en ventanilla.</p>
                    </article>
                    <article class="step reveal">
                        <span class="step-badge">Paso 2</span>
                        <h4>Clasificaci&#xF3;n</h4>
                        <p>Asignaci&#xF3;n de ruta y tratamiento operativo seg&#xFA;n servicio.</p>
                    </article>
                    <article class="step reveal">
                        <span class="step-badge">Paso 3</span>
                        <h4>Tr&#xE1;nsito</h4>
                        <p>Monitoreo de estado durante desplazamiento y traspasos.</p>
                    </article>
                    <article class="step reveal">
                        <span class="step-badge">Paso 4</span>
                        <h4>Entrega</h4>
                        <p>Confirmaci&#xF3;n final de recepci&#xF3;n y cierre del ciclo log&#xED;stico.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section compliance" id="cumplimiento">
            <div class="container">
                <div class="compliance-title reveal">
                    <h3>&#xBF;Sabes lo que puedes y lo que no puedes enviar por el servicio postal?</h3>
                    <div class="service-divider"></div>
                </div>

                <div class="compliance-grid reveal">
                    <article class="compliance-card compliance-side compliance-danger">
                        <h4>Art&#xED;culos u Objetos Vol&#xE1;tiles Peligrosos</h4>
                        <ul>
                            <li>EXPLOSIVOS</li>
                            <li>GASES COMPRIMIDOS</li>
                            <li>L&#xCD;QUIDOS INFLAMABLES</li>
                            <li>SUSTANCIAS COMBURENTES</li>
                            <li>SUSTANCIAS T&#xD3;XICAS</li>
                            <li>MATERIALES RADIOACTIVOS</li>
                        </ul>
                        <div class="compliance-foot">
                            <span class="label">NO</span>
                            <div class="sub">Art&#xED;culos u Objetos Peligrosos</div>
                        </div>
                    </article>

                    <article class="compliance-card compliance-main compliance-allowed">
                        <h4>Art&#xED;culos u Objetos Permitidos</h4>
                        <ul>
                            <li>ROPA</li>
                            <li>DOCUMENTOS CARTAS</li>
                            <li>APARATOS ELECTR&#xD3;NICOS</li>
                            <li>COSM&#xC9;TICOS</li>
                            <li>ART&#xCD;CULOS PARA EL HOGAR</li>
                            <li>TODO LO QUE SE LE OCURRA</li>
                        </ul>
                        <div class="compliance-foot">
                            <span class="label">SI</span>
                            <div class="sub">Art&#xED;culos u Objetos Permitidos</div>
                            <a
                                class="btn"
                                href="https://www.upu.int/UPU/media/upu/files/UPU/outreachAndCampaigns/Dangerous%20Goods%20Campaign/UPU_Flyer_es_web.pdf"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Consulte Aqu&#xED;
                            </a>
                        </div>
                    </article>

                    <article class="compliance-card compliance-side compliance-blocked">
                        <h4>Art&#xED;culos u Objetos Peligrosos Prohibidos</h4>
                        <ul>
                            <li>MEDICAMENTOS</li>
                            <li>ESTRUPEFACIENTES</li>
                            <li>ANIMALES EN GENERAL</li>
                            <li>DINERO</li>
                            <li>BATER&#xCD;AS EL&#xC9;CTRICAS</li>
                            <li>ALIMENTOS PERECEDEROS</li>
                        </ul>
                        <div class="compliance-foot">
                            <span class="label">NO</span>
                            <div class="sub">Art&#xED;culos u Objetos Prohibidos</div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

    </main>

    <div class="search-loading-modal" id="searchLoadingModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="searchLoadingTitle">
        <div class="search-loading-card" role="status" aria-live="polite">
            <div class="search-loading-spinner" aria-hidden="true"></div>
            <h3 id="searchLoadingTitle">Buscando tu env&#xED;o...</h3>
            <p id="searchLoadingText">Estamos consultando los eventos del c&#xF3;digo.</p>
        </div>
    </div>
    <div class="search-loading-modal search-error-modal" id="searchErrorModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="searchErrorTitle">
        <div class="search-loading-card search-error-card" role="alertdialog" aria-live="assertive">
            <div class="search-error-icon" aria-hidden="true">!</div>
            <h3 id="searchErrorTitle">Error de b&#xFA;squeda</h3>
            <p id="searchErrorText">Verifica el c&#xF3;digo e int&#xE9;ntalo nuevamente.</p>
            <button class="btn btn-light search-error-btn" id="searchErrorClose" type="button">Entendido</button>
        </div>
    </div>

<<<<<<< HEAD
    <div class="preregistro-modal" id="preregistroModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="preregistroTitle">
        <div class="preregistro-panel">
            <button class="preregistro-close" type="button" id="preregistroClose" aria-label="Cerrar formulario">
                &times;
            </button>

            <div class="preregistro-head">
                <div>
                    <p class="preregistro-kicker">Preregistro publico</p>
                    <h3 id="preregistroTitle">Hacer envio desde casa</h3>
                    <p>Completa tus datos, guarda tu codigo y presentalo en admision para recuperar tu envio de forma rapida.</p>
                </div>
            </div>

            <div class="preregistro-intro-grid">
                <article class="preregistro-intro-card">
                    <span class="preregistro-intro-step">Paso 1</span>
                    <strong>Llena tus datos</strong>
                    <p>Registra remitente, destinatario, contenido y destino.</p>
                </article>
                <article class="preregistro-intro-card">
                    <span class="preregistro-intro-step">Paso 2</span>
                    <strong>Guarda tu codigo</strong>
                    <p>Se genera un codigo unico para recuperar el preregistro.</p>
                </article>
                <article class="preregistro-intro-card">
                    <span class="preregistro-intro-step">Paso 3</span>
                    <strong>Presenta tu codigo en admision</strong>
                    <p>Con ese codigo, admision recupera tus datos y completa el registro final.</p>
                </article>
            </div>

            @if (session('success'))
                <div class="preregistro-alert preregistro-alert-success">
                    {{ session('success') }}
                    @if (session('preregistro_codigo'))
                        <div class="preregistro-code-card mt-2">
                            <span class="preregistro-code-label">Tu codigo generado</span>
                            <strong class="preregistro-code-value">{{ session('preregistro_codigo') }}</strong>
                            <span class="preregistro-code-help">Presenta este codigo en admision para recuperar tus datos.</span>
                        </div>
                    @endif
                </div>
            @endif

            @if ($errors->any())
                <div class="preregistro-alert preregistro-alert-error">
                    {{ $errors->first('general') ?: 'Revisa los campos del preregistro y vuelve a intentar.' }}
                </div>
            @endif

            <form method="POST" action="{{ route('preregistros.public.store') }}" class="preregistro-form">
                @csrf

                <div class="preregistro-section-card">
                    <div class="preregistro-section-head">
                        <span class="preregistro-section-pill">Datos del envio</span>
                        <p>Selecciona origen, servicio, destino y caracteristicas del paquete.</p>
                    </div>
                <div class="preregistro-grid">
                    <div class="preregistro-field">
                        <label>Origen</label>
                        <select name="origen">
                            <option value="">Seleccione...</option>
                            @foreach($preregistroCiudades as $ciudad)
                                <option value="{{ $ciudad }}" @selected(old('origen') === $ciudad)>{{ $ciudad }}</option>
                            @endforeach
                        </select>
                        @error('origen') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="preregistro-field">
                        <label>Tipo de correspondencia</label>
                        <input type="text" name="tipo_correspondencia" value="{{ old('tipo_correspondencia') }}" placeholder="Ej: DOCUMENTO, OFICIAL">
                        @error('tipo_correspondencia') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="preregistro-field">
                        <label>Servicio</label>
                        <select name="servicio_id" class="preregistro-select-compact" title="Selecciona el servicio">
                            <option value="">Seleccione...</option>
                            @foreach($preregistroServicios as $servicio)
                                @php
                                    $serviceLabel = (string) \Illuminate\Support\Str::of($servicio->nombre_servicio)
                                        ->replace('_', ' ')
                                        ->squish()
                                        ->limit(28, '...');
                                @endphp
                                <option value="{{ $servicio->id }}" title="{{ $servicio->nombre_servicio }}" @selected((int) old('servicio_id') === (int) $servicio->id)>{{ $serviceLabel }}</option>
                            @endforeach
                        </select>
                        @error('servicio_id') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="preregistro-field">
                        <label>Destino</label>
                        <select name="destino_id">
                            <option value="">Seleccione...</option>
                            @foreach($preregistroDestinos as $destino)
                                <option value="{{ $destino->id }}" @selected((int) old('destino_id') === (int) $destino->id)>{{ $destino->nombre_destino }}</option>
                            @endforeach
                        </select>
                        @error('destino_id') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="preregistro-field">
                        <label>Servicio especial</label>
                        <select name="servicio_especial">
                            <option value="">Seleccione...</option>
                            <option value="POR COBRAR" @selected(old('servicio_especial') === 'POR COBRAR')>POR COBRAR</option>
                            <option value="IDA Y VUELTA" @selected(old('servicio_especial') === 'IDA Y VUELTA')>IDA Y VUELTA</option>
                        </select>
                        @error('servicio_especial') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="preregistro-field">
                        <label>Cantidad</label>
                        <input type="number" min="1" name="cantidad" value="{{ old('cantidad', 1) }}">
                        @error('cantidad') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="preregistro-field preregistro-field-full">
                        <label>Contenido</label>
                        <textarea name="contenido" rows="2">{{ old('contenido') }}</textarea>
                        @error('contenido') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="preregistro-field">
                        <label>Peso</label>
                        <input type="number" step="0.001" min="0.001" name="peso" value="{{ old('peso') }}">
                        @error('peso') <small>{{ $message }}</small> @enderror
                    </div>
                </div>
                </div>

                <div class="preregistro-section-card">
                    <div class="preregistro-section-head">
                        <span class="preregistro-section-pill">Datos de contacto</span>
                        <p>Estos datos se recuperaran luego en admision usando tu codigo generado.</p>
                    </div>
                <div class="preregistro-grid">
                    <div class="preregistro-field">
                        <label>Nombre remitente</label>
                        <input type="text" name="nombre_remitente" value="{{ old('nombre_remitente') }}">
                        @error('nombre_remitente') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="preregistro-field">
                        <label>Nombre envia</label>
                        <input type="text" name="nombre_envia" value="{{ old('nombre_envia') }}">
                        @error('nombre_envia') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="preregistro-field">
                        <label>Carnet</label>
                        <input type="text" name="carnet" value="{{ old('carnet') }}">
                        @error('carnet') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="preregistro-field">
                        <label>Telefono remitente</label>
                        <input type="text" name="telefono_remitente" value="{{ old('telefono_remitente') }}">
                        @error('telefono_remitente') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="preregistro-field">
                        <label>Nombre destinatario</label>
                        <input type="text" name="nombre_destinatario" value="{{ old('nombre_destinatario') }}">
                        @error('nombre_destinatario') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="preregistro-field">
                        <label>Telefono destinatario</label>
                        <input type="text" name="telefono_destinatario" value="{{ old('telefono_destinatario') }}">
                        @error('telefono_destinatario') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="preregistro-field preregistro-field-full">
                        <label>Direccion</label>
                        <input type="text" name="direccion" value="{{ old('direccion') }}">
                        @error('direccion') <small>{{ $message }}</small> @enderror
                    </div>
                </div>
                </div>

                <div class="preregistro-actions">
                    <p>Guarda tu codigo. En admision lo usaran para recuperar tu preregistro.</p>
                    <button class="btn btn-primary" type="submit">Enviar preregistro</button>
                </div>
            </form>
        </div>
    </div>

    <div class="preregistro-success-modal" id="preregistroSuccessModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="preregistroSuccessTitle">
        <div class="preregistro-success-card">
            <div class="preregistro-success-icon" aria-hidden="true">OK</div>
            <p class="preregistro-success-kicker">Preregistro guardado</p>
            <h3 id="preregistroSuccessTitle">Su codigo es</h3>
            <div class="preregistro-success-code" id="preregistroSuccessCode">{{ session('preregistro_codigo') }}</div>
            <p class="preregistro-success-copy">Guarda este codigo. En admision lo usaran para recuperar tus datos.</p>
            <div class="preregistro-success-actions">
                <button class="btn btn-light" type="button" id="copyPreregistroCode">Copiar codigo</button>
                <a class="btn btn-light" href="{{ session('preregistro_ticket_url', '#') }}" id="downloadPreregistroTicket" @if(!session('preregistro_ticket_url')) style="display:none" @endif>
                    Descargar ticket PDF
                </a>
                <button class="btn btn-primary" type="button" id="closePreregistroSuccess">Entendido</button>
            </div>
        </div>
    </div>

=======
>>>>>>> a41ccfb (Uchazara)
    <button class="back-to-top" id="backToTop" type="button" aria-label="Subir al inicio">
        &uarr;
    </button>

    @include('partials.landing-footer')

    <script>
        const topbar = document.getElementById('topbar');
        const menuToggle = document.getElementById('menuToggle');
        const menu = document.getElementById('menu');
<<<<<<< HEAD
        const preregistroModal = document.getElementById('preregistroModal');
        const preregistroClose = document.getElementById('preregistroClose');
        const preregistroTriggers = document.querySelectorAll('[data-open-preregistro], .btn-home-shipping');
        const preregistroSuccessModal = document.getElementById('preregistroSuccessModal');
        const closePreregistroSuccess = document.getElementById('closePreregistroSuccess');
        const copyPreregistroCode = document.getElementById('copyPreregistroCode');
        const preregistroSuccessCode = document.getElementById('preregistroSuccessCode');
        const preregistroTicketUrl = @json(session('preregistro_ticket_url'));


        const openPreregistroModal = () => {
            if (!preregistroModal) return;
            preregistroModal.classList.add('is-open');
            preregistroModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('has-loading-modal');
        };

        const closePreregistroModal = () => {
            if (!preregistroModal) return;
            preregistroModal.classList.remove('is-open');
            preregistroModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('has-loading-modal');
        };

        const openPreregistroSuccessModal = () => {
            if (!preregistroSuccessModal) return;
            preregistroSuccessModal.classList.add('is-open');
            preregistroSuccessModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('has-loading-modal');
        };

        const closePreregistroSuccessModal = () => {
            if (!preregistroSuccessModal) return;
            preregistroSuccessModal.classList.remove('is-open');
            preregistroSuccessModal.setAttribute('aria-hidden', 'true');
            if (!preregistroModal?.classList.contains('is-open')) {
                document.body.classList.remove('has-loading-modal');
            }
        };

        preregistroTriggers.forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                if (!preregistroModal) return;
                event.preventDefault();
                openPreregistroModal();
            });
        });

        preregistroClose?.addEventListener('click', closePreregistroModal);
        preregistroModal?.addEventListener('click', (event) => {
            if (event.target === preregistroModal) {
                closePreregistroModal();
            }
        });
        preregistroSuccessModal?.addEventListener('click', (event) => {
            if (event.target === preregistroSuccessModal) {
                closePreregistroSuccessModal();
            }
        });
        closePreregistroSuccess?.addEventListener('click', closePreregistroSuccessModal);
        copyPreregistroCode?.addEventListener('click', async () => {
            const code = preregistroSuccessCode?.textContent?.trim();
            if (!code) return;

            try {
                await navigator.clipboard.writeText(code);
                copyPreregistroCode.textContent = 'Codigo copiado';
                setTimeout(() => {
                    copyPreregistroCode.textContent = 'Copiar codigo';
                }, 1600);
            } catch (error) {
                window.prompt('Copia tu codigo generado:', code);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePreregistroModal();
                closePreregistroSuccessModal();
            }
        });

        @if ($errors->any() || session('success'))
            openPreregistroModal();
        @endif
        @if (session('preregistro_codigo'))
            openPreregistroSuccessModal();
            if (preregistroTicketUrl) {
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = preregistroTicketUrl;
                document.body.appendChild(iframe);
            }
        @endif
=======
>>>>>>> a41ccfb (Uchazara)

        menuToggle?.addEventListener('click', () => { const isOpen = menu.classList.toggle('open'); menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false'); });
        menu.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => { menu.classList.remove('open'); menuToggle?.setAttribute('aria-expanded', 'false'); }));

        const onScroll = () => topbar.classList.toggle('scrolled', window.scrollY > 8);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        const reveals = document.querySelectorAll('.reveal');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                entry.target.style.transition = 'opacity .55s ease, transform .55s ease';
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.16 });

        reveals.forEach((el, i) => {
            el.style.transitionDelay = `${Math.min(i * 35, 220)}ms`;
            observer.observe(el);
        });

        const heroFigure = document.getElementById('heroFigure');
        if (heroFigure && window.matchMedia('(pointer:fine)').matches) {
            window.addEventListener('mousemove', (e) => {
                const x = (e.clientX / window.innerWidth - 0.5) * 10;
                const y = (e.clientY / window.innerHeight - 0.5) * 8;
                heroFigure.style.transform = `translate3d(${x}px, ${y}px, 0)`;
            });
            window.addEventListener('mouseleave', () => {
                heroFigure.style.transform = 'translate3d(0, 0, 0)';
            });
        }

        const shippingProgress = document.getElementById('shippingProgress');
        if (shippingProgress) {
            requestAnimationFrame(() => {
                shippingProgress.style.width = '72%';
            });
        }

        const sectionIds = ['inicio', 'servicios', 'proceso', 'cumplimiento', 'contacto'];
        const headerLinks = Array.from(document.querySelectorAll('.menu a[href*="#"]'));
        const bandLinks = Array.from(document.querySelectorAll('.band-item[href^="#"]'));
        const sections = sectionIds.map((id) => document.getElementById(id)).filter(Boolean);

        const hashOf = (href) => {
            const idx = href.indexOf('#');
            return idx >= 0 ? href.slice(idx + 1) : '';
        };

        const setActive = (id) => {
            bandLinks.forEach((link) => {
                const isActive = hashOf(link.getAttribute('href') || '') === id;
                link.classList.toggle('is-active', isActive);
            });
        };

        const smoothTo = (id) => {
            const target = document.getElementById(id);
            if (!target) return;
            const offset = (topbar?.offsetHeight || 0) + 14;
            const y = target.getBoundingClientRect().top + window.scrollY - offset;
            window.scrollTo({ top: y, behavior: 'smooth' });
        };

        bandLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                const id = hashOf(link.getAttribute('href') || '');
                if (!sectionIds.includes(id)) return;
                event.preventDefault();
                smoothTo(id);
                setActive(id);
                history.replaceState(null, '', `#${id}`);
            });
        });

        headerLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                const id = hashOf(link.getAttribute('href') || '');
                if (!id) return;
                if (!document.getElementById(id)) return;
                event.preventDefault();
                smoothTo(id);
                history.replaceState(null, '', `#${id}`);
            });
        });

        const detectActiveByScroll = () => {
            const topOffset = (topbar?.offsetHeight || 0) + 90;
            const currentY = window.scrollY + topOffset;

            let activeId = sectionIds[0];
            sections.forEach((section) => {
                if (section.offsetTop <= currentY) activeId = section.id;
            });

            setActive(activeId);
        };

        window.addEventListener('scroll', detectActiveByScroll, { passive: true });

        const initial = window.location.hash.replace('#', '');
        if (sectionIds.includes(initial)) {
            setActive(initial);
            setTimeout(() => smoothTo(initial), 100);
        } else {
            setActive('inicio');
        }
        detectActiveByScroll();

        const backToTop = document.getElementById('backToTop');
        const handleBackButton = () => {
            if (!backToTop) return;
            backToTop.classList.toggle('is-visible', window.scrollY > 420);
        };
        window.addEventListener('scroll', handleBackButton, { passive: true });
        handleBackButton();

        backToTop?.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            history.replaceState(null, '', '#inicio');
            setActive('inicio');
        });

        const trackForm = document.getElementById('trackForm');
        const trackFeedback = document.getElementById('trackFeedback');
        const captchaAnswer = document.getElementById('captchaAnswer');
        const captchaQuestion = document.getElementById('captchaQuestion');
        const resultsTitle = document.getElementById('resultsTitle');
        const resultsTotal = document.getElementById('resultsTotal');
        const resultsEmpty = document.getElementById('resultsEmpty');
        const resultsList = document.getElementById('resultsList');
        const searchLoadingModal = document.getElementById('searchLoadingModal');
        const searchLoadingText = document.getElementById('searchLoadingText');
        const searchErrorModal = document.getElementById('searchErrorModal');
        const searchErrorTitle = document.getElementById('searchErrorTitle');
        const searchErrorText = document.getElementById('searchErrorText');
        const searchErrorClose = document.getElementById('searchErrorClose');

        const showLoadingModal = (message = 'Estamos consultando los eventos del código.') => {
            if (!searchLoadingModal) return;
            if (searchLoadingText) searchLoadingText.textContent = message;
            searchLoadingModal.classList.add('is-open');
            searchLoadingModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('has-loading-modal');
        };

        const hideLoadingModal = () => {
            if (!searchLoadingModal) return;
            searchLoadingModal.classList.remove('is-open');
            searchLoadingModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('has-loading-modal');
        };

        const showErrorModal = (message = 'Verifica el código e inténtalo nuevamente.') => {
            if (!searchErrorModal) return;
            if (searchErrorText) searchErrorText.textContent = message;
            searchErrorModal.classList.add('is-open');
            searchErrorModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('has-loading-modal');
        };

        const hideErrorModal = () => {
            if (!searchErrorModal) return;
            searchErrorModal.classList.remove('is-open');
            searchErrorModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('has-loading-modal');
        };

        const openErrorModal = (title = 'Error de busqueda', message = 'Verifica el codigo e intentalo nuevamente.') => {
            if (searchErrorTitle) searchErrorTitle.textContent = title;
            showErrorModal(message);
        };

        const updateCaptchaQuestion = (question) => {
            if (!captchaQuestion || !question) return;
            captchaQuestion.textContent = question;
        };

        const clearTrackingForm = () => {
            const input = trackForm?.querySelector('input[name="codigo"]');
            if (input) input.value = '';
            if (captchaAnswer) captchaAnswer.value = '';
        };

        const loadCaptcha = async () => {
            try {
                const response = await fetch("{{ route('api.busqueda.captcha') }}", {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await response.json();
                updateCaptchaQuestion(data?.pregunta || '{{ $captchaPregunta }}');
            } catch (error) {
                updateCaptchaQuestion('{{ $captchaPregunta }}');
            } finally {
                if (captchaAnswer) captchaAnswer.value = '';
            }
        };

        // Evita que el modal quede "pegado" al volver con el boton atras (bfcache/historial).
        hideLoadingModal();
        hideErrorModal();
        window.addEventListener('pageshow', () => { hideLoadingModal(); hideErrorModal(); });
        window.addEventListener('popstate', () => { hideLoadingModal(); hideErrorModal(); });
        searchErrorClose?.addEventListener('click', hideErrorModal);
        searchErrorModal?.addEventListener('click', (e) => {
            if (e.target === searchErrorModal) hideErrorModal();
        });
        const formatDate = (value) => {
            if (!value) return '-';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return new Intl.DateTimeFormat('es-BO', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(date);
        };

        const showFeedback = (message, type = '') => {
            if (!trackFeedback) return;
            trackFeedback.textContent = message;
            trackFeedback.classList.remove('is-error', 'is-success');
            if (type) trackFeedback.classList.add(type);
        };

        const renderResults = (codigo, eventos, emptyMessage = 'No se encontraron eventos para este código.') => {
            if (!resultsList || !resultsEmpty || !resultsTitle || !resultsTotal) return;

            resultsList.innerHTML = '';
            resultsTitle.textContent = codigo ? `Código: ${codigo}` : '';
            resultsTotal.textContent = codigo ? `${eventos.length} evento(s)` : '';

            if (!eventos.length) {
                resultsEmpty.style.display = emptyMessage ? 'block' : 'none';
                resultsEmpty.textContent = emptyMessage;
                return;
            }

            resultsEmpty.style.display = 'none';
            eventos.forEach((evento) => {
                const item = document.createElement('article');
                item.className = 'tracking-event-item';

                const left = document.createElement('div');
                const title = document.createElement('h4');
                const created = document.createElement('p');
                const code = document.createElement('span');
                const servicio = evento.servicio ? ` | Servicio: ${evento.servicio}` : '';

                title.textContent = evento.nombre_evento ?? ('Evento #' + (evento.evento_id ?? '-'));
                created.textContent = `Registrado: ${formatDate(evento.created_at)}${servicio}`;
                code.className = 'tracking-event-code';
                code.textContent = evento.codigo ?? codigo;

                left.appendChild(title);
                left.appendChild(created);
                item.appendChild(left);
                item.appendChild(code);
                resultsList.appendChild(item);
            });
        };

        trackForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const input = trackForm.querySelector('input[name="codigo"]');
            const codigo = (input?.value || '').trim().toUpperCase();
            const captchaValue = (captchaAnswer?.value || '').trim();

            if (!codigo) {
                showFeedback('', '');
                openErrorModal('Codigo invalido', 'Ingresa un codigo valido.');
                return;
            }

            if (!captchaValue) {
                showFeedback('', '');
                openErrorModal('Captcha requerido', 'Escribe el captcha para continuar.');
                captchaAnswer?.focus();
                return;
            }

            showFeedback('', '');
            showLoadingModal('Estamos consultando los eventos del código.');

            try {
                const response = await fetch(`/api/busqueda/ems-eventos?codigo=${encodeURIComponent(codigo)}&captcha_answer=${encodeURIComponent(captchaValue)}`, {
                    headers: { 'Accept': 'application/json' },
                });

                const data = await response.json();
                if (!response.ok) {
                    const message = data?.message || 'No se pudo realizar la búsqueda.';
                    if (data?.captcha?.pregunta) {
                        updateCaptchaQuestion(data.captcha.pregunta);
                    } else {
                        await loadCaptcha();
                    }
                    clearTrackingForm();
                    throw new Error(message);
                }

                showLoadingModal('Paquete encontrado. Redirigiendo al detalle...');
                clearTrackingForm();
                window.location.href = `${trackForm.getAttribute('action')}?codigo=${encodeURIComponent(codigo)}`;
            } catch (error) {
                hideLoadingModal();
                const message = error.message || 'No existe dicho paquete';
                const isCaptchaError = message.toLowerCase().includes('verificacion de seguridad');
                renderResults('', [], '');
                showFeedback('', '');
                openErrorModal(isCaptchaError ? 'Captcha incorrecto' : 'No encontramos el envio', message);
            }
        });

        if (!captchaQuestion?.textContent?.trim()) {
            loadCaptcha();
        }

    </script>
</body>
</html>



