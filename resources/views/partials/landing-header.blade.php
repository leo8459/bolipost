@php
    $tickerLabel = trim((string) data_get($landingNewsTicker ?? [], 'label', ''));
    $tickerItems = collect(data_get($landingNewsTicker ?? [], 'items', []))
        ->filter(fn ($item) => trim((string) data_get($item, 'title', '')) !== '')
        ->values();
    $tickerAnimated = $tickerItems->count() > 1;
    $tickerRenderItems = $tickerAnimated ? $tickerItems->concat($tickerItems) : $tickerItems;
@endphp

<header class="topbar{{ $tickerItems->isNotEmpty() ? ' topbar--with-ticker' : '' }}" id="topbar">
    @if ($tickerItems->isNotEmpty())
        <div class="cb-news-ticker">
            <div class="cb-news-ticker__inner{{ ! $tickerAnimated ? ' cb-news-ticker__inner--static' : '' }}">
                @if ($tickerLabel !== '')
                    <span class="cb-news-ticker__badge">{{ $tickerLabel }}</span>
                @endif
                <div class="cb-news-ticker__viewport{{ ! $tickerAnimated ? ' cb-news-ticker__viewport--static' : '' }}" aria-live="polite">
                    <div class="cb-news-ticker__track">
                        @foreach ($tickerRenderItems as $item)
                            @php
                                $title = trim((string) data_get($item, 'title', ''));
                                $url = trim((string) data_get($item, 'url', ''));
                                $isInternal = $url !== '' && str_starts_with($url, '/');
                            @endphp
                            @if ($url !== '')
                                <a
                                    class="cb-news-ticker__item"
                                    href="{{ $url }}"
                                    @if (! $isInternal) target="_blank" rel="noopener noreferrer" @endif
                                >
                                    <span class="cb-news-ticker__item-text">{{ $title }}</span>
                                </a>
                            @else
                                <span class="cb-news-ticker__item">
                                    <span class="cb-news-ticker__item-text">{{ $title }}</span>
                                </span>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="container nav">
        <a class="brand" href="#inicio" aria-label="Ir al inicio">
            <img src="{{ asset('images/AGBClogo1.png') }}" alt="Correos de Bolivia">
            <span>TrackingBO</span>
        </a>

        <ul class="menu" id="menu">
            <li><a href="#inicio">Inicio</a></li>
            <li><a href="https://www.correos.gob.bo/quienes-somos" target="_blank" rel="noopener noreferrer">&iquest;Qui&eacute;nes somos?</a></li>
            <li><a href="https://www.correos.gob.bo/noticias" target="_blank" rel="noopener noreferrer">Noticias</a></li>
            <li><a href="https://institucional.correos.gob.bo:8007/" target="_blank" rel="noopener noreferrer">Institucional</a></li>
            <li class="menu-shipping-access-item">
                <a href="{{ route('preregistros.public.create') }}" class="menu-shipping-access" target="_blank" rel="noopener noreferrer">
                    <span class="menu-shipping-access-label">Generar preenv&iacute;o EMS</span>
                </a>
            </li>
            <li class="menu-client-access-item">
                <a href="{{ route('clientes.login', absolute: false) }}" class="menu-client-access">
                    <span class="menu-client-access-label">Ingreso clientes</span>
                </a>
            </li>
        </ul>

        <div class="nav-actions">
            @if (auth('cliente')->check())
                <a class="btn btn-public-panel" href="{{ route('clientes.dashboard') }}">
                    Mi panel
                </a>
                <form method="POST" action="{{ route('clientes.logout') }}" class="nav-inline-form">
                    @csrf
                    <button type="submit" class="btn btn-public-login">
                        Cerrar sesión
                    </button>
                </form>
            @else
                <a class="btn btn-client-login-cta" href="{{ route('clientes.login', absolute: false) }}">
                    <span class="btn-client-login-cta__eyebrow">Clientes</span>
                    <span class="btn-client-login-cta__label">Iniciar sesi&oacute;n</span>
                </a>
            @endif
            <a class="btn btn-home-shipping" href="{{ route('preregistros.public.create') }}" target="_blank" rel="noopener noreferrer">
                <span class="btn-home-shipping__eyebrow">Preenv&iacute;o</span>
                <span class="btn-home-shipping__label">Generar envío</span>
            </a>
            <button class="menu-toggle" id="menuToggle" type="button" aria-label="Abrir menu" aria-expanded="false">
                <span class="menu-toggle-bars" aria-hidden="true">
                    <i></i>
                    <i></i>
                    <i></i>
                </span>
            </button>
        </div>
    </div>
</header>
