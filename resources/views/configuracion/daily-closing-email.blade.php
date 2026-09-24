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
        <div class="col-lg-4">
            <div class="card card-info card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-paper-plane mr-2"></i>Envío del cierre</h3>
                    <span class="badge {{ $dailyClosingEnabled ? 'badge-success' : 'badge-secondary' }} float-right">{{ $dailyClosingEnabled ? 'Activo' : 'Inactivo' }}</span>
                </div>
                <div class="card-body">
                    <p>El cierre automático se envía a las <strong>20:00, hora de Bolivia</strong>, con los movimientos realizados ese día.</p>
                    <p class="text-muted">Para un envío manual, elija el día. El correo y el Excel incluirán únicamente los movimientos de esa fecha.</p>

                    <form method="GET" action="{{ route('contract-expiration-email.daily-closing.index') }}" class="mb-3">
                        <label for="preview-date">Día que desea revisar</label>
                        <div class="input-group">
                            <input type="date" id="preview-date" name="date" value="{{ $selectedDate }}" max="{{ now('America/La_Paz')->toDateString() }}" class="form-control" required>
                            <div class="input-group-append">
                                <button class="btn btn-outline-info"><i class="fas fa-search mr-1"></i>Ver</button>
                            </div>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('contract-expiration-email.daily-closing.send') }}" class="mb-3">
                        @csrf
                        <input type="hidden" name="date" value="{{ $selectedDate }}">
                        <button class="btn btn-success btn-block" @disabled(empty($recipients))>
                            <i class="fas fa-envelope mr-1"></i>Enviar cierre del {{ \Carbon\CarbonImmutable::parse($selectedDate)->format('d/m/Y') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('contract-expiration-email.daily-closing.update') }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="enabled" value="{{ $dailyClosingEnabled ? 0 : 1 }}">
                        <button class="btn btn-outline-primary btn-block">{{ $dailyClosingEnabled ? 'Desactivar cierre automático' : 'Activar cierre automático' }}</button>
                    </form>
                    <small class="text-muted d-block mt-3">El envío manual no impide el cierre automático de las 20:00.</small>
                </div>
            </div>

            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Agregar destinatario</h3></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('contract-expiration-email.daily-closing.recipients.store') }}">
                        @csrf
                        <label for="recipient">Correo electrónico</label>
                        <input type="email" id="recipient" name="recipient" value="{{ old('recipient') }}" class="form-control @error('recipient') is-invalid @enderror" maxlength="254" placeholder="nombre@correos.gob.bo" required>
                        @error('recipient')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <button class="btn btn-primary btn-block mt-3"><i class="fas fa-plus mr-1"></i>Agregar</button>
                    </form>
                    <small class="text-muted d-block mt-2">Hasta 50 correos. Esta lista es exclusiva del Cierre diario.</small>
                </div>
            </div>

            <div class="card card-info card-outline">
                <div class="card-header"><h3 class="card-title">Correos guardados</h3><span class="badge badge-info float-right">{{ count($recipients) }}</span></div>
                <div class="card-body p-0">
                    @forelse ($recipients as $recipient)
                        <div class="d-flex align-items-center p-3 border-bottom">
                            <span class="text-break mr-2">{{ $recipient }}</span>
                            <form method="POST" action="{{ route('contract-expiration-email.daily-closing.recipients.destroy') }}" class="ml-auto">
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
