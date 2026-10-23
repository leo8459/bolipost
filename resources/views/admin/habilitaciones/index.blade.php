@extends('adminlte::page')

@section('title', 'Habilitar servicios')

@section('content_header')
    <h1>Habilitar servicios</h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card card-outline card-primary">
        <div class="card-header">
            <h2 class="card-title">Disponibilidad de servicios</h2>
        </div>
        <form method="POST" action="{{ route('admin.service-availability.update') }}">
            @csrf
            @method('PATCH')
            <div class="card-body">
                <p class="text-muted">Al deshabilitar un servicio, se ocultarán sus opciones para crear o generar nuevos envíos.</p>

                @php
                    $serviceDetails = [
                        'ems' => [
                            'label' => 'EMS',
                            'description' => 'Muestra u oculta el botón Nuevo en Admisiones EMS.',
                            'icon' => 'fas fa-box',
                        ],
                        'contratos' => [
                            'label' => 'CONTRATOS',
                            'description' => 'Muestra u oculta las opciones para crear solicitudes de Contratos y Correspondencia.',
                            'icon' => 'fas fa-file-contract',
                        ],
                        'solicitud' => [
                            'label' => 'SOLICITUD',
                            'description' => 'Muestra u oculta el botón Nuevo para Delivery Express.',
                            'icon' => 'fas fa-file-signature',
                        ],
                    ];
                @endphp

                @foreach ($serviceDetails as $service => $details)
                    <div class="border rounded p-3 mb-3 d-flex justify-content-between align-items-center flex-wrap">
                        <div class="pr-3 mb-2 mb-md-0">
                            <h3 class="h5 mb-1"><i class="{{ $details['icon'] }} text-primary mr-2" aria-hidden="true"></i>{{ $details['label'] }}</h3>
                            <div class="text-muted">{{ $details['description'] }}</div>
                        </div>
                        <div class="d-flex align-items-center">
                            <span
                                class="badge badge-{{ $services[$service] ? 'success' : 'secondary' }} mr-3"
                                data-service-status="{{ $service }}"
                            >
                                {{ $services[$service] ? 'Habilitado' : 'Deshabilitado' }}
                            </span>
                            <div class="custom-control custom-switch">
                                <input type="hidden" name="{{ $service }}" value="0">
                                <input
                                    type="checkbox"
                                    class="custom-control-input"
                                    id="service-{{ $service }}"
                                    name="{{ $service }}"
                                    value="1"
                                    @checked($services[$service])
                                    aria-label="Habilitar o deshabilitar {{ $details['label'] }}"
                                >
                                <label class="custom-control-label" for="service-{{ $service }}">
                                    Habilitar o deshabilitar
                                </label>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="card-footer text-right">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-1" aria-hidden="true"></i> Guardar cambios
                </button>
            </div>
        </form>
    </div>
@stop

@push('js')
    <script>
        document.querySelectorAll('[data-service-status]').forEach(function (status) {
            const service = status.dataset.serviceStatus;
            const control = document.getElementById('service-' + service);

            if (!control) return;

            control.addEventListener('change', function () {
                const enabled = control.checked;
                status.textContent = enabled ? 'Habilitado' : 'Deshabilitado';
                status.classList.toggle('badge-success', enabled);
                status.classList.toggle('badge-secondary', !enabled);
            });
        });
    </script>
@endpush
