@extends('adminlte::page')

@section('title', 'Todos los paquetes')

@section('content_header')
    <h1 class="mb-0">Todos los paquetes</h1>
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
            <div class="card-header">
                <div>
                    <h3 class="card-title mb-1">Busqueda unificada</h3>
                    <div class="tp-muted">EMS, contratos, certificados, ordinarios y solicitudes.</div>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('todos-paquetes.index') }}" class="row align-items-end">
                    <div class="col-lg-5 col-md-6 mb-2">
                        <label class="small font-weight-bold">Buscar por cualquier campo</label>
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            class="form-control"
                            placeholder="Codigo, CN-33, destinatario, telefono, ciudad, estado..."
                        >
                    </div>
                    <div class="col-lg-2 col-md-3 mb-2">
                        <label class="small font-weight-bold">Tipo</label>
                        <select name="type" class="form-control">
                            <option value="">Todos</option>
                            @foreach($types as $typeKey => $typeConfig)
                                <option value="{{ $typeKey }}" @selected($type === $typeKey)>{{ $typeConfig['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-3 mb-2">
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
                    <div class="col-lg-2 mb-2">
                        <div class="tp-actions">
                            <button type="submit" class="btn btn-primary">Buscar</button>
                            <a href="{{ route('todos-paquetes.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card tp-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h3 class="card-title mb-1">Resultados</h3>
                    <div class="tp-muted">Total filtrado: {{ $paquetes->total() }}</div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 tp-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Codigo</th>
                                <th>CN-33</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Destinatario</th>
                                <th>Telefono</th>
                                <th>Peso</th>
                                <th>Precio</th>
                                <th>Estado</th>
                                <th>Actualizado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($paquetes as $paquete)
                                <tr>
                                    <td><span class="tp-badge">{{ $paquete->tipo }}</span></td>
                                    <td class="font-weight-bold">{{ $paquete->codigo ?: '-' }}</td>
                                    <td>{{ $paquete->cod_especial ?: '-' }}</td>
                                    <td>{{ $paquete->origen ?: '-' }}</td>
                                    <td>{{ $paquete->destino ?: '-' }}</td>
                                    <td>{{ $paquete->destinatario ?: '-' }}</td>
                                    <td>{{ $paquete->telefono ?: '-' }}</td>
                                    <td>{{ $paquete->peso !== '' ? $paquete->peso : '-' }}</td>
                                    <td>{{ $paquete->precio !== '' ? $paquete->precio : '-' }}</td>
                                    <td style="min-width: 190px;">
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
                                    <td>{{ $paquete->updated_at ? \Illuminate\Support\Carbon::parse($paquete->updated_at)->format('d/m/Y H:i') : '-' }}</td>
                                    <td class="text-right">
                                        <a
                                            href="{{ route('todos-paquetes.index', array_merge(request()->query(), ['edit_type' => $paquete->type_key, 'edit_id' => $paquete->record_id])) }}"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Editar datos"
                                        >
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-4">No hay paquetes con los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $paquetes->links() }}
            </div>
        </div>
    </div>

    @if($editing)
        <div class="modal fade show" id="editPackageModal" tabindex="-1" role="dialog" style="display:block;" aria-modal="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                <div class="modal-content tp-modal">
                    <form method="POST" action="{{ route('todos-paquetes.datos', ['type' => $editing['type'], 'id' => $editing['id']]) }}">
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
                                        @if(in_array($field, ['observacion', 'observaciones', 'direccion', 'direccion_d', 'referencia'], true))
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
@endsection

@section('css')
    <style>
        .todos-paquetes-page {
            background:#f5f7fb;
            border:1px solid #e5eaf3;
            border-radius:12px;
            padding:14px;
        }
        .tp-card {
            border:0;
            border-radius:10px;
            box-shadow:0 12px 26px rgba(15, 23, 42, .08);
            overflow:hidden;
        }
        .tp-card .card-header,
        .tp-modal .modal-header {
            background:#20539A;
            color:#fff;
            border:0;
        }
        .tp-muted {
            color:rgba(255,255,255,.78);
            font-size:.85rem;
        }
        .tp-actions {
            display:flex;
            gap:8px;
        }
        .tp-actions .btn {
            flex:1 1 0;
        }
        .tp-table thead th {
            background:#edf3ff;
            color:#1f3f78;
            font-size:.78rem;
            text-transform:uppercase;
            white-space:nowrap;
        }
        .tp-table td {
            vertical-align:middle;
            font-size:.88rem;
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
        .tp-state-select {
            min-width:170px;
            font-weight:700;
        }
    </style>
@endsection
