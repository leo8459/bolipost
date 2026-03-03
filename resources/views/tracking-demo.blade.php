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
        $servicioActual = strtoupper((string) ($ultimoEvento->servicio ?? 'EMS'));

        $primerPaso = in_array($servicioActual, ['ORDI', 'CERTI'], true) ? 'Clasificacion' : 'Admision';
        $incluyeCartero = str_contains($eventoTextos, 'cartero')
            || str_contains($eventoTextos, 'distrib')
            || str_contains($eventoTextos, 'domicilio')
            || str_contains($eventoTextos, 'intento');

        $pasos = [$primerPaso, 'Despacho', 'Expedicion', 'Ventanilla'];
        if ($incluyeCartero) {
            $pasos[] = 'Cartero';
        }
        $pasos[] = 'Entregado';

        $idxEntregado = $incluyeCartero ? 5 : 4;
        $pasoActual = 0;

        if ($primerPaso === 'Clasificacion') {
            if (str_contains($eventoTextos, 'clasific') || str_contains($eventoTextos, 'recibid') || str_contains($eventoTextos, 'registr')) $pasoActual = max($pasoActual, 0);
        } else {
            if (str_contains($eventoTextos, 'admi') || str_contains($eventoTextos, 'recibid') || str_contains($eventoTextos, 'registr')) $pasoActual = max($pasoActual, 0);
        }
        if (str_contains($eventoTextos, 'despach')) $pasoActual = max($pasoActual, 1);
        if (str_contains($eventoTextos, 'exped') || str_contains($eventoTextos, 'transit') || str_contains($eventoTextos, 'saca')) $pasoActual = max($pasoActual, 2);
        if (str_contains($eventoTextos, 'ventanilla') || str_contains($eventoTextos, 'oficina') || str_contains($eventoTextos, 'listo')) $pasoActual = max($pasoActual, 3);
        if ($incluyeCartero && (str_contains($eventoTextos, 'cartero') || str_contains($eventoTextos, 'distrib') || str_contains($eventoTextos, 'domicilio') || str_contains($eventoTextos, 'intento'))) {
            $pasoActual = max($pasoActual, 4);
        }
        if (str_contains($eventoTextos, 'entreg') || str_contains($eventoTextos, 'complet') || str_contains($eventoTextos, 'recepcionado')) $pasoActual = max($pasoActual, $idxEntregado);

        $estadoGlobal = $pasoActual === $idxEntregado ? 'Entregado' : 'En transito';
        if ($tieneIncidencia && $pasoActual < $idxEntregado) $estadoGlobal = 'En transito con incidencia';

        $destinoLabel = str_ends_with(strtoupper($codigo), 'BO') ? 'Bolivia' : 'Internacional';
        $historial = $eventos->groupBy(fn($item) => \Illuminate\Support\Carbon::parse($item->created_at)->format('Y-m-d'));
        $paises = [
            'BOLIVIA' => [
                'flag_code' => 'bo',
                'tokens' => ['BOLIVIA', 'BOL', 'BO', 'LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'SUCRE', 'TARIJA', 'BENI', 'PANDO'],
            ],
            'ARGENTINA' => [
                'flag_code' => 'ar',
                'tokens' => ['ARGENTINA', 'ARG', 'AR', 'BUENOS AIRES', 'CORDOBA', 'MENDOZA', 'ROSARIO'],
            ],
            'BRASIL' => [
                'flag_code' => 'br',
                'tokens' => ['BRASIL', 'BRAZIL', 'BRA', 'BR', 'SAO PAULO', 'RIO DE JANEIRO', 'CURITIBA'],
            ],
            'CHILE' => [
                'flag_code' => 'cl',
                'tokens' => ['CHILE', 'CHL', 'CL', 'SANTIAGO', 'VALPARAISO', 'ANTOFAGASTA'],
            ],
            'PERU' => [
                'flag_code' => 'pe',
                'tokens' => ['PERU', 'PER', 'PE', 'LIMA', 'AREQUIPA', 'CUSCO'],
            ],
            'PARAGUAY' => [
                'flag_code' => 'py',
                'tokens' => ['PARAGUAY', 'PRY', 'PY', 'ASUNCION'],
            ],
            'URUGUAY' => [
                'flag_code' => 'uy',
                'tokens' => ['URUGUAY', 'URY', 'UY', 'MONTEVIDEO'],
            ],
        ];

        $detectarPais = function (?string $texto) use ($paises): ?string {
            $valor = mb_strtoupper(trim((string) $texto));
            if ($valor === '') {
                return null;
            }

            foreach ($paises as $pais => $cfg) {
                foreach ($cfg['tokens'] as $token) {
                    if (str_contains($valor, mb_strtoupper($token))) {
                        return $pais;
                    }
                }
            }

            if (preg_match('/^BO[A-Z0-9]+/', $valor)) {
                return 'BOLIVIA';
            }
            if (preg_match('/^AR[A-Z0-9]+/', $valor)) {
                return 'ARGENTINA';
            }
            if (preg_match('/^BR[A-Z0-9]+/', $valor)) {
                return 'BRASIL';
            }
            if (preg_match('/^CL[A-Z0-9]+/', $valor)) {
                return 'CHILE';
            }
            if (preg_match('/^PE[A-Z0-9]+/', $valor)) {
                return 'PERU';
            }

            return null;
        };

        $banderaDePais = function (?string $pais) use ($paises): string {
            if (!$pais) {
                return '';
            }
            return (string) ($paises[$pais]['flag_code'] ?? '');
        };
    @endphp

    @include('partials.landing-header')

    <main class="track-page">
        <section class="tracking-shell">
            <div class="container tracking-layout">
                <article class="status-card reveal-block" style="--reveal-delay: 30ms;">
                    <div class="status-banner">
                        <div class="status-orb status-orb-a" aria-hidden="true"></div>
                        <div class="status-orb status-orb-b" aria-hidden="true"></div>
                        <div class="status-icon">{!! $pasoActual === $idxEntregado ? '&#10003;' : '&#8226;' !!}</div>
                        <div>
                            <h1>{{ $estadoGlobal }}</h1>
                            <p>{{ $ultimoNombre }}</p>
                            <span>Tracking: {{ $codigo }}</span>
                        </div>
                    </div>

                    <div class="status-meta">
                        <div class="meta-item meta-accent-a">
                            <small>Origen</small>
                            <strong>Agencia Boliviana de Correos</strong>
                        </div>
                        <div class="meta-item meta-accent-b">
                            <small>Destino</small>
                            <strong>{{ $destinoLabel }}</strong>
                        </div>
                        <div class="meta-item meta-accent-c">
                            <small>Servicio</small>
                            <strong>{{ $servicioActual }}</strong>
                        </div>
                        <div class="meta-item meta-accent-d">
                            <small>Ultima actualizacion</small>
                            <strong>{{ $fechaUltima->format('d/m/Y H:i') }}</strong>
                        </div>
                    </div>
                </article>

                <article class="card progress-card reveal-block" style="--reveal-delay: 110ms;">
                    <div class="card-head">
                        <h2>Progreso del envio</h2>
                        <span>Paso actual: <strong>{{ $pasos[$pasoActual] }}</strong></span>
                    </div>
                    <ol class="progress-track" style="--steps-count: {{ count($pasos) }};">
                        @foreach ($pasos as $index => $paso)
                            @php
                                $done = $index < $pasoActual;
                                $current = $index === $pasoActual;
                            @endphp
                            <li class="{{ $done ? 'is-done' : '' }} {{ $current ? 'is-current' : '' }}" style="--step-index: {{ $index }};">
                                <div class="step-dot">{!! $done ? '&#10003;' : ($current ? '&#9679;' : '&#9675;') !!}</div>
                                <span>{{ $paso }}</span>
                            </li>
                        @endforeach
                    </ol>
                </article>

                <article class="card history-card reveal-block" style="--reveal-delay: 230ms;">
                    <div class="card-head">
                        <h2>Historial de seguimiento</h2>
                    </div>

                    @foreach ($historial as $fecha => $items)
                        <section class="history-group">
                            <div class="history-group-head">
                                <h3>{{ \Illuminate\Support\Carbon::parse($fecha)->locale('es')->translatedFormat('j M Y') }}</h3>
                                <span>{{ $items->count() }} evento(s)</span>
                            </div>

                            <div class="history-feed">
                                @foreach ($items as $evento)
                                    @php
                                        $esUltimo = $loop->first && $loop->parent->first;
                                    @endphp
                                    <article class="history-event {{ $esUltimo ? 'is-latest' : '' }} reveal-item" style="--item-index: {{ $loop->iteration }};">
                                        <div class="history-event-side">
                                            <time>{{ \Illuminate\Support\Carbon::parse($evento->created_at)->format('H:i') }}</time>
                                        </div>

                                        <div class="history-event-body">
                                            <div class="history-event-head">
                                                <h4>{{ $evento->nombre_evento ?? ('Evento #' . ($evento->evento_id ?? '-')) }}</h4>
                                            </div>
                                            <div class="history-event-meta">
                                                @php
                                                    $office = trim((string) ($evento->office ?? ''));
                                                    $nextOffice = trim((string) ($evento->next_office ?? ''));
                                                    $paisOrigen = trim((string) ($evento->pais_origen ?? 'BOLIVIA'));
                                                    $paisOrigenDetectado = $detectarPais($paisOrigen) ?? 'BOLIVIA';
                                                    $banderaPaisOrigen = $banderaDePais($paisOrigenDetectado);
                                                    $banderaOffice = $banderaDePais($detectarPais($office));
                                                    $banderaNextOffice = $banderaDePais($detectarPais($nextOffice));
                                                @endphp
                                                @if ($office === '')
                                                    <div class="history-meta-row">
                                                        <span class="history-meta-label">Pais Origen</span>
                                                        <span class="history-meta-value">{{ $paisOrigen }} @if($banderaPaisOrigen !== '')<img class="country-flag" src="https://flagcdn.com/16x12/{{ $banderaPaisOrigen }}.png" alt="Bandera {{ $paisOrigenDetectado }}">@endif</span>
                                                    </div>
                                                @else
                                                    <div class="history-meta-row">
                                                        <span class="history-meta-label">Oficina</span>
                                                        <span class="history-meta-value">{{ $office }} @if($banderaOffice !== '')<img class="country-flag" src="https://flagcdn.com/16x12/{{ $banderaOffice }}.png" alt="Bandera oficina">@endif</span>
                                                    </div>
                                                @endif
                                                @if ($nextOffice !== '')
                                                    <div class="history-meta-row">
                                                        <span class="history-meta-label">Siguiente Oficina</span>
                                                        <span class="history-meta-value">{{ $nextOffice }} @if($banderaNextOffice !== '')<img class="country-flag" src="https://flagcdn.com/16x12/{{ $banderaNextOffice }}.png" alt="Bandera siguiente oficina">@endif</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
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

        const revealBlocks = document.querySelectorAll('.reveal-block');
        const revealItems = document.querySelectorAll('.reveal-item');
        const progressItems = document.querySelectorAll('.progress-track li');

        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                io.unobserve(entry.target);
            });
        }, { threshold: 0.12 });

        revealBlocks.forEach((el) => io.observe(el));
        revealItems.forEach((el) => io.observe(el));

        const animateProgress = () => {
            progressItems.forEach((li, idx) => {
                window.setTimeout(() => li.classList.add('is-animated'), idx * 90);
            });
        };
        window.setTimeout(animateProgress, 220);
    </script>
</body>
</html>
