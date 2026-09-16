@extends('adminlte::page')

@section('title', 'Cierre diario')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="mb-1">Cierre diario</h1>
            <p class="text-muted mb-0">Envío de correo · Resumen de contratos y EMS</p>
        </div>
        <span class="badge badge-info p-2">Todos los días · 20:00 · Bolivia</span>
    </div>
@stop

@section('content')
    @foreach (['status' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $type)
        @if (session($key))
            <div class="alert alert-{{ $type }}">{{ session($key) }}</div>
        @endif
    @endforeach

    <div class="row">
        <div class="col-lg-4">
            <div class="card card-info card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-paper-plane mr-2"></i>Envío del cierre</h3>
                    <span class="badge {{ $dailyClosingEnabled ? 'badge-success' : 'badge-secondary' }} float-right">{{ $dailyClosingEnabled ? 'Activo' : 'Inactivo' }}</span>
                </div>
                <div class="card-body">
                    <p>El cierre automático se envía a las <strong>20:00, hora de Bolivia</strong>, a los destinatarios guardados.</p>
                    <p class="text-muted">Incluye movimientos del día, pendientes acumulados, pendientes por cartero y un Excel dividido por departamento de destino con el detalle de todos los envíos pendientes.</p>
                    <form method="POST" action="{{ route('contract-expiration-email.daily-closing.send') }}" class="mb-3">
                        @csrf
                        <button class="btn btn-success btn-block" @disabled(empty($recipients))><i class="fas fa-envelope mr-1"></i>Enviar cierre ahora</button>
                    </form>
                    <form method="POST" action="{{ route('contract-expiration-email.daily-closing.update') }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="enabled" value="{{ $dailyClosingEnabled ? 0 : 1 }}">
                        <button class="btn btn-outline-primary btn-block">{{ $dailyClosingEnabled ? 'Desactivar cierre automático' : 'Activar cierre automático' }}</button>
                    </form>
                    <small class="text-muted d-block mt-3">El envío manual usa la hora actual y no impide el cierre de las 20:00.</small>
                </div>
            </div>
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Agregar destinatario</h3></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('contract-expiration-email.recipients.store') }}">
                        @csrf
                        <label for="recipient">Correo electrónico</label>
                        <input type="email" id="recipient" name="recipient" value="{{ old('recipient') }}" class="form-control @error('recipient') is-invalid @enderror" maxlength="254" placeholder="nombre@correos.gob.bo" required>
                        @error('recipient')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <button class="btn btn-primary btn-block mt-3"><i class="fas fa-plus mr-1"></i>Agregar</button>
                    </form>
                    <small class="text-muted d-block mt-2">Hasta 50 correos. La lista se comparte con los avisos de Contratos.</small>
                </div>
            </div>
            <div class="card card-info card-outline">
                <div class="card-header"><h3 class="card-title">Correos guardados</h3><span class="badge badge-info float-right">{{ count($recipients) }}</span></div>
                <div class="card-body p-0">
                    @forelse ($recipients as $recipient)
                        <div class="d-flex align-items-center p-3 border-bottom">
                            <span class="text-break mr-2">{{ $recipient }}</span>
                            <form method="POST" action="{{ route('contract-expiration-email.recipients.destroy') }}" class="ml-auto">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="recipient" value="{{ $recipient }}">
                                <button class="btn btn-outline-danger btn-sm" aria-label="Quitar {{ $recipient }}"><i class="fas fa-trash-alt"></i></button>
                            </form>
                        </div>
                    @empty
                        <p class="text-muted p-3 mb-0">Agregue un correo para poder enviar el cierre.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="alert alert-light border">Vista previa al <strong>{{ $report['cutoff'] }}</strong>, hora de Bolivia. Actividad desde las 00:00; pendientes acumulados, incluidas solicitudes sin recojo. El cierre es informativo y permite seguir trabajando.</div>
            @foreach ($report['modules'] as $module)
                <div class="card card-warning card-outline">
                    <div class="card-header"><h3 class="card-title">{{ $module['name'] }}</h3></div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            @foreach (['Registrados hoy' => $module['registered'], 'Entregados hoy' => $module['delivered'], 'Pendientes' => $module['pending']->count(), 'Sin cartero activo' => $module['unassigned']] as $label => $value)
                                <div class="col-6 col-xl-3 mb-2"><strong class="h3 d-block">{{ $value }}</strong><span class="text-muted">{{ $label }}</span></div>
                            @endforeach
                        </div>
                        <h5>Movimientos del día</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead><tr><th>Evento</th><th>Movimientos</th><th>Envíos distintos</th></tr></thead>
                                <tbody>
                                    @forelse ($module['activity'] as $event)
                                        <tr><td>{{ $event->nombre_evento ?? 'Evento '.$event->evento_id }}</td><td>{{ $event->movimientos }}</td><td>{{ $event->paquetes }}</td></tr>
                                    @empty
                                        <tr><td colspan="3" class="text-muted">Sin movimientos registrados hoy.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <h5>Pendientes por cartero</h5>
                        <table class="table table-sm table-striped">
                            <thead><tr><th>Cartero</th><th>Pendientes</th></tr></thead>
                            <tbody>
                                @forelse ($module['couriers'] as $courier)
                                    <tr><td>{{ $courier['name'] }}</td><td>{{ $courier['pending'] }}</td></tr>
                                @empty
                                    <tr><td colspan="2" class="text-muted">Sin pendientes asignados a carteros.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <h5>Detalle de pendientes</h5>
                        <div class="table-responsive" style="max-height:360px">
                            <table class="table table-sm table-hover">
                                <thead><tr><th>Código</th><th>Destino</th><th>Estado</th><th>Cartero</th></tr></thead>
                                <tbody>
                                    @forelse ($module['pending']->take(100) as $row)
                                        <tr><td>{{ $row->codigo }}</td><td>{{ $row->destino ?: '—' }}</td><td>{{ $row->estado ?: 'Sin estado' }}</td><td>{{ $row->cartero ?? 'Sin cartero activo' }}</td></tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted">No hay pendientes.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted">Se muestran hasta 100 pendientes por servicio. El Excel enviado por correo incluye todos.</small>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@stop
