@php
    $editOnlyPhoto = $editOnlyPhoto ?? false;
@endphp

<style>
    .bitacora-form-shell {
        padding: 20px;
        background: #fff;
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

    .bitacora-form-helper {
        display: block;
        margin-top: 0.45rem;
        font-size: 0.85rem;
        font-weight: 600;
        color: #20539A;
    }

    .text-uppercase-live {
        text-transform: uppercase;
    }

    .bitacora-photo-card {
        margin-top: 1rem;
        padding: 1rem;
        border: 1px dashed #cbd5e1;
        border-radius: 14px;
        background: #f8fbff;
    }

    .bitacora-photo-preview {
        display: none;
        width: 100%;
        max-width: 340px;
        max-height: 320px;
        object-fit: contain;
        margin-top: 0.9rem;
        border-radius: 12px;
        border: 1px solid #d7deea;
        background: #fff;
        padding: 0.4rem;
    }

    .bitacora-photo-preview.is-visible {
        display: block;
    }

</style>

<div class="bitacora-form-shell">
        @if (!$editOnlyPhoto)
            <div class="alert alert-info">
                Usuario logueado: <strong>{{ auth()->user()->name ?? 'Usuario del sistema' }}</strong>
                <br>
                Escribe el <strong>cod_especial</strong>; el sistema puede cargar datos de apoyo, pero solo se guardara la bitacora manual que registres aqui.
            </div>

            <div class="form-group mb-3">
                <div class="col-md-12">
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
                    <small id="cn33-summary-help" class="bitacora-form-helper"></small>
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
                            class="form-control text-uppercase-live @error('transportadora') is-invalid @enderror"
                            style="text-transform: uppercase;"
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
                        <small class="bitacora-form-helper is-info">Si pegas el numero de despacho del CN-33, el peso se cargara automaticamente. Luego puedes cambiarlo manualmente.</small>
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

        <div class="bitacora-photo-card">
            <div class="form-group mb-0">
                <label for="imagen_factura">Foto o factura</label>
                <input
                    type="file"
                    id="imagen_factura"
                    name="imagen_factura"
                    accept=".jpg,.jpeg,.png,.webp,.pdf"
                    class="form-control-file @error('imagen_factura') is-invalid @enderror"
                >
                @error('imagen_factura')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <small class="bitacora-form-helper">Puedes subir JPG, PNG, WEBP o PDF. Si subes una imagen, el sistema la optimiza para que pese menos antes de guardarla.</small>
                @php
                    $existingImageUrl = $bitacora->imagen_factura ? asset('storage/' . $bitacora->imagen_factura) : null;
                    $existingImageExtension = $bitacora->imagen_factura ? strtolower(pathinfo((string) $bitacora->imagen_factura, PATHINFO_EXTENSION)) : null;
                    $canPreviewExistingImage = $existingImageUrl && in_array($existingImageExtension, ['jpg', 'jpeg', 'png', 'webp'], true);
                @endphp
                @if ($canPreviewExistingImage)
                    <img
                        src="{{ $existingImageUrl }}"
                        alt="Imagen actual de factura"
                        id="imagen_factura_preview"
                        class="bitacora-photo-preview is-visible"
                    >
                @else
                    <img
                        src=""
                        alt="Previsualizacion de la imagen"
                        id="imagen_factura_preview"
                        class="bitacora-photo-preview"
                    >
                @endif
                @if ($existingImageUrl)
                    <div class="mt-2">
                        <a href="{{ $existingImageUrl }}" target="_blank" class="btn btn-sm btn-outline-azul">Ver archivo actual</a>
                    </div>
                @endif
            </div>
        </div>

    <div class="bitacora-form-footer">
        <div class="bitacora-form-actions">
            <a href="{{ route('bitacoras.index') }}" class="btn btn-outline-azul">Volver</a>
            @if ($editOnlyPhoto || auth()->user()?->can('feature.bitacoras.index.save') || auth()->user()?->can('feature.bitacoras.create.save'))
                <button type="submit" class="btn btn-dorado">Guardar</button>
            @endif
        </div>
    </div>
</div>

@if (!$editOnlyPhoto)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const codeInput = document.getElementById('cod_especial');
            const weightInput = document.getElementById('peso');
            const transportadoraInput = document.getElementById('transportadora');
            const helpBox = document.getElementById('cn33-summary-help');
            const endpoint = @json(route('bitacoras.cn33-summary'));
            let debounceTimer = null;
            let lastFetchedCode = '';

            if (!codeInput || !weightInput || !helpBox) {
                return;
            }

            const normalizeUppercaseValue = (input) => {
                if (!input) {
                    return;
                }

                input.value = String(input.value || '').toUpperCase();
            };

            if (transportadoraInput) {
                normalizeUppercaseValue(transportadoraInput);
                transportadoraInput.addEventListener('input', function () {
                    normalizeUppercaseValue(transportadoraInput);
                });
                transportadoraInput.addEventListener('change', function () {
                    normalizeUppercaseValue(transportadoraInput);
                });
                transportadoraInput.addEventListener('blur', function () {
                    normalizeUppercaseValue(transportadoraInput);
                });
            }

            const setHelp = (message, type = '') => {
                helpBox.textContent = message;
                helpBox.className = 'bitacora-form-helper';
                if (type !== '') {
                    helpBox.classList.add(type);
                }
            };

            const loadSummary = async () => {
                const rawCode = (codeInput.value || '').trim().toUpperCase();
                if (rawCode === '' || rawCode === lastFetchedCode) {
                    return;
                }

                lastFetchedCode = rawCode;
                setHelp('Consultando peso del CN-33...', 'is-info');

                try {
                    const response = await fetch(`${endpoint}?cod_especial=${encodeURIComponent(rawCode)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    if (!response.ok) {
                        throw new Error('No se pudo consultar el CN-33.');
                    }

                    const data = await response.json();
                    if (!data || !data.exists) {
                        setHelp('No se encontro ese CN-33. Verifica el numero de despacho.', 'is-warning');
                        return;
                    }

                    weightInput.value = data.peso ?? '';
                    setHelp(`Peso cargado automaticamente: ${data.peso ?? '0.000'} kg. Puedes cambiarlo manualmente si lo necesitas.`, 'is-info');
                } catch (error) {
                    setHelp('No se pudo consultar el peso automatico del CN-33 en este momento.', 'is-warning');
                }
            };

            const scheduleSummaryLoad = () => {
                lastFetchedCode = '';
                clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(loadSummary, 350);
            };

            codeInput.addEventListener('input', scheduleSummaryLoad);
            codeInput.addEventListener('change', scheduleSummaryLoad);
            codeInput.addEventListener('blur', loadSummary);
            codeInput.addEventListener('paste', function () {
                window.setTimeout(scheduleSummaryLoad, 50);
            });

            if ((codeInput.value || '').trim() !== '') {
                loadSummary();
            }
        });
    </script>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const imageInput = document.getElementById('imagen_factura');
        const imagePreview = document.getElementById('imagen_factura_preview');

        if (!imageInput || !imagePreview) {
            return;
        }

        imageInput.addEventListener('change', function (event) {
            const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
            if (!file) {
                return;
            }

            if ((file.type || '').toLowerCase() === 'application/pdf') {
                imagePreview.removeAttribute('src');
                imagePreview.classList.remove('is-visible');
                return;
            }

            const objectUrl = URL.createObjectURL(file);
            imagePreview.src = objectUrl;
            imagePreview.classList.add('is-visible');
        });
    });
</script>
