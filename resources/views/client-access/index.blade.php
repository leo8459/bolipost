@extends('adminlte::page')

@section('title', 'Accesos Clientes')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <span>Clientes y roles del portal</span>
                    </div>

                    @if ($message = Session::get('success'))
                        <div class="alert alert-success mb-0">
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    @endif

                    <div class="card-body">
                        <div class="alert alert-info">
                            Asigna uno o varios roles del guard <strong>cliente</strong> a cada cuenta del portal.
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Cliente</th>
                                        <th>Email</th>
                                        <th>Codigo</th>
                                        <th>Roles Cliente</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($clientes as $cliente)
                                        <tr>
                                            <td>{{ ++$i }}</td>
                                            <td>{{ $cliente->name }}</td>
                                            <td>{{ $cliente->email }}</td>
                                            <td>{{ $cliente->codigo_cliente }}</td>
                                            <td>
                                                @php
                                                    $roleNames = $cliente->getRoleNames();
                                                @endphp
                                                @if ($roleNames->isNotEmpty())
                                                    @foreach ($roleNames as $roleName)
                                                        <span class="badge badge-warning">{{ $roleName }}</span>
                                                    @endforeach
                                                @else
                                                    <span class="text-muted">Sin roles asignados</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a class="btn btn-sm btn-success" href="{{ route('client-access.edit', $cliente->id) }}">
                                                    <i class="fa fa-fw fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                {!! $clientes->links() !!}
            </div>
        </div>
    </div>
    @include('footer')
@endsection
