<div class="box box-info padding-1">
    <div class="box-body">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label for="codigoSucursal">Codigo sucursal</label>
                    <input
                        type="number"
                        id="codigoSucursal"
                        name="codigoSucursal"
                        value="{{ old('codigoSucursal', $sucursal->codigoSucursal ?? '') }}"
                        class="form-control @error('codigoSucursal') is-invalid @enderror"
                        min="0"
                        required
                    >
                    @error('codigoSucursal')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label for="puntoVenta">Punto de venta</label>
                    <input
                        type="number"
                        id="puntoVenta"
                        name="puntoVenta"
                        value="{{ old('puntoVenta', $sucursal->puntoVenta ?? '') }}"
                        class="form-control @error('puntoVenta') is-invalid @enderror"
                        min="0"
                        required
                    >
                    @error('puntoVenta')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label for="municipio">Municipio</label>
                    <input
                        type="text"
                        id="municipio"
                        name="municipio"
                        value="{{ old('municipio', $sucursal->municipio ?? '') }}"
                        class="form-control @error('municipio') is-invalid @enderror"
                        maxlength="25"
                        required
                    >
                    @error('municipio')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label for="departamento">Departamento</label>
                    <input
                        type="text"
                        id="departamento"
                        name="departamento"
                        value="{{ old('departamento', $sucursal->departamento ?? '') }}"
                        class="form-control @error('departamento') is-invalid @enderror"
                        maxlength="15"
                    >
                    @error('departamento')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label for="telefono">Telefono</label>
                    <input
                        type="text"
                        id="telefono"
                        name="telefono"
                        value="{{ old('telefono', $sucursal->telefono ?? '') }}"
                        class="form-control @error('telefono') is-invalid @enderror"
                        maxlength="8"
                        required
                    >
                    @error('telefono')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="box-footer mt20">
        <div class="d-flex justify-content-between">
            <a href="{{ route('sucursales.index') }}" class="btn btn-secondary">Volver</a>
            @if(($sucursal->exists ?? false))
                @aclcan('edit', null, 'sucursales')
                <button type="submit" class="btn btn-primary">Guardar</button>
                @endaclcan
            @else
                @aclcan('create', null, 'sucursales')
                <button type="submit" class="btn btn-primary">Guardar</button>
                @endaclcan
            @endif
        </div>
    </div>
</div>
