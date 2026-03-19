<div class="box box-info padding-1">
    <div class="box-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="nombre_servicio">Nombre del servicio</label>
                    <input
                        type="text"
                        id="nombre_servicio"
                        name="nombre_servicio"
                        value="{{ old('nombre_servicio', $servicio->nombre_servicio ?? '') }}"
                        class="form-control @error('nombre_servicio') is-invalid @enderror"
                        required
                    >
                    @error('nombre_servicio')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label for="actividadEconomica">Actividad economica</label>
                    <input
                        type="text"
                        id="actividadEconomica"
                        name="actividadEconomica"
                        value="{{ old('actividadEconomica', $servicio->actividadEconomica ?? '') }}"
                        class="form-control @error('actividadEconomica') is-invalid @enderror"
                        maxlength="6"
                    >
                    @error('actividadEconomica')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label for="codigoSin">Codigo SIN</label>
                    <input
                        type="text"
                        id="codigoSin"
                        name="codigoSin"
                        value="{{ old('codigoSin', $servicio->codigoSin ?? '') }}"
                        class="form-control @error('codigoSin') is-invalid @enderror"
                        maxlength="7"
                    >
                    @error('codigoSin')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="codigo">Codigo</label>
                    <input
                        type="text"
                        id="codigo"
                        name="codigo"
                        value="{{ old('codigo', $servicio->codigo ?? '') }}"
                        class="form-control @error('codigo') is-invalid @enderror"
                        maxlength="50"
                    >
                    @error('codigo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="unidadMedida">Unidad de medida</label>
                    <input
                        type="number"
                        id="unidadMedida"
                        name="unidadMedida"
                        value="{{ old('unidadMedida', $servicio->unidadMedida ?? '') }}"
                        class="form-control @error('unidadMedida') is-invalid @enderror"
                        min="0"
                    >
                    @error('unidadMedida')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-12">
                <div class="form-group mb-3">
                    <label for="descripcion">Descripcion</label>
                    <textarea
                        id="descripcion"
                        name="descripcion"
                        rows="3"
                        class="form-control @error('descripcion') is-invalid @enderror"
                    >{{ old('descripcion', $servicio->descripcion ?? '') }}</textarea>
                    @error('descripcion')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="box-footer mt20">
        <div class="d-flex justify-content-between">
            <a href="{{ route('servicios.index') }}" class="btn btn-secondary">Volver</a>
            @if(($servicio->exists ?? false))
                @aclcan('edit', null, 'servicios')
                <button type="submit" class="btn btn-primary">Guardar</button>
                @endaclcan
            @else
                @aclcan('create', null, 'servicios')
                <button type="submit" class="btn btn-primary">Guardar</button>
                @endaclcan
            @endif
        </div>
    </div>
</div>
