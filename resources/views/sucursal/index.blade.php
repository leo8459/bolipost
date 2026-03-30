@extends('adminlte::page')
@section('title', 'Sucursales')
@section('template_title')
    Sucursales
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span id="card_title">Administracion de Sucursales</span>
                            @aclcan('create', null, 'sucursales')
                                <a href="{{ route('sucursales.create') }}" class="btn btn-primary btn-sm">
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
                        <form method="GET" action="{{ route('sucursales.index') }}" class="mb-3">
                            <div class="row">
                                <div class="col-md-10">
                                    <div class="form-group mb-0">
                                        <label>Buscar</label>
                                        <input
                                            type="text"
                                            name="q"
                                            value="{{ $q }}"
                                            class="form-control"
                                            placeholder="Codigo, punto de venta, municipio, departamento o telefono..."
                                        >
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-group mb-0 w-100 d-flex" style="gap:8px;">
                                        <button type="submit" class="btn btn-outline-primary flex-fill">Filtrar</button>
                                        <a href="{{ route('sucursales.index') }}" class="btn btn-outline-secondary flex-fill">Limpiar</a>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead">
                                    <tr>
                                        <th>Codigo Sucursal</th>
                                        <th>Punto Venta</th>
                                        <th>Municipio</th>
                                        <th>Departamento</th>
                                        <th>Telefono</th>
                                        <th>Usuarios</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($sucursales as $sucursal)
                                        <tr>
                                            <td>{{ $sucursal->codigoSucursal }}</td>
                                            <td>{{ $sucursal->puntoVenta }}</td>
                                            <td>{{ $sucursal->municipio }}</td>
                                            <td>{{ $sucursal->departamento ?: '-' }}</td>
                                            <td>{{ $sucursal->telefono }}</td>
                                            <td>{{ $sucursal->users_count }}</td>
                                            <td>
                                                <div class="d-flex" style="gap:8px;">
                                                    @aclcan('edit', null, 'sucursales')
                                                        <a class="btn btn-sm btn-success" href="{{ route('sucursales.edit', $sucursal->id) }}" title="Editar">
                                                            <i class="fa fa-fw fa-edit"></i>
                                                        </a>
                                                    @endaclcan

                                                    @aclcan('delete', null, 'sucursales')
                                                        <form action="{{ route('sucursales.destroy', $sucursal->id) }}" method="POST">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button
                                                                type="submit"
                                                                class="btn btn-danger btn-sm"
                                                                title="Dar de baja"
                                                                onclick="return confirm('Seguro que deseas dar de baja esta sucursal?')"
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

                        {!! $sucursales->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
