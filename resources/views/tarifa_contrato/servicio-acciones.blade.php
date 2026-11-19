@php $editando = $formularioServicio === 'editar-'.$servicio->id; @endphp
<dialog id="servicio-acciones-{{ $servicio->id }}" class="tarifario-modal" aria-labelledby="servicio-acciones-titulo-{{ $servicio->id }}" @if($editando) data-acciones-reabrir @endif>
    <header class="tarifario-modal-cabecera">
        <div>
            <h2 id="servicio-acciones-titulo-{{ $servicio->id }}">Acciones del servicio</h2>
            <p>{{ $servicio->nombre }} · {{ $servicio->anio ?? 'Año por definir' }}</p>
        </div>
        <form method="dialog">
            <button type="submit" class="btn tarifario-modal-cerrar" aria-label="Cerrar acciones" autofocus>
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </form>
    </header>
    <div class="tarifario-panel-contenido">
        @if($editando && $errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <div class="tarifario-opciones-secundarias mt-0">
            @if($puedeGuardar)
                <details class="tarifario-desplegable" @if($editando || !$puedeEliminar) open @endif>
                    <summary>Editar nombre y año</summary>
                    <form method="POST" action="{{ route('tarifa-contrato.servicios-update', $servicio) }}" class="mt-2">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="formulario_servicio" value="editar-{{ $servicio->id }}">
                        <div class="form-group">
                            <label for="nombre-{{ $servicio->id }}">Nombre del servicio *</label>
                            <input class="form-control" id="nombre-{{ $servicio->id }}" name="nombre" value="{{ $editando ? old('nombre') : $servicio->nombre }}" maxlength="255" required>
                        </div>
                        <div class="form-group">
                            <label for="anio-{{ $servicio->id }}">Año *</label>
                            <input type="number" class="form-control" id="anio-{{ $servicio->id }}" name="anio" value="{{ $editando ? old('anio') : $servicio->anio }}" min="2000" max="2100" required>
                        </div>
                        <button type="submit" class="btn btn-sm tarifario-boton-azul">Guardar cambios</button>
                    </form>
                </details>
            @endif
            @if($puedeEliminar)
                <details class="tarifario-desplegable">
                    <summary class="text-danger">Eliminar servicio</summary>
                    <form method="POST" action="{{ route('tarifa-contrato.servicios-destroy', $servicio) }}" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <p class="small mb-2">¿Quieres quitar este servicio del catálogo? Podrás añadirlo de nuevo si lo necesitas.</p>
                        <button type="submit" class="btn btn-sm btn-outline-danger">Confirmar eliminación</button>
                    </form>
                </details>
            @endif
        </div>
    </div>
</dialog>
