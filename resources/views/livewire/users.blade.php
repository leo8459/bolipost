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

        .users-filter-toolbar {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px;
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.12);
        }

        .users-filter-btn {
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 800;
            padding: 8px 12px;
            transition: background .15s ease, color .15s ease, box-shadow .15s ease;
        }

        .users-filter-btn:hover {
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
        }

        .users-filter-btn.is-active {
            background: #fff;
            color: var(--azul);
            box-shadow: 0 5px 14px rgba(0, 0, 0, 0.14);
        }

        .users-company-select {
            min-width: 280px;
            border: 0;
            border-radius: 12px;
            background: #fff;
            color: #22344d;
            font-weight: 700;
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

        .users-import-box {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px;
            background: #fff;
        }

        .users-group-card {
            border: 1px solid #dbe4f1;
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 8px 20px rgba(32, 83, 154, 0.06);
        }

        .users-group-card + .users-group-card {
            margin-top: 14px;
        }

        .users-group-card__head {
            padding: 14px 16px;
            background: linear-gradient(90deg, rgba(32, 83, 154, 0.08), rgba(42, 102, 184, 0.04));
            border-bottom: 1px solid #e3ebf5;
        }

        .users-group-card__title {
            color: var(--azul);
            font-size: 1rem;
            font-weight: 900;
            margin: 0;
        }

        .users-group-card__meta {
            color: var(--muted);
            font-size: 0.82rem;
            margin-top: 4px;
        }

        .users-group-list {
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .users-group-list__item {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr) minmax(0, 0.8fr) minmax(0, 1.2fr);
            gap: 14px;
            padding: 14px 16px;
            border-top: 1px solid #eef2f7;
            align-items: center;
        }

        .users-group-list__item:first-child {
            border-top: 0;
        }

        .users-group-user strong {
            display: block;
            color: #22344d;
            font-size: 0.95rem;
        }

        .users-group-user span,
        .users-group-user small,
        .users-group-roles small {
            color: var(--muted);
        }

        .users-group-roles {
            text-align: right;
        }

        .users-group-kpi {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            min-height: 34px;
            border-radius: 999px;
            background: rgba(32, 83, 154, 0.12);
            color: var(--azul);
            font-weight: 900;
            padding: 0 10px;
        }

        .users-import-progress {
            display: none;
            margin-top: 10px;
        }

        .users-import-progress .progress {
            height: 18px;
            border-radius: 999px;
            overflow: hidden;
            background: #e5e7eb;
        }

        .users-import-progress .progress-bar {
            font-size: 0.75rem;
            font-weight: 800;
            transition: width 0.35s ease;
        }

        .regional-picker {
            border: 1px solid #d9e2ef;
            border-radius: 8px;
            background: #f8fafc;
            padding: 10px;
        }

        .regional-picker-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .regional-option {
            margin: 0;
        }

        .regional-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .regional-option-label {
            display: flex;
            align-items: center;
            min-height: 38px;
            border: 1px solid #d8dee9;
            border-radius: 8px;
            background: #fff;
            color: #263238;
            cursor: pointer;
            font-weight: 800;
            line-height: 1.1;
            padding: 8px 10px;
            transition: border-color .15s ease, background .15s ease, color .15s ease, box-shadow .15s ease;
            user-select: none;
        }

        .regional-option-label::before {
            content: "\f00c";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border: 1px solid #bac6d8;
            border-radius: 4px;
            color: transparent;
            margin-right: 8px;
            font-size: 11px;
            flex: 0 0 auto;
        }

        .regional-option input:checked + .regional-option-label {
            border-color: var(--azul);
            background: rgba(32, 83, 154, 0.08);
            color: var(--azul);
            box-shadow: 0 0 0 2px rgba(32, 83, 154, 0.08);
        }

        .regional-option input:checked + .regional-option-label::before {
            background: var(--azul);
            border-color: var(--azul);
            color: #fff;
        }

        .regional-option input:focus + .regional-option-label {
            box-shadow: 0 0 0 3px rgba(254, 204, 54, 0.35);
        }

        @media (max-width: 767.98px) {
            .regional-picker-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .users-group-list__item {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .users-group-roles {
                text-align: left;
            }
        }

        @media (max-width: 420px) {
            .regional-picker-grid {
                grid-template-columns: 1fr;
            }
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
                    <button wire:click="toggleGroupByBillingSucursal" class="btn btn-outline-light2" type="button">
                        {{ $groupByBillingSucursal ? 'Ver lista normal' : 'Agrupar por sucursal facturacion' }}
                    </button>
                    <div class="users-filter-toolbar">
                        <button
                            wire:click="showAllUsers"
                            class="users-filter-btn {{ ! $showOnlyWithEmpresa ? 'is-active' : '' }}"
                            type="button"
                        >
                            <i class="fas fa-users mr-1"></i> Mostrar usuarios
                        </button>
                        <button
                            wire:click="showEmpresaUsers"
                            class="users-filter-btn {{ $showOnlyWithEmpresa ? 'is-active' : '' }}"
                            type="button"
                        >
                            <i class="fas fa-building mr-1"></i> Mostrar empresas
                        </button>
                    </div>
                    @if($showOnlyWithEmpresa)
                        <select wire:model.live="filterEmpresaId" class="form-control users-company-select">
                            <option value="">Todas las empresas</option>
                            @foreach($empresas as $empresa)
                                <option value="{{ $empresa->id }}">
                                    {{ $empresa->codigo_cliente }} - {{ $empresa->nombre }} ({{ $empresa->sigla }})
                                </option>
                            @endforeach
                        </select>
                    @endif
                    <a href="{{ route('users.excel') }}" class="btn btn-success">Excel</a>
                    <a href="{{ route('users.pdf') }}" class="btn btn-danger">PDF</a>
                    <a href="{{ route('users.template-excel') }}" class="btn btn-info">Plantilla</a>
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
            @if (session()->has('import_errors'))
                <div class="alert alert-warning m-3 mb-0">
                    <strong>Errores de importacion:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach(session('import_errors', []) as $importError)
                            <li>{{ $importError }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card-body">
                <div class="users-import-box mb-3">
                    <form method="POST" action="{{ route('users.import') }}" enctype="multipart/form-data" id="users-import-form" class="d-flex flex-column flex-md-row align-items-md-center" style="gap: 10px;">
                        @csrf
                        <div class="flex-grow-1">
                            <label class="mb-1 font-weight-bold">Importar usuarios masivamente</label>
                            <input type="file" name="archivo" class="form-control" accept=".xlsx,.xls,.csv" required>
                            <small class="text-muted">Usa la plantilla para crear usuarios nuevos. Si el alias o email ya existen, esa fila no se importa.</small>
                            <div class="users-import-progress" id="users-import-progress">
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span id="users-import-progress-text">Preparando importacion...</span>
                                    <span id="users-import-progress-time">0s</span>
                                </div>
                                <div class="progress">
                                    <div
                                        class="progress-bar bg-success"
                                        id="users-import-progress-bar"
                                        role="progressbar"
                                        style="width: 0%;"
                                        aria-valuenow="0"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                    >0%</div>
                                </div>
                                <small class="text-muted d-block mt-1">No cierres esta ventana hasta que termine la importacion.</small>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-2 mt-md-4" id="users-import-submit">
                            Importar Excel
                        </button>
                    </form>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="users-muted">
                        @if($searchQuery !== '')
                            Resultado para: <strong>{{ $searchQuery }}</strong>
                            @if($groupByBillingSucursal)
                                <span class="ml-2">| Vista agrupada por sucursal de facturacion</span>
                            @endif
                        @else
                            @if($showOnlyWithEmpresa)
                                Mostrando usuarios con empresa
                                @if($filterEmpresaId !== '')
                                    @php($empresaFiltro = $empresas->firstWhere('id', (int) $filterEmpresaId))
                                    @if($empresaFiltro)
                                        : <strong>{{ $empresaFiltro->codigo_cliente }} - {{ $empresaFiltro->nombre }}</strong>
                                    @endif
                                @endif
                                @if($groupByBillingSucursal)
                                    <span class="ml-2">| Vista agrupada por sucursal de facturacion</span>
                                @endif
                            @else
                                {{ $groupByBillingSucursal ? 'Mostrando usuarios agrupados por sucursal de facturacion' : 'Mostrando todos los usuarios' }}
                            @endif
                        @endif
                    </div>
                    <div class="users-muted small">
                        Total: <strong>{{ $groupByBillingSucursal ? $groupedUsers->sum(fn ($group) => $group['users']->count()) : $users->total() }}</strong>
                    </div>
                </div>

                @if($groupByBillingSucursal)
                    <div class="mb-3 users-muted small">
                        Sucursales encontradas: <strong>{{ $groupedUsers->count() }}</strong>
                    </div>

                    @forelse ($groupedUsers as $group)
                        <section class="users-group-card">
                            <div class="users-group-card__head d-flex justify-content-between align-items-start" style="gap: 12px;">
                                <div>
                                    <h5 class="users-group-card__title">{{ $group['label'] }}</h5>
                                    <div class="users-group-card__meta">{{ $group['meta'] !== '' ? $group['meta'] : 'Sin detalle adicional.' }}</div>
                                </div>
                                <span class="users-group-kpi">{{ $group['users']->count() }}</span>
                            </div>
                            <ul class="users-group-list">
                                @foreach ($group['users'] as $user)
                                    <li class="users-group-list__item">
                                        <div class="users-group-user">
                                            <strong>{{ $user->name }}</strong>
                                            <span>{{ $user->email }}</span><br>
                                            <small>Alias: {{ $user->alias ?: '-' }}</small>
                                        </div>
                                        <div class="users-group-user">
                                            <small>Regional</small>
                                            <strong>{{ $user->regionalesTexto() ?: '-' }}</strong>
                                        </div>
                                        <div class="users-group-user">
                                            <small>Estado</small><br>
                                            <span class="badge {{ $user->trashed() ? 'badge-danger' : 'badge-success' }}">
                                                {{ $user->trashed() ? 'Inactivo' : 'Activo' }}
                                            </span>
                                        </div>
                                        <div class="users-group-roles">
                                            @forelse ($user->roles as $role)
                                                <span class="badge-role">{{ $role->name }}</span>
                                            @empty
                                                <small>Sin rol</small>
                                            @endforelse
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @empty
                        <div class="text-center py-4 users-muted">No se encontraron usuarios para agrupar.</div>
                    @endforelse
                @else
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
                                    <td>{{ $user->regionalesTexto() ?: '-' }}</td>
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
                                                        title="Cambiar contraseña"
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
                @endif
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
                                    <input type="text" wire:model.defer="alias" class="form-control" placeholder="Ej: juan.perez+lp" required>
                                    <small class="text-muted">Se permiten caracteres especiales. Evita solo duplicados.</small>
                                    @error('alias') <small class="text-danger d-block">{{ $message }}</small> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ $editingId ? 'Nueva contraseña (opcional)' : 'Contraseña' }}</label>
                                    <input type="password" wire:model.defer="password" class="form-control" placeholder="Contraseña">
                                    @error('password') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Regional</label>
                                    <div class="regional-picker">
                                        <div class="regional-picker-grid">
                                        @foreach($regionales as $regional)
                                            <div class="regional-option">
                                                <input
                                                    type="checkbox"
                                                    wire:model.defer="regionalesSeleccionadas"
                                                    id="regional_{{ \Illuminate\Support\Str::slug($regional) }}"
                                                    value="{{ $regional }}"
                                                >
                                                <label class="regional-option-label" for="regional_{{ \Illuminate\Support\Str::slug($regional) }}">
                                                    {{ $regional }}
                                                </label>
                                            </div>
                                        @endforeach
                                        </div>
                                    </div>
                                    <small class="text-muted">Puedes seleccionar varias regionales. La primera seleccionada sera la regional principal.</small>
                                    @error('regionalesSeleccionadas') <small class="text-danger d-block">{{ $message }}</small> @enderror
                                    @error('regionalesSeleccionadas.*') <small class="text-danger d-block">{{ $message }}</small> @enderror
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
                                    <small class="text-muted d-block">La sucursal de facturacion no cambia las regionales seleccionadas.</small>
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
                        <h5 class="modal-title">Cambiar contraseña</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-0">
                            <label>Nueva contraseña</label>
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

    const usersImportForm = document.getElementById('users-import-form');
    if (usersImportForm && !window.__usersImportProgressBound) {
        window.__usersImportProgressBound = true;

        usersImportForm.addEventListener('submit', function () {
            const progressWrap = document.getElementById('users-import-progress');
            const progressBar = document.getElementById('users-import-progress-bar');
            const progressText = document.getElementById('users-import-progress-text');
            const progressTime = document.getElementById('users-import-progress-time');
            const submitButton = document.getElementById('users-import-submit');
            const startedAt = Date.now();

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Importando...';
            }

            if (progressWrap) {
                progressWrap.style.display = 'block';
            }

            function updateProgress() {
                const elapsedSeconds = Math.floor((Date.now() - startedAt) / 1000);
                const percent = Math.min(95, 8 + Math.floor(elapsedSeconds * 1.4));

                if (progressBar) {
                    progressBar.style.width = percent + '%';
                    progressBar.setAttribute('aria-valuenow', String(percent));
                    progressBar.textContent = percent + '%';
                }

                if (progressTime) {
                    progressTime.textContent = elapsedSeconds + 's';
                }

                if (progressText) {
                    if (percent < 35) {
                        progressText.textContent = 'Leyendo archivo Excel...';
                    } else if (percent < 75) {
                        progressText.textContent = 'Validando y guardando usuarios...';
                    } else {
                        progressText.textContent = 'Finalizando importacion...';
                    }
                }
            }

            updateProgress();
            window.setInterval(updateProgress, 1000);
        });
    }
</script>
