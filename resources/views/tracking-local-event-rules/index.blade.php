@extends('adminlte::page')
@section('title', 'Reglas tracking local')
@section('template_title')
    Reglas tracking local
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                            <div>
                                <span id="card_title">Administracion de eventos locales visibles en TrackingBO</span>
                                <div class="text-muted small">Misma logica de SITRA aplicada a eventos locales.</div>
                            </div>
                            <div class="d-flex" style="gap:8px;flex-wrap:wrap;">
                                <form method="POST" action="{{ route('tracking-local-event-rules.sync') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Sincronizar catalogo</button>
                                </form>
                                <a href="{{ route('tracking-local-event-rules.create') }}" class="btn btn-primary btn-sm">Crear regla</a>
                            </div>
                        </div>
                    </div>

                    @if ($message = Session::get('success'))
                        <div class="alert alert-success m-3 mb-0"><p class="mb-0">{{ $message }}</p></div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger m-3 mb-0">
                            @foreach ($errors->all() as $error)
                                <p class="mb-0">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div class="card-body">
                        <form method="GET" action="{{ route('tracking-local-event-rules.index') }}" class="mb-3">
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="form-group mb-0">
                                        <label>Buscar</label>
                                        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nombre original, visible o fuente...">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group mb-0">
                                        <label>Fuente</label>
                                        <select name="source_table" class="form-control">
                                            <option value="">Todas</option>
                                            @foreach ($sourceOptions as $value => $label)
                                                <option value="{{ $value }}" @selected(request('source_table') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group mb-0">
                                        <label>Visibilidad</label>
                                        <select name="visible" class="form-control">
                                            <option value="">Todos</option>
                                            <option value="1" @selected(request('visible') === '1')>Visibles</option>
                                            <option value="0" @selected(request('visible') === '0')>Ocultos</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-group mb-0 w-100 d-flex" style="gap:8px;">
                                        <button type="submit" class="btn btn-outline-primary flex-fill">Filtrar</button>
                                        <a href="{{ route('tracking-local-event-rules.index') }}" class="btn btn-outline-secondary flex-fill">Limpiar</a>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead">
                                    <tr>
                                        <th>Fuente</th>
                                        <th>Event ID</th>
                                        <th>Nombre original</th>
                                        <th>Nombre visible</th>
                                        <th>Visible</th>
                                        <th>Orden</th>
                                        <th>Notas</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($rules as $rule)
                                        <tr>
                                            <td>{{ $sourceOptions[$rule->source_table] ?? $rule->source_table }}</td>
                                            <td>{{ $rule->event_id }}</td>
                                            <td>{{ $rule->raw_name }}</td>
                                            <td>{{ $rule->display_name }}</td>
                                            <td>
                                                <form method="POST" action="{{ route('tracking-local-event-rules.toggle-visibility', $rule) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                                                    <button type="submit" class="btn btn-sm {{ $rule->is_visible ? 'btn-success' : 'btn-secondary' }}">
                                                        {{ $rule->is_visible ? 'Visible' : 'Oculto' }}
                                                    </button>
                                                </form>
                                            </td>
                                            <td>{{ $rule->sort_order }}</td>
                                            <td>{{ $rule->notes }}</td>
                                            <td>
                                                <div class="d-flex" style="gap:8px;">
                                                    <a href="{{ route('tracking-local-event-rules.edit', $rule) }}" class="btn btn-sm btn-success">Editar</a>
                                                    <form method="POST" action="{{ route('tracking-local-event-rules.destroy', $rule) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Seguro que deseas eliminar esta regla?')">Eliminar</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-4">No hay reglas registradas.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {!! $rules->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
