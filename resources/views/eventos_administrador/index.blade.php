@extends('adminlte::page')

@section('title', 'Eventos Administrador')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="mb-1">Eventos Administrador</h1>
            <p class="text-muted mb-0">Ingresos al sistema y cambios detectados en la base de datos.</p>
        </div>
        <span class="badge badge-dark p-2"><i class="fas fa-shield-alt mr-1"></i> Vista exclusiva para administradores</span>
    </div>
@stop

@section('content')
    <div class="row">
        <div class="col-md-6 col-xl-3">
            <div class="small-box bg-info">
                <div class="inner"><h3>{{ number_format($loginCount, 0, ',', '.') }}</h3><p>Sesiones iniciadas</p></div>
                <div class="icon"><i class="fas fa-sign-in-alt"></i></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="small-box bg-warning">
                <div class="inner"><h3>{{ number_format($mutationCount, 0, ',', '.') }}</h3><p>Cambios registrados en BD</p></div>
                <div class="icon"><i class="fas fa-database"></i></div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-primary">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-filter mr-1"></i> Buscar eventos</h3></div>
        <div class="card-body">
            <form method="GET" action="{{ route('eventos-administrador.index') }}" class="row align-items-end">
                <div class="col-lg-4 form-group">
                    <label for="buscar">Usuario, IP, paquete o tabla</label>
                    <input id="buscar" name="buscar" class="form-control" value="{{ $search }}" placeholder="Ej.: usuario, 192.168.1.10 o EE123456789BO">
                </div>
                <div class="col-lg-2 form-group">
                    <label for="tipo">Tipo de evento</label>
                    <select id="tipo" name="tipo" class="form-control">
                        <option value="todos" @selected($type === 'todos')>Todos</option>
                        <option value="accesos" @selected($type === 'accesos')>Ingresos y salidas</option>
                        <option value="cambios" @selected($type === 'cambios')>Cambios en la base</option>
                    </select>
                </div>
                <div class="col-lg-2 form-group">
                    <label for="desde">Desde</label>
                    <input id="desde" name="desde" type="date" class="form-control" value="{{ $from }}">
                </div>
                <div class="col-lg-2 form-group">
                    <label for="hasta">Hasta</label>
                    <input id="hasta" name="hasta" type="date" class="form-control" value="{{ $until }}">
                </div>
                <div class="col-lg-2 form-group">
                    <button class="btn btn-primary mr-1" type="submit"><i class="fas fa-search mr-1"></i>Buscar</button>
                    <a class="btn btn-outline-secondary" href="{{ route('eventos-administrador.index') }}" title="Limpiar filtros"><i class="fas fa-times"></i></a>
                </div>
            </form>
            <div class="small text-muted">Los cambios se registran desde que se instala la auditoría. Para conexiones directas, la IP mostrada es el origen que ve PostgreSQL; una VPN, NAT o proxy puede mostrar una IP distinta a la configurada en el equipo.</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Historial de accesos y modificaciones</h3>
            <div class="card-tools"><span class="badge badge-light">{{ number_format($events->total(), 0, ',', '.') }} eventos</span></div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Fecha y hora</th>
                            <th>Evento</th>
                            <th>Usuario / conexión BD</th>
                            <th>Paquete / tabla</th>
                            <th>IP del equipo / origen observado</th>
                            <th>Equipo / cliente BD</th>
                            <th>Detalle del cambio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($events as $event)
                            @php
                                $badge = match ($event->action) {
                                    'INGRESO' => 'badge-success',
                                    'SALIDA' => 'badge-secondary',
                                    'ELIMINACION' => 'badge-danger',
                                    'CAMBIO DE ESTADO' => 'badge-warning',
                                    'CREACION' => 'badge-primary',
                                    default => 'badge-info',
                                };
                                $device = trim((string) $event->user_agent);
                                if ($device === '') {
                                    $device = trim((string) $event->application_name);
                                }
                                $requestIp = $event->request_ip ? explode('/', (string) $event->request_ip, 2)[0] : null;
                                $databaseIp = $event->database_client_ip ? explode('/', (string) $event->database_client_ip, 2)[0] : null;
                                $observedIp = $requestIp ?: $databaseIp;
                            @endphp
                            <tr>
                                <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($event->happened_at)->format('d/m/Y H:i:s') }}</td>
                                <td><span class="badge {{ $badge }}">{{ $event->action }}</span><small class="d-block text-muted">{{ $event->source === 'ACCESO' ? 'Sesión' : strtoupper($event->table_name) }}</small></td>
                                <td>
                                    <strong>{{ $event->actor ?: 'No identificado' }}</strong>
                                    @if($event->actor_alias)<small class="d-block text-muted">Alias: {{ $event->actor_alias }}</small>@endif
                                    @if($event->actor_id)<small class="d-block text-muted">ID: {{ $event->actor_id }}</small>@endif
                                    @if($event->database_user)<small class="d-block text-muted">Rol BD: {{ $event->database_user }}@if($event->session_role && $event->session_role !== $event->database_user) / sesión: {{ $event->session_role }}@endif</small>@endif
                                </td>
                                <td>
                                    @if($event->source === 'ACCESO')
                                        Usuario #{{ $event->record_identifier }}
                                    @else
                                        <strong>{{ $event->target_label }}</strong><small class="d-block text-muted">{{ $event->table_name }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-light">{{ $observedIp ?: 'No disponible' }}</span>
                                    <small class="d-block text-muted">{{ $requestIp ? 'IP recibida por la aplicación' : ($databaseIp ? 'IP de origen vista por PostgreSQL' : 'Origen no disponible') }}</small>
                                    @if($requestIp && $databaseIp && $requestIp !== $databaseIp)<small class="d-block text-muted">Conexión BD: {{ $databaseIp }}</small>@endif
                                </td>
                                <td class="text-break" style="min-width: 180px; max-width: 300px;">
                                    {{ $device !== '' ? $device : ($event->database_pid ? 'Conexión PostgreSQL · PID '.$event->database_pid : 'No disponible') }}
                                    @if($event->application_name && $event->user_agent)<small class="d-block text-muted">Cliente BD: {{ $event->application_name }}</small>@endif
                                </td>
                                <td style="min-width: 220px;">
                                    @if($event->source === 'ACCESO')
                                        {{ $event->action === 'INGRESO' ? 'Inicio de sesión registrado' : 'Cierre de sesión registrado' }}
                                    @elseif(count($event->changes))
                                        @foreach($event->changes as $change)
                                            <div class="mb-1"><code>{{ $change['field'] }}</code>:
                                                <span class="text-muted">{{ is_scalar($change['before']) ? $change['before'] : json_encode($change['before'], JSON_UNESCAPED_UNICODE) }}</span>
                                                <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                                <strong>{{ is_scalar($change['after']) ? $change['after'] : json_encode($change['after'], JSON_UNESCAPED_UNICODE) }}</strong>
                                            </div>
                                        @endforeach
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-5"><i class="fas fa-search fa-2x d-block mb-2"></i>No hay eventos que coincidan con los filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($events->hasPages())<div class="card-footer">{{ $events->links() }}</div>@endif
    </div>
@stop
