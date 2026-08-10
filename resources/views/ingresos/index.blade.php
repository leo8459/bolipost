@extends('adminlte::page')

@section('title', 'Ingresos')

@section('content_header')
    <h1>Ingresos de usuarios</h1>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-6 col-xl-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ number_format($totalLogs) }}</h3>
                    <p>Ingresos registrados</p>
                </div>
                <div class="icon">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ number_format($uniqueUsers) }}</h3>
                    <p>Usuarios que ingresaron</p>
                </div>
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="small-box {{ $unregisteredCount > 0 ? 'bg-danger' : 'bg-secondary' }}">
                <div class="inner">
                    <h3>{{ number_format($unregisteredCount) }}</h3>
                    <p>Sesiones sin usuario registrado</p>
                </div>
                <div class="icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <a href="{{ route('ingresos.index', ['view' => 'unregistered']) }}" class="small-box-footer">
                    Revisar control <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <a href="{{ route('ingresos.index') }}" class="btn {{ $view === 'history' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="fas fa-history mr-1"></i> Historial
        </a>
        <a href="{{ route('ingresos.index', ['view' => 'unregistered']) }}" class="btn {{ $view === 'unregistered' ? 'btn-danger' : 'btn-outline-danger' }}">
            <i class="fas fa-user-times mr-1"></i> Logueados sin registro
            <span class="badge badge-light ml-1">{{ $unregisteredCount }}</span>
        </a>
    </div>

    @if ($view === 'history')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Filtros</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('ingresos.index') }}" class="row" id="loginFiltersForm">
                <div class="col-lg-4">
                    <div class="form-group">
                        <label for="search">Usuario, alias, correo o IP</label>
                        <input
                            type="text"
                            name="search"
                            id="search"
                            class="form-control"
                            value="{{ $search }}"
                            placeholder="Buscar ingreso"
                        >
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="form-group">
                        <label for="period">Controlar por</label>
                        <select name="period" id="period" class="form-control">
                            <option value="all" @selected($period === 'all')>Todos</option>
                            <option value="day" @selected($period === 'day')>Día y hora</option>
                            <option value="month" @selected($period === 'month')>Mes</option>
                            <option value="range" @selected($period === 'range')>Rango de fechas</option>
                        </select>
                    </div>
                </div>

                <div class="col-lg-2 period-field" data-period-field="day">
                    <div class="form-group">
                        <label for="day">Día</label>
                        <input type="date" name="day" id="day" class="form-control" value="{{ $day }}" data-period-required>
                    </div>
                </div>
                <div class="col-lg-2 period-field" data-period-field="day">
                    <div class="form-group">
                        <label for="time_from">Hora desde</label>
                        <input type="time" name="time_from" id="time_from" class="form-control" value="{{ $timeFrom }}">
                    </div>
                </div>
                <div class="col-lg-2 period-field" data-period-field="day">
                    <div class="form-group">
                        <label for="time_to">Hora hasta</label>
                        <input type="time" name="time_to" id="time_to" class="form-control" value="{{ $timeTo }}">
                    </div>
                </div>

                <div class="col-lg-2 period-field" data-period-field="month">
                    <div class="form-group">
                        <label for="month">Mes</label>
                        <input type="month" name="month" id="month" class="form-control" value="{{ $month }}" data-period-required>
                    </div>
                </div>

                <div class="col-lg-2 period-field" data-period-field="range">
                    <div class="form-group">
                        <label for="from">Desde</label>
                        <input type="date" name="from" id="from" class="form-control" value="{{ $from }}" data-period-required>
                    </div>
                </div>
                <div class="col-lg-2 period-field" data-period-field="range">
                    <div class="form-group">
                        <label for="to">Hasta</label>
                        <input type="date" name="to" id="to" class="form-control" value="{{ $to }}" data-period-required>
                    </div>
                </div>

                <div class="col-lg-4 d-flex align-items-end">
                    <div class="form-group w-100">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search mr-1"></i> Buscar
                        </button>
                        <a href="{{ route('ingresos.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-times mr-1"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif

    @if ($view === 'history')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Historial de ingresos</h3>
            <div class="card-tools">
                <span class="badge badge-success">
                    <i class="fas fa-circle mr-1"></i> Monitoreo en tiempo real
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Fecha y hora</th>
                            <th>Usuario</th>
                            <th>Alias</th>
                            <th>Tiempo transcurrido en el sistema</th>
                            <th>IP</th>
                            <th>Navegador / equipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @php
                                $user = $log->user;
                                $roleNames = $user?->roles?->pluck('name')->filter()->values();
                                $rolesText = $roleNames?->isNotEmpty() ? $roleNames->implode(', ') : null;
                                $hasEmpresaRole = $roleNames?->contains(fn ($role) => mb_strtolower(trim((string) $role)) === 'empresa') ?? false;
                                $empresaName = trim((string) ($user?->empresa?->sigla ?: $user?->empresa?->nombre));
                            @endphp
                            <tr>
                                <td>{{ optional($log->logged_in_at)->format('d/m/Y H:i:s') }}</td>
                                <td>
                                    <strong>{{ $user?->name ?? $log->user_name ?? 'Usuario eliminado' }}</strong>
                                    <small class="d-block text-muted">
                                        Rol: {{ $rolesText ?: 'Sin rol asignado' }}
                                    </small>
                                </td>
                                <td>
                                    <strong>{{ $user?->alias ?? $log->user_alias ?? '-' }}</strong>
                                    @if (($hasEmpresaRole || $user?->empresa_id) && $empresaName !== '')
                                        <small class="d-block text-muted">
                                            Empresa: {{ $empresaName }}
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    @if ($log->logged_in_at)
                                        <span
                                            class="badge {{ $log->logged_out_at ? 'badge-secondary' : 'badge-primary' }} elapsed-login-time"
                                            data-login-at="{{ $log->logged_in_at->toIso8601String() }}"
                                            @if ($log->logged_out_at)
                                                data-logout-at="{{ $log->logged_out_at->toIso8601String() }}"
                                            @endif
                                        >
                                            Calculando...
                                        </span>
                                        <small class="d-block text-muted mt-1">
                                            {{ $log->logged_out_at ? 'Cerrado' : 'Activo' }}
                                        </small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td><span class="badge badge-light">{{ $log->ip_address ?? '-' }}</span></td>
                                <td class="text-break">{{ $log->user_agent ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">No hay ingresos registrados con esos filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($logs->hasPages())
            <div class="card-footer">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
    @else
        <div class="card card-outline {{ $unregisteredCount > 0 ? 'card-danger' : 'card-success' }}">
            <div class="card-header">
                <h3 class="card-title">Usuarios logueados que ya no están registrados</h3>
                <div class="card-tools">
                    <span class="badge {{ $unregisteredCount > 0 ? 'badge-danger' : 'badge-success' }}">
                        {{ $unregisteredCount > 0 ? 'Requiere revisión' : 'Sin novedades' }}
                    </span>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-0">
                    Se consideran activas las sesiones con actividad dentro de los últimos
                    {{ config('session.lifetime', 120) }} minutos. Aquí aparecen únicamente si el usuario fue eliminado
                    o dado de baja después de iniciar sesión.
                </p>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Última actividad</th>
                            <th>Usuario registrado al ingresar</th>
                            <th>ID de usuario</th>
                            <th>IP</th>
                            <th>Navegador / equipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($unregisteredSessions as $session)
                            <tr>
                                <td>{{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->format('d/m/Y H:i:s') }}</td>
                                <td>
                                    <strong>{{ $session->login_log?->user_name ?: 'Sin nombre disponible' }}</strong>
                                    <small class="d-block text-muted">
                                        Alias: {{ $session->login_log?->user_alias ?: '-' }}
                                    </small>
                                </td>
                                <td><span class="badge badge-danger">{{ $session->user_id }}</span></td>
                                <td><span class="badge badge-light">{{ $session->ip_address ?: '-' }}</span></td>
                                <td class="text-break">{{ $session->user_agent ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <i class="fas fa-check-circle text-success mr-1"></i>
                                    No existen usuarios logueados fuera del registro del sistema.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($unregisteredSessions->hasPages())
                <div class="card-footer">
                    {{ $unregisteredSessions->links() }}
                </div>
            @endif
        </div>
    @endif
@endsection

@section('js')
    <script>
        (function () {
            const periodSelect = document.getElementById('period');
            const periodFields = document.querySelectorAll('[data-period-field]');

            const refreshPeriodFields = function () {
                if (!periodSelect) {
                    return;
                }

                periodFields.forEach(function (field) {
                    const visible = field.dataset.periodField === periodSelect.value;
                    field.classList.toggle('d-none', !visible);
                    field.querySelectorAll('input').forEach(function (input) {
                        input.disabled = !visible;
                        input.required = visible && input.hasAttribute('data-period-required');
                    });
                });
            };

            if (periodSelect) {
                periodSelect.addEventListener('change', refreshPeriodFields);
                refreshPeriodFields();
            }

            const elapsedBadges = document.querySelectorAll('.elapsed-login-time[data-login-at]');

            if (!elapsedBadges.length) {
                return;
            }

            const pluralize = function (value, singular, plural) {
                return value + ' ' + (value === 1 ? singular : plural);
            };

            const formatElapsed = function (totalSeconds) {
                const days = Math.floor(totalSeconds / 86400);
                const hours = Math.floor((totalSeconds % 86400) / 3600);
                const minutes = Math.floor((totalSeconds % 3600) / 60);
                const seconds = totalSeconds % 60;

                if (days > 0) {
                    return [
                        pluralize(days, 'dia', 'dias'),
                        pluralize(hours, 'hora', 'horas'),
                        pluralize(minutes, 'min', 'min')
                    ].join(' ');
                }

                if (hours > 0) {
                    return [
                        pluralize(hours, 'hora', 'horas'),
                        pluralize(minutes, 'min', 'min'),
                        pluralize(seconds, 'seg', 'seg')
                    ].join(' ');
                }

                return [
                    pluralize(minutes, 'min', 'min'),
                    pluralize(seconds, 'seg', 'seg')
                ].join(' ');
            };

            const refreshElapsedTimes = function () {
                const now = Date.now();

                elapsedBadges.forEach(function (badge) {
                    const loginAt = Date.parse(badge.dataset.loginAt);
                    const logoutAt = badge.dataset.logoutAt ? Date.parse(badge.dataset.logoutAt) : null;

                    if (Number.isNaN(loginAt) || (logoutAt !== null && Number.isNaN(logoutAt))) {
                        badge.textContent = '-';
                        return;
                    }

                    const finalTime = logoutAt || now;
                    const elapsedSeconds = Math.max(0, Math.floor((finalTime - loginAt) / 1000));
                    badge.textContent = formatElapsed(elapsedSeconds);
                });
            };

            refreshElapsedTimes();
            window.setInterval(refreshElapsedTimes, 1000);
        })();
    </script>
@endsection
