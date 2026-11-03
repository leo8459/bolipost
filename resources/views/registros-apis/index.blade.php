@extends('adminlte::page')

@section('title', 'Registros de APIs')

@section('content_header')
    <h1>Registros de APIs</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h3 class="card-title">APIs recibidas</h3>
                <div class="text-muted small mt-1">Guarda el método, URL, token, descripción y un JSON opcional.</div>
            </div>
            <a href="{{ route('registros-apis.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Registrar API
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success m-3 mb-0">{{ session('success') }}</div>
        @endif

        <div class="card-body">
            <form method="GET" action="{{ route('registros-apis.index') }}" class="form-row align-items-end mb-3">
                <div class="form-group col-md-6">
                    <label for="q">Buscar</label>
                    <input id="q" type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="URL o descripción">
                </div>
                <div class="form-group col-md-3">
                    <label for="method">Método</label>
                    <select id="method" name="method" class="form-control">
                        <option value="">Todos</option>
                        @foreach ($methods as $method)
                            <option value="{{ $method }}" @selected(request('method') === $method)>{{ $method }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3 d-flex" style="gap: 8px">
                    <button class="btn btn-outline-primary flex-fill" type="submit">Filtrar</button>
                    <a class="btn btn-outline-secondary flex-fill" href="{{ route('registros-apis.index') }}">Limpiar</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Método</th>
                            <th>URL</th>
                            <th>Token</th>
                            <th>Descripción</th>
                            <th>JSON</th>
                            <th>Registrada</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($registros as $registro)
                            <tr>
                                <td><span class="badge badge-{{ in_array($registro->method, ['GET', 'HEAD', 'OPTIONS'], true) ? 'info' : 'primary' }}">{{ $registro->method }}</span></td>
                                <td class="text-break" style="min-width: 220px">{{ $registro->url }}</td>
                                <td><code>••••••••{{ mb_substr($registro->token, -4) }}</code></td>
                                <td style="min-width: 220px; white-space: pre-line">{{ $registro->description }}</td>
                                <td>{{ $registro->json_body ? 'Sí' : 'No' }}</td>
                                <td>{{ $registro->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('registros-apis.edit', $registro) }}" class="btn btn-success btn-sm" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('registros-apis.destroy', $registro) }}" class="d-inline" onsubmit="return confirm('¿Eliminar este registro de API?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Todavía no hay APIs registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $registros->links() }}
        </div>
    </div>
    @include('footer')
@endsection
