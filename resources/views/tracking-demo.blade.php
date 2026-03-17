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
    <link rel="stylesheet" href="{{ asset('css/preregistro-modal.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tracking-demo.css') }}">
</head>
<body>
    @php
        $ultimoNombre = $ultimoEvento->nombre_evento ?? ('Evento #' . ($ultimoEvento->evento_id ?? '-'));
        $eventoTextos = $eventos->map(fn($item) => mb_strtolower((string) ($item->nombre_evento ?? '')))->implode(' | ');
        $tieneIncidencia = str_contains($eventoTextos, 'fall') || str_contains($eventoTextos, 'incid') || str_contains($eventoTextos, 'devuelt');
        $fechaUltima = \Illuminate\Support\Carbon::parse($ultimoEvento->created_at);
        $servicioActual = strtoupper((string) ($ultimoEvento->servicio ?? 'EMS'));
        $origenLabel = 'Correos de Bolivia';
        $origenIso2 = null;

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

        $entregaConfirmada = $eventos->contains(function ($item) {
            $texto = mb_strtolower((string) ($item->nombre_evento ?? ''));
            if ($texto === '') {
                return false;
            }

            $exclusiones = [
                'listo para entregar',
                'oficina de entrega',
                'intento fallido',
                'no entregado',
                'pendiente de entrega',
            ];

            foreach ($exclusiones as $palabra) {
                if (str_contains($texto, $palabra)) {
                    return false;
                }
            }

            $confirmaciones = [
                'entregado exitosamente',
                'entregado al cliente',
                'entregado al destinatario',
                'entrega realizada',
                'envio entregado',
                'paquete entregado',
                'recepcionado por destinatario',
                'recibido por destinatario',
            ];

            foreach ($confirmaciones as $palabra) {
                if (str_contains($texto, $palabra)) {
                    return true;
                }
            }

            return false;
        });

        if ($entregaConfirmada) $pasoActual = max($pasoActual, $idxEntregado);

        $estadoGlobal = $pasoActual === $idxEntregado ? 'Entregado' : 'En transito';
        if ($tieneIncidencia && $pasoActual < $idxEntregado) $estadoGlobal = 'En transito con incidencia';

        $normalizarIso2 = function (?string $valor): ?string {
            $iso = strtoupper(trim((string) $valor));
            return preg_match('/^[A-Z]{2}$/', $iso) === 1 ? $iso : null;
        };
        $iso2DesdeCodigoS10 = function (?string $valor) use ($normalizarIso2): ?string {
            $codigo = strtoupper(trim((string) $valor));
            if (preg_match('/^[A-Z]{2}\d{9}[A-Z]{2}$/', $codigo) !== 1) {
                return null;
            }

            return $normalizarIso2(substr($codigo, -2));
        };
        $normalizarNombrePais = function (?string $valor): string {
            $texto = mb_strtoupper(trim((string) $valor));
            if ($texto === '') {
                return '';
            }

            if (class_exists(\Normalizer::class)) {
                $texto = \Normalizer::normalize($texto, \Normalizer::FORM_D) ?: $texto;
                $texto = preg_replace('/\p{Mn}+/u', '', $texto) ?: $texto;
            }

            return preg_replace('/\s+/', ' ', $texto) ?: $texto;
        };
        $iso2DesdeNombrePais = function (?string $valor) use ($normalizarNombrePais, $normalizarIso2): ?string {
            $pais = $normalizarNombrePais($valor);
            if ($pais === '' || !class_exists(\ResourceBundle::class)) {
                return null;
            }

            foreach (['en', 'es', 'fr', 'pt', 'de', 'it'] as $locale) {
                $bundle = \ResourceBundle::create($locale, 'ICUDATA-region');
                if (!$bundle) {
                    continue;
                }

                $countries = $bundle->get('Countries');
                if (!$countries) {
                    continue;
                }

                foreach ($countries as $iso => $label) {
                    $isoCode = $normalizarIso2((string) $iso);
                    if ($isoCode === null) {
                        continue;
                    }

                    if ($normalizarNombrePais((string) $label) === $pais) {
                        return $isoCode;
                    }
                }
            }

            return null;
        };
        $extraerPaisDesdeOffice = function (?string $valor): string {
            $texto = trim((string) $valor);
            if ($texto === '') {
                return '';
            }

            if (preg_match('/(?:pa[ií]s\s+origen|country\s*origin)\s*:\s*(.+)$/iu', $texto, $m) === 1) {
                return trim((string) $m[1]);
            }

            return '';
        };

        $iso2DesdeOficina = function (?string $texto) use ($normalizarIso2): ?string {
            $valor = strtoupper(trim((string) $texto));
            if ($valor === '') {
                return null;
            }

            if (preg_match('/\b([A-Z]{2})[A-Z0-9]{3,}\b/', $valor, $m) === 1) {
                return $normalizarIso2($m[1]);
            }

            return null;
        };
        $eventoOrigen = $eventos->first(function ($item) use ($extraerPaisDesdeOffice) {
            return $extraerPaisDesdeOffice($item->office ?? '') !== '';
        });
        $ciudadOrigenLocal = trim((string) ($eventos->firstWhere('ciudad_origen')?->ciudad_origen ?? ''));
        $ciudadDestinoLocal = trim((string) ($eventos->firstWhere('ciudad_destino')?->ciudad_destino ?? ''));
        if ($ciudadOrigenLocal !== '') {
            $origenLabel = ucwords(mb_strtolower($ciudadOrigenLocal));
            $origenIso2 = 'BO';
        } elseif ($eventoOrigen) {
            $origenLabel = $extraerPaisDesdeOffice($eventoOrigen->office ?? '');
            $origenIso2 = $iso2DesdeCodigoS10($eventoOrigen->codigo ?? $codigo)
                ?? $iso2DesdeNombrePais($origenLabel);
        } else {
            $origenIso2 = $iso2DesdeCodigoS10($codigo);
            if ($origenIso2 !== null) {
                $origenLabel = $origenIso2;
            }
        }

        $destinoIso2 = $eventos->reduce(function ($carry, $item) use ($iso2DesdeOficina) {
            if ($carry !== null) {
                return $carry;
            }

            return $iso2DesdeOficina($item->office ?? '') ?? $iso2DesdeOficina($item->next_office ?? '');
        }, null);
        $codigoIso2 = $iso2DesdeCodigoS10($codigo);
        $esDestinoNacional = $ciudadDestinoLocal !== '' || $destinoIso2 === 'BO' || $codigoIso2 === 'BO';
        $destinoLabel = $ciudadDestinoLocal !== ''
            ? ucwords(mb_strtolower($ciudadDestinoLocal))
            : ($esDestinoNacional ? 'Nacional' : ($destinoIso2 ?? 'Internacional'));
        $destinoBanderaIso2 = $esDestinoNacional ? 'BO' : $destinoIso2;
        $historial = $eventos->groupBy(fn($item) => \Illuminate\Support\Carbon::parse($item->created_at)->format('Y-m-d'));

        $detectarDepartamentoBolivia = function (?string $texto): ?string {
            $valor = mb_strtoupper(trim((string) $texto));
            if ($valor === '') {
                return null;
            }

            $mapa = [
                'LA PAZ' => 'La Paz',
                'ORURO' => 'Oruro',
                'POTOSI' => 'Potosi',
                'COCHABAMBA' => 'Cochabamba',
                'SANTA CRUZ' => 'Santa Cruz',
                'CHUQUISACA' => 'Chuquisaca',
                'SUCRE' => 'Chuquisaca',
                'TARIJA' => 'Tarija',
                'TRINIDAD' => 'Beni',
                'COBIJA' => 'Pando',
            ];

            foreach ($mapa as $clave => $nombre) {
                if (str_contains($valor, $clave)) {
                    return $nombre;
                }
            }

            return null;
        };

        $contactosRegional = [
            'La Paz' => [
                'regional' => 'Oficina Central: La Paz',
                'direccion' => 'Avenida Mariscal Santa Cruz Esquina Calle Oruro Edificio Telecomunicaciones',
                'coords' => '-16.4980703,-68.1355719',
            ],
            'Cochabamba' => [
                'regional' => 'Regional: Cochabamba',
                'direccion' => 'Calle Ayacucho esquina Av. Heroinas N° 113',
                'coords' => "17°23'34.1\"S 66°09'31.0\"W",
            ],
            'Santa Cruz' => [
                'regional' => 'Regional: Santa Cruz',
                'direccion' => 'Calle Cobija Entre Sucre y Ballivian N° 24',
                'coords' => "17°47'00.6\"S 63°10'28.8\"W",
            ],
            'Oruro' => [
                'regional' => 'Regional: Oruro',
                'direccion' => 'Calle Presidente Montes Esquina Junin N° 1456',
                'coords' => "17°58'07.3\"S 67°06'53.6\"W",
            ],
            'Potosi' => [
                'regional' => 'Regional: Potosi',
                'direccion' => 'Calle Hoyos Esquina Topater, Villa Imperial de Potosi',
                'coords' => "19°35'19.3\"S 65°44'56.2\"W",
            ],
            'Tarija' => [
                'regional' => 'Regional: Tarija',
                'direccion' => 'Calle Mariscal Sucre esquina Virginio Lema N° 397',
                'coords' => "21°32'10.0\"S 64°44'04.5\"W",
            ],
            'Chuquisaca' => [
                'regional' => 'Regional: Sucre',
                'direccion' => 'Calle Junin Esquina Ayacucho N° 699',
                'coords' => "19°02'49.8\"S 65°15'41.0\"W",
            ],
            'Beni' => [
                'regional' => 'Regional: Beni',
                'direccion' => 'Calle Cipriano Barace N°10 Entre Manuel Limpias y Calle Sucre',
                'coords' => "14°50'04.0\"S 64°54'11.8\"W",
            ],
            'Pando' => [
                'regional' => 'Regional: Pando',
                'direccion' => 'Av. Bruno Recua N.- 59',
                'coords' => "11°01'03.8\"S 68°45'15.9\"W",
            ],
        ];

        $eventoListoParaEntregar = $eventos->first(function ($item) {
            $texto = mb_strtolower((string) ($item->nombre_evento ?? ''));
            return str_contains($texto, 'listo para entregar')
                || str_contains($texto, 'oficina de entrega');
        });

        $mostrarAvisoRecojo = false;
        $mensajeAvisoRecojo = '';
        $detalleRegionalRecojo = null;
        $mapUrlRegionalRecojo = null;
        if ($entregaConfirmada) {
            $mensajeAvisoRecojo = 'Tu paquete ya fue entregado. No tienes acciones pendientes en oficina.';
            $mostrarAvisoRecojo = true;
        } elseif ($eventoListoParaEntregar) {
            $oficinaAviso = trim((string) ($eventoListoParaEntregar->office ?? ''));
            $isoOficinaAviso = $iso2DesdeOficina($oficinaAviso);
            $esBolivia = ($isoOficinaAviso === 'BO') || str_ends_with(strtoupper($codigo), 'BO');

            if ($esBolivia) {
                $departamento = $detectarDepartamentoBolivia($oficinaAviso);
                $mensajeAvisoRecojo = $departamento
                    ? 'Tu paquete esta listo para entregar. Debes pasar a recoger en el departamento de ' . $departamento . '.'
                    : 'Tu paquete esta listo para entregar. Debes pasar a recoger en tu oficina de destino en Bolivia.';
                if ($departamento && isset($contactosRegional[$departamento])) {
                    $detalleRegionalRecojo = $contactosRegional[$departamento];
                } else {
                    $detalleRegionalRecojo = $contactosRegional['La Paz'];
                }
                if ($detalleRegionalRecojo) {
                    $mapQuery = trim((string) ($detalleRegionalRecojo['coords'] ?? ''));
                    if ($mapQuery === '') {
                        $mapQuery = trim($detalleRegionalRecojo['direccion'] . ' ' . $detalleRegionalRecojo['regional'] . ' Bolivia');
                    }
                    $mapUrlRegionalRecojo = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($mapQuery);
                }
                $mostrarAvisoRecojo = true;
            }
        }
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
                            <strong><span data-country-name @if($origenIso2) data-country-iso="{{ strtolower($origenIso2) }}" @endif>{{ $origenLabel }}</span> @if($origenIso2)<img class="country-flag" src="https://flagcdn.com/16x12/{{ strtolower($origenIso2) }}.png" alt="Bandera origen">@endif</strong>
                        </div>
                        <div class="meta-item meta-accent-b">
                            <small>Destino</small>
                            <strong><span @if(!$esDestinoNacional && $destinoBanderaIso2) data-country-name data-country-iso="{{ strtolower($destinoBanderaIso2) }}" @endif>{{ $destinoLabel }}</span> @if($destinoBanderaIso2)<img class="country-flag" src="https://flagcdn.com/16x12/{{ strtolower($destinoBanderaIso2) }}.png" alt="Bandera destino">@endif</strong>
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

                @if ($mostrarAvisoRecojo)
                    <article class="card pickup-notice reveal-block" style="--reveal-delay: 170ms;">
                        <p>{{ $mensajeAvisoRecojo }}</p>
                        @if ($detalleRegionalRecojo)
                            <div class="pickup-contact-grid">
                                <div class="pickup-contact-row">
                                    <span class="pickup-contact-label">Oficina</span>
                                    <strong>{{ $detalleRegionalRecojo['regional'] }}</strong>
                                </div>
                                <div class="pickup-contact-row">
                                    <span class="pickup-contact-label">Direccion</span>
                                    <strong>
                                        @if ($mapUrlRegionalRecojo)
                                            <a class="pickup-map-link" href="{{ $mapUrlRegionalRecojo }}" target="_blank" rel="noopener noreferrer">{{ $detalleRegionalRecojo['direccion'] }}</a>
                                        @else
                                            {{ $detalleRegionalRecojo['direccion'] }}
                                        @endif
                                    </strong>
                                </div>
                            </div>
                        @endif
                    </article>
                @endif

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
                                                    $codigoEvento = trim((string) ($evento->codigo ?? $codigo));
                                                    $paisDesdeOffice = $extraerPaisDesdeOffice($office);
                                                    $isoOffice = $paisDesdeOffice !== ''
                                                        ? ($iso2DesdeCodigoS10($codigoEvento) ?? $iso2DesdeNombrePais($paisDesdeOffice))
                                                        : $iso2DesdeOficina($office);
                                                    $isoNextOffice = $iso2DesdeOficina($nextOffice);
                                                @endphp
                                                @if ($office !== '')
                                                    <div class="history-meta-row">
                                                        <span class="history-meta-label">Oficina</span>
                                                        <span class="history-meta-value">{{ $office }} @if($isoOffice)<img class="country-flag" src="https://flagcdn.com/16x12/{{ strtolower($isoOffice) }}.png" alt="Bandera oficina">@endif</span>
                                                    </div>
                                                @endif
                                                @if ($nextOffice !== '')
                                                    <div class="history-meta-row">
                                                        <span class="history-meta-label">Siguiente Oficina</span>
                                                        <span class="history-meta-value">{{ $nextOffice }} @if($isoNextOffice)<img class="country-flag" src="https://flagcdn.com/16x12/{{ strtolower($isoNextOffice) }}.png" alt="Bandera siguiente oficina">@endif</span>
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

    @include('partials.preregistro-modal')
    @include('partials.landing-footer')

    <script>
        const topbar = document.getElementById('topbar');
        const menuToggle = document.getElementById('menuToggle');
        const menu = document.getElementById('menu');
        const preregistroModal = document.getElementById('preregistroModal');
        const preregistroClose = document.getElementById('preregistroClose');
        const preregistroTriggers = document.querySelectorAll('[data-open-preregistro], .btn-home-shipping');
        const preregistroSuccessModal = document.getElementById('preregistroSuccessModal');
        const closePreregistroSuccess = document.getElementById('closePreregistroSuccess');
        const copyPreregistroCode = document.getElementById('copyPreregistroCode');
        const preregistroSuccessCode = document.getElementById('preregistroSuccessCode');
        const preregistroTicketUrl = @json(session('preregistro_ticket_url'));


        menuToggle?.addEventListener('click', () => { const isOpen = menu.classList.toggle('open'); menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false'); });
        menu.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => { menu.classList.remove('open'); menuToggle?.setAttribute('aria-expanded', 'false'); }));

        const onScroll = () => topbar.classList.toggle('scrolled', window.scrollY > 8);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

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

        @if ($errors->any())
            openPreregistroModal();
        @endif

        @if (session('preregistro_codigo'))
            openPreregistroSuccessModal();
            if (preregistroTicketUrl) {
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = preregistroTicketUrl;
                document.body.appendChild(iframe);
                setTimeout(() => iframe.remove(), 5000);
            }
        @endif

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

        if (typeof Intl !== 'undefined' && typeof Intl.DisplayNames !== 'undefined') {
            const regionNames = new Intl.DisplayNames(['es', 'en'], { type: 'region' });
            document.querySelectorAll('[data-country-name][data-country-iso]').forEach((el) => {
                const iso = (el.getAttribute('data-country-iso') || '').toUpperCase();
                if (!/^[A-Z]{2}$/.test(iso)) return;
                const label = regionNames.of(iso);
                if (label) el.textContent = label;
            });
        }
    </script>
</body>
</html>


