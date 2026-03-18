<div class="box box-info padding-1">
    <div class="box-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="nombre">Nombre</label>
                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        value="{{ old('nombre', $servicioExtra->nombre ?? '') }}"
                        class="form-control @error('nombre') is-invalid @enderror"
                        required
                    >
                    @error('nombre')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="descripcion">Descripcion</label>
                    <input
                        type="text"
                        id="descripcion"
                        name="descripcion"
                        value="{{ old('descripcion', $servicioExtra->descripcion ?? '') }}"
                        class="form-control @error('descripcion') is-invalid @enderror"
                    >
                    @error('descripcion')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="box-footer mt20">
        <div class="d-flex justify-content-between">
            <a href="{{ route('servicio-extras.index') }}" class="btn btn-secondary">Volver</a>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </div>
</div>
