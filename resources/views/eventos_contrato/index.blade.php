@extends('adminlte::page')
@section('title', 'Eventos CONTRATO')
@section('template_title')
    Eventos CONTRATO
@endsection

@section('content')
    <div id="eventos-contrato-listado">
        @livewire('eventos-tabla', ['tipo' => 'contrato'])
    </div>

    <dialog id="contrato-buscando" class="contrato-dialog contrato-buscando" aria-labelledby="contrato-buscando-titulo" aria-describedby="contrato-buscando-detalle">
        <div class="contrato-buscando-ilustracion" aria-hidden="true">
            <span class="contrato-buscando-sombra"></span>
            @php($carteroUrl = asset('images/eventos/cartero-buscando.webp') . '?v=' . filemtime(public_path('images/eventos/cartero-buscando.webp')))
            <img class="contrato-buscando-cartero" src="{{ $carteroUrl }}" width="208" height="208" alt="" draggable="false">
            <img class="contrato-buscando-paquete" src="{{ $carteroUrl }}" width="208" height="208" alt="" draggable="false">
            <span class="contrato-buscando-destello"></span>
        </div>
        <div class="contrato-buscando-contenido">
            <h2 id="contrato-buscando-titulo" tabindex="-1" autofocus>Buscando tu paquete</h2>
            <p id="contrato-buscando-detalle">Consultando los eventos del envío…</p>
            <div class="contrato-buscando-progreso" aria-hidden="true"></div>
        </div>
    </dialog>

    <dialog id="contrato-foto" class="contrato-dialog contrato-foto" aria-labelledby="contrato-foto-titulo">
        <header class="contrato-foto-cabecera">
            <h2 id="contrato-foto-titulo">Foto de entrega</h2>
            <form method="dialog">
                <button type="submit" aria-label="Cerrar imagen" title="Cerrar imagen" autofocus>
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </form>
        </header>
        <div class="contrato-foto-visor" tabindex="0" role="region" aria-label="Imagen ampliable" data-foto-visor>
            <div class="contrato-foto-lienzo">
                <p role="status" data-foto-mensaje></p>
                <img alt="Comprobante de entrega" draggable="false" hidden>
            </div>
        </div>
        <footer class="contrato-foto-controles">
            <div class="contrato-foto-zoom" role="group" aria-label="Controles de zoom">
                <button type="button" aria-label="Alejar imagen" title="Alejar imagen" data-foto-zoom="out" disabled>
                    <i class="fas fa-minus" aria-hidden="true"></i>
                </button>
                <button type="button" aria-label="Ajustar imagen al visor" title="Ajustar imagen al visor" data-foto-zoom="reset" disabled>
                    <span data-foto-nivel>100%</span>
                </button>
                <button type="button" aria-label="Ampliar imagen" title="Ampliar imagen" data-foto-zoom="in" disabled>
                    <i class="fas fa-plus" aria-hidden="true"></i>
                </button>
            </div>
            <span class="contrato-foto-ayuda">Usa la rueda para ampliar y arrastra para recorrer la foto.</span>
        </footer>
    </dialog>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('css/eventos-contrato.css') }}?v={{ filemtime(public_path('css/eventos-contrato.css')) }}">
@endpush

@push('js')
    <script src="{{ asset('js/eventos-contrato.js') }}?v={{ filemtime(public_path('js/eventos-contrato.js')) }}" defer></script>
@endpush
