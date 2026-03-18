@extends('adminlte::page')
@section('title', 'Servicios Extras')
@section('template_title')
    Servicios Extras
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span id="card_title">Administracion de Servicios Extras</span>
                            <a href="{{ route('servicio-extras.create') }}" class="btn btn-primary btn-sm">
                                Crear Nuevo
                            </a>
                        </div>
                    </div>

                    @if ($message = Session::get('success'))
                        <div class="alert alert-success m-3 mb-0">
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    @endif

                    <div class="card-body">
                        <form method="GET" action="{{ route('servicio-extras.index') }}" class="mb-3">
                            <div class="row">
                                <div class="col-md-10">
                                    <div class="form-group mb-0">
                                        <label>Buscar</label>
                                        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Nombre o descripcion...">
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-group mb-0 w-100 d-flex" style="gap:8px;">
                                        <button type="submit" class="btn btn-outline-primary flex-fill">Filtrar</button>
                                        <a href="{{ route('servicio-extras.index') }}" class="btn btn-outline-secondary flex-fill">Limpiar</a>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead">
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Descripcion</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($servicioExtras as $servicioExtra)
                                        <tr>
                                            <td>{{ $servicioExtra->nombre }}</td>
                                            <td>{{ $servicioExtra->descripcion ?: '-' }}</td>
                                            <td>
                                                <form action="{{ route('servicio-extras.destroy', $servicioExtra->id) }}" method="POST">
                                                    <a class="btn btn-sm btn-success" href="{{ route('servicio-extras.edit', $servicioExtra->id) }}" title="Editar">
                                                        <i class="fa fa-fw fa-edit"></i>
                                                    </a>
                                                    @csrf
                                                    @method('DELETE')
                                                    <button
                                                        type="submit"
                                                        class="btn btn-danger btn-sm"
                                                        title="Eliminar"
                                                        onclick="return confirm('Seguro que deseas eliminar este servicio extra?')"
                                                    >
                                                        <i class="fa fa-fw fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center py-4">No hay registros</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {!! $servicioExtras->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
