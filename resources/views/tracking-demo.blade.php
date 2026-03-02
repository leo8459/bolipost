<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TrackingBo | Estatus de Envio</title>
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
        $ultimoNombre = $ultimoEvento->nombre_evento ?? ('Evento #' . ($ultimoEvento->evento_id ?? '-'));
        $eventoTextos = $eventos->map(fn($item) => mb_strtolower((string) ($item->nombre_evento ?? '')))->implode(' | ');
        $tieneIncidencia = str_contains($eventoTextos, 'fall') || str_contains($eventoTextos, 'incid') || str_contains($eventoTextos, 'devuelt');
        $fechaUltima = \Illuminate\Support\Carbon::parse($ultimoEvento->created_at);

        $etapa = 2;
        if (str_contains($eventoTextos, 'admit') || str_contains($eventoTextos, 'registr') || str_contains($eventoTextos, 'recibid')) $etapa = max($etapa, 1);
        if (str_contains($eventoTextos, 'transit') || str_contains($eventoTextos, 'saca') || str_contains($eventoTextos, 'despach')) $etapa = max($etapa, 2);
        if (str_contains($eventoTextos, 'oficina') || str_contains($eventoTextos, 'listo') || str_contains($eventoTextos, 'intento')) $etapa = max($etapa, 3);
        if (str_contains($eventoTextos, 'entreg') || str_contains($eventoTextos, 'complet') || str_contains($eventoTextos, 'recepcionado')) $etapa = max($etapa, 4);

        $estadoGlobal = $etapa === 4 ? 'Entregado' : 'En transito';
        if ($tieneIncidencia && $etapa < 4) $estadoGlobal = 'En transito con incidencia';
        $traceRows = $eventos->values()->chunk(3);

    @endphp

    @include('partials.landing-header')

    <main class="track-page">
        <section class="status-hero">
            <div class="container">
                <div class="hero-top">
                    <h1>Sigue el estatus de tu envio</h1>
                  
                </div>

                <div class="meta-strip">
                    <div><span>Numero de guia:</span> <strong>{{ str_pad((string) $ultimoEvento->id, 12, '0', STR_PAD_LEFT) }}</strong></div>
                    <div><span>Codigo de rastreo:</span> <strong>{{ $codigo }}</strong></div>
                    <div><span>Fecha ultima actualizacion:</span> <strong>{{ $fechaUltima->format('d/m/Y') }}</strong></div>
                </div>

                <p class="hero-help">La trazabilidad completa del envio se muestra abajo en forma de camino cronologico.</p>
                <p class="trace-read-help">Lectura simple: comienza en <strong>Inicio</strong> y sigue la linea punteada hasta <strong>Actual</strong>.</p>

                <article class="progress-panel">
                    <div class="trace-road" id="traceList">
                        @foreach ($traceRows as $rowIndex => $row)
                            @php
                                $isReverse = $rowIndex % 2 === 1;
                                $rowEventos = $isReverse ? $row->reverse()->values() : $row->values();
                            @endphp
                            <div class="trace-row {{ $isReverse ? 'is-reverse' : '' }}">
                                @foreach ($rowEventos as $evento)
                                    @php
                                        $isCurrent = $evento->id === $eventos->first()->id;
                                        $isStart = $evento->id === $eventos->last()->id;
                                        $nombre = $evento->nombre_evento ?? ('Evento #' . ($evento->evento_id ?? '-'));
                                    @endphp
                                    <article class="trace-node {{ $isCurrent ? 'is-current' : '' }}">
                                        <div class="trace-top">
                                            <span class="trace-code">{{ $codigo }}</span>
                                            @if ($isStart)
                                                <span class="trace-badge trace-badge-start">Inicio</span>
                                            @elseif ($isCurrent)
                                                <span class="trace-badge">Actual</span>
                                            @endif
                                        </div>
                                        <h3>{{ $nombre }}</h3>
                                        <div class="trace-detail">
                                            <span><strong>Fecha:</strong> {{ \Illuminate\Support\Carbon::parse($evento->created_at)->format('Y-m-d H:i:s') }}</span>
                                            <span><strong>Hace:</strong> {{ \Illuminate\Support\Carbon::parse($evento->created_at)->locale('es')->diffForHumans() }}</span>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                            @if (!$loop->last)
                                <div class="trace-drop {{ $isReverse ? 'left' : 'right' }}"></div>
                            @endif
                        @endforeach
                    </div>
                </article>
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

        const items = document.querySelectorAll('.trace-node');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-in');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.15 });

        items.forEach((el, i) => {
            el.style.transitionDelay = `${Math.min(i * 45, 280)}ms`;
            observer.observe(el);
        });
    </script>
</body>
</html>
