@php
    $landingPrefix = request()->is('/') ? '' : url('/');
@endphp

<header class="topbar" id="topbar">
    <div class="container nav">
        <a class="brand" href="{{ $landingPrefix }}#inicio">
            <img src="{{ asset('images/AGBClogo1.png') }}" alt="Correos de Bolivia">
            <span>TrackingBO</span>
        </a>

        <ul class="menu" id="menu">
            <li><a href="https://correos.gob.bo/about/" target="_blank" rel="noopener noreferrer">Qui&eacute;nes Somos</a></li>
            <li><a href="https://correos.gob.bo/services/" target="_blank" rel="noopener noreferrer">Nuestros Servicios</a></li>
            <li><a href="https://correos.gob.bo/contact-us/" target="_blank" rel="noopener noreferrer">Cont&aacute;ctanos</a></li>
            <li>
                <a href="{{ route('clientes.login') }}" class="menu-client-access">
                    <span class="menu-client-access-label">Ingresar</span>
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
                        Cerrar sesion
                    </button>
                </form>
            @else
                <!-- <a class="btn btn-public-login" href="{{ route('clientes.login') }}">
                    Login publico
                </a>
                <a class="btn btn-public-register" href="{{ route('clientes.register') }}">
                    Registro publico
                </a> -->
            @endif
            <!-- <a class="btn btn-home-shipping" href="{{ route('preregistros.public.create') }}">
                Hacer envio desde casa
            </a> -->
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
