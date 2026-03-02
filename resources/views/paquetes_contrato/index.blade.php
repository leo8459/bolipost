@extends('adminlte::page')
@section('title', 'Paquetes Contrato')
@section('template_title')
    Paquetes Contrato
@endsection

@section('content')
@livewire('recojo')
    @if (session('download_reporte_url'))
        <div class="container-fluid mt-2">
            <div class="alert alert-info d-flex justify-content-between align-items-center">
                <span>Reporte listo para descargar.</span>
                <a href="{{ session('download_reporte_url') }}" target="_blank" class="btn btn-sm btn-primary">
                    Descargar reporte
                </a>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const url = @json(session('download_reporte_url'));
                if (!url) return;

                const frame = document.createElement('iframe');
                frame.style.display = 'none';
                frame.src = url;
                document.body.appendChild(frame);

                setTimeout(function () {
                    if (frame && frame.parentNode) {
                        frame.parentNode.removeChild(frame);
                    }
                }, 60000);
            });
        </script>
    @endif
@endsection
