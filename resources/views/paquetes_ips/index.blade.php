@extends('adminlte::page')

@section('title', 'Paquetes IPS')

@section('content_header')
    <div class="ips-page-heading">
        <div>
            <h1 class="mb-1">Paquetes IPS</h1>
            <p class="mb-0">Consulta los paquetes internacionales registrados en el sistema postal.</p>
        </div>
    </div>
@endsection

@section('content')
    <div class="ips-page">
        @if($error)
            <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span>{{ $error }}</span>
            </div>
        @endif

        <div class="card ips-card mb-3">
            <div class="card-header ips-card-head">
                <div class="ips-card-heading">
                    <span class="ips-heading-icon"><i class="fas fa-search"></i></span>
                    <div>
                        <h3 class="card-title">Buscar paquetes</h3>
                        <div class="ips-muted">Filtra por código, otro dato disponible en IPS o fecha de registro.</div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('paquetes-ips.index') }}" class="row align-items-end">
                    <div class="col-lg-4 col-md-6 mb-3">
                        <label for="ips-search" class="small font-weight-bold">Buscar</label>
                        <input
                            id="ips-search"
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            class="form-control"
                            maxlength="100"
                            placeholder="Ej.: CP556326695CN"
                        >
                    </div>
                    <div class="col-lg-2 col-md-3 mb-3">
                        <label for="ips-fecha-desde" class="small font-weight-bold">Desde</label>
                        <input
                            id="ips-fecha-desde"
                            type="date"
                            name="fecha_desde"
                            value="{{ $fechaDesde }}"
                            class="form-control {{ session('errors')?->has('fecha_desde') ? 'is-invalid' : '' }}"
                            max="{{ now()->toDateString() }}"
                        >
                        @if(session('errors')?->has('fecha_desde'))
                            <div class="invalid-feedback">Selecciona ambas fechas del rango.</div>
                        @endif
                    </div>
                    <div class="col-lg-2 col-md-3 mb-3">
                        <label for="ips-fecha-hasta" class="small font-weight-bold">Hasta</label>
                        <input
                            id="ips-fecha-hasta"
                            type="date"
                            name="fecha_hasta"
                            value="{{ $fechaHasta }}"
                            class="form-control {{ session('errors')?->has('fecha_hasta') ? 'is-invalid' : '' }}"
                            min="{{ $fechaDesde }}"
                            max="{{ now()->toDateString() }}"
                        >
                        @if(session('errors')?->has('fecha_hasta'))
                            <div class="invalid-feedback">La fecha final debe ser igual o posterior a la inicial.</div>
                        @endif
                    </div>
                    <div class="col-lg-1 col-md-3 mb-3">
                        <label for="ips-per-page" class="small font-weight-bold">Por página</label>
                        <select id="ips-per-page" name="per_page" class="form-control">
                            @foreach($perPageOptions as $option)
                                <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-5 mb-3">
                        <div class="ips-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search mr-1"></i> Buscar
                            </button>
                            <a href="{{ route('paquetes-ips.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card ips-card">
            <div class="card-header ips-card-head ips-results-head">
                <div class="ips-card-heading">
                    <span class="ips-heading-icon"><i class="fas fa-globe-americas"></i></span>
                    <div>
                        <h3 class="card-title">Listado de paquetes</h3>
                        <div class="ips-muted">Información obtenida directamente desde IPS.</div>
                    </div>
                </div>
                <div class="ips-results-tools">
                    <span class="ips-total-pill">
                        <strong>{{ number_format($packages->count()) }}</strong> en esta página
                    </span>
                    <a href="{{ request()->fullUrl() }}" class="btn btn-outline-light ips-refresh-btn" title="Actualizar listado">
                        <i class="fas fa-sync-alt"></i>
                        <span>Actualizar</span>
                    </a>
                    <span class="ips-total-pill ips-general-total">
                        <i class="fas fa-boxes"></i>
                        <span>Total filtrado:</span>
                        @if($totalPackages !== null)
                            <strong>{{ number_format($totalPackages) }}</strong>
                        @else
                            <strong title="La API de IPS no devolvió el total del resultado">No disponible</strong>
                        @endif
                    </span>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive ips-desktop-results">
                    <table class="table table-hover table-striped mb-0 ips-table">
                        <thead>
                            <tr>
                                <th>Identificador</th>
                                <th>Fecha de registro</th>
                                <th>Código</th>
                                <th>Servicio</th>
                                <th>Peso</th>
                                <th>Clase de correo</th>
                                <th>Contenido</th>
                                <th>Estado postal</th>
                                <th>Trayecto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($packages as $package)
                                @php
                                    $originCode = data_get($package, 'origen.codigo');
                                    $originName = data_get($package, 'origen.nombre');
                                    $destinationCode = data_get($package, 'destino.codigo');
                                    $destinationName = data_get($package, 'destino.nombre');
                                @endphp
                                <tr>
                                    <td><span class="ips-id">#{{ data_get($package, 'mailitm_pid', '-') }}</span></td>
                                    <td class="text-nowrap">
                                        @if(data_get($package, 'fecha_registro'))
                                            {{ \Illuminate\Support\Carbon::parse(data_get($package, 'fecha_registro'))->format('d/m/Y H:i') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="ips-code-cell">
                                        <strong>{{ data_get($package, 'codigo', '-') ?: '-' }}</strong>
                                        <small>S10: {{ data_get($package, 'codigo_s10', '-') ?: '-' }}</small>
                                    </td>
                                    <td><span class="ips-service-badge">{{ data_get($package, 'tipo_servicio', '-') ?: '-' }}</span></td>
                                    <td class="text-nowrap">
                                        @if(is_numeric(data_get($package, 'peso')))
                                            <strong>{{ number_format((float) data_get($package, 'peso'), 3, ',', '.') }}</strong> kg
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ data_get($package, 'clase_correo', '-') ?: '-' }}</td>
                                    <td>{{ data_get($package, 'contenido', '-') ?: '-' }}</td>
                                    <td><span class="ips-state-badge">{{ data_get($package, 'estado_postal', '-') ?: '-' }}</span></td>
                                    <td>
                                        <div class="ips-route">
                                            <span title="{{ $originName ?: '-' }}">
                                                <small>Origen</small>
                                                <strong>{{ $originCode ?: '-' }}</strong>
                                                <em>{{ $originName ?: '-' }}</em>
                                            </span>
                                            <i class="fas fa-long-arrow-alt-right"></i>
                                            <span title="{{ $destinationName ?: '-' }}">
                                                <small>Destino</small>
                                                <strong>{{ $destinationCode ?: '-' }}</strong>
                                                <em>{{ $destinationName ?: '-' }}</em>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        @if($error)
                                            No se pudieron cargar los paquetes.
                                        @elseif($search !== '')
                                            No se encontraron paquetes para “{{ $search }}”.
                                        @elseif($fechaDesde && $fechaHasta)
                                            No se encontraron paquetes entre el {{ \Illuminate\Support\Carbon::parse($fechaDesde)->format('d/m/Y') }} y el {{ \Illuminate\Support\Carbon::parse($fechaHasta)->format('d/m/Y') }}.
                                        @else
                                            No hay paquetes para mostrar en esta página.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="ips-mobile-results">
                    @forelse($packages as $package)
                        <article class="ips-package-card">
                            <div class="ips-package-card-head">
                                <div>
                                    <span class="ips-service-badge">{{ data_get($package, 'tipo_servicio', '-') ?: '-' }}</span>
                                    <h4>{{ data_get($package, 'codigo', '-') ?: '-' }}</h4>
                                    <small>S10: {{ data_get($package, 'codigo_s10', '-') ?: '-' }}</small>
                                </div>
                                <span class="ips-id">#{{ data_get($package, 'mailitm_pid', '-') }}</span>
                            </div>
                            <div class="ips-package-route">
                                <span><small>Origen</small><strong>{{ data_get($package, 'origen.codigo', '-') ?: '-' }}</strong>{{ data_get($package, 'origen.nombre', '-') ?: '-' }}</span>
                                <i class="fas fa-arrow-right"></i>
                                <span><small>Destino</small><strong>{{ data_get($package, 'destino.codigo', '-') ?: '-' }}</strong>{{ data_get($package, 'destino.nombre', '-') ?: '-' }}</span>
                            </div>
                            <div class="ips-package-grid">
                                <div><small>Fecha de registro</small><strong>{{ data_get($package, 'fecha_registro') ? \Illuminate\Support\Carbon::parse(data_get($package, 'fecha_registro'))->format('d/m/Y H:i') : '-' }}</strong></div>
                                <div><small>Peso</small><strong>{{ is_numeric(data_get($package, 'peso')) ? number_format((float) data_get($package, 'peso'), 3, ',', '.').' kg' : '-' }}</strong></div>
                                <div><small>Estado postal</small><strong>{{ data_get($package, 'estado_postal', '-') ?: '-' }}</strong></div>
                                <div><small>Clase de correo</small><strong>{{ data_get($package, 'clase_correo', '-') ?: '-' }}</strong></div>
                                <div><small>Contenido</small><strong>{{ data_get($package, 'contenido', '-') ?: '-' }}</strong></div>
                            </div>
                        </article>
                    @empty
                        <div class="text-center text-muted py-5">No hay paquetes para mostrar.</div>
                    @endforelse
                </div>
            </div>

            @if($packages->hasPages())
                <div class="card-footer ips-pagination">
                    <span>Página {{ $packages->currentPage() }}</span>
                    {{ $packages->onEachSide(1)->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

@section('css')
    <style>
        .ips-page-heading h1 { color:#17233c; font-size:1.85rem; font-weight:800; letter-spacing:-.02em; }
        .ips-page-heading p { color:#64748b; font-size:.93rem; }
        .ips-page { background:#f1f5f9; border:1px solid #dfe7f1; border-radius:16px; padding:16px; }
        .ips-card { border:0; border-radius:14px; box-shadow:0 10px 28px rgba(15,23,42,.08); overflow:hidden; }
        .ips-card-head { min-height:72px; display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px 20px; background:#20539A; color:#fff; border:0; }
        .ips-card-heading { min-width:0; display:flex; align-items:center; gap:12px; }
        .ips-heading-icon { width:40px; height:40px; flex:0 0 40px; display:inline-flex; align-items:center; justify-content:center; border-radius:11px; background:rgba(255,255,255,.14); }
        .ips-card .card-title { float:none; margin:0 0 3px; font-size:1.05rem; line-height:1.2; font-weight:800; }
        .ips-muted { color:rgba(255,255,255,.78); font-size:.85rem; }
        .ips-actions { display:flex; gap:8px; }
        .ips-actions .btn { flex:1 1 0; }
        .ips-results-tools { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .ips-total-pill { display:inline-flex; align-items:baseline; gap:5px; padding:8px 12px; border:1px solid rgba(255,255,255,.22); border-radius:999px; color:#dbeafe; font-size:.78rem; white-space:nowrap; }
        .ips-total-pill strong { color:#fff; font-size:.95rem; }
        .ips-general-total { border-color:rgba(255,255,255,.38); background:rgba(15,37,75,.22); }
        .ips-general-total > span { color:#fff; font-weight:700; }
        .ips-refresh-btn { display:inline-flex; align-items:center; gap:7px; font-weight:700; }
        .ips-table thead th { background:#eaf1fb; color:#1f3f78; border-top:0; border-bottom:1px solid #d8e2f0; font-size:.7rem; font-weight:800; letter-spacing:.045em; padding:13px 12px; text-transform:uppercase; white-space:nowrap; }
        .ips-table td { border-color:#e7edf5; color:#26344d; vertical-align:middle; font-size:.82rem; padding:12px; }
        .ips-table tbody tr:hover td { background:#f4f8ff; }
        .ips-id { color:#52647e; font-weight:800; white-space:nowrap; }
        .ips-code-cell { min-width:165px; }
        .ips-code-cell strong, .ips-code-cell small { display:block; }
        .ips-code-cell strong { color:#17233c; }
        .ips-code-cell small { margin-top:4px; color:#7b879a; }
        .ips-service-badge, .ips-state-badge { display:inline-flex; border-radius:999px; padding:5px 10px; font-size:.72rem; font-weight:800; white-space:nowrap; }
        .ips-service-badge { background:#eef4ff; color:#20539A; text-transform:capitalize; }
        .ips-state-badge { background:#ecfdf3; color:#18794e; }
        .ips-route { min-width:300px; display:grid; grid-template-columns:minmax(110px,1fr) 20px minmax(110px,1fr); align-items:center; gap:8px; }
        .ips-route > span { min-width:0; }
        .ips-route small, .ips-route strong, .ips-route em { display:block; }
        .ips-route small { color:#8290a5; font-size:.66rem; font-weight:800; text-transform:uppercase; }
        .ips-route strong { color:#20539A; font-size:.8rem; }
        .ips-route em { overflow:hidden; color:#4f5f76; font-size:.74rem; font-style:normal; text-overflow:ellipsis; white-space:nowrap; }
        .ips-route i { color:#4f79b7; text-align:center; }
        .ips-pagination { display:flex; align-items:center; justify-content:space-between; gap:12px; color:#64748b; }
        .ips-pagination nav { margin-left:auto; }
        .ips-pagination .pagination { margin-bottom:0; }
        .ips-mobile-results { display:none; }
        @media (max-width: 767.98px) {
            .ips-page { margin:0 -7.5px; padding:10px; border-radius:12px; }
            .ips-card-head, .ips-results-head { align-items:flex-start; flex-direction:column; }
            .ips-results-tools { width:100%; justify-content:space-between; }
            .ips-general-total { width:100%; justify-content:center; }
            .ips-refresh-btn span { display:none; }
            .ips-desktop-results { display:none; }
            .ips-mobile-results { display:block; padding:12px; }
            .ips-package-card { margin-bottom:12px; padding:14px; border:1px solid #dce5f1; border-radius:13px; background:#fff; box-shadow:0 5px 16px rgba(15,23,42,.06); }
            .ips-package-card:last-child { margin-bottom:0; }
            .ips-package-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
            .ips-package-card-head h4 { margin:8px 0 2px; color:#17233c; font-size:1rem; font-weight:800; }
            .ips-package-card-head small { color:#7b879a; }
            .ips-package-route { display:grid; grid-template-columns:1fr 20px 1fr; align-items:center; gap:7px; margin:14px 0; padding:12px; border-radius:11px; background:#f5f8fc; }
            .ips-package-route span { min-width:0; color:#52647e; font-size:.72rem; overflow-wrap:anywhere; }
            .ips-package-route small, .ips-package-route strong { display:block; }
            .ips-package-route small { color:#8290a5; font-size:.63rem; font-weight:800; text-transform:uppercase; }
            .ips-package-route strong { margin:2px 0; color:#20539A; font-size:.85rem; }
            .ips-package-route i { color:#4f79b7; text-align:center; }
            .ips-package-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
            .ips-package-grid div { min-width:0; }
            .ips-package-grid small, .ips-package-grid strong { display:block; }
            .ips-package-grid small { color:#8290a5; font-size:.65rem; font-weight:800; text-transform:uppercase; }
            .ips-package-grid strong { margin-top:3px; color:#26344d; font-size:.78rem; overflow-wrap:anywhere; }
            .ips-pagination { align-items:stretch; flex-direction:column; }
            .ips-pagination nav { margin-left:0; }
        }
    </style>
@endsection
