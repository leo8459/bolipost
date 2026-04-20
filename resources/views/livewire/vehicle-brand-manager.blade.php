<div class="bp-livewire-skin">
    @include('livewire.partials.button-theme')
    <style>
        .brand-form-field {
            border-radius: 10px;
            min-height: calc(2.35rem + 2px);
            border: 1px solid #ced4da;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }
        .brand-form-field:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .15);
        }
        select.brand-form-field {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 2.2rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 16 16'%3E%3Cpath fill='%236c757d' d='M2.646 5.646a.5.5 0 0 1 .708 0L8 10.293l4.646-4.647a.5.5 0 0 1 .708.708l-5 5a.5.5 0 0 1-.708 0l-5-5a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .75rem center;
            background-size: 14px;
        }
    </style>

    <div class="page-title mb-4 d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0"><i class="fas fa-tags me-2 text-primary"></i>Marcas de Vehiculos</h1>
        @if(!$showForm)
            <button type="button" wire:click="create" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nueva Marca
            </button>
        @endif
    </div>

    @if (session('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($showForm)
        <div class="bp-gestiones-form-overlay">
        <div class="card shadow-sm mb-4 bp-gestiones-form-card">
            <div class="card-header">{{ $isEdit ? 'Editar Marca' : 'Nueva Marca' }}</div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Nombre *</label>
                            <input type="text" wire:model="nombre" class="form-control brand-form-field @error('nombre') is-invalid @enderror" required>
                            @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Pais de Origen *</label>
                            <select wire:model="pais_origen" class="form-control brand-form-field @error('pais_origen') is-invalid @enderror" required>
                                <option value="">Seleccionar pais</option>
                                @foreach($countryOptions as $country)
                                    <option value="{{ $country }}">{{ $country }}</option>
                                @endforeach
                            </select>
                            @error('pais_origen') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>{{ $isEdit ? 'Actualizar' : 'Guardar' }}</button>
                        <button type="button" wire:click="cancelForm" class="btn btn-secondary">Volver al listado</button>
                    </div>
                </form>
            </div>
        </div>
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="p-3 border-bottom">
                    <div class="row g-2">
                        <div class="col-12 col-md-8">
                            <input
                                type="text"
                                class="form-control"
                                wire:model.live.debounce.350ms="search"
                                placeholder="Buscar por cualquier campo">
                        </div>
                        <div class="col-12 col-md-4">
                            <select class="form-control brand-form-field" wire:model.live="statusFilter">
                                <option value="todos">Todos</option>
                                <option value="activos">Activos</option>
                                <option value="inactivos">Inactivos</option>
                            </select>
                        </div>
                    </div>
                </div>
                @if($brands->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Pais de Origen</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($brands as $brand)
                                    <tr>
                                        <td>{{ $brand->nombre }}</td>
                                        <td>{{ $brand->pais_origen ?? '-' }}</td>
                                        <td>
                                            <span class="badge {{ $brand->activo ? 'bg-success' : 'bg-danger' }}">
                                                {{ $brand->activo ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button wire:click="edit({{ $brand->id }})" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></button>
                                                @if($brand->activo)
                                                    <button wire:click="delete({{ $brand->id }})" onclick="return confirm('Confirmar eliminacion?')" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                @else
                                                    <button wire:click="reactivate({{ $brand->id }})" onclick="return confirm('Confirmar reactivacion?')" class="btn btn-sm btn-outline-success"><i class="fas fa-power-off"></i></button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-5 text-center text-muted">
                        <i class="fas fa-tags fa-3x mb-3 opacity-25"></i>
                        <h5>No hay marcas registradas</h5>
                    </div>
                @endif
            </div>
        </div>
        <div class="mt-4">{{ $brands->links() }}</div>
    @endif
</div>
