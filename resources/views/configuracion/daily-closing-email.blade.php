@extends('adminlte::page')

@section('title', 'Cierre diario')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="mb-1">Cierre diario</h1>
            <p class="text-muted mb-0">Envío de correo · Movimientos de contratos y EMS por día</p>
        </div>
        <span class="badge badge-info p-2">Automático · 20:00 · Bolivia</span>
    </div>
@stop

@section('content')
    @foreach (['status' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $type)
        @if (session($key))
            <div class="alert alert-{{ $type }}">{{ session($key) }}</div>
        @endif
    @endforeach
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row">
        @include('configuracion.partials.daily-closing-mail-controls', [
            'automaticEnabled' => $dailyClosingEnabled,
        ])

        <div class="col-lg-8">
            <div class="alert alert-light border">
                Vista previa del <strong>{{ \Carbon\CarbonImmutable::parse($selectedDate)->format('d/m/Y') }}</strong>, hora de Bolivia.
                Solo se muestran movimientos realizados durante ese día.
            </div>
            @foreach ($report['modules'] as $module)
                <div class="card card-warning card-outline">
                    <div class="card-header"><h3 class="card-title">{{ $module['name'] }}</h3></div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            @foreach (['Registrados ese día' => $module['registered'], 'Entregados ese día' => $module['delivered'], 'Movimientos' => $module['movements']->count(), 'Envíos con movimiento' => $module['moved_packages']] as $label => $value)
                                <div class="col-6 col-xl-3 mb-2"><strong class="h3 d-block">{{ $value }}</strong><span class="text-muted">{{ $label }}</span></div>
                            @endforeach
                        </div>

                        <h5>Resumen por evento</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead><tr><th>Evento</th><th>Movimientos</th><th>Envíos distintos</th></tr></thead>
                                <tbody>
                                    @forelse ($module['activity'] as $event)
                                        <tr><td>{{ $event->nombre_evento ?? 'Evento '.$event->evento_id }}</td><td>{{ $event->movimientos }}</td><td>{{ $event->paquetes }}</td></tr>
                                    @empty
                                        <tr><td colspan="3" class="text-muted">Sin movimientos registrados en la fecha elegida.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <h5>Detalle de movimientos</h5>
                        <div class="table-responsive" style="max-height:420px">
                            <table class="table table-sm table-hover">
                                <thead><tr><th>Código</th><th>Eventos del día (hora · evento · usuario)</th><th>Destino</th></tr></thead>
                                <tbody>
                                    @forelse ($module['daily_packages']->take(200) as $package)
                                        <tr>
                                            <td>{{ $package->codigo }}</td>
                                            <td>{!! nl2br(e($package->timeline)) !!}</td>
                                            <td>{{ $package->destino ?: '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-muted">No hay movimientos para este servicio.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted">Se muestran hasta 200 códigos por servicio, con todos sus eventos del día. El Excel enviado por correo incluye todos los de la fecha.</small>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@stop
