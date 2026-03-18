<div>
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
                            <input type="text" wire:model="nombre" class="form-control @error('nombre') is-invalid @enderror" required>
                            @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Pais de Origen</label>
                            <input type="text" wire:model="pais_origen" class="form-control" list="paises-origen-lista" placeholder="Seleccionar o escribir pais">
                            <datalist id="paises-origen-lista">
                                <option value="Japon"></option>
                                <option value="Corea del Sur"></option>
                                <option value="China"></option>
                                <option value="India"></option>
                                <option value="Tailandia"></option>
                                <option value="Indonesia"></option>
                                <option value="Estados Unidos"></option>
                                <option value="Mexico"></option>
                                <option value="Canada"></option>
                                <option value="Brasil"></option>
                                <option value="Argentina"></option>
                                <option value="Alemania"></option>
                                <option value="Francia"></option>
                                <option value="Italia"></option>
                                <option value="Espana"></option>
                                <option value="Reino Unido"></option>
                                <option value="Suecia"></option>
                                <option value="Republica Checa"></option>
                                <option value="Turquia"></option>
                                <option value="Sudafrica"></option>
                                <option value="Rusia"></option>
                                <option value="Hungria"></option>
                            </datalist>
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
                    <input
                        type="text"
                        class="form-control"
                        wire:model.live.debounce.350ms="search"
                        placeholder="Buscar por cualquier campo">
                </div>
                @if($brands->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Pais de Origen</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($brands as $brand)
                                    <tr>
                                        <td>{{ $brand->nombre }}</td>
                                        <td>{{ $brand->pais_origen ?? '-' }}</td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button wire:click="edit({{ $brand->id }})" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></button>
                                                <button wire:click="delete({{ $brand->id }})" onclick="return confirm('Confirmar eliminacion?')" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
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
