@extends('layouts.cliente-adminlte')

@section('title', 'Mis Solicitudes')

@section('css')
    <style>
        .order-success-modal .modal-content {
            overflow: hidden;
            border: 0;
            border-radius: 22px;
            box-shadow: 0 24px 70px rgba(16, 49, 95, .28);
        }

        .order-success-modal .modal-body {
            padding: 34px 30px 30px;
            text-align: center;
        }

        .order-success-modal__icon {
            display: flex;
            width: 78px;
            height: 78px;
            margin: 0 auto 20px;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #103f81;
            background: #ffcc29;
            font-size: 36px;
            box-shadow: 0 10px 24px rgba(255, 190, 0, .35);
        }

        .order-success-modal__title {
            margin: 0;
            color: #103f81;
            font-size: 28px;
            font-weight: 800;
        }

        .order-success-modal__message {
            margin: 12px 0 0;
            color: #26384f;
            font-size: 18px;
            line-height: 1.5;
        }

        .order-success-modal__notice {
            margin-top: 20px;
            padding: 14px 16px;
            border-radius: 12px;
            color: #103f81;
            background: #fff7d6;
            font-size: 14px;
            line-height: 1.55;
        }

        .order-success-modal__button {
            min-width: 150px;
            margin-top: 22px;
            padding: 10px 24px;
            border: 0;
            border-radius: 999px;
            color: #fff;
            background: #103f81;
            font-weight: 700;
        }
    </style>
@endsection

@section('content_header')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div>
            <h1 class="m-0 text-dark">Mis solicitudes</h1>
            <small class="text-muted">Revisa todas las solicitudes que registraste con tu cuenta cliente.</small>
        </div>
        <div class="d-flex flex-column flex-md-row">
            <a href="{{ route('clientes.solicitudes.create') }}" class="btn btn-outline-primary mt-3 mt-md-0 mr-md-2">
                Nueva solicitud
            </a>
            <a href="{{ route('clientes.dashboard') }}" class="btn btn-outline-warning mt-3 mt-md-0">
                Volver al panel
            </a>
        </div>
    </div>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning" role="alert">
            {{ session('warning') }}
        </div>
    @endif

    <div class="card card-outline card-warning">
        <div class="card-header">
            <h3 class="card-title">Historial de solicitudes</h3>
        </div>
        <div class="card-body p-0">
            @if ($solicitudes->isEmpty())
                <div class="p-3 text-muted">Todavia no registraste solicitudes.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Servicio</th>
                                <th>Tarifario</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($solicitudes as $solicitud)
                                <tr>
                                    <td><strong>{{ $solicitud->codigo_solicitud }}</strong></td>
                                    <td>{{ $solicitud->servicioExtra?->descripcion ?: ($solicitud->servicioExtra?->nombre ?? '-') }}</td>
                                    <td>
                                        @if($solicitud->tarifarioTiktoker)
                                            <span class="d-block">#{{ $solicitud->tarifarioTiktoker->id }}</span>
                                            <small class="text-muted">
                                                {{ $solicitud->tarifarioTiktoker->origen?->nombre_origen }}
                                                /
                                                {{ $solicitud->tarifarioTiktoker->destino?->nombre_destino }}
                                                @if($solicitud->tarifarioTiktoker->servicioExtra)
                                                    / {{ $solicitud->tarifarioTiktoker->servicioExtra->nombre }}
                                                @endif
                                            </small>
                                        @else
                                            <span class="text-muted">Sin tarifa</span>
                                        @endif
                                    </td>
                                    <td>{{ $solicitud->origen }}</td>
                                    <td>{{ $solicitud->destino?->nombre_destino }}</td>
                                    <td>
                                        @php($estadoNombre = (string) optional($solicitud->estadoRegistro)->nombre_estado)
                                        <span class="badge badge-{{ strtoupper($estadoNombre) === 'SOLICITUD' ? 'warning' : 'success' }}">
                                            {{ $estadoNombre !== '' ? $estadoNombre : '-' }}
                                        </span>
                                    </td>
                                    <td>{{ optional($solicitud->created_at)->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if (session('pickup_notice'))
        <div class="modal fade order-success-modal" id="orderSuccessModal" tabindex="-1" role="dialog" aria-labelledby="orderSuccessModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="order-success-modal__icon" aria-hidden="true">
                            <i class="fas fa-check"></i>
                        </div>
                        <h2 class="order-success-modal__title" id="orderSuccessModalTitle">¡Muchas gracias por su pedido!</h2>
                        <p class="order-success-modal__message">Enseguida nos comunicaremos con usted.</p>
                        <div class="order-success-modal__notice">
                            <strong>Alerta:</strong><br>
                            {{ session('pickup_notice') }}
                        </div>
                        <button type="button" class="order-success-modal__button" data-dismiss="modal">Entendido</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('js')
    @if (session('pickup_notice'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.jQuery) {
                    window.jQuery('#orderSuccessModal').modal('show');
                }
            });
        </script>
    @endif
@endsection
