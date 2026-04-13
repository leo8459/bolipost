@php
    $editOnlyPhoto = $editOnlyPhoto ?? false;
@endphp

<style>
    .bitacora-form-shell {
        padding: 20px;
        background: #fff;
    }

    .bitacora-form-note {
        background: linear-gradient(135deg, #1b8ea5 0%, #2aa4bb 100%);
        color: #fff;
        border: 0;
        border-radius: 14px;
        padding: 18px 20px;
        margin-bottom: 1.25rem;
        box-shadow: 0 10px 24px rgba(30, 136, 168, 0.16);
    }

    .bitacora-form-note strong {
        color: #fff;
    }

    .bitacora-form-shell label {
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0.5rem;
    }

    .bitacora-form-shell .form-control,
    .bitacora-form-shell .form-control-file {
        min-height: 44px;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        box-shadow: none;
    }

    .bitacora-form-shell .form-control:focus,
    .bitacora-form-shell .form-control-file:focus {
        border-color: #20539A;
        box-shadow: 0 0 0 0.15rem rgba(32, 83, 154, 0.12);
    }

    .bitacora-form-shell .form-control-file {
        padding: 8px 10px;
        background: #fff;
    }

    .btn-dorado {
        background: #FECC36;
        color: #fff;
        font-weight: 800;
        border: none;
        border-radius: 12px;
        padding: 10px 18px;
    }

    .btn-dorado:hover {
        filter: brightness(.95);
        color: #fff;
    }

    .btn-outline-azul {
        border: 1px solid rgba(32, 83, 154, 0.25);
        color: #20539A;
        font-weight: 800;
        border-radius: 12px;
        padding: 10px 18px;
        background: #fff;
    }

    .btn-outline-azul:hover {
        background: rgba(32, 83, 154, 0.05);
        color: #20539A;
    }

    .bitacora-form-footer {
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid #e4e8f2;
    }

    .bitacora-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        flex-wrap: wrap;
    }
</style>

<div class="bitacora-form-shell">
        @if (!$editOnlyPhoto)
            <div class="bitacora-form-note">
                Usuario logueado: <strong>{{ auth()->user()->name ?? 'Usuario del sistema' }}</strong>
                <br>
                Escribe el <strong>cod_especial</strong> y el sistema registrara automaticamente una bitacora por cada paquete EMS, contrato y ordinario relacionado.
                <br>
                Para <strong>certificados</strong>, puedes ingresar su <strong>cod_especial</strong> o su <strong>codigo</strong> y tambien se registraran en la bitacora.
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="form-group mb-3">
                        <label for="cod_especial">Cod Especial</label>
                        <input
                            type="text"
                            id="cod_especial"
                            name="cod_especial"
                            value="{{ old('cod_especial', $bitacora->cod_especial) }}"
                            class="form-control @error('cod_especial') is-invalid @enderror"
                            placeholder="Ej: LPZ00001 o codigo certificado"
                        >
                        @error('cod_especial')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="transportadora">Transportadora</label>
                        <input
                            type="text"
                            id="transportadora"
                            name="transportadora"
                            value="{{ old('transportadora', $bitacora->transportadora) }}"
                            class="form-control @error('transportadora') is-invalid @enderror"
                        >
                        @error('transportadora')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="provincia">Provincia</label>
                        <input
                            type="text"
                            id="provincia"
                            name="provincia"
                            value="{{ old('provincia', $bitacora->provincia) }}"
                            class="form-control @error('provincia') is-invalid @enderror"
                        >
                        @error('provincia')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="factura">Factura</label>
                        <input
                            type="text"
                            id="factura"
                            name="factura"
                            value="{{ old('factura', $bitacora->factura) }}"
                            class="form-control @error('factura') is-invalid @enderror"
                        >
                        @error('factura')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="precio_total">Precio Total</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            id="precio_total"
                            name="precio_total"
                            value="{{ old('precio_total', $bitacora->precio_total) }}"
                            class="form-control @error('precio_total') is-invalid @enderror"
                            placeholder="Si lo dejas vacio se calculara por cod_especial"
                        >
                        @error('precio_total')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="peso">Peso</label>
                        <input
                            type="number"
                            step="0.001"
                            min="0"
                            id="peso"
                            name="peso"
                            value="{{ old('peso', $bitacora->peso) }}"
                            class="form-control @error('peso') is-invalid @enderror"
                            placeholder="Si lo dejas vacio se calculara por cod_especial"
                        >
                        @error('peso')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        @else
            <div class="alert alert-secondary">
                <div><strong>Cod. especial:</strong> {{ $bitacora->cod_especial ?: '-' }}</div>
                <div><strong>Factura:</strong> {{ $bitacora->factura ?: '-' }}</div>
                <div><strong>Transportadora:</strong> {{ $bitacora->transportadora ?: '-' }}</div>
                <div><strong>Usuario:</strong> {{ $bitacora->user->name ?? '-' }}</div>
            </div>

            <div class="form-group mb-3">
                <label for="factura">Factura</label>
                <input
                    type="text"
                    id="factura"
                    name="factura"
                    value="{{ old('factura', $bitacora->factura) }}"
                    class="form-control @error('factura') is-invalid @enderror"
                    placeholder="Puedes anadir o cambiar la factura despues"
                >
                @error('factura')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endif

        <div class="form-group mb-3">
            <label for="imagen_factura">Imagen Factura</label>
            <input
                type="file"
                id="imagen_factura"
                name="imagen_factura"
                class="form-control-file @error('imagen_factura') is-invalid @enderror"
                accept=".jpg,.jpeg,.png,.webp,.pdf"
            >
            @error('imagen_factura')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror

            @if(!empty($bitacora->imagen_factura))
                <div class="mt-2">
                    <a href="{{ asset('storage/' . $bitacora->imagen_factura) }}" target="_blank" class="btn btn-sm btn-outline-info">
                        Ver archivo actual
                    </a>
                </div>
            @endif
        </div>

    <div class="bitacora-form-footer">
        <div class="bitacora-form-actions">
            <a href="{{ route('bitacoras.index') }}" class="btn btn-outline-azul">Volver</a>
            <button type="submit" class="btn btn-dorado">Guardar</button>
        </div>
    </div>
</div>
