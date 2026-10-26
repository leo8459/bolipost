@php
    $editOnlyPhoto = $editOnlyPhoto ?? false;
@endphp

<style>
    .bitacora-form-shell {
        --bitacora-ink: #172b4d;
        --bitacora-muted: #68788f;
        --bitacora-blue: #20539a;
        --bitacora-border: #e1e8f1;
        padding: 24px 28px 26px;
        background: #fff;
        color: var(--bitacora-ink);
    }

    .bitacora-form-shell label {
        display: block;
        margin-bottom: 0.45rem;
        color: #253750;
        font-size: 0.9rem;
        font-weight: 700;
    }

    .bitacora-form-shell .form-control,
    .bitacora-form-shell .form-control-file {
        min-height: 46px;
        border: 1px solid #cfd8e4;
        border-radius: 9px;
        box-shadow: none;
        color: #21344f;
        transition: border-color 0.16s ease, box-shadow 0.16s ease;
    }

    .bitacora-form-shell .form-control:focus,
    .bitacora-form-shell .form-control-file:focus {
        border-color: #20539A;
        box-shadow: 0 0 0 3px rgba(32, 83, 154, 0.12);
    }

    .btn-dorado {
        min-height: 44px;
        background: #20539a;
        color: #fff;
        font-weight: 700;
        border: 1px solid #1d4c8d;
        border-radius: 9px;
        padding: 0.65rem 1.15rem;
        transition: background-color 0.16s ease, box-shadow 0.16s ease;
    }

    .btn-dorado:hover {
        background: #173f75;
        color: #fff;
        box-shadow: 0 3px 9px rgba(32, 83, 154, 0.2);
    }

    .btn-outline-azul {
        min-height: 44px;
        border: 1px solid #cbd7e5;
        color: #315578;
        font-weight: 700;
        border-radius: 9px;
        padding: 0.65rem 1.15rem;
        background: #fff;
    }

    .btn-outline-azul:hover {
        background: #f5f8fc;
        border-color: #a9bfd9;
        color: #20539a;
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
        color: #68788f;
        font-size: 0.82rem;
        font-weight: 500;
        line-height: 1.45;
    }

    .text-uppercase-live {
        text-transform: uppercase;
    }

    .bitacora-photo-card {
        margin-top: 0.4rem;
        padding: 1rem 1.1rem;
        border: 1px solid #e1e8f1;
        border-radius: 12px;
        background: #fbfcfe;
    }

    .bitacora-photo-card .form-control-file {
        width: 100%;
        height: auto;
        padding: 0.55rem;
        background: #fff;
    }

    .bitacora-photo-preview {
        display: none;
        width: 100%;
        max-width: 360px;
        max-height: 320px;
        object-fit: contain;
        margin-top: 0.9rem;
        border-radius: 10px;
        border: 1px solid #d7deea;
        background: #fff;
        padding: 0.4rem;
    }

    .bitacora-photo-preview.is-visible {
        display: block;
    }

    .bitacora-code-entry {
        padding: 1rem 1.1rem;
        border: 1px solid #dbe5f1;
        border-radius: 12px;
        background: linear-gradient(135deg, #f7faff 0%, #fff 100%);
    }

    .bitacora-code-entry .form-control {
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: 0.035em;
        text-transform: uppercase;
    }

    .bitacora-code-types {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin: 0.65rem 0 0.6rem;
    }

    .bitacora-code-type {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 0.25rem 0.65rem;
        border: 1px solid #d9e3ef;
        background: #fff;
        color: #3c5779;
        font-size: 0.74rem;
        font-weight: 700;
    }

    .bitacora-lookup-status {
        display: block;
        min-height: 2.65rem;
        margin-top: 0.5rem;
        padding: 0.55rem 0.7rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
        color: #52657d;
        font-size: 0.83rem;
        font-weight: 600;
        line-height: 1.45;
    }

    .bitacora-lookup-status.is-info {
        border-color: #d7e5f6;
        background: #f1f7fd;
        color: #20539a;
    }

    .bitacora-lookup-status.is-success {
        border-color: #cde8d7;
        background: #f0faf3;
        color: #176b3b;
    }

    .bitacora-lookup-status.is-warning {
        border-color: #f0dfbb;
        background: #fff9ec;
        color: #885b0b;
    }

    .bitacora-form-intro {
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        margin-bottom: 1.35rem;
        padding: 0.95rem 1rem;
        border: 1px solid #dce8f6;
        border-left: 4px solid #20539a;
        border-radius: 10px;
        background: #f6f9fd;
    }

    .bitacora-form-intro-icon {
        display: grid;
        flex: 0 0 34px;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 9px;
        background: #e4edf9;
        color: #20539a;
    }

    .bitacora-form-intro strong,
    .bitacora-form-intro span {
        display: block;
    }

    .bitacora-form-intro strong {
        margin-bottom: 0.15rem;
        color: #172b4d;
    }

    .bitacora-form-intro-copy {
        color: #68788f;
        font-size: 0.88rem;
        line-height: 1.45;
    }

    .bitacora-form-section {
        margin-bottom: 1.4rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid #edf1f6;
    }

    .bitacora-section-heading {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .bitacora-section-step {
        display: inline-flex;
        flex: 0 0 30px;
        width: 30px;
        height: 30px;
        align-items: center;
        justify-content: center;
        border-radius: 9px;
        background: #eaf0f8;
        color: #20539a;
        font-size: 0.78rem;
        font-weight: 800;
    }

    .bitacora-section-heading h4 {
        margin: 0;
        color: #172b4d;
        font-size: 1rem;
        font-weight: 800;
    }

    .bitacora-section-heading p {
        margin: 0.18rem 0 0;
        color: #68788f;
        font-size: 0.84rem;
    }

    .bitacora-form-section .form-group {
        margin-bottom: 0.9rem;
    }

    .bitacora-form-shell .form-control::placeholder {
        color: #94a1b2;
        opacity: 1;
    }

    .bitacora-form-shell .form-control:read-only {
        background: #f4f6f9;
    }

    .bitacora-form-footer {
        margin-top: 1.35rem;
        padding-top: 1rem;
        border-top: 1px solid #e8edf3;
    }

    .bitacora-edit-summary {
        margin-bottom: 1rem;
        padding: 1rem 1.1rem;
        border: 1px solid #e1e8f1;
        border-radius: 10px;
        background: #f8fafc;
        color: #52657d;
        line-height: 1.8;
    }

    @media (max-width: 576px) {
        .bitacora-form-shell {
            padding: 14px;
        }

        .bitacora-code-entry {
            padding: 0.8rem;
        }

        .bitacora-form-actions {
            flex-direction: column-reverse;
        }

        .bitacora-form-actions .btn {
            width: 100%;
        }
    }

</style>

<div class="bitacora-form-shell">
        @if (!$editOnlyPhoto)
            <div class="bitacora-form-intro">
                <span class="bitacora-form-intro-icon" aria-hidden="true"><i class="fas fa-user-check"></i></span>
                <div>
                    <strong>{{ auth()->user()->name ?? 'Usuario del sistema' }}</strong>
                    <span class="bitacora-form-intro-copy">Registro manual de transporte, costos y evidencia para el envío.</span>
                </div>
            </div>

            <section class="bitacora-form-section" aria-labelledby="bitacora-identificacion-heading">
                <div class="bitacora-section-heading">
                    <span class="bitacora-section-step">01</span>
                    <div>
                        <h4 id="bitacora-identificacion-heading">Identificación del envío</h4>
                        <p>Ingresa el código especial para consultar los datos disponibles.</p>
                    </div>
                </div>
                <div class="bitacora-code-entry">
                    <div class="form-group mb-0">
                        <label for="cod_especial">Código especial <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        id="cod_especial"
                        name="cod_especial"
                        value="{{ old('cod_especial', $bitacora->cod_especial) }}"
                        class="form-control @error('cod_especial') is-invalid @enderror"
                        placeholder="Escribe o pega el código especial"
                        maxlength="50"
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        required
                    >
                        <div class="bitacora-code-types" aria-label="Tipos de código admitidos">
                            <span class="bitacora-code-type">CN-33</span>
                            <span class="bitacora-code-type">Solicitud</span>
                            <span class="bitacora-code-type">Delivery Express</span>
                        </div>
                        <small id="cn33-summary-help" class="bitacora-lookup-status" role="status" aria-live="polite">Al reconocer el código, el peso se cargará automáticamente si está disponible.</small>
                        @error('cod_especial')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="bitacora-form-section" aria-labelledby="bitacora-transporte-heading">
                <div class="bitacora-section-heading">
                    <span class="bitacora-section-step">02</span>
                    <div>
                        <h4 id="bitacora-transporte-heading">Transporte y facturación</h4>
                        <p>Completa el traslado y los datos de la factura asociada.</p>
                    </div>
                </div>
                <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="form-group mb-3">
                        <label for="tipo_transporte">Tipo de transporte <span class="text-danger">*</span></label>
                        <select
                            id="tipo_transporte"
                            name="tipo_transporte"
                            class="form-control @error('tipo_transporte') is-invalid @enderror"
                            required
                        >
                            <option value="">Selecciona aéreo o terrestre</option>
                            <option value="aereo" {{ old('tipo_transporte') === 'aereo' ? 'selected' : '' }}>Aéreo</option>
                            <option value="terrestre" {{ old('tipo_transporte') === 'terrestre' ? 'selected' : '' }}>Terrestre</option>
                        </select>
                        @error('tipo_transporte')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="form-group mb-3">
                        <label for="transportadora">Transportadora <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            id="transportadora"
                            name="transportadora"
                            value="{{ old('transportadora', $bitacora->transportadora) }}"
                            class="form-control text-uppercase-live @error('transportadora') is-invalid @enderror"
                            style="text-transform: uppercase;"
                            placeholder="Selecciona primero el tipo de transporte"
                            disabled
                        >
                        @error('transportadora')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="bitacora-form-helper">Para aéreo se asigna BOA automáticamente. Para terrestre, escribe la transportadora.</small>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="form-group mb-3">
                        <label for="provincia">Provincia <span class="text-muted">(opcional)</span></label>
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

                <div class="col-lg-3 col-md-6">
                    <div class="form-group mb-3">
                        <label for="factura">Factura <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            id="factura"
                            name="factura"
                            value="{{ old('factura', $bitacora->factura) }}"
                            class="form-control @error('factura') is-invalid @enderror"
                            required
                        >
                        @error('factura')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
            </section>

            <section class="bitacora-form-section" aria-labelledby="bitacora-costos-heading">
                <div class="bitacora-section-heading">
                    <span class="bitacora-section-step">03</span>
                    <div>
                        <h4 id="bitacora-costos-heading">Importe y peso</h4>
                        <p>Registra el monto facturado y el peso total del envío.</p>
                    </div>
                </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="precio_total">Precio Total <span class="text-danger">*</span></label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            id="precio_total"
                            name="precio_total"
                            value="{{ old('precio_total', $bitacora->precio_total) }}"
                            class="form-control @error('precio_total') is-invalid @enderror"
                            placeholder="Ingresa el precio total"
                            required
                        >
                        @error('precio_total')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="peso">Peso <span class="text-danger">*</span></label>
                        <input
                            type="number"
                            step="0.001"
                            min="0"
                            max="150"
                            id="peso"
                            name="peso"
                            value="{{ old('peso', $bitacora->peso) }}"
                            class="form-control @error('peso') is-invalid @enderror"
                            placeholder="Ingresa el peso"
                            required
                        >
                        @error('peso')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="bitacora-form-helper is-info">Máximo: 150 kg. El peso detectado se puede corregir manualmente.</small>
                    </div>
                </div>
            </div>
            </section>
        @else
            <div class="bitacora-edit-summary">
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

        <section class="bitacora-form-section bitacora-evidence-section" aria-labelledby="bitacora-evidencia-heading">
            <div class="bitacora-section-heading">
                <span class="bitacora-section-step">{{ $editOnlyPhoto ? '01' : '04' }}</span>
                <div>
                    <h4 id="bitacora-evidencia-heading">{{ $editOnlyPhoto ? 'Actualizar evidencia' : 'Factura o evidencia' }}</h4>
                    <p>Adjunta un archivo legible para respaldar el registro.</p>
                </div>
            </div>
        <div class="bitacora-photo-card">
            <div class="form-group mb-0">
                <label for="imagen_factura">Archivo de factura @if(!$editOnlyPhoto)<span class="text-danger">*</span>@endif</label>
                <input
                    type="file"
                    id="imagen_factura"
                    name="imagen_factura"
                    accept=".jpg,.jpeg,.png,.webp,.pdf"
                    class="form-control-file @error('imagen_factura') is-invalid @enderror"
                    @if(!$editOnlyPhoto) required @endif
                >
                @error('imagen_factura')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <small class="bitacora-form-helper">Formatos permitidos: JPG, PNG, WEBP o PDF. Las imágenes se optimizan al guardarse.</small>
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
        </section>

    <div class="bitacora-form-footer">
        <div class="bitacora-form-actions">
            <a href="{{ route('bitacoras.index') }}" class="btn btn-outline-azul">Cancelar</a>
            @if ($editOnlyPhoto || auth()->user()?->can('feature.bitacoras.index.save') || auth()->user()?->can('feature.bitacoras.create.save'))
                <button type="submit" class="btn btn-dorado">{{ $editOnlyPhoto ? 'Guardar cambios' : 'Registrar bitácora' }}</button>
            @endif
        </div>
    </div>
</div>

@if (!$editOnlyPhoto)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const codeInput = document.getElementById('cod_especial');
            const weightInput = document.getElementById('peso');
            const transportTypeInput = document.getElementById('tipo_transporte');
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

            if (transportTypeInput && transportadoraInput) {
                let previousTransportType = transportTypeInput.value;

                const syncTransportadora = (clearAutoBoa = false) => {
                    const isAereo = transportTypeInput.value === 'aereo';
                    const isTerrestre = transportTypeInput.value === 'terrestre';

                    if (isAereo) {
                        transportadoraInput.value = 'BOA';
                        transportadoraInput.readOnly = true;
                        transportadoraInput.disabled = false;
                        transportadoraInput.required = true;
                        transportadoraInput.placeholder = 'BOA';
                    } else if (isTerrestre) {
                        if (clearAutoBoa && previousTransportType === 'aereo' && transportadoraInput.value === 'BOA') {
                            transportadoraInput.value = '';
                        }
                        transportadoraInput.readOnly = false;
                        transportadoraInput.disabled = false;
                        transportadoraInput.required = true;
                        transportadoraInput.placeholder = 'Escribe la transportadora';
                    } else {
                        transportadoraInput.value = '';
                        transportadoraInput.readOnly = false;
                        transportadoraInput.disabled = true;
                        transportadoraInput.required = false;
                        transportadoraInput.placeholder = 'Selecciona primero el tipo de transporte';
                    }

                    previousTransportType = transportTypeInput.value;
                };

                syncTransportadora();
                transportTypeInput.addEventListener('change', function () {
                    syncTransportadora(true);
                });
            }

            const setHelp = (message, type = '') => {
                helpBox.textContent = message;
                helpBox.className = 'bitacora-lookup-status';
                if (type !== '') {
                    helpBox.classList.add(type);
                }
            };

            const loadSummary = async () => {
                const rawCode = (codeInput.value || '').trim().toUpperCase();
                if (rawCode === '') {
                    lastFetchedCode = '';
                    setHelp('Al reconocer el código, el peso se cargará automáticamente si está disponible.');
                    return;
                }

                if (rawCode === lastFetchedCode) {
                    return;
                }

                lastFetchedCode = rawCode;
                setHelp('Buscando el código en despachos, solicitudes y Delivery Express…', 'is-info');

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
                    if (rawCode !== (codeInput.value || '').trim().toUpperCase()) {
                        return;
                    }

                    if (!data || !data.exists) {
                        setHelp('No encontramos ese código en CN-33, solicitudes o Delivery Express. Verifica el código especial.', 'is-warning');
                        return;
                    }

                    weightInput.value = data.peso ?? '';
                    setHelp(`Código reconocido. Peso total: ${data.peso ?? '0.000'} kg. Puedes ajustarlo manualmente.`, 'is-success');
                } catch (error) {
                    if (rawCode === (codeInput.value || '').trim().toUpperCase()) {
                        setHelp('No se pudieron consultar los datos automáticos. Al guardar, el sistema confirmará que el código exista.', 'is-warning');
                    }
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
