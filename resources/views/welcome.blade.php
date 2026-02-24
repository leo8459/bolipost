<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TrackingBo | Plataforma Empresarial Postal</title>
    <meta name="description" content="TrackingBo ofrece rastreo postal y gestión operativa con enfoque empresarial e institucional.">
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
                    <p class="kicker reveal">¿Estás buscando tu paquete?</p>
                    <h1 class="reveal">RASTREA TU CÓDIGO</h1>
                    <p class="reveal">Este es un servicio de seguimiento de código de rastreo postal a nivel internacional y nacional de la Agencia Boliviana de Correos.</p>
                    <div class="hero-track reveal">
                        <div class="hero-track-title">Rastrea tu código</div>
                        <form class="hero-track-form" action="{{ route('tracking.demo') }}" method="GET">
                            <input
                                class="track-input"
                                type="text"
                                name="codigo"
                                placeholder="Ingresa tu código de rastreo"
                                value="RE700274030ES"
                                aria-label="Código de rastreo"
                            >
                            <button class="btn btn-light hero-track-btn" type="submit">Buscar</button>
                        </form>
                        <label class="hero-captcha-label" for="captchaDemo">Verificación de seguridad</label>
                        <div class="hero-captcha-row">
                            <input
                                id="captchaDemo"
                                class="track-captcha-input"
                                type="text"
                                placeholder="Ingrese el texto"
                                aria-label="Texto de verificación"
                            >
                            <div class="hero-captcha-code">6M2B9</div>
                        </div>
                      
                        
                    </div>
                </div>

                <div class="hero-figure" id="heroFigure">
                    <img class="hero-img reveal" src="{{ asset('images/MONITO.png') }}" alt="Operador TrackingBo">
                </div>
            </div>
        </section>

        <section class="band">
            <div class="container band-grid">
                <a class="band-item" href="#servicios">
                    <span class="band-item-title">Servicios</span>
                    <span class="band-item-sub">Envío y recojo nacional</span>
                </a>
                <a class="band-item" href="#proceso">
                    <span class="band-item-title">Proceso</span>
                    <span class="band-item-sub">Flujo operativo estándar</span>
                </a>
                <a class="band-item" href="#cumplimiento">
                    <span class="band-item-title">Cumplimiento</span>
                    <span class="band-item-sub">Objetos permitidos y restringidos</span>
                </a>
                <a class="band-item" href="#contacto">
                    <span class="band-item-title">Contacto</span>
                    <span class="band-item-sub">Canales y atención oficial</span>
                </a>
            </div>
        </section>

        <section class="section services-section" id="servicios">
            <div class="container">
                <div class="reveal">
                    <div class="service-title">
                        <h3>Enviar y Recoger Paquetes con la Agencia Boliviana de Correos: Simple y Rápido</h3>
                        <div class="service-divider"></div>
                    </div>
                    <div class="service-journey">
                        <article class="service-row">
                            <div class="service-copy">
                                <span class="service-pill">Envío de paquetes</span>
                                <h4>Envía tus paquetes en nuestras oficinas</h4>
                                <p>Envía tus paquetes en la Agencia Boliviana de Correos de manera sencilla. Visita nuestras oficinas cercanas, empaca bien tu paquete y proporciona la dirección del destinatario.</p>
                                <h5>Requerimientos para el envío de paquetes:</h5>
                                <ul class="service-list">
                                    <li>Fotocopia de C.I.</li>
                                    <li>Paquete abierto con el embalaje necesario.</li>
                                    <li>Paquete rotulado con datos personales y destino.</li>
                                    <li>Fotocopia de pasaporte (personas extranjeras).</li>
                                </ul>
                            </div>
                            <div class="service-art" aria-hidden="true">
                                <img src="{{ asset('images/carta.png') }}" alt="Ilustración de carta para envío">
                            </div>
                        </article>

                        <article class="service-row">
                            <div class="service-art" aria-hidden="true">
                                <img src="{{ asset('images/mano.png') }}" alt="Ilustración de mano recibiendo carta">
                            </div>
                            <div class="service-copy">
                                <span class="service-pill">Recojo de paquetes</span>
                                <h4>Recoge tus paquetes en nuestras oficinas</h4>
                                <p>Para recoger paquetes en la Agencia Boliviana de Correos, sigue pasos simples: ve a nuestras oficinas cuando recibas un aviso, lleva una identificación válida y el código de rastreo.</p>
                                <h5>Requerimientos para recoger tus paquetes:</h5>
                                <ul class="service-list">
                                    <li>C.I. o pasaporte vigente.</li>
                                    <li>Código de rastreo del envío.</li>
                                    <li>Verificación de datos antes de la recepción final.</li>
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
                    <h2>Flujo operativo estandarizado</h2>
                    <p>Proceso claro para fortalecer eficiencia, control y confianza del cliente.</p>
                </div>

                <div class="flow">
                    <article class="step reveal">
                        <span class="step-badge">Paso 1</span>
                        <h4>Recepción</h4>
                        <p>Registro y validación inicial del envío en ventanilla.</p>
                    </article>
                    <article class="step reveal">
                        <span class="step-badge">Paso 2</span>
                        <h4>Clasificación</h4>
                        <p>Asignación de ruta y tratamiento operativo según servicio.</p>
                    </article>
                    <article class="step reveal">
                        <span class="step-badge">Paso 3</span>
                        <h4>Tránsito</h4>
                        <p>Monitoreo de estado durante desplazamiento y traspasos.</p>
                    </article>
                    <article class="step reveal">
                        <span class="step-badge">Paso 4</span>
                        <h4>Entrega</h4>
                        <p>Confirmación final de recepción y cierre del ciclo logístico.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section compliance" id="cumplimiento">
            <div class="container">
                <div class="compliance-title reveal">
                    <h3>¿Sabes lo que puedes y lo que no puedes enviar por el servicio postal?</h3>
                    <div class="service-divider"></div>
                </div>

                <div class="compliance-grid reveal">
                    <article class="compliance-card compliance-side compliance-danger">
                        <h4>Artículos u Objetos Volátiles Peligrosos</h4>
                        <ul>
                            <li>EXPLOSIVOS</li>
                            <li>GASES COMPRIMIDOS</li>
                            <li>LÍQUIDOS INFLAMABLES</li>
                            <li>SUSTANCIAS COMBURENTES</li>
                            <li>SUSTANCIAS TÓXICAS</li>
                            <li>MATERIALES RADIOACTIVOS</li>
                        </ul>
                        <div class="compliance-foot">
                            <span class="label">NO</span>
                            <div class="sub">Artículos u Objetos Peligrosos</div>
                        </div>
                    </article>

                    <article class="compliance-card compliance-main compliance-allowed">
                        <h4>Artículos u Objetos Permitidos</h4>
                        <ul>
                            <li>ROPA</li>
                            <li>DOCUMENTOS CARTAS</li>
                            <li>APARATOS ELECTRÓNICOS</li>
                            <li>COSMÉTICOS</li>
                            <li>ARTÍCULOS PARA EL HOGAR</li>
                            <li>TODO LO QUE SE LE OCURRA</li>
                        </ul>
                        <div class="compliance-foot">
                            <span class="label">SI</span>
                            <div class="sub">Artículos u Objetos Permitidos</div>
                            <a
                                class="btn"
                                href="https://www.upu.int/UPU/media/upu/files/UPU/outreachAndCampaigns/Dangerous%20Goods%20Campaign/UPU_Flyer_es_web.pdf"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Consulte Aquí
                            </a>
                        </div>
                    </article>

                    <article class="compliance-card compliance-side compliance-blocked">
                        <h4>Artículos u Objetos Peligrosos Prohibidos</h4>
                        <ul>
                            <li>MEDICAMENTOS</li>
                            <li>ESTRUPEFACIENTES</li>
                            <li>ANIMALES EN GENERAL</li>
                            <li>DINERO</li>
                            <li>BATERÍAS ELÉCTRICAS</li>
                            <li>ALIMENTOS PERECEDEROS</li>
                        </ul>
                        <div class="compliance-foot">
                            <span class="label">NO</span>
                            <div class="sub">Artículos u Objetos Prohibidos</div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

    </main>

    <button class="back-to-top" id="backToTop" type="button" aria-label="Subir al inicio">
        &uarr;
    </button>

    @include('partials.landing-footer')

    <script>
        const topbar = document.getElementById('topbar');
        const menuToggle = document.getElementById('menuToggle');
        const menu = document.getElementById('menu');

        menuToggle?.addEventListener('click', () => menu.classList.toggle('open'));
        menu.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => menu.classList.remove('open')));

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

        const sectionIds = ['servicios', 'proceso', 'cumplimiento', 'contacto'];
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
            setActive('servicios');
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
            setActive('servicios');
        });

    </script>
</body>
</html>

