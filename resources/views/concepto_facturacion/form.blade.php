<div class="box box-info padding-1">
    <div class="box-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $concepto->nombre ?? '') }}" class="form-control @error('nombre') is-invalid @enderror" required>
                    @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label for="actividad_economica">Actividad economica</label>
                    <input type="text" id="actividad_economica" name="actividad_economica" value="{{ old('actividad_economica', $concepto->actividad_economica ?? '') }}" class="form-control @error('actividad_economica') is-invalid @enderror" maxlength="6" required>
                    @error('actividad_economica')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label for="codigo_sin">Codigo SIN</label>
                    <input type="text" id="codigo_sin" name="codigo_sin" value="{{ old('codigo_sin', $concepto->codigo_sin ?? '') }}" class="form-control @error('codigo_sin') is-invalid @enderror" maxlength="7" required>
                    @error('codigo_sin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="codigo">Codigo</label>
                    <input type="text" id="codigo" name="codigo" value="{{ old('codigo', $concepto->codigo ?? '') }}" class="form-control @error('codigo') is-invalid @enderror" maxlength="50" required>
                    @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="unidad_medida">Unidad de medida</label>
                    <input type="number" id="unidad_medida" name="unidad_medida" value="{{ old('unidad_medida', $concepto->unidad_medida ?? 58) }}" class="form-control @error('unidad_medida') is-invalid @enderror" min="1" required>
                    @error('unidad_medida')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="precio_base">Precio base</label>
                    <input type="number" step="0.01" min="0" id="precio_base" name="precio_base" value="{{ old('precio_base', isset($concepto->precio_base) ? number_format((float) $concepto->precio_base, 2, '.', '') : '0.00') }}" class="form-control @error('precio_base') is-invalid @enderror" required>
                    @error('precio_base')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group mb-3">
                    <label for="descripcion">Descripcion</label>
                    <textarea id="descripcion" name="descripcion" rows="3" class="form-control @error('descripcion') is-invalid @enderror" required>{{ old('descripcion', $concepto->descripcion ?? '') }}</textarea>
                    @error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-check mb-3">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" id="activo" name="activo" value="1" class="form-check-input" @checked(old('activo', $concepto->activo ?? true))>
                    <label class="form-check-label" for="activo">Activo</label>
                </div>
            </div>
        </div>
    </div>

    <div class="box-footer mt20">
        <div class="d-flex justify-content-between">
            <a href="{{ route('conceptos-facturacion.index') }}" class="btn btn-secondary">Volver</a>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </div>
</div>
