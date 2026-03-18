@extends('adminlte::page')
@section('title', 'Tarifario Tiktoker')
@section('template_title')
    Tarifario Tiktoker
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span id="card_title">Administracion de Tarifario Tiktoker</span>
                            <div class="d-flex" style="gap:8px;">
                                <a href="{{ route('tarifario-tiktoker.import-form') }}" class="btn btn-info btn-sm">
                                    Importar Excel
                                </a>
                                <a href="{{ route('tarifario-tiktoker.create') }}" class="btn btn-primary btn-sm">
                                    Crear Nuevo
                                </a>
                            </div>
                        </div>
                    </div>

                    @if ($message = Session::get('success'))
                        <div class="alert alert-success m-3 mb-0">
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    @endif
                    @if ($message = Session::get('warning'))
                        <div class="alert alert-warning m-3 mb-0">
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    @endif
                    @if (session()->has('import_errors'))
                        <div class="alert alert-danger m-3 mb-0">
                            <p class="mb-2"><strong>Errores de importacion (primeros 20):</strong></p>
                            <ul class="mb-0">
                                @foreach (session('import_errors', []) as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="card-body">
                        <form method="GET" action="{{ route('tarifario-tiktoker.index') }}" class="mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Origen</label>
                                        <select name="origen_id" class="form-control">
                                            <option value="">Todos</option>
                                            @foreach($origenes as $origen)
                                                <option value="{{ $origen->id }}" {{ (int) $origenId === (int) $origen->id ? 'selected' : '' }}>
                                                    {{ $origen->nombre_origen }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Destino</label>
                                        <select name="destino_id" class="form-control">
                                            <option value="">Todos</option>
                                            @foreach($destinos as $destino)
                                                <option value="{{ $destino->id }}" {{ (int) $destinoId === (int) $destino->id ? 'selected' : '' }}>
                                                    {{ $destino->nombre_destino }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Busqueda general</label>
                                        <input
                                            type="text"
                                            name="q"
                                            value="{{ $q }}"
                                            class="form-control"
                                            placeholder="Origen, destino, peso, tiempo..."
                                        >
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-group w-100 d-flex" style="gap:8px;">
                                        <button type="submit" class="btn btn-outline-primary flex-fill">Filtrar</button>
                                        <a href="{{ route('tarifario-tiktoker.index') }}" class="btn btn-outline-secondary flex-fill">Limpiar</a>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead">
                                    <tr>
                                        <th>ID</th>
                                        <th>Origen</th>
                                        <th>Destino</th>
                                        <th>Servicio Extra</th>
                                        <th>Peso 1</th>
                                        <th>Peso 2</th>
                                        <th>Peso 3</th>
                                        <th>Peso Extra</th>
                                        <th>Tiempo Entrega</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($tarifas as $tarifa)
                                        <tr>
                                            <td>{{ $tarifa->id }}</td>
                                            <td>{{ $tarifa->origen?->nombre_origen }}</td>
                                            <td>{{ $tarifa->destino?->nombre_destino }}</td>
                                            <td>{{ $tarifa->servicioExtra?->nombre ?? '-' }}</td>
                                            <td>{{ number_format((float) $tarifa->peso1, 2) }}</td>
                                            <td>{{ number_format((float) $tarifa->peso2, 2) }}</td>
                                            <td>{{ number_format((float) $tarifa->peso3, 2) }}</td>
                                            <td>{{ number_format((float) $tarifa->peso_extra, 2) }}</td>
                                            <td>{{ $tarifa->tiempo_entrega }} h</td>
                                            <td>
                                                <form action="{{ route('tarifario-tiktoker.destroy', $tarifa->id) }}" method="POST">
                                                    <a class="btn btn-sm btn-success" href="{{ route('tarifario-tiktoker.edit', $tarifa->id) }}" title="Editar">
                                                        <i class="fa fa-fw fa-edit"></i>
                                                    </a>
                                                    @csrf
                                                    @method('DELETE')
                                                    <button
                                                        type="submit"
                                                        class="btn btn-danger btn-sm"
                                                        title="Eliminar"
                                                        onclick="return confirm('Seguro que deseas eliminar este tarifario tiktoker?')"
                                                    >
                                                        <i class="fa fa-fw fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center py-4">No hay registros</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {!! $tarifas->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
