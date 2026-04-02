@extends('adminlte::page')
@section('title', 'Paquetes Ordinarios')
@section('template_title')
    Paqueteria Postal
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <div style="display: flex; justify-content: space-between; align-items: center;">

                            <span id="card_title">
                                {{ __('Administracion Roles TrackinBO') }}
                            </span>

                            <div class="float-right">
                                <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm float-right"
                                    data-placement="left">
                                    {{ __('Crear Nuevo') }}
                                </a>
                            </div>
                        </div>
                    </div>
                    @if ($message = Session::get('success'))
                        <div class="alert alert-success">
                            <p>{{ $message }}</p>
                        </div>
                    @endif
                    @if ($message = Session::get('warning'))
                        <div class="alert alert-warning">
                            <p>{{ $message }}</p>
                        </div>
                    @endif

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead">
                                    <tr>
                                        <th>No</th>

                                        <th>Rol</th>
                                        <th>Permisos</th>
                                        <th>Usuarios</th>

                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($roles as $role)
                                        <tr>
                                            <td>{{ ++$i }}</td>

                                            <td>{{ $role->name }}</td>
                                            <td>{{ $role->permissions_count }}</td>
                                            <td>
                                                <button
                                                    type="button"
                                                    class="btn btn-link btn-sm p-0"
                                                    data-toggle="modal"
                                                    data-target="#roleUsersModal{{ $role->id }}"
                                                >
                                                    {{ $role->assigned_users_count }}
                                                </button>
                                            </td>

                                            <td>
                                                <div class="d-flex align-items-center" style="gap: .35rem;">
                                                    <a class="btn btn-sm btn-success"
                                                        href="{{ route('roles.edit', $role->id) }}"
                                                        title="Editar"><i
                                                            class="fa fa-fw fa-edit"></i></a>
                                                    <form action="{{ route('roles.duplicate', $role->id) }}" method="POST" class="m-0">
                                                        @csrf
                                                        <button type="submit" class="btn btn-info btn-sm" title="Duplicar">
                                                            <i class="fa fa-fw fa-copy"></i>
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('roles.destroy', $role->id) }}" method="POST" class="m-0">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar"><i
                                                                class="fa fa-fw fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @foreach ($roles as $role)
                            <div class="modal fade" id="roleUsersModal{{ $role->id }}" tabindex="-1" role="dialog"
                                aria-labelledby="roleUsersModalLabel{{ $role->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="roleUsersModalLabel{{ $role->id }}">
                                                Usuarios asignados al rol: {{ $role->name }}
                                            </h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="text-muted mb-3">
                                                Total de usuarios asignados: {{ $role->assigned_users_count }}
                                            </p>

                                            @if ($role->assigned_users_count > 0)
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-striped mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>Nombre</th>
                                                                <th>Email</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($role->assigned_users as $assignedUser)
                                                                <tr>
                                                                    <td>{{ $assignedUser['id'] }}</td>
                                                                    <td>{{ $assignedUser['name'] }}</td>
                                                                    <td>{{ $assignedUser['email'] }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @else
                                                <div class="alert alert-secondary mb-0">
                                                    Este rol no tiene usuarios asignados.
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                {!! $roles->links() !!}
            </div>
        </div>
    </div>
    @include('footer')
@endsection
