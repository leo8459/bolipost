<div>
    <style>
        :root {
            --azul: #20539a;
            --dorado: #fecc36;
            --bg: #f5f7fb;
            --line: #e5e7eb;
            --muted: #6b7280;
        }

        .users-wrap {
            background: var(--bg);
            padding: 18px;
            border-radius: 16px;
        }

        .users-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 12px 26px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .users-header {
            background: linear-gradient(90deg, var(--azul), #2a66b8);
            color: #fff;
            padding: 18px 20px;
        }

        .users-search {
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.45);
            padding: 10px 12px;
            background: rgba(255, 255, 255, 0.95);
        }

        .btn-dorado {
            background: var(--dorado);
            color: #fff;
            font-weight: 800;
            border: none;
            border-radius: 12px;
            padding: 10px 14px;
        }

        .btn-dorado:hover {
            filter: brightness(0.95);
            color: #fff;
        }

        .btn-outline-light2 {
            border: 1px solid rgba(255, 255, 255, 0.75);
            color: #fff;
            font-weight: 800;
            border-radius: 12px;
            padding: 10px 14px;
            background: transparent;
        }

        .btn-outline-light2:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
        }

        .table thead th {
            background: rgba(32, 83, 154, 0.08);
            color: var(--azul);
            font-weight: 900;
            border-bottom: 2px solid rgba(32, 83, 154, 0.2);
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .badge-role {
            background: rgba(32, 83, 154, 0.12);
            color: var(--azul);
            font-weight: 700;
            border-radius: 999px;
            padding: 4px 10px;
            display: inline-block;
            margin: 2px;
        }

        .modal-content {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.22);
        }

        .modal-header {
            background: linear-gradient(90deg, var(--azul), #2a66b8);
            color: #fff;
            border-bottom: 0;
        }

        .modal-footer {
            border-top: 1px solid var(--line);
            background: #fafafa;
        }

        .users-modal-footer {
            position: sticky;
            bottom: 0;
            z-index: 2;
        }

        .users-muted {
            color: var(--muted);
        }

        .users-user-modal .modal-dialog {
            max-width: 1140px;
            width: calc(100% - 1rem);
            margin: 1.5rem auto;
        }

        .users-user-modal .modal-body {
            overflow-y: visible;
        }
    </style>

    <div class="users-wrap">
        <div class="card users-card">
            <div class="users-header d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <h4 class="mb-0">Gestion de Usuarios</h4>
                </div>

                <div class="d-flex flex-wrap align-items-center mt-3 mt-md-0" style="gap: 8px;">
                    <input
                        type="text"
                        wire:model="search"
                        wire:keydown.enter="searchUsers"
                        class="form-control users-search"
                        placeholder="Buscar por nombre, alias, email, CI, empresa o sucursal"
                        style="min-width: 260px;"
                    >
                    <button wire:click="searchUsers" class="btn btn-outline-light2" type="button">Buscar</button>
                    <a href="{{ route('users.excel') }}" class="btn btn-success">Excel</a>
                    <a href="{{ route('users.pdf') }}" class="btn btn-danger">PDF</a>
                    @can('users.create')
                        <button type="button" class="btn btn-dorado" wire:click="openCreateModal">Nuevo Usuario</button>
                    @endcan
                </div>
            </div>

            @if (session()->has('success'))
                <div class="alert alert-success m-3 mb-0">{{ session('success') }}</div>
            @endif
            @if (session()->has('warning'))
                <div class="alert alert-warning m-3 mb-0">{{ session('warning') }}</div>
            @endif

            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="users-muted">
                        @if($searchQuery !== '')
                            Resultado para: <strong>{{ $searchQuery }}</strong>
                        @else
                            Mostrando todos los usuarios
                        @endif
                    </div>
                    <div class="users-muted small">
                        Total: <strong>{{ $users->total() }}</strong>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Alias</th>
                                <th>Email</th>
                                <th>Regional</th>
                                <th>Sucursal</th>
                                <th>Empresa</th>
                                <th>CI</th>
                                <th>Estado</th>
                                <th>Roles</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $user)
                                <tr>
                                    <td>{{ ($users->currentPage() - 1) * $users->perPage() + $loop->iteration }}</td>
                                    <td>{{ $user->name }}</td>
                                    <td><span class="badge badge-primary">{{ $user->alias ?? '-' }}</span></td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ $user->ciudad }}</td>
                                    <td>
                                        @if ($user->sucursal)
                                            Suc. {{ $user->sucursal->codigoSucursal }} / PV {{ $user->sucursal->puntoVenta }} - {{ $user->sucursal->municipio }}
                                        @else
                                            <span class="users-muted">Sin sucursal</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($user->empresa)
                                            {{ $user->empresa->codigo_cliente }} - {{ $user->empresa->nombre }} ({{ $user->empresa->sigla }})
                                        @else
                                            <span class="users-muted">Sin empresa</span>
                                        @endif
                                    </td>
                                    <td>{{ $user->ci }}</td>
                                    <td>
                                        <span class="badge {{ $user->trashed() ? 'badge-danger' : 'badge-success' }}">
                                            {{ $user->trashed() ? 'Inactivo' : 'Activo' }}
                                        </span>
                                    </td>
                                    <td>
                                        @forelse ($user->roles as $role)
                                            <span class="badge-role">{{ $role->name }}</span>
                                        @empty
                                            <span class="users-muted">Sin rol</span>
                                        @endforelse
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            @if ($user->trashed())
                                                @can('users.restore')
                                                    <button
                                                        wire:click="confirmStatusAction({{ $user->id }}, 'restore')"
                                                        class="btn btn-sm btn-success"
                                                        type="button"
                                                        title="Reactivar"
                                                    >
                                                        <i class="fa fa-undo"></i>
                                                    </button>
                                                @endcan
                                            @else
                                                @can('users.edit')
                                                    <button
                                                        wire:click="openEditModal({{ $user->id }})"
                                                        class="btn btn-sm btn-primary"
                                                        type="button"
                                                        title="Editar"
                                                    >
                                                        <i class="fa fa-edit"></i>
                                                    </button>
                                                @endcan
                                                @can('users.update')
                                                    <button
                                                        wire:click="openPasswordModal({{ $user->id }})"
                                                        class="btn btn-sm btn-info"
                                                        type="button"
                                                        title="Cambiar contrasena"
                                                    >
                                                        <i class="fa fa-key"></i>
                                                    </button>
                                                @endcan
                                                @can('users.destroy')
                                                    <button
                                                        wire:click="confirmStatusAction({{ $user->id }}, 'delete')"
                                                        class="btn btn-sm btn-warning"
                                                        type="button"
                                                        title="Dar de baja"
                                                    >
                                                        <i class="fa fa-arrow-down"></i>
                                                    </button>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4 users-muted">No se encontraron usuarios.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade users-user-modal" id="userModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form wire:submit.prevent="saveUser">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingId ? 'Editar usuario' : 'Nuevo usuario' }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nombre completo</label>
                                    <input type="text" wire:model.defer="name" class="form-control" placeholder="Nombre completo">
                                    @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" wire:model.defer="email" class="form-control" placeholder="Correo electronico">
                                    @error('email') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Alias de acceso *</label>
                                    <input type="text" wire:model.defer="alias" class="form-control" placeholder="Ej: juan_perez" required>
                                    <small class="text-muted">Solo letras, numeros, guion y guion bajo.</small>
                                    @error('alias') <small class="text-danger d-block">{{ $message }}</small> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ $editingId ? 'Nueva contrasena (opcional)' : 'Contrasena' }}</label>
                                    <input type="password" wire:model.defer="password" class="form-control" placeholder="Contrasena">
                                    @error('password') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Regional</label>
                                    <select wire:model.defer="ciudad" class="form-control">
                                        <option value="">Seleccione la regional</option>
                                        @foreach($regionales as $regional)
                                            <option value="{{ $regional }}">{{ $regional }}</option>
                                        @endforeach
                                    </select>
                                    @error('ciudad') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Carnet de identidad (opcional)</label>
                                    <input type="text" wire:model.defer="ci" class="form-control" placeholder="CI (opcional)">
                                    @error('ci') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Empresa (opcional)</label>
                                    <select wire:model.defer="empresa_id" class="form-control">
                                        <option value="">Sin empresa</option>
                                        @foreach($empresas as $empresa)
                                            <option value="{{ $empresa->id }}">
                                                {{ $empresa->codigo_cliente }} - {{ $empresa->nombre }} ({{ $empresa->sigla }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('empresa_id') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Sucursal de facturacion (opcional)</label>
                                    <select wire:model.defer="sucursal_id" class="form-control">
                                        <option value="">Sin sucursal</option>
                                        @foreach($sucursales as $sucursal)
                                            <option value="{{ $sucursal->id }}">
                                                Sucursal {{ $sucursal->codigoSucursal }} - Punto {{ $sucursal->puntoVenta }} - {{ $sucursal->municipio }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted d-block">La regional sigue usando el campo ciudad y no se modifica.</small>
                                    @error('sucursal_id') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-0">
                            <label>Roles</label>
                            <div class="row">
                                @foreach($roles as $role)
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                wire:model.defer="roleIds"
                                                id="role_{{ $role->id }}"
                                                value="{{ $role->id }}"
                                            >
                                            <label class="form-check-label" for="role_{{ $role->id }}">{{ $role->name }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('roleIds') <small class="text-danger">{{ $message }}</small> @enderror
                            @error('roleIds.*') <small class="text-danger d-block">{{ $message }}</small> @enderror
                        </div>
                    </div>
                    <div class="modal-footer users-modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">{{ $editingId ? 'Guardar cambios' : 'Crear usuario' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <form wire:submit.prevent="updatePassword">
                    <div class="modal-header">
                        <h5 class="modal-title">Cambiar contrasena</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-0">
                            <label>Nueva contrasena</label>
                            <input type="password" wire:model.defer="newPassword" class="form-control" placeholder="Minimo 8 caracteres">
                            @error('newPassword') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar accion</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @if($statusAction === 'restore')
                        Se reactivara el usuario seleccionado.
                    @else
                        El usuario seleccionado sera dado de baja.
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="applyStatusAction">Confirmar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    if (!window.__usersModalHandlersBound) {
        window.__usersModalHandlersBound = true;

        window.addEventListener('openUserModal', () => {
            $('#userModal').modal('show');
        });

        window.addEventListener('closeUserModal', () => {
            $('#userModal').modal('hide');
        });

        window.addEventListener('openPasswordModal', () => {
            $('#passwordModal').modal('show');
        });

        window.addEventListener('closePasswordModal', () => {
            $('#passwordModal').modal('hide');
        });

        window.addEventListener('openStatusModal', () => {
            $('#statusModal').modal('show');
        });

        window.addEventListener('closeStatusModal', () => {
            $('#statusModal').modal('hide');
        });
    }
</script>
