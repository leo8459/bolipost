@extends('adminlte::page')

@section('title', 'Todos los paquetes')

@section('content_header')
    <div class="tp-page-heading">
        <div>
            <h1 class="mb-1">Todos los paquetes</h1>
            <p class="mb-0">Consulta y administra todos los servicios desde un solo lugar.</p>
        </div>
    </div>
@endsection

@section('content')
    <div class="todos-paquetes-page">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                Revisa los campos marcados antes de continuar.
            </div>
        @endif

        <div class="card tp-card mb-3">
            <div class="card-header tp-card-head">
                <div class="tp-card-heading">
                    <span class="tp-heading-icon"><i class="fas fa-search"></i></span>
                    <div>
                        <h3 class="card-title">Busqueda unificada</h3>
                        <div class="tp-muted">Busca en EMS, contratos, certificados, ordinarios y solicitudes.</div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('todos-paquetes.index') }}" class="row align-items-end">
                    <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                        <label class="small font-weight-bold">Buscar por cualquier campo</label>
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            class="form-control"
                            placeholder="Codigo, CN-33, empresa, destinatario, telefono, ciudad, estado..."
                        >
                    </div>
                    <div class="col-xl-2 col-lg-2 col-md-4 mb-3">
                        <label class="small font-weight-bold">Tipo</label>
                        <select name="type" class="form-control">
                            <option value="">Todos</option>
                            @foreach($types as $typeKey => $typeConfig)
                                <option value="{{ $typeKey }}" @selected($type === $typeKey)>{{ $typeConfig['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-2 col-md-4 mb-3">
                        <label class="small font-weight-bold">Estado</label>
                        <select name="estado_id" class="form-control">
                            <option value="0">Todos</option>
                            @foreach($estados as $estado)
                                <option value="{{ $estado->id }}" @selected((int) $estadoId === (int) $estado->id)>
                                    {{ $estado->nombre_estado }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-1 col-lg-1 col-md-2 col-6 mb-3">
                        <label for="peso_min" class="small font-weight-bold">Peso mín.</label>
                        <input
                            type="number"
                            name="peso_min"
                            id="peso_min"
                            value="{{ request()->filled('peso_min') ? request('peso_min') : '' }}"
                            class="form-control @error('peso_min') is-invalid @enderror"
                            min="0"
                            step="0.001"
                            placeholder="1"
                        >
                    </div>
                    <div class="col-xl-1 col-lg-1 col-md-2 col-6 mb-3">
                        <label for="peso_max" class="small font-weight-bold">Peso máx.</label>
                        <input
                            type="number"
                            name="peso_max"
                            id="peso_max"
                            value="{{ request()->filled('peso_max') ? request('peso_max') : '' }}"
                            class="form-control @error('peso_max') is-invalid @enderror"
                            min="0"
                            step="0.001"
                            placeholder="10000"
                        >
                    </div>
                    <div class="col-xl-2 col-lg-2 col-md-4 mb-3">
                        <div class="tp-actions">
                            <button type="submit" class="btn btn-primary">Buscar</button>
                            <a href="{{ route('todos-paquetes.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                        </div>
                    </div>
                </form>
                @error('peso_min')
                    <div class="text-danger small">{{ $message }}</div>
                @enderror
                @error('peso_max')
                    <div class="text-danger small">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="card tp-card">
            <div class="card-header tp-card-head tp-results-head">
                <div class="tp-card-heading">
                    <span class="tp-heading-icon"><i class="fas fa-boxes"></i></span>
                    <div>
                        <h3 class="card-title">Resultados</h3>
                        <div class="tp-muted">Paquetes encontrados con los filtros actuales</div>
                    </div>
                </div>
                <div class="tp-results-tools">
                    <span class="tp-total-pill"><strong>{{ number_format($paquetes->total()) }}</strong> registros</span>
                    @aclcan('print', null, 'todos-paquetes.index')
                        <a
                            href="{{ route('todos-paquetes.export.excel', request()->except(['page', 'create', 'edit_type', 'edit_id'])) }}"
                            class="btn btn-outline-success tp-create-btn"
                        >
                            <i class="fas fa-file-excel"></i>
                            <span>Exportar Excel</span>
                        </a>
                    @endaclcan
                    @aclcan('create', null, 'todos-paquetes.index')
                        <a
                            href="{{ route('todos-paquetes.index', array_merge(request()->except(['edit_type', 'edit_id']), ['create' => 1])) }}"
                            class="btn btn-success tp-create-btn"
                        >
                            <i class="fas fa-plus-circle"></i>
                            <span>Crear nuevo</span>
                        </a>
                    @endaclcan
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive tp-desktop-results">
                    <table class="table table-hover table-striped mb-0 tp-table">
                        <thead>
                            <tr>
                                <th>Envio</th>
                                <th>Trayecto</th>
                                <th>Empresa</th>
                                <th>Destinatario</th>
                                <th>Peso / Precio</th>
                                <th>Reporte</th>
                                <th>Estado</th>
                                <th>Actualizacion</th>
                                <th class="tp-actions-column">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($paquetes as $paquete)
                                <tr>
                                    <td class="tp-shipment-cell">
                                        <span class="tp-badge">{{ $paquete->tipo }}</span>
                                        <strong>{{ $paquete->codigo ?: 'SIN CODIGO' }}</strong>
                                        <small>CN-33: {{ $paquete->cod_especial ?: '-' }}</small>
                                    </td>
                                    <td>
                                        <div class="tp-route">
                                            <span>{{ $paquete->origen ?: '-' }}</span>
                                            <i class="fas fa-long-arrow-alt-right"></i>
                                            <span>{{ $paquete->destino ?: '-' }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $paquete->empresa ?: '-' }}</td>
                                    <td>
                                        <div class="tp-person">
                                            <strong>{{ $paquete->destinatario ?: '-' }}</strong>
                                            <small><i class="fas fa-phone-alt"></i> {{ $paquete->telefono ?: '-' }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="tp-metrics">
                                            <span><small>Peso</small>{{ $paquete->peso !== '' ? $paquete->peso : '-' }}</span>
                                            <span><small>Precio</small>{{ $paquete->precio !== '' ? $paquete->precio : '-' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if(!empty($paquete->salida_report_codigo))
                                            <a
                                                href="{{ route('todos-paquetes.reporte-salida', ['codigo' => $paquete->salida_report_codigo]) }}"
                                                class="btn btn-sm btn-warning tp-report-btn"
                                                title="Reimprimir reporte de salida {{ $paquete->salida_report_codigo }}"
                                                target="_blank"
                                            >
                                                <i class="fas fa-file-pdf"></i> Reimprimir
                                            </a>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary tp-report-btn tp-change-cartero-btn mt-1"
                                                title="Cambiar cartero de toda la lista {{ $paquete->salida_report_codigo }}"
                                                data-toggle="modal"
                                                data-target="#changeCarteroReportModal"
                                                data-report-code="{{ $paquete->salida_report_codigo }}"
                                                data-report-action="{{ route('todos-paquetes.reporte-salida.cambiar-cartero', ['codigo' => $paquete->salida_report_codigo]) }}"
                                            >
                                                <i class="fas fa-user-edit"></i> Cartero
                                            </button>
                                            <div class="small text-muted mt-1">{{ $paquete->salida_report_codigo }}</div>
                                        @else
                                            <span class="text-muted small">Sin reporte</span>
                                        @endif
                                    </td>
                                    <td class="tp-state-cell">
                                        <form method="POST" action="{{ route('todos-paquetes.estado', ['type' => $paquete->type_key, 'id' => $paquete->record_id]) }}">
                                            @csrf
                                            @method('PATCH')
                                            @foreach(request()->query() as $key => $value)
                                                @if(is_scalar($value))
                                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                                @endif
                                            @endforeach
                                            <select name="estado_id" class="form-control form-control-sm tp-state-select" onchange="this.form.submit()">
                                                @foreach($estados as $estado)
                                                    <option value="{{ $estado->id }}" @selected((int) $paquete->estado_id === (int) $estado->id)>
                                                        {{ $estado->nombre_estado }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="tp-updated">
                                            <strong>{{ $paquete->updated_at ? \Illuminate\Support\Carbon::parse($paquete->updated_at)->format('d/m/Y') : '-' }}</strong>
                                            <small>{{ $paquete->updated_at ? \Illuminate\Support\Carbon::parse($paquete->updated_at)->format('H:i') : '' }}</small>
                                            @if($paquete->justificacion)
                                                <span title="{{ $paquete->justificacion }}">{{ \Illuminate\Support\Str::limit($paquete->justificacion, 42) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-right tp-actions-column">
                                        <div class="tp-row-actions">
                                            @include('partials.rastreo-eventos-button', [
                                                'tipo' => $paquete->type_key,
                                                'codigo' => $paquete->codigo,
                                                'class' => 'btn btn-sm btn-outline-info',
                                            ])
                                            @if($paquete->type_key === 'contrato')
                                                <a
                                                    href="{{ route('todos-paquetes.guia', ['type' => $paquete->type_key, 'id' => $paquete->record_id]) }}"
                                                    class="btn btn-sm btn-outline-success"
                                                    title="Reimprimir guia de empresa"
                                                    target="_blank"
                                                >
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            @endif
                                            @aclcan('edit', null, 'todos-paquetes.index')
                                                <a
                                                    href="{{ route('todos-paquetes.index', array_merge(request()->except(['create']), ['edit_type' => $paquete->type_key, 'edit_id' => $paquete->record_id])) }}"
                                                    class="btn btn-sm btn-outline-primary tp-edit-btn"
                                                    title="Editar datos"
                                                >
                                                    <i class="fas fa-pencil-alt"></i>
                                                    <span>Editar</span>
                                                </a>
                                            @endaclcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">No hay paquetes con los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="tp-mobile-results">
                    @forelse($paquetes as $paquete)
                        <article class="tp-package-card">
                            <div class="tp-package-card-head">
                                <div>
                                    <span class="tp-badge">{{ $paquete->tipo }}</span>
                                    <h4>{{ $paquete->codigo ?: 'SIN CODIGO' }}</h4>
                                    <small>CN-33: {{ $paquete->cod_especial ?: '-' }}</small>
                                </div>
                                <span class="tp-card-date">
                                    {{ $paquete->updated_at ? \Illuminate\Support\Carbon::parse($paquete->updated_at)->format('d/m/Y') : '-' }}
                                </span>
                            </div>
                            <div class="tp-package-route">
                                <span><small>Origen</small>{{ $paquete->origen ?: '-' }}</span>
                                <i class="fas fa-arrow-right"></i>
                                <span><small>Destino</small>{{ $paquete->destino ?: '-' }}</span>
                            </div>
                            <div class="tp-package-grid">
                                <div><small>Empresa</small><strong>{{ $paquete->empresa ?: '-' }}</strong></div>
                                <div><small>Destinatario</small><strong>{{ $paquete->destinatario ?: '-' }}</strong></div>
                                <div><small>Telefono</small><strong>{{ $paquete->telefono ?: '-' }}</strong></div>
                                <div><small>Peso / Precio</small><strong>{{ $paquete->peso !== '' ? $paquete->peso : '-' }} / {{ $paquete->precio !== '' ? $paquete->precio : '-' }}</strong></div>
                            </div>
                            <form method="POST" action="{{ route('todos-paquetes.estado', ['type' => $paquete->type_key, 'id' => $paquete->record_id]) }}" class="tp-mobile-state">
                                @csrf
                                @method('PATCH')
                                @foreach(request()->query() as $key => $value)
                                    @if(is_scalar($value))
                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                    @endif
                                @endforeach
                                <label>Estado</label>
                                <select name="estado_id" class="form-control tp-state-select" onchange="this.form.submit()">
                                    @foreach($estados as $estado)
                                        <option value="{{ $estado->id }}" @selected((int) $paquete->estado_id === (int) $estado->id)>
                                            {{ $estado->nombre_estado }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                            @if($paquete->justificacion)
                                <div class="tp-mobile-note"><strong>Justificacion:</strong> {{ $paquete->justificacion }}</div>
                            @endif
                            <div class="tp-mobile-actions">
                                @include('partials.rastreo-eventos-button', [
                                    'tipo' => $paquete->type_key,
                                    'codigo' => $paquete->codigo,
                                    'class' => 'btn btn-outline-info',
                                ])
                                @if(!empty($paquete->salida_report_codigo))
                                    <a href="{{ route('todos-paquetes.reporte-salida', ['codigo' => $paquete->salida_report_codigo]) }}"
                                       class="btn btn-outline-warning" target="_blank">
                                        <i class="fas fa-file-pdf"></i> Reporte
                                    </a>
                                @endif
                                @if($paquete->type_key === 'contrato')
                                    <a href="{{ route('todos-paquetes.guia', ['type' => $paquete->type_key, 'id' => $paquete->record_id]) }}"
                                       class="btn btn-outline-success" target="_blank">
                                        <i class="fas fa-print"></i> Guia
                                    </a>
                                @endif
                                @aclcan('edit', null, 'todos-paquetes.index')
                                    <a href="{{ route('todos-paquetes.index', array_merge(request()->except(['create']), ['edit_type' => $paquete->type_key, 'edit_id' => $paquete->record_id])) }}"
                                       class="btn btn-primary">
                                        <i class="fas fa-pencil-alt"></i> Editar
                                    </a>
                                @endaclcan
                            </div>
                        </article>
                    @empty
                        <div class="text-center text-muted py-5">No hay paquetes con los filtros seleccionados.</div>
                    @endforelse
                </div>
            </div>
            <div class="card-footer tp-pagination">
                {{ $paquetes->links() }}
            </div>
        </div>
    </div>

    @aclcan('create', null, 'todos-paquetes.index')
        @include('todos_paquetes.partials.create-modal')
    @endaclcan

    @if($editing)
        <div class="modal fade show" id="editPackageModal" tabindex="-1" role="dialog" style="display:block;" aria-modal="true">
            <div class="modal-dialog modal-lg tp-edit-dialog" role="document">
                <div class="modal-content tp-modal">
                    <form method="POST" class="tp-edit-form" action="{{ route('todos-paquetes.datos', ['type' => $editing['type'], 'id' => $editing['id']]) }}">
                        @csrf
                        @method('PUT')
                        @foreach(request()->except(['edit_type', 'edit_id']) as $key => $value)
                            @if(is_scalar($value))
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title">Editar {{ $editing['label'] }}</h5>
                                <div class="small text-white-50">Los cambios actualizaran solo los datos del paquete.</div>
                            </div>
                            <a href="{{ route('todos-paquetes.index', request()->except(['edit_type', 'edit_id'])) }}" class="close text-white" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </a>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                @foreach($editing['fields'] as $field => $label)
                                    <div class="col-md-6 mb-3">
                                        <label class="small font-weight-bold">{{ $label }}</label>
                                        @if(in_array($field, ['origen', 'ciudad', 'cuidad', 'destino'], true))
                                            <select name="{{ $field }}" class="form-control @error($field) is-invalid @enderror">
                                                <option value="">Seleccione...</option>
                                                @foreach($departamentos as $departamento)
                                                    <option value="{{ $departamento }}" @selected(old($field, $editing['values'][$field] ?? '') === $departamento)>
                                                        {{ $departamento }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @elseif(in_array($field, ['observacion', 'observaciones', 'direccion', 'direccion_d', 'referencia', 'justificacion'], true))
                                            <textarea name="{{ $field }}" rows="3" class="form-control @error($field) is-invalid @enderror">{{ old($field, $editing['values'][$field] ?? '') }}</textarea>
                                        @else
                                            <input
                                                type="{{ in_array($field, $editing['numeric'], true) ? 'number' : 'text' }}"
                                                step="{{ in_array($field, $editing['numeric'], true) ? '0.001' : '' }}"
                                                name="{{ $field }}"
                                                value="{{ old($field, $editing['values'][$field] ?? '') }}"
                                                class="form-control @error($field) is-invalid @enderror"
                                            >
                                        @endif
                                        @error($field)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="{{ route('todos-paquetes.index', request()->except(['edit_type', 'edit_id'])) }}" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    <div class="modal fade" id="changeCarteroReportModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content tp-modal">
                <form method="POST" id="changeCarteroReportForm" action="#">
                    @csrf
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">Cambiar cartero</h5>
                            <div class="small text-white-50">
                                Se cambiara el cartero de todos los paquetes del reporte <strong id="changeCarteroReportCode">-</strong>.
                            </div>
                        </div>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning small">
                            Esta accion usa el codigo unico del reporte de salida y cambia toda la lista completa.
                        </div>
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Nuevo cartero</label>
                            <select name="user_id" class="form-control" required @disabled($carteros->isEmpty())>
                                <option value="">Seleccione cartero...</option>
                                @foreach($carteros as $cartero)
                                    <option value="{{ $cartero->id }}">{{ $cartero->name }} - {{ $cartero->ciudad ?: 'SIN CIUDAD' }}</option>
                                @endforeach
                            </select>
                            @if($carteros->isEmpty())
                                <small class="form-text text-danger">No hay carteros disponibles en tu departamento.</small>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" @disabled($carteros->isEmpty())>
                            Cambiar cartero
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .tp-page-heading h1 {
            color:#17233c;
            font-size:1.85rem;
            font-weight:800;
            letter-spacing:-.02em;
        }
        .tp-page-heading p {
            color:#64748b;
            font-size:.93rem;
        }
        .todos-paquetes-page {
            background:#f1f5f9;
            border:1px solid #dfe7f1;
            border-radius:16px;
            padding:16px;
        }
        .tp-card {
            border:0;
            border-radius:14px;
            box-shadow:0 10px 28px rgba(15, 23, 42, .08);
            overflow:hidden;
        }
        .tp-card .card-header,
        .tp-modal .modal-header {
            background:#20539A;
            color:#fff;
            border:0;
        }
        .tp-card-head {
            min-height:72px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            padding:14px 20px;
        }
        .tp-card-heading {
            min-width:0;
            display:flex;
            align-items:center;
            gap:12px;
        }
        .tp-heading-icon {
            width:40px;
            height:40px;
            flex:0 0 40px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius:11px;
            background:rgba(255,255,255,.14);
            font-size:1rem;
        }
        .tp-card .card-title {
            float:none;
            margin:0 0 3px;
            font-size:1.05rem;
            line-height:1.2;
            font-weight:800;
        }
        .tp-muted {
            color:rgba(255,255,255,.78);
            font-size:.85rem;
            line-height:1.35;
        }
        .tp-results-tools {
            display:flex;
            align-items:center;
            gap:10px;
        }
        .tp-total-pill {
            display:inline-flex;
            align-items:baseline;
            gap:5px;
            padding:8px 12px;
            border:1px solid rgba(255,255,255,.22);
            border-radius:999px;
            color:#dbeafe;
            font-size:.78rem;
            white-space:nowrap;
        }
        .tp-total-pill strong {
            color:#fff;
            font-size:.95rem;
        }
        .tp-actions {
            display:flex;
            gap:8px;
        }
        .tp-actions .btn {
            flex:1 1 0;
        }
        .tp-create-btn {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:7px;
            font-weight:800;
            white-space:nowrap;
        }
        .tp-table thead th {
            background:#eaf1fb;
            color:#1f3f78;
            border-top:0;
            border-bottom:1px solid #d8e2f0;
            font-family:inherit;
            font-size:.7rem;
            font-weight:800;
            letter-spacing:.045em;
            padding:13px 12px;
            text-transform:uppercase;
            white-space:nowrap;
        }
        .tp-table td {
            border-color:#e7edf5;
            color:#26344d;
            font-family:inherit;
            vertical-align:middle;
            font-size:.82rem;
            padding:12px;
        }
        .tp-table tbody tr:hover td {
            background:#f4f8ff;
        }
        .tp-badge {
            display:inline-flex;
            border-radius:999px;
            padding:4px 9px;
            background:#eef4ff;
            color:#20539A;
            font-weight:800;
            font-size:.72rem;
        }
        .tp-shipment-cell {
            min-width:185px;
        }
        .tp-shipment-cell strong,
        .tp-shipment-cell small {
            display:block;
        }
        .tp-shipment-cell strong {
            margin-top:6px;
            color:#17233c;
            font-size:.84rem;
        }
        .tp-shipment-cell small {
            margin-top:3px;
            color:#7b879a;
        }
        .tp-route {
            min-width:150px;
            display:flex;
            align-items:center;
            gap:7px;
            font-weight:700;
            white-space:nowrap;
        }
        .tp-route i {
            color:#4f79b7;
        }
        .tp-person {
            min-width:150px;
            max-width:220px;
        }
        .tp-person strong,
        .tp-person small {
            display:block;
        }
        .tp-person strong {
            color:#26344d;
            line-height:1.35;
        }
        .tp-person small {
            margin-top:4px;
            color:#64748b;
        }
        .tp-metrics {
            min-width:105px;
            display:flex;
            gap:14px;
        }
        .tp-metrics span {
            color:#17233c;
            font-weight:800;
        }
        .tp-metrics small {
            display:block;
            color:#8793a5;
            font-size:.65rem;
            font-weight:700;
            text-transform:uppercase;
        }
        .tp-state-select {
            min-width:145px;
            font-weight:700;
        }
        .tp-state-cell {
            min-width:165px;
        }
        .tp-updated {
            min-width:105px;
        }
        .tp-updated strong,
        .tp-updated small,
        .tp-updated span {
            display:block;
        }
        .tp-updated small,
        .tp-updated span {
            color:#7b879a;
            margin-top:2px;
        }
        .tp-row-actions {
            display:inline-flex;
            align-items:center;
            gap:6px;
            white-space:nowrap;
        }
        .tp-edit-btn {
            display:inline-flex;
            align-items:center;
            gap:5px;
        }
        .tp-actions-column {
            position:sticky;
            right:0;
            z-index:1;
            min-width:120px;
            background:#fff;
            box-shadow:-8px 0 14px rgba(15,23,42,.04);
        }
        .tp-table thead .tp-actions-column {
            z-index:2;
            background:#eaf1fb;
        }
        .tp-table.table-striped tbody tr:nth-of-type(odd) .tp-actions-column {
            background:#f6f7f9;
        }
        .tp-table tbody tr:hover .tp-actions-column {
            background:#f4f8ff;
        }
        .tp-report-btn {
            color:#10233f;
            font-weight:800;
            display:inline-flex;
            align-items:center;
            gap:5px;
        }
        .tp-report-btn:hover {
            color:#10233f;
        }
        .tp-change-cartero-btn {
            background:#fff;
            color:#20539A;
            border-color:#20539A;
        }
        .tp-change-cartero-btn:hover {
            background:#eef4ff;
            color:#173f75;
        }
        .tp-mobile-results {
            display:none;
        }
        .tp-pagination {
            display:flex;
            align-items:center;
            justify-content:flex-end;
            min-height:64px;
            background:#fff;
            border-top:1px solid #e7edf5;
        }
        .tp-pagination nav {
            margin-left:auto;
        }
        .tp-service-picker {
            max-width:620px;
            margin:0 auto 22px;
            padding:16px;
            background:#eef4ff;
            border:1px solid #cbdaf5;
            border-radius:10px;
        }
        .tp-create-prompt {
            min-height:180px;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            gap:12px;
            color:#64748b;
            background:#f8fafc;
            border:2px dashed #cbd5e1;
            border-radius:10px;
            font-weight:700;
        }
        .tp-create-prompt i {
            font-size:2rem;
            color:#20539A;
        }
        .tp-form-heading {
            display:flex;
            align-items:center;
            gap:8px;
            color:#20539A;
            font-size:1.05rem;
            font-weight:800;
            border-bottom:1px solid #dbe5f4;
            padding-bottom:10px;
            margin-bottom:18px;
        }
        #createPackageModal,
        #editPackageModal {
            overflow:hidden;
            padding-right:0 !important;
        }
        .tp-create-dialog,
        .tp-edit-dialog {
            height:calc(100vh - 24px);
            height:calc(100dvh - 24px);
            margin:12px auto;
            display:flex;
            align-items:stretch;
        }
        .tp-create-dialog .modal-content,
        .tp-edit-dialog .modal-content {
            width:100%;
            max-height:100%;
            min-height:0;
            overflow:hidden;
        }
        .tp-create-form,
        .tp-edit-form {
            height:100%;
            min-height:0;
            display:flex;
            flex-direction:column;
        }
        .tp-create-form .modal-header,
        .tp-create-form .modal-footer,
        .tp-edit-form .modal-header,
        .tp-edit-form .modal-footer {
            flex:0 0 auto;
            position:relative;
            z-index:2;
        }
        .tp-create-form .modal-body,
        .tp-edit-form .modal-body {
            flex:1 1 auto;
            min-height:0;
            overflow-y:auto;
            overscroll-behavior:contain;
            -webkit-overflow-scrolling:touch;
        }
        .tp-create-form .modal-footer,
        .tp-edit-form .modal-footer {
            background:#fff;
            box-shadow:0 -8px 20px rgba(15, 23, 42, .08);
        }
        @media (max-width: 767.98px) {
            .content-header {
                padding-bottom:8px;
            }
            .tp-page-heading h1 {
                font-size:1.45rem;
            }
            .tp-page-heading p {
                font-size:.83rem;
            }
            .todos-paquetes-page {
                padding:8px;
                border-radius:8px;
            }
            .tp-card-head,
            .tp-results-head {
                min-height:auto;
                align-items:stretch;
                flex-direction:column;
                gap:14px;
                padding:14px;
            }
            .tp-heading-icon {
                width:36px;
                height:36px;
                flex-basis:36px;
            }
            .tp-results-tools {
                display:grid;
                grid-template-columns:1fr;
                width:100%;
            }
            .tp-total-pill {
                justify-content:center;
            }
            .tp-create-btn {
                width:100%;
                min-height:44px;
            }
            .tp-actions {
                flex-direction:column;
            }
            .tp-desktop-results {
                display:none;
            }
            .tp-mobile-results {
                display:block;
                padding:10px;
                background:#edf2f7;
            }
            .tp-package-card {
                margin-bottom:10px;
                padding:14px;
                background:#fff;
                border:1px solid #dae3ee;
                border-radius:12px;
                box-shadow:0 5px 14px rgba(15,23,42,.05);
            }
            .tp-package-card:last-child {
                margin-bottom:0;
            }
            .tp-package-card-head {
                display:flex;
                align-items:flex-start;
                justify-content:space-between;
                gap:12px;
                padding-bottom:12px;
                border-bottom:1px solid #e8edf4;
            }
            .tp-package-card-head h4 {
                margin:6px 0 2px;
                color:#17233c;
                font-size:.96rem;
                font-weight:800;
                overflow-wrap:anywhere;
            }
            .tp-package-card-head small,
            .tp-card-date {
                color:#7b879a;
                font-size:.72rem;
            }
            .tp-card-date {
                white-space:nowrap;
            }
            .tp-package-route {
                display:grid;
                grid-template-columns:1fr auto 1fr;
                align-items:center;
                gap:10px;
                margin:12px 0;
                padding:10px;
                background:#eef4ff;
                border-radius:9px;
                text-align:center;
            }
            .tp-package-route span {
                color:#1f3f78;
                font-weight:800;
            }
            .tp-package-route small,
            .tp-package-grid small {
                display:block;
                margin-bottom:2px;
                color:#7b879a;
                font-size:.65rem;
                font-weight:700;
                text-transform:uppercase;
            }
            .tp-package-route i {
                color:#4f79b7;
            }
            .tp-package-grid {
                display:grid;
                grid-template-columns:repeat(2, minmax(0, 1fr));
                gap:12px;
                margin-bottom:14px;
            }
            .tp-package-grid strong {
                display:block;
                color:#26344d;
                font-size:.8rem;
                line-height:1.3;
                overflow-wrap:anywhere;
            }
            .tp-mobile-state {
                margin-bottom:12px;
                padding-top:12px;
                border-top:1px solid #e8edf4;
            }
            .tp-mobile-state label {
                display:block;
                margin-bottom:5px;
                color:#42526b;
                font-size:.72rem;
                font-weight:800;
                text-transform:uppercase;
            }
            .tp-mobile-state .tp-state-select {
                width:100%;
                min-width:0;
                min-height:42px;
            }
            .tp-mobile-note {
                margin-bottom:12px;
                padding:9px 10px;
                border-left:3px solid #f59e0b;
                background:#fffbeb;
                color:#7c5b14;
                border-radius:5px;
                font-size:.75rem;
            }
            .tp-mobile-actions {
                display:grid;
                grid-template-columns:repeat(2, minmax(0, 1fr));
                gap:8px;
            }
            .tp-mobile-actions .btn {
                min-height:40px;
                display:inline-flex;
                align-items:center;
                justify-content:center;
                gap:5px;
                margin:0;
                font-size:.78rem;
                white-space:normal;
            }
            .tp-pagination {
                justify-content:center;
                overflow-x:auto;
                padding:10px;
            }
            .tp-pagination nav {
                margin:0 auto;
            }
            .tp-modal .modal-header {
                padding:12px 14px;
            }
            .tp-modal .modal-body {
                padding:14px;
            }
            .tp-modal .modal-footer {
                display:grid;
                grid-template-columns:1fr;
                gap:8px;
                padding:12px 14px;
            }
            .tp-modal .modal-footer .btn {
                width:100%;
                min-height:44px;
                margin:0;
            }
            .modal-dialog {
                margin:8px;
                max-width:calc(100% - 16px);
            }
            .tp-create-dialog,
            .tp-edit-dialog {
                width:100%;
                max-width:none;
                height:100vh;
                height:100dvh;
                margin:0;
            }
            .tp-create-dialog .modal-content,
            .tp-edit-dialog .modal-content {
                border:0;
                border-radius:0;
            }
            .tp-service-picker {
                padding:12px;
                margin-bottom:14px;
            }
        }
    </style>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const packageType = document.getElementById('packageType');
            const createForms = Array.from(document.querySelectorAll('[data-package-form]'));
            const createPrompt = document.getElementById('createFormPrompt');
            const createSubmit = document.getElementById('createPackageSubmit');

            const updateCreateForm = function () {
                const selectedType = packageType ? packageType.value : '';

                createForms.forEach(function (formSection) {
                    const isActive = formSection.getAttribute('data-package-form') === selectedType;
                    formSection.classList.toggle('d-none', !isActive);
                    formSection.querySelectorAll('[data-form-control]').forEach(function (control) {
                        control.disabled = !isActive;
                    });
                });

                if (createPrompt) {
                    createPrompt.classList.toggle('d-none', selectedType !== '');
                }
                if (createSubmit) {
                    createSubmit.disabled = selectedType === '';
                }
            };

            if (packageType) {
                packageType.addEventListener('change', updateCreateForm);
                updateCreateForm();
            }

            @if($showCreateModal || $editing)
                document.body.classList.add('modal-open');
            @endif

            $('#changeCarteroReportModal').on('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const reportCode = button ? button.getAttribute('data-report-code') : '';
                const action = button ? button.getAttribute('data-report-action') : '#';

                const form = document.getElementById('changeCarteroReportForm');
                const codeLabel = document.getElementById('changeCarteroReportCode');

                if (form) {
                    form.setAttribute('action', action || '#');
                }
                if (codeLabel) {
                    codeLabel.textContent = reportCode || '-';
                }
            });
        });
    </script>
@endsection
