@if(($contratosPorRecoger ?? 0) > 0)
<div class="alert alert-danger d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3">
    <div>
        <strong>Tienes paquetes por recoger:</strong>
        {{ number_format((int) $contratosPorRecoger) }}
        en {{ $userCity !== '' ? $userCity : 'tu regional' }}.
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
        con mas de {{ (int) data_get($regionalPendingAlert, 'hours', 72) }} horas habiles en
        {{ data_get($regionalPendingAlert, 'regional', $userCity !== '' ? $userCity : 'tu regional') }}.
    </div>
    <div class="text-muted small mt-2 mt-md-0">
        Se descuentan sabados y domingos.
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
        Hay {{ number_format((int) data_get($pendingCn33Alert, 'count', 0)) }} CN-33 sin bitacora por mas de {{ (int) data_get($pendingCn33Alert, 'grace_hours', 24) }} horas.
        @if((string) data_get($pendingCn33Alert, 'regional', '') !== '')
        Solo se muestran registros de {{ data_get($pendingCn33Alert, 'regional') }}.
        @else
        Se muestran registros a nivel nacional.
        @endif
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
    <a href="{{ route('bitacoras.create') }}" class="btn btn-sm btn-outline-dark mt-2 mt-md-0">
        Registrar bitacora
    </a>
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
                                    <th>Primer registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(($department->rows ?? collect()) as $row)
                                    <tr>
                                        <td>{{ $row->numero_despacho ?? $row->cod_especial }}</td>
                                        <td class="text-right">{{ number_format((int) ($row->days_delay ?? 0)) }}</td>
                                        <td class="text-right">{{ number_format((float) ($row->peso_total ?? 0), 3) }}</td>
                                        <td class="text-right">{{ number_format((int) ($row->total_registros ?? 0)) }}</td>
                                        <td>{{ optional($row->first_created_at)->format('d/m/Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('bitacoras.create') }}" class="btn btn-danger">Registrar bitacora</a>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@endforeach
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
