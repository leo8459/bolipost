@extends('adminlte::page')

@section('title', 'Clientes')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <span>Clientes del portal</span>
                    </div>

                    <div class="card-body">
                        <div class="alert alert-info">
                            Esta vista te permite revisar las cuentas del portal cliente sin mezclarlo con los usuarios internos.
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Codigo</th>
                                        <th>Rol Legacy</th>
                                        <th>Roles Cliente</th>
                                        <th>Proveedor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($clientes as $cliente)
                                        <tr>
                                            <td>{{ ++$i }}</td>
                                            <td>{{ $cliente->name }}</td>
                                            <td>{{ $cliente->email }}</td>
                                            <td>{{ $cliente->codigo_cliente }}</td>
                                            <td>{{ $cliente->rol ?: '-' }}</td>
                                            <td>
                                                @php($roleNames = $cliente->getRoleNames())
                                                @if ($roleNames->isNotEmpty())
                                                    @foreach ($roleNames as $roleName)
                                                        <span class="badge badge-warning">{{ $roleName }}</span>
                                                    @endforeach
                                                @else
                                                    <span class="text-muted">Sin roles</span>
                                                @endif
                                            </td>
                                            <td>{{ strtoupper((string) $cliente->provider) }}</td>
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
