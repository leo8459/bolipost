<div class="modal fade" id="empresaSucursalesModal" tabindex="-1" aria-labelledby="empresaSucursalesTitulo" aria-hidden="true" wire:ignore.self>
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="empresaSucursalesTitulo">
                    Sucursales @if($empresaSucursales) de {{ $empresaSucursales->nombre }} @endif
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            @if($empresaSucursales)
                <div class="modal-body">
                    @if(session('sucursales_success'))
                        <div class="alert alert-success" role="status">{{ session('sucursales_success') }}</div>
                    @endif
                    @error('sucursalEliminar')
                        <div class="alert alert-danger" role="alert">{{ $message }}</div>
                    @enderror

                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th scope="col">Sucursal</th>
                                    <th scope="col">Ciudad</th>
                                    <th scope="col">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($empresaSucursales->sucursales as $sucursal)
                                    <tr wire:key="empresa-sucursal-{{ $sucursal->id }}">
                                        <td>{{ $sucursal->nombre }}</td>
                                        <td>{{ $sucursal->ciudad ?: '—' }}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="editSucursal({{ $sucursal->id }})">Editar</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteSucursal({{ $sucursal->id }})"
                                                wire:confirm="¿Eliminar esta sucursal?">Eliminar</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted">Esta empresa aún no tiene sucursales registradas.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <form wire:submit="saveSucursal" class="border-top pt-3">
                        @php
                            $variasSucursales = count($sucursalFilas) > 1;
                            $ciudadAnterior = $empresaSucursales->sucursales->firstWhere('id', $sucursalEditingId)?->ciudad;
                        @endphp
                        <h6>{{ $sucursalEditingId ? 'Editar sucursal' : ($variasSucursales ? 'Nuevas sucursales' : 'Nueva sucursal') }}</h6>
                        @error('sucursalFilas')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror

                        @foreach(array_keys($sucursalFilas) as $index)
                            <div class="form-row" wire:key="sucursal-form-{{ $empresaSucursales->id }}-{{ $sucursalEditingId ?? 'nueva' }}-{{ $index }}">
                                <div class="form-group {{ $variasSucursales ? 'col-md-6' : 'col-md-7' }}">
                                    <label for="empresaSucursalNombre-{{ $index }}">Nombre de la sucursal</label>
                                    <input id="empresaSucursalNombre-{{ $index }}" type="text" wire:model="sucursalFilas.{{ $index }}.nombre" required maxlength="255"
                                        class="form-control @error('sucursalFilas.'.$index.'.nombre') is-invalid @enderror">
                                    @error('sucursalFilas.'.$index.'.nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="form-group col-md-5">
                                    <label for="empresaSucursalCiudad-{{ $index }}">Ciudad (opcional)</label>
                                    <select id="empresaSucursalCiudad-{{ $index }}" wire:model="sucursalFilas.{{ $index }}.ciudad"
                                        class="form-control @error('sucursalFilas.'.$index.'.ciudad') is-invalid @enderror">
                                        <option value="">Selecciona una ciudad</option>
                                        @foreach($ciudadesSucursal as $valor => $ciudad)
                                            <option value="{{ $valor }}">{{ $ciudad }}</option>
                                        @endforeach
                                        @if(filled($ciudadAnterior) && ! array_key_exists($ciudadAnterior, $ciudadesSucursal))
                                            <option value="{{ $ciudadAnterior }}">{{ $ciudadAnterior }} (valor anterior)</option>
                                        @endif
                                    </select>
                                    @error('sucursalFilas.'.$index.'.ciudad') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                @if($variasSucursales && ! $sucursalEditingId)
                                    <div class="form-group col-md-1 d-flex align-items-start">
                                        <button type="button" class="btn btn-outline-danger mt-md-4" wire:click="removeSucursalFila(@js($index))"
                                            aria-label="Quitar fila de sucursal" title="Quitar fila de sucursal" wire:loading.attr="disabled">
                                            <i class="fas fa-times" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        <div class="d-flex flex-wrap">
                            @if(! $sucursalEditingId)
                                <button type="button" class="btn btn-outline-primary mr-2 mb-2" wire:click="addSucursalFila" wire:loading.attr="disabled">
                                    <i class="fas fa-plus mr-1" aria-hidden="true"></i> Añadir otra sucursal
                                </button>
                            @endif
                            <button type="submit" class="btn btn-primary mr-2 mb-2" wire:loading.attr="disabled">
                                {{ $sucursalEditingId ? 'Guardar cambios' : ($variasSucursales ? 'Crear sucursales' : 'Crear sucursal') }}
                            </button>
                            @if($sucursalEditingId)
                                <button type="button" class="btn btn-secondary mb-2" wire:click="resetSucursalForm">Cancelar edición</button>
                            @endif
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
