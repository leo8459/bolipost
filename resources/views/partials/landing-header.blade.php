@php
    $landingPrefix = request()->is('/') ? '' : url('/');
@endphp

<header class="topbar" id="topbar">
    <div class="container nav">
        <a class="brand" href="{{ $landingPrefix }}#inicio">
<<<<<<< HEAD
            <img src="{{ asset('images/AGBClogo2.png') }}" alt="Correos de Bolivia">
            <span>TrackingBO</span>
=======
            <img src="{{ asset('images/AGBClogo2.png') }}" alt="Agencia Boliviana de Correos">
            <span>TrackingBo</span>
>>>>>>> a41ccfb (Uchazara)
        </a>

        <ul class="menu" id="menu">
            <li><a href="https://correos.gob.bo/about/" target="_blank" rel="noopener noreferrer">Qui&eacute;nes Somos</a></li>
            <li><a href="https://correos.gob.bo/services/" target="_blank" rel="noopener noreferrer">Nuestros Servicios</a></li>
            <li><a href="https://correos.gob.bo/contact-us/" target="_blank" rel="noopener noreferrer">Cont&aacute;ctanos</a></li>
        </ul>

        <div class="nav-actions">
<<<<<<< HEAD
            <a class="btn btn-home-shipping" href="{{ route('preregistros.public.create') }}">
                Hacer envio desde casa
            </a>
=======
>>>>>>> a41ccfb (Uchazara)
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
