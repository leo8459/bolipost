@extends('adminlte::page')
@section('title', 'Servicios')
@section('template_title')
    Servicios
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span id="card_title">Administracion de Servicios</span>
                            @aclcan('create', null, 'servicios')
                                <a href="{{ route('servicios.create') }}" class="btn btn-primary btn-sm">
                                    Crear Nuevo
                                </a>
                            @endaclcan
                        </div>
                    </div>

                    @if ($message = Session::get('success'))
                        <div class="alert alert-success m-3 mb-0">
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    @endif

                    @if ($message = Session::get('error'))
                        <div class="alert alert-danger m-3 mb-0">
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    @endif

                    <div class="card-body">
                        <form method="GET" action="{{ route('servicios.index') }}" class="mb-3">
                            <div class="row">
                                <div class="col-md-10">
                                    <div class="form-group mb-0">
                                        <label>Buscar</label>
                                        <input
                                            type="text"
                                            name="q"
                                            value="{{ $q }}"
                                            class="form-control"
                                            placeholder="Nombre, codigo, SIN, actividad o descripcion..."
                                        >
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-group mb-0 w-100 d-flex" style="gap:8px;">
                                        <button type="submit" class="btn btn-outline-primary flex-fill">Filtrar</button>
                                        <a href="{{ route('servicios.index') }}" class="btn btn-outline-secondary flex-fill">Limpiar</a>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead">
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Act. Economica</th>
                                        <th>Codigo SIN</th>
                                        <th>Codigo</th>
                                        <th>Unidad Medida</th>
                                        <th>Descripcion</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($servicios as $servicio)
                                        <tr>
                                            <td>{{ $servicio->nombre_servicio }}</td>
                                            <td>{{ $servicio->actividadEconomica ?: '-' }}</td>
                                            <td>{{ $servicio->codigoSin ?: '-' }}</td>
                                            <td>{{ $servicio->codigo ?: '-' }}</td>
                                            <td>{{ $servicio->unidadMedida ?? '-' }}</td>
                                            <td>{{ $servicio->descripcion ?: '-' }}</td>
                                            <td>
                                                <div class="d-flex" style="gap:8px;">
                                                    @aclcan('edit', null, 'servicios')
                                                        <a class="btn btn-sm btn-success" href="{{ route('servicios.edit', $servicio->id) }}" title="Editar">
                                                            <i class="fa fa-fw fa-edit"></i>
                                                        </a>
                                                    @endaclcan

                                                    @aclcan('delete', null, 'servicios')
                                                        <form action="{{ route('servicios.destroy', $servicio->id) }}" method="POST">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button
                                                                type="submit"
                                                                class="btn btn-danger btn-sm"
                                                                title="Dar de baja"
                                                                onclick="return confirm('Seguro que deseas dar de baja este servicio?')"
                                                            >
                                                                <i class="fa fa-fw fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    @endaclcan
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4">No hay registros</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {!! $servicios->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
