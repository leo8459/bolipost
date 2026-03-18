<div>
    <style>
        .profile-card {
            border: 0;
            border-radius: 14px;
            overflow: hidden;
        }
        .profile-card .card-header {
            background: linear-gradient(120deg, #0d6efd 0%, #0a58ca 100%);
            color: #fff;
            border: 0;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        .profile-field {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 10px 12px;
            background: #fff;
        }
        .profile-label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            color: #6c757d;
            margin-bottom: 2px;
            letter-spacing: 0.04em;
        }
        .profile-value {
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
        }
    </style>

    <div class="page-title mb-4 d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">
            <i class="fas fa-users me-2 text-primary"></i>{{ auth()->user()?->role === 'conductor' ? 'Mi Perfil de Conductor' : 'Gestion de Conductores' }}
        </h1>
        @if(!$showForm && auth()->user()?->role !== 'conductor')
            <button type="button" wire:click="create" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nuevo Conductor
            </button>
        @endif
    </div>

    @if (session('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(auth()->user()?->role === 'conductor' && !$showForm)
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card profile-card shadow-sm h-100">
                    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-id-card me-2"></i>Mi Perfil de Conductor</span>
                        <span class="badge bg-light text-primary px-3 py-2">
                            {{ ($driverProfile?->activo ?? false) ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="profile-grid">
                            <div class="profile-field">
                                <span class="profile-label">Nombre</span>
                                <span class="profile-value">{{ $driverProfile?->nombre ?? '-' }}</span>
                            </div>
                            <div class="profile-field">
                                <span class="profile-label">Email</span>
                                <span class="profile-value">{{ $driverProfile?->email ?? $driverProfile?->user?->email ?? '-' }}</span>
                            </div>
                            <div class="profile-field">
                                <span class="profile-label">Telefono</span>
                                <span class="profile-value">{{ $driverProfile?->telefono ?? '-' }}</span>
                            </div>
                            <div class="profile-field">
                                <span class="profile-label">Licencia</span>
                                <span class="profile-value">{{ $driverProfile?->licencia ?? '-' }}</span>
                            </div>
                            <div class="profile-field">
                                <span class="profile-label">Tipo Licencia</span>
                                <span class="profile-value">{{ $driverProfile?->tipo_licencia ?? '-' }}</span>
                            </div>
                            <div class="profile-field">
                                <span class="profile-label">Vencimiento</span>
                                <span class="profile-value">{{ optional($driverProfile?->fecha_vencimiento_licencia)->format('d/m/Y') ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showForm)
        <div class="bp-gestiones-form-overlay">
        <div class="card shadow-sm mb-4 bp-gestiones-form-card">
            <div class="card-header">
                {{ $isEdit ? 'Editar Conductor' : 'Nuevo Conductor' }}
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="nombre" class="form-label fw-bold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="nombre" wire:model="nombre" class="form-control @error('nombre') is-invalid @enderror" required>
                            @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="user_id" class="form-label fw-bold">Usuario</label>
                            <select id="user_id" wire:model="user_id" class="form-select">
                                <option value="">Sin usuario</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="licencia" class="form-label fw-bold">Licencia</label>
                            <input type="text" id="licencia" wire:model="licencia" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="tipo_licencia" class="form-label fw-bold">Tipo Licencia</label>
                            <input type="text" id="tipo_licencia" wire:model="tipo_licencia" class="form-control" placeholder="A, B, C, etc.">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="fecha_vencimiento_licencia" class="form-label fw-bold">Vencimiento Licencia</label>
                            <input type="date" id="fecha_vencimiento_licencia" wire:model="fecha_vencimiento_licencia" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="telefono" class="form-label fw-bold">Telefono</label>
                            <input type="text" id="telefono" wire:model="telefono" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="email" class="form-label fw-bold">Email</label>
                            <input type="email" id="email" wire:model="email" class="form-control">
                        </div>
                        <div class="col-12 col-md-8">
                            <label for="memorandum_file" class="form-label fw-bold">Memorandum (imagen o PDF)</label>
                            <input type="file" id="memorandum_file" wire:model="memorandum_file" class="form-control @error('memorandum_file') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            @error('memorandum_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if($memorandum_path)
                                <div class="mt-2">
                                    <label class="form-label fw-bold mb-1">Ruta almacenada (BD)</label>
                                    <input type="text" class="form-control form-control-sm bg-light" value="{{ $memorandum_path }}" readonly>
                                </div>
                                <div class="form-text mt-1">
                                    Archivo actual:
                                    @if($editingDriverId)
                                        <a href="{{ route('drivers.memorandum.download', $editingDriverId) }}" target="_blank" rel="noopener noreferrer">abrir memorandum</a>
                                    @else
                                        <span class="text-muted">guarda primero para habilitar apertura</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="col-12 col-md-4 d-flex align-items-center">
                            <div class="form-check mt-md-4">
                                <input class="form-check-input" type="checkbox" id="activo" wire:model="activo">
                                <label class="form-check-label" for="activo">Activo</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i>{{ $isEdit ? 'Actualizar' : 'Guardar' }}
                        </button>
                        <button type="button" wire:click="cancelForm" class="btn btn-secondary">Volver al listado</button>
                    </div>
                </form>
            </div>
        </div>
        </div>
    @elseif(auth()->user()?->role !== 'conductor')
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="p-3 border-bottom">
                    <input
                        type="text"
                        class="form-control"
                        wire:model.live.debounce.350ms="search"
                        placeholder="Buscar por cualquier campo">
                </div>
                @if($drivers->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Licencia</th>
                                    <th>Telefono</th>
                                    <th>Estado</th>
                                    @if(auth()->user()?->role !== 'conductor')
                                        <th class="text-center">Acciones</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($drivers as $driver)
                                    <tr>
                                        <td>{{ $driver->nombre }}</td>
                                        <td>{{ $driver->licencia }}</td>
                                        <td>{{ $driver->telefono }}</td>
                                        <td>
                                            <span class="badge {{ $driver->activo ? 'bg-success' : 'bg-danger' }}">
                                                {{ $driver->activo ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        @if(auth()->user()?->role !== 'conductor')
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <button wire:click="edit({{ $driver->id }})" class="btn btn-sm btn-outline-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button wire:click="delete({{ $driver->id }})" onclick="return confirm('Confirmar eliminacion?')" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-5 text-center text-muted">
                        <i class="fas fa-users fa-3x mb-3 opacity-25"></i>
                        <h5>Sin conductores registrados</h5>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-4">
            {{ $drivers->links() }}
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body text-muted">
                Esta vista muestra solo su perfil personal de conductor.
            </div>
        </div>
    @endif
</div>
