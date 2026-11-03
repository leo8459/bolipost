@extends('adminlte::page')

@section('title', 'Clientes DELIVERY EXPRESS')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <span>Clientes DELIVERY EXPRESS</span>
                        <span class="badge badge-primary float-right">{{ number_format($clientes->total(), 0, ',', '.') }} clientes</span>
                    </div>

                    <div class="card-body">
                        <div class="alert alert-info">
                            Aquí se muestran todas las cuentas registradas en el portal de Delivery Express. Usa la paginación para recorrer la lista completa.
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
                                        <th>Acciones</th>
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
                                            <td class="text-nowrap">
                                                @if (auth('web')->user()?->isSuperAdmin() && !session()->has('impersonator_id') && !session()->has('impersonator_cliente_admin_id'))
                                                    <form method="POST" action="{{ route('clientes-admin.impersonate', $cliente) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success" title="Ingresar con este cliente">
                                                            <i class="fas fa-sign-in-alt mr-1"></i>Ingresar
                                                        </button>
                                                    </form>
                                                @endif
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
