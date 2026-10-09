@extends('adminlte::page')
@section('title', 'Tarifarios padre')

@section('content')
    <section class="content container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Tarifarios padre</h3>
                @aclcan('create', null, 'tarifario-padre')
                    <a href="{{ route('tarifario-padre.create') }}" class="btn btn-primary btn-sm ml-auto">Crear tarifario padre</a>
                @endaclcan
            </div>
            <div class="card-body">
                @foreach (['success' => 'success', 'error' => 'danger'] as $key => $style)
                    @if (session($key))
                        <div class="alert alert-{{ $style }}">{{ session($key) }}</div>
                    @endif
                @endforeach
                <p class="text-muted">Crea nombres como Tarifario padre 1 o Tarifario padre 2 y asígnalos al crear o editar tus servicios.</p>
                <form method="GET" action="{{ route('tarifario-padre.index') }}" class="mb-3">
                    <label for="q">Buscar por nombre</label>
                    <div class="input-group">
                        <input id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Nombre del tarifario padre">
                        <div class="input-group-append">
                            <button class="btn btn-outline-primary" type="submit">Buscar</button>
                            <a href="{{ route('tarifario-padre.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                        </div>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead><tr><th>ID</th><th>Nombre</th><th>Servicios asociados</th><th>Acciones</th></tr></thead>
                        <tbody>
                            @forelse ($tarifariosPadre as $padre)
                                <tr>
                                    <td>{{ $padre->id }}</td>
                                    <td>{{ $padre->nombre }}</td>
                                    <td>{{ $padre->servicios_count }}</td>
                                    <td>
                                        @aclcan('edit', null, 'tarifario-padre')
                                            <a href="{{ route('tarifario-padre.edit', $padre) }}" class="btn btn-sm btn-success">Editar</a>
                                        @endaclcan
                                        @aclcan('delete', null, 'tarifario-padre')
                                            <form method="POST" action="{{ route('tarifario-padre.destroy', $padre) }}" class="d-inline" onsubmit="return confirm('¿Eliminar este tarifario padre?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" @disabled($padre->servicios_count > 0) title="{{ $padre->servicios_count > 0 ? 'Primero reasigna o desvincula los servicios asociados' : 'Eliminar tarifario padre' }}">Eliminar</button>
                                            </form>
                                        @endaclcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-4">No hay tarifarios padre registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $tarifariosPadre->links() }}
            </div>
        </div>
    </section>
    @include('footer')
@endsection
