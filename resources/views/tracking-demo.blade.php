<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TrackingBo | Eventos de Rastreo</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing-shared.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tracking-demo.css') }}">
</head>
<body>
    @php
        $codigo = strtoupper((string) request('codigo', 'RE700274030ES'));
    @endphp

    @include('partials.landing-header')

    <main>
        <section class="hero">
            <div class="container">
                <div class="hero-wrap">
                    <article class="hero-card">
                        Su paquete <strong>{{ $codigo }}</strong> fue encontrado. Estos son sus eventos de ejemplo:
                        <div class="hero-actions">
                            <a class="btn btn-light" href="{{ url('/') }}#inicio">Volver a la página principal</a>
                          
                        </div>
                    </article>
                    <aside class="hero-side">
                        <h3>Resumen de rastreo</h3>
                        <div class="hero-side-item"><span>Estado:</span><strong class="live-state">En tránsito</strong></div>
                        <div class="hero-side-item"><span>Último evento:</span><strong>Intento de entrega</strong></div>
                        <div class="hero-side-item"><span>Oficina:</span><strong>BOLPBA - LA PAZ</strong></div>
                    </aside>
                </div>
            </div>
        </section>

        <section class="timeline-section">
            <div class="container">
                <div class="timeline-head">
                    <h1>Últimos Eventos</h1>
                    <p>Visualización de eventos de ejemplo con estilo institucional TrackingBo.</p>
                    <div class="timeline-note">Más reciente arriba</div>
                </div>

                <div class="legend">
                    <span class="legend-pill warn">Incidencia</span>
                    <span class="legend-pill move">En tránsito</span>
                    <span class="legend-pill ok">Completado</span>
                </div>

                <div class="summary">
                    <div class="summary-item">
                        <strong>Código</strong>
                        <span>{{ $codigo }}</span>
                    </div>
                    <div class="summary-item">
                        <strong>Estado Actual</strong>
                        <span>En tránsito a oficina de entrega</span>
                    </div>
                    <div class="summary-item">
                        <strong>Última Oficina</strong>
                        <span>BOLPBA - LA PAZ LC/AO</span>
                    </div>
                </div>

                <div class="timeline-skeleton" id="timelineSkeleton" aria-hidden="true">
                    <div class="skeleton-card"></div>
                    <div class="skeleton-card"></div>
                    <div class="skeleton-card"></div>
                </div>

                <div class="timeline" id="timelineList">
                    <article class="event latest">
                        <div class="event-head">
                            <span class="event-badge">{{ $codigo }}</span>
                            <span class="event-status status-warn">Incidencia</span>
                        </div>
                        <h3 class="event-title">Intento fallido de entrega del paquete</h3>
                        <p class="event-office">Oficina: BOLPBA - LA PAZ LC/AO</p>
                        <p class="event-date">2026-02-10 16:31:02</p>
                        <span class="event-time">Hace 2 horas</span>
                        <div class="event-bar"></div>
                    </article>

                    <article class="event">
                        <div class="event-head">
                            <span class="event-badge">{{ $codigo }}</span>
                            <span class="event-status status-move">En tránsito</span>
                        </div>
                        <h3 class="event-title">Paquete recibido en oficina de entrega (Listo para entregar)</h3>
                        <p class="event-office">Oficina: BOLPBA - LA PAZ LC/AO</p>
                        <p class="event-date">2026-01-22 13:19:47</p>
                        <span class="event-time">Hace 1 día</span>
                        <div class="event-bar"></div>
                    </article>

                    <article class="event">
                        <div class="event-head">
                            <span class="event-badge">{{ $codigo }}</span>
                            <span class="event-status status-move">En tránsito</span>
                        </div>
                        <h3 class="event-title">Paquete recibido en oficina de entrega (Listo para entregar)</h3>
                        <p class="event-office">Oficina: BOLPBA - LA PAZ LC/AO</p>
                        <p class="event-date">2026-01-22 13:18:09</p>
                        <span class="event-time">Hace 1 día</span>
                        <div class="event-bar"></div>
                    </article>

                    <article class="event">
                        <div class="event-head">
                            <span class="event-badge">{{ $codigo }}</span>
                            <span class="event-status status-ok">Completado</span>
                        </div>
                        <h3 class="event-title">Paquete incluido en la saca nacional</h3>
                        <p class="event-office">Oficina: BOLPBA - PLANTA CENTRAL</p>
                        <p class="event-date">2026-01-20 09:41:12</p>
                        <span class="event-time">Hace 3 días</span>
                        <div class="event-bar"></div>
                    </article>
                </div>
            </div>
        </section>
    </main>

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

        const timeline = document.getElementById('timelineList');
        const skeleton = document.getElementById('timelineSkeleton');
        if (timeline && skeleton) {
            timeline.classList.add('is-loading');
            skeleton.classList.add('is-visible');
            setTimeout(() => {
                timeline.classList.remove('is-loading');
                skeleton.classList.remove('is-visible');
            }, 850);
        }

        const events = document.querySelectorAll('.event');
        const eventObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-in');
                eventObserver.unobserve(entry.target);
            });
        }, { threshold: 0.2 });
        events.forEach((event, index) => {
            event.style.transitionDelay = `${Math.min(index * 90, 260)}ms`;
            eventObserver.observe(event);
        });
    </script>
</body>
</html>
