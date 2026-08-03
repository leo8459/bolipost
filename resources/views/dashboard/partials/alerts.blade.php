@if(($contratosPorRecoger ?? 0) > 0)
<div class="alert alert-danger d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3">
    <div>
        <strong>Tienes paquetes por recoger:</strong>
        {{ number_format((int) $contratosPorRecoger) }}
        @if(($pickupAlertIsNational ?? false) === true)
            a nivel nacional.
            @if(collect($contratosPorRecogerPorDepartamento ?? [])->isNotEmpty())
                <div class="mt-2 d-flex flex-wrap">
                    @foreach($contratosPorRecogerPorDepartamento as $departamento)
                        <span class="btn btn-sm btn-light border mr-2 mb-2">
                            {{ $departamento->departamento }}: {{ number_format((int) $departamento->total) }}
                        </span>
                    @endforeach
                </div>
            @endif
        @else
            en {{ $userCity !== '' ? $userCity : 'tu regional' }}.
        @endif
    </div>
    <div class="d-flex flex-column flex-md-row mt-2 mt-md-0">
        @if(($canPlayPickupAlertSound ?? false) === true)
        <button id="pickupAlertSoundBtn" type="button" class="btn btn-sm btn-light mr-md-2 mb-2 mb-md-0">
            Activar sonido
        </button>
        @endif
        <a href="{{ route('paquetes-contrato.recoger-envios', [], false) }}" class="btn btn-sm btn-outline-dark">
            Ir a recoger envios
        </a>
    </div>
</div>
@endif

@if(((int) data_get($regionalPendingAlert ?? [], 'count', 0)) > 0)
<div class="alert alert-warning d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3">
    <div>
        <strong>Tiene paquetes pendientes:</strong>
        {{ number_format((int) data_get($regionalPendingAlert, 'count', 0)) }}
        con mas de {{ (int) data_get($regionalPendingAlert, 'hours', 72) }} horas habiles
        @if(data_get($regionalPendingAlert, 'scope') === 'nacional')
            a nivel nacional.
            @if(collect(data_get($regionalPendingAlert, 'departments', []))->isNotEmpty())
                <div class="mt-2 d-flex flex-wrap">
                    @foreach(data_get($regionalPendingAlert, 'departments', []) as $departamento)
                        <span class="btn btn-sm btn-light border mr-2 mb-2">
                            {{ $departamento->departamento }}: {{ number_format((int) $departamento->total) }}
                        </span>
                    @endforeach
                </div>
            @endif
        @else
            en {{ data_get($regionalPendingAlert, 'regional', $userCity !== '' ? $userCity : 'tu regional') }}.
        @endif
    </div>
    <div class="text-muted small mt-2 mt-md-0">
        Se descuentan sabados, domingos y feriados nacionales de Bolivia.
    </div>
</div>
@endif

@if(((int) data_get($carteroPendingAlert ?? [], 'count', 0)) > 0)
<div class="alert alert-info d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3">
    <div>
        <strong>El cartero {{ data_get($carteroPendingAlert, 'name', 'Sin nombre') }} tiene {{ number_format((int) data_get($carteroPendingAlert, 'count', 0)) }} paquetes.</strong>
        Pendientes por entregar en su bandeja CARTERO.
    </div>
    <a href="{{ route('carteros.cartero') }}" class="btn btn-sm btn-outline-primary mt-2 mt-md-0">
        Ir a mi bandeja
    </a>
</div>
@endif

@if(((int) data_get($pendingCn33Alert ?? [], 'count', 0)) > 0)
@php
    $dashboardPendingCn33Rows = collect(data_get($pendingCn33Alert, 'rows', []));
    $dashboardPendingCn33Departments = $dashboardPendingCn33Rows
        ->groupBy(function ($row) {
            $regional = trim((string) ($row->regional ?? ''));
            return $regional !== '' ? $regional : 'SIN DEPARTAMENTO';
        })
        ->map(function ($rows, $department) {
            return (object) [
                'department' => $department,
                'total_cn33' => $rows->count(),
                'max_days_delay' => (int) $rows->max('days_delay'),
                'rows' => $rows->sortByDesc('days_delay')->values(),
            ];
        })
        ->sortByDesc(function ($item) {
            return ((int) ($item->total_cn33 ?? 0) * 100000) + (int) ($item->max_days_delay ?? 0);
        })
        ->values();
@endphp
<div class="alert alert-danger d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3">
    <div>
        <strong>Registrar bitacora de envio nacional.</strong>
        Hay {{ number_format((int) data_get($pendingCn33Alert, 'count', 0)) }} CN-33 sin bitacora por mas de {{ (int) data_get($pendingCn33Alert, 'grace_hours', 24) }} horas desde su dia y hora de despacho.
        @if((string) data_get($pendingCn33Alert, 'regional', '') !== '')
        Solo se muestran registros de {{ data_get($pendingCn33Alert, 'regional') }}.
        @else
        Se muestran registros a nivel nacional.
        @endif
        Se consideran despachos desde {{ optional(data_get($pendingCn33Alert, 'alert_start_date'))->format('d/m/Y') ?? '17/07/2026' }}.
        Retraso maximo: {{ number_format((int) data_get($pendingCn33Alert, 'max_days_delay', 0)) }} dia(s).
        @if($dashboardPendingCn33Departments->isNotEmpty())
            <div class="mt-2 d-flex flex-wrap">
                @foreach($dashboardPendingCn33Departments as $index => $department)
                    <button
                        type="button"
                        class="btn btn-light border mr-2 mb-2"
                        data-toggle="modal"
                        data-target="#pendingCn33DepartmentModal{{ $index }}"
                    >
                        {{ $department->department }}: {{ number_format((int) ($department->total_cn33 ?? 0)) }}
                    </button>
                @endforeach
            </div>
        @endif
    </div>
    @can('feature.bitacoras.index.create')
        <a href="{{ route('bitacoras.create') }}" class="btn btn-sm btn-outline-dark mt-2 mt-md-0">
            Registrar bitacora
        </a>
    @endcan
</div>

@foreach($dashboardPendingCn33Departments as $index => $department)
    <div class="modal fade" id="pendingCn33DepartmentModal{{ $index }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        {{ $department->department }} - CN-33 que no tienen bitacora
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border">
                        <strong>Total CN-33 sin bitacora:</strong> {{ number_format((int) ($department->total_cn33 ?? 0)) }}
                        |
                        <strong>Retraso maximo:</strong> {{ number_format((int) ($department->max_days_delay ?? 0)) }} dia(s)
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>CN-33 sin bitacora</th>
                                    <th class="text-right">Dias de retraso</th>
                                    <th class="text-right">Peso</th>
                                    <th class="text-right">Registros</th>
                                    <th>Dia/Hora despacho</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(($department->rows ?? collect()) as $cnIndex => $row)
                                    <tr>
                                        <td>
                                            <button
                                                type="button"
                                                class="btn btn-link font-weight-bold p-0"
                                                data-toggle="modal"
                                                data-target="#pendingCn33PackagesModal{{ $index }}_{{ $cnIndex }}"
                                                title="Ver todos los paquetes del CN-33"
                                            >
                                                {{ $row->numero_despacho ?? $row->cod_especial }}
                                            </button>
                                        </td>
                                        <td class="text-right">{{ number_format((int) ($row->days_delay ?? 0)) }}</td>
                                        <td class="text-right">{{ number_format((float) ($row->peso_total ?? 0), 3) }}</td>
                                        <td class="text-right">{{ number_format((int) ($row->total_registros ?? 0)) }}</td>
                                        <td>{{ optional($row->dispatch_created_at ?? $row->first_created_at)->format('d/m/Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    @can('feature.bitacoras.index.create')
                        <a href="{{ route('bitacoras.create') }}" class="btn btn-danger">Registrar bitacora</a>
                    @endcan
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    @foreach(($department->rows ?? collect()) as $cnIndex => $row)
        <div class="modal fade cn33-packages-modal" id="pendingCn33PackagesModal{{ $index }}_{{ $cnIndex }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <div>
                            <h5 class="modal-title">CN-33 {{ $row->numero_despacho ?? $row->cod_especial }}</h5>
                            <small>Paquetes incluidos en este despacho</small>
                        </div>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-light border d-flex flex-wrap justify-content-between">
                            <span><strong>Regional:</strong> {{ $row->regional ?: 'Sin regional' }}</span>
                            <span><strong>Total paquetes:</strong> {{ number_format(collect($row->packages ?? [])->count()) }}</span>
                            <span><strong>Peso total:</strong> {{ number_format((float) ($row->peso_total ?? 0), 3) }}</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Servicio</th>
                                        <th>Codigo del paquete</th>
                                        <th>Origen</th>
                                        <th>Destino</th>
                                        <th>Destinatario</th>
                                        <th class="text-right">Peso</th>
                                        <th>Registrado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse(collect($row->packages ?? []) as $package)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $package->servicio ?: '-' }}</td>
                                            <td><strong>{{ $package->codigo ?: '-' }}</strong></td>
                                            <td>{{ $package->origen ?: '-' }}</td>
                                            <td>{{ $package->destino ?: '-' }}</td>
                                            <td>{{ $package->destinatario ?: '-' }}</td>
                                            <td class="text-right">{{ number_format((float) $package->peso, 3) }}</td>
                                            <td>{{ optional($package->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                No se encontraron paquetes para este CN-33.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endforeach

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.jQuery) {
            return;
        }

        window.jQuery('.cn33-packages-modal').on('hidden.bs.modal', function () {
            if (window.jQuery('.modal.show').length > 0) {
                document.body.classList.add('modal-open');
            }
        });
    });
</script>
@endif

@if((bool) data_get($carteroPendingSummary ?? [], 'enabled', false))
@php
    $carteroPendingRows = collect(data_get($carteroPendingSummary, 'rows', collect()));
    $carteroPendingDepartments = $carteroPendingRows
        ->groupBy(fn ($row) => trim((string) ($row->ciudad ?? '')) !== '' ? trim((string) $row->ciudad) : 'SIN DEPARTAMENTO')
        ->map(function ($rows, $department) {
            return (object) [
                'department' => $department,
                'total_carteros' => $rows->count(),
                'total_pendientes' => (int) $rows->sum(fn ($row) => (int) ($row->pendientes ?? 0)),
                'rows' => $rows->sortByDesc(fn ($row) => (int) ($row->pendientes ?? 0))->values(),
            ];
        })
        ->sortByDesc(fn ($item) => (int) ($item->total_pendientes ?? 0))
        ->values();
@endphp
<div class="alert alert-secondary mb-3">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div>
            <strong>Departamentos con envios pendientes ({{ data_get($carteroPendingSummary, 'scope') === 'nacional' ? 'Nivel nacional' : ($userCity !== '' ? $userCity : 'Tu regional') }}):</strong>
            {{ $carteroPendingDepartments->count() }} departamento(s) con paquetes en CARTERO.
        </div>
    </div>
    <div class="mt-2 d-flex flex-wrap">
        @foreach($carteroPendingDepartments as $index => $department)
            <button
                type="button"
                class="btn btn-light border mr-2 mb-2"
                data-toggle="modal"
                data-target="#carteroPendingDepartmentModal{{ $index }}"
            >
                {{ $department->department }}: {{ number_format((int) ($department->total_pendientes ?? 0)) }}
            </button>
        @endforeach
    </div>
</div>

@foreach($carteroPendingDepartments as $index => $department)
    <div class="modal fade" id="carteroPendingDepartmentModal{{ $index }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title">
                        {{ $department->department }} - carteros con pendientes
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border">
                        <strong>Total carteros:</strong> {{ number_format((int) ($department->total_carteros ?? 0)) }}
                        |
                        <strong>Total pendientes:</strong> {{ number_format((int) ($department->total_pendientes ?? 0)) }}
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Cartero</th>
                                    <th class="text-right">Pendientes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(($department->rows ?? collect()) as $row)
                                    <tr>
                                        <td>{{ $row->name }}</td>
                                        <td class="text-right">{{ number_format((int) ($row->pendientes ?? 0)) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@endforeach
@endif
