@extends('adminlte::page')
@section('title', 'Paquetes EMS')
@section('template_title')
    Paqueteria Postal
@endsection

@section('content')
@if (session('download_boleta_url'))
    <div class="alert alert-success m-3">
        <strong>Paquete guardado.</strong>
        La guia se esta generando.
        <a href="{{ session('download_boleta_url') }}" target="_blank" class="btn btn-sm btn-outline-success ml-2">
            Descargar guia
        </a>
    </div>
@endif
@livewire('paquetes-ems')
@endsection

@section('js')
@if (session('download_boleta_url'))
<script>
    window.addEventListener('load', function () {
        const boletaUrl = @json(session('download_boleta_url'));

        // Descarga automatica sin popup para evitar bloqueos del navegador.
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = boletaUrl;
        document.body.appendChild(iframe);
    });
</script>
@endif
@endsection
