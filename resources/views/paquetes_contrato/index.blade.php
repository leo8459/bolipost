@extends('adminlte::page')
@section('title', 'Paquetes Contrato')
@section('template_title')
    Paquetes Contrato
@endsection

@section('content')
@livewire('recojo')
    @php
        $reporteUrls = session('download_reporte_urls', session('download_reporte_url') ? [session('download_reporte_url')] : []);
    @endphp
    @if (count($reporteUrls))
        <div class="container-fluid mt-2">
            <div class="alert alert-info d-flex justify-content-between align-items-center">
                <span>{{ count($reporteUrls) > 1 ? 'Guias de ida y vuelta listas para descargar.' : 'Reporte listo para descargar.' }}</span>
                <div>
                    @foreach ($reporteUrls as $indice => $reporteUrl)
                        <a href="{{ $reporteUrl }}" target="_blank" class="btn btn-sm btn-primary ml-1">
                            {{ count($reporteUrls) > 1 ? ($indice === 0 ? 'Descargar guia de ida' : 'Descargar guia de vuelta') : 'Descargar reporte' }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const urls = @json(array_values($reporteUrls));
                urls.forEach(function (url, index) {
                    setTimeout(function () {
                        const frame = document.createElement('iframe');
                        frame.style.display = 'none';
                        frame.src = url;
                        document.body.appendChild(frame);

                        setTimeout(function () {
                            if (frame && frame.parentNode) frame.parentNode.removeChild(frame);
                        }, 60000);
                    }, index * 800);
                });
            });
        </script>
    @endif
@endsection
