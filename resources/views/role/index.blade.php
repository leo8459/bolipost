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
                                        <th>Guard</th>
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
                                            <td>
                                                <span class="badge {{ $role->guard_name === 'web' ? 'badge-primary' : 'badge-secondary' }}">
                                                    {{ $role->guard_name }}
                                                </span>
                                            </td>
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
                                                        <button
                                                            type="submit"
                                                            class="btn btn-danger btn-sm js-role-delete-btn"
                                                            title="Eliminar"
                                                            data-role-name="{{ $role->name }}"
                                                            data-users-count="{{ $role->assigned_users_count }}"
                                                            data-is-protected="{{ $role->name === config('acl.super_admin_role') ? '1' : '0' }}"
                                                        ><i
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

                        <div class="modal fade" id="roleDeleteBlockedModal" tabindex="-1" role="dialog"
                            aria-labelledby="roleDeleteBlockedModalLabel" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="roleDeleteBlockedModalLabel">
                                            No se puede eliminar el rol
                                        </h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="mb-2" id="roleDeleteBlockedMessage">
                                            Este rol tiene usuarios asignados y no puede eliminarse.
                                        </p>
                                        <p class="text-muted mb-0">
                                            Revisa el motivo indicado y luego vuelve a intentarlo.
                                        </p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="roleDeleteConfirmModal" tabindex="-1" role="dialog"
                            aria-labelledby="roleDeleteConfirmModalLabel" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="roleDeleteConfirmModalLabel">
                                            Confirmar eliminacion
                                        </h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="mb-2" id="roleDeleteConfirmMessage">
                                            Estas seguro de que quieres eliminar este rol?
                                        </p>
                                        <p class="text-muted mb-0">
                                            Esta accion eliminara el rol y sus permisos asociados.
                                        </p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                        <button type="button" class="btn btn-danger" id="confirmRoleDeleteBtn">Si, eliminar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {!! $roles->links() !!}
            </div>
        </div>
    </div>
    @include('footer')

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const deleteButtons = Array.from(document.querySelectorAll('.js-role-delete-btn'));
            const blockedMessage = document.getElementById('roleDeleteBlockedMessage');
            const confirmMessage = document.getElementById('roleDeleteConfirmMessage');
            const confirmDeleteButton = document.getElementById('confirmRoleDeleteBtn');
            let pendingDeleteForm = null;

            deleteButtons.forEach((button) => {
                button.addEventListener('click', function (event) {
                    const usersCount = Number(button.dataset.usersCount || 0);
                    const roleName = button.dataset.roleName || 'este rol';
                    const isProtected = button.dataset.isProtected === '1';
                    const deleteForm = button.closest('form');

                    if (isProtected) {
                        event.preventDefault();

                        if (blockedMessage) {
                            blockedMessage.textContent = 'El rol "' + roleName + '" es el super administrador y no puede eliminarse.';
                        }

                        $('#roleDeleteBlockedModal').modal('show');
                        return;
                    }

                    if (usersCount <= 0) {
                        event.preventDefault();
                        pendingDeleteForm = deleteForm;

                        if (confirmMessage) {
                            confirmMessage.textContent = 'Estas seguro de que quieres eliminar el rol "' + roleName + '"?';
                        }

                        $('#roleDeleteConfirmModal').modal('show');
                        return;
                    }

                    event.preventDefault();

                    if (blockedMessage) {
                        blockedMessage.textContent = 'El rol "' + roleName + '" tiene ' + usersCount + ' usuario(s) asignado(s) y no puede eliminarse.';
                    }

                    $('#roleDeleteBlockedModal').modal('show');
                });
            });

            if (confirmDeleteButton) {
                confirmDeleteButton.addEventListener('click', function () {
                    if (!pendingDeleteForm) {
                        return;
                    }

                    $('#roleDeleteConfirmModal').modal('hide');
                    pendingDeleteForm.submit();
                });
            }
        });
    </script>
@endsection
