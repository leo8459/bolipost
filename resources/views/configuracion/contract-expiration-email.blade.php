@extends('adminlte::page')

@section('title', 'Correo electronico')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="mb-1">Correo electronico</h1>
            <p class="text-muted mb-0">Avisos de vencimiento de contratos empresariales</p>
        </div>
        <span class="badge badge-warning p-2">Ventana de aviso: 90 dias</span>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i> {{ session('warning') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger"><i class="fas fa-times-circle mr-1"></i> {{ session('error') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-5">
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user-plus mr-2"></i>Agregar destinatario</h3>
                </div>
                <form method="POST" action="{{ route('contract-expiration-email.recipients.store') }}">
                    @csrf
                    <div class="card-body">
                        <label for="recipient">Correo electronico</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-at"></i></span>
                            </div>
                            <input
                                type="email"
                                id="recipient"
                                name="recipient"
                                class="form-control @error('recipient') is-invalid @enderror"
                                value="{{ old('recipient') }}"
                                placeholder="nombre@correos.gob.bo"
                                maxlength="254"
                                required>
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus mr-1"></i> Agregar
                                </button>
                            </div>
                        </div>
                        @error('recipient')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <small class="form-text text-muted">Puede guardar hasta 50 destinatarios.</small>
                    </div>
                </form>
            </div>

            <div class="card card-info card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-address-book mr-2"></i>Correos guardados
                    </h3>
                    <span class="badge badge-info ml-auto">{{ count($recipients) }}</span>
                </div>
                <div class="card-body p-0">
                    @forelse ($recipients as $index => $recipient)
                        <div class="d-flex align-items-center px-3 py-3 {{ ! $loop->last ? 'border-bottom' : '' }}">
                            <span class="badge badge-light border mr-3">{{ $index + 1 }}</span>
                            <div class="text-truncate">
                                <i class="fas fa-envelope text-info mr-2"></i>
                                <a href="mailto:{{ $recipient }}" class="font-weight-bold">{{ $recipient }}</a>
                            </div>
                            <form
                                method="POST"
                                action="{{ route('contract-expiration-email.recipients.destroy') }}"
                                class="ml-auto"
                                onsubmit="return confirm('Desea quitar este correo de la lista?');">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="recipient" value="{{ $recipient }}">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Quitar correo">
                                    <i class="fas fa-trash-alt mr-1"></i> Quitar
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="text-center text-muted px-3 py-4">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p class="mb-0">Todavia no hay correos guardados.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h5><i class="fas fa-paper-plane text-primary mr-2"></i>Envio manual</h5>
                    <p class="text-muted">Envia ahora un resumen de todos los contratos mostrados a los destinatarios guardados.</p>
                    <form method="POST" action="{{ route('contract-expiration-email.send') }}" onsubmit="return confirm('¿Desea enviar ahora el aviso de vencimiento?');">
                        @csrf
                        <button type="submit" class="btn btn-success btn-block" @disabled(empty($recipients) || $alerts->isEmpty())>
                            <i class="fas fa-envelope mr-1"></i> Mandar correo ahora
                        </button>
                    </form>
                </div>
            </div>

            <div class="card {{ $automaticSendingEnabled ? 'card-success' : 'card-secondary' }} card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-clock mr-2"></i>Envio automatico
                    </h3>
                    <span class="badge {{ $automaticSendingEnabled ? 'badge-success' : 'badge-secondary' }} ml-auto p-2">
                        {{ $automaticSendingEnabled ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        {{ $automaticSendingEnabled
                            ? 'Los avisos se enviaran los dias 1 y 15 de cada mes a las 08:00.'
                            : 'Los envios programados estan detenidos. El envio manual sigue disponible.' }}
                    </p>
                    <form
                        method="POST"
                        action="{{ route('contract-expiration-email.automatic-sending.update') }}"
                        onsubmit="return confirm('{{ $automaticSendingEnabled ? 'Desea desactivar los envios automaticos?' : 'Desea activar los envios automaticos?' }}');">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="enabled" value="{{ $automaticSendingEnabled ? 0 : 1 }}">
                        <button type="submit" class="btn {{ $automaticSendingEnabled ? 'btn-outline-danger' : 'btn-success' }} btn-block">
                            <i class="fas {{ $automaticSendingEnabled ? 'fa-pause' : 'fa-play' }} mr-1"></i>
                            {{ $automaticSendingEnabled ? 'Desactivar envio automatico' : 'Activar envio automatico' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card card-warning card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title"><i class="fas fa-file-contract mr-2"></i>Contratos incluidos en el aviso</h3>
                    <span class="badge badge-warning ml-auto">{{ $alerts->count() }}</span>
                </div>
                <div class="card-body p-0">
                    @if ($alerts->isEmpty())
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p class="mb-0">No existen contratos por vencer en los proximos 90 dias.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Empresa</th>
                                        <th>Fecha de vencimiento</th>
                                        <th class="text-center">Dias restantes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($alerts as $alert)
                                        <tr>
                                            <td class="font-weight-bold">{{ $alert['empresa'] }}</td>
                                            <td>{{ $alert['fin_contrato'] }}</td>
                                            <td class="text-center">
                                                <span class="badge {{ $alert['days_left'] <= 30 ? 'badge-danger' : 'badge-warning' }} p-2">
                                                    {{ $alert['days_left'] }} dia(s)
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@stop
