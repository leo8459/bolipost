@extends('adminlte::page')

@section('title', 'Roles Clientes')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Administracion de Roles del Portal Cliente</span>
                            <a href="{{ route('client-roles.create') }}" class="btn btn-warning btn-sm">Crear Rol Cliente</a>
                        </div>
                    </div>

                    @if ($message = Session::get('success'))
                        <div class="alert alert-success mb-0">
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    @endif
                    @if ($message = Session::get('warning'))
                        <div class="alert alert-warning mb-0">
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    @endif

                    <div class="card-body">
                        <div class="alert alert-warning">
                            Este modulo administra un ACL independiente para clientes autenticados con el guard <strong>cliente</strong>.
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Rol Cliente</th>
                                        <th>Permisos</th>
                                        <th>Clientes</th>
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
                                                    data-target="#clientRoleUsersModal{{ $role->id }}"
                                                >
                                                    {{ $role->assigned_clients_count }}
                                                </button>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center" style="gap:.35rem;">
                                                    <a class="btn btn-sm btn-success" href="{{ route('client-roles.edit', $role->id) }}" title="Editar">
                                                        <i class="fa fa-fw fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('client-roles.destroy', $role->id) }}" method="POST" class="m-0">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            type="submit"
                                                            class="btn btn-danger btn-sm js-client-role-delete-btn"
                                                            data-role-name="{{ $role->name }}"
                                                            data-clients-count="{{ $role->assigned_clients_count }}"
                                                            title="Eliminar"
                                                        >
                                                            <i class="fa fa-fw fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @foreach ($roles as $role)
                            <div class="modal fade" id="clientRoleUsersModal{{ $role->id }}" tabindex="-1" role="dialog"
                                aria-labelledby="clientRoleUsersModalLabel{{ $role->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="clientRoleUsersModalLabel{{ $role->id }}">
                                                Clientes asignados al rol: {{ $role->name }}
                                            </h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            @if ($role->assigned_clients_count > 0)
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
                                                            @foreach ($role->assigned_clients as $assignedClient)
                                                                <tr>
                                                                    <td>{{ $assignedClient['id'] }}</td>
                                                                    <td>{{ $assignedClient['name'] }}</td>
                                                                    <td>{{ $assignedClient['email'] }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @else
                                                <div class="alert alert-secondary mb-0">
                                                    Este rol no tiene clientes asignados.
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="modal fade" id="clientRoleDeleteBlockedModal" tabindex="-1" role="dialog"
                            aria-labelledby="clientRoleDeleteBlockedModalLabel" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="clientRoleDeleteBlockedModalLabel">No se puede eliminar el rol cliente</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="mb-0" id="clientRoleDeleteBlockedMessage"></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="clientRoleDeleteConfirmModal" tabindex="-1" role="dialog"
                            aria-labelledby="clientRoleDeleteConfirmModalLabel" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="clientRoleDeleteConfirmModalLabel">Confirmar eliminacion</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="mb-0" id="clientRoleDeleteConfirmMessage"></p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                        <button type="button" class="btn btn-danger" id="confirmClientRoleDeleteBtn">Si, eliminar</button>
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
            const deleteButtons = Array.from(document.querySelectorAll('.js-client-role-delete-btn'));
            const blockedMessage = document.getElementById('clientRoleDeleteBlockedMessage');
            const confirmMessage = document.getElementById('clientRoleDeleteConfirmMessage');
            const confirmButton = document.getElementById('confirmClientRoleDeleteBtn');
            let pendingDeleteForm = null;

            deleteButtons.forEach((button) => {
                button.addEventListener('click', function (event) {
                    const clientsCount = Number(button.dataset.clientsCount || 0);
                    const roleName = button.dataset.roleName || 'este rol cliente';
                    const deleteForm = button.closest('form');

                    event.preventDefault();

                    if (clientsCount > 0) {
                        if (blockedMessage) {
                            blockedMessage.textContent = 'El rol cliente "' + roleName + '" tiene ' + clientsCount + ' cliente(s) asignado(s) y no puede eliminarse.';
                        }

                        $('#clientRoleDeleteBlockedModal').modal('show');
                        return;
                    }

                    pendingDeleteForm = deleteForm;

                    if (confirmMessage) {
                        confirmMessage.textContent = 'Estas seguro de que quieres eliminar el rol cliente "' + roleName + '"?';
                    }

                    $('#clientRoleDeleteConfirmModal').modal('show');
                });
            });

            if (confirmButton) {
                confirmButton.addEventListener('click', function () {
                    if (!pendingDeleteForm) {
                        return;
                    }

                    $('#clientRoleDeleteConfirmModal').modal('hide');
                    pendingDeleteForm.submit();
                });
            }
        });
    </script>
@endsection
