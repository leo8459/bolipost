@php
    $landingPrefix = request()->is('/') ? '' : url('/');
@endphp

<header class="topbar" id="topbar">
    <div class="container nav">
        <a class="brand" href="{{ $landingPrefix }}#inicio">
            <img src="{{ asset('images/AGBClogo.png') }}" alt="TrackingBo">
            <span>TrackingBo</span>
        </a>

        <ul class="menu" id="menu">
            <li><a href="{{ url('/quienes-somos') }}">Quiénes Somos</a></li>
            <li><a href="{{ url('/nuestros-servicios') }}">Nuestros Servicios</a></li>
            <li><a href="{{ url('/contactanos') }}">Contáctanos</a></li>
        </ul>

       
    </div>
</header>
