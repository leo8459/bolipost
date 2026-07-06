@php
    $editOnlyPhoto = $editOnlyPhoto ?? false;
    $facturaFechaEmisionValue = old('factura_fecha_emision');

    if ($facturaFechaEmisionValue === null && $bitacora->factura_fecha_emision) {
        $facturaFechaEmisionValue = $bitacora->factura_fecha_emision->format('Y-m-d\TH:i');
    } elseif (is_string($facturaFechaEmisionValue) && trim($facturaFechaEmisionValue) !== '') {
        try {
            $facturaFechaEmisionValue = \Carbon\Carbon::parse($facturaFechaEmisionValue)->format('Y-m-d\TH:i');
        } catch (\Throwable $e) {
        }
    }
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

    .bitacora-form-helper {
        display: block;
        margin-top: 0.45rem;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .bitacora-form-helper.is-info {
        color: #20539A;
    }

    .bitacora-form-helper.is-warning {
        color: #b45309;
    }

    .bitacora-qr-result {
        display: none;
        margin-top: 0.85rem;
        padding: 0.9rem 1rem;
        border-radius: 12px;
        font-size: 0.92rem;
        font-weight: 600;
    }

    .bitacora-qr-result.is-success {
        display: block;
        background: #ecfdf3;
        color: #166534;
        border: 1px solid #86efac;
    }

    .bitacora-qr-result.is-warning {
        display: block;
        background: #fff7ed;
        color: #9a3412;
        border: 1px solid #fdba74;
    }

    .bitacora-file-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 0.75rem;
    }

    .bitacora-file-option {
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        padding: 12px;
        background: #f8fafc;
    }

    .bitacora-file-option-title {
        display: block;
        color: #0f172a;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    .bitacora-scanner-panel {
        display: none;
        margin-top: 0.85rem;
        padding: 12px;
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        background: #0f172a;
        color: #fff;
    }

    .bitacora-scanner-panel.is-active {
        display: block;
    }

    .bitacora-scanner-video {
        width: 100%;
        max-height: 360px;
        border-radius: 12px;
        background: #020617;
        object-fit: cover;
    }

    .bitacora-scanner-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 0.75rem;
    }

    @media (max-width: 768px) {
        .bitacora-file-actions {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="bitacora-form-shell">
        @if (!$editOnlyPhoto)
            <div class="bitacora-form-note">
                Usuario logueado: <strong>{{ auth()->user()->name ?? 'Usuario del sistema' }}</strong>
                <br>
                Sube primero una <strong>imagen o PDF de la factura para su lectura</strong>. El sistema intentara leer el QR y completar los datos automaticamente.
                <br>
                Escribe el <strong>cod_especial</strong> y el sistema registrara automaticamente una bitacora por cada paquete EMS, contrato y ordinario relacionado.
                <br>
                Para <strong>certificados</strong>, puedes ingresar su <strong>cod_especial</strong> o su <strong>codigo</strong> y tambien se registraran en la bitacora.
            </div>

            <div class="form-group mb-3">
                <label for="imagen_factura">Imagen o PDF de la Factura</label>
                <small class="bitacora-form-helper is-info">En celular puedes escanear el QR en vivo. En PC puedes subir una imagen o PDF de la factura.</small>
                <div class="bitacora-file-actions">
                    <div class="bitacora-file-option">
                        <span class="bitacora-file-option-title">Escanear QR con camara</span>
                        <button type="button" id="start_qr_scanner" class="btn btn-outline-azul btn-block">
                            Iniciar lector
                        </button>
                    </div>

                    <div class="bitacora-file-option">
                        <span class="bitacora-file-option-title">Subir imagen o PDF</span>
                        <input
                            type="file"
                            id="imagen_factura"
                            name="imagen_factura"
                            class="form-control-file js-factura-file @error('imagen_factura') is-invalid @enderror"
                            accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf"
                        >
                    </div>
                </div>

                <div id="bitacora-scanner-panel" class="bitacora-scanner-panel">
                    <video id="bitacora-scanner-video" class="bitacora-scanner-video" playsinline muted></video>
                    <div class="bitacora-scanner-actions">
                        <button type="button" id="stop_qr_scanner" class="btn btn-outline-light">
                            Detener lector
                        </button>
                    </div>
                </div>

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

            <div id="bitacora-qr-result" class="bitacora-qr-result"></div>
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
                        <small id="cn33-summary-help" class="bitacora-form-helper"></small>
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
                        <small class="bitacora-form-helper is-info">Si pegas el numero de despacho del CN-33, el peso se cargara automaticamente. Luego puedes cambiarlo manualmente.</small>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="factura_fecha_emision">Fecha Emision Factura</label>
                        <input
                            type="datetime-local"
                            id="factura_fecha_emision"
                            name="factura_fecha_emision"
                            value="{{ $facturaFechaEmisionValue }}"
                            class="form-control @error('factura_fecha_emision') is-invalid @enderror"
                        >
                        @error('factura_fecha_emision')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="factura_nit_emisor">NIT Emisor</label>
                        <input
                            type="text"
                            id="factura_nit_emisor"
                            name="factura_nit_emisor"
                            value="{{ old('factura_nit_emisor', $bitacora->factura_nit_emisor) }}"
                            class="form-control @error('factura_nit_emisor') is-invalid @enderror"
                        >
                        @error('factura_nit_emisor')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="factura_cuf">CUF</label>
                        <input
                            type="text"
                            id="factura_cuf"
                            name="factura_cuf"
                            value="{{ old('factura_cuf', $bitacora->factura_cuf) }}"
                            class="form-control @error('factura_cuf') is-invalid @enderror"
                        >
                        @error('factura_cuf')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="factura_razon_social">Razon Social Emisor</label>
                        <input
                            type="text"
                            id="factura_razon_social"
                            name="factura_razon_social"
                            value="{{ old('factura_razon_social', $bitacora->factura_razon_social) }}"
                            class="form-control @error('factura_razon_social') is-invalid @enderror"
                        >
                        @error('factura_razon_social')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="factura_cliente">Cliente Factura</label>
                        <input
                            type="text"
                            id="factura_cliente"
                            name="factura_cliente"
                            value="{{ old('factura_cliente', $bitacora->factura_cliente) }}"
                            class="form-control @error('factura_cliente') is-invalid @enderror"
                        >
                        @error('factura_cliente')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="qr_url">URL QR</label>
                        <input
                            type="url"
                            id="qr_url"
                            name="qr_url"
                            value="{{ old('qr_url', $bitacora->qr_url) }}"
                            class="form-control @error('qr_url') is-invalid @enderror"
                        >
                        @error('qr_url')
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

        <input type="hidden" id="qr_texto" name="qr_texto" value="{{ old('qr_texto', $bitacora->qr_texto) }}">

        <div class="form-group mb-3">
            <label for="factura_direccion">Direccion Factura</label>
            <input
                type="text"
                id="factura_direccion"
                name="factura_direccion"
                value="{{ old('factura_direccion', $bitacora->factura_direccion) }}"
                class="form-control @error('factura_direccion') is-invalid @enderror"
            >
            @error('factura_direccion')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

    <div class="bitacora-form-footer">
        <div class="bitacora-form-actions">
            <a href="{{ route('bitacoras.index') }}" class="btn btn-outline-azul">Volver</a>
            <button type="submit" class="btn btn-dorado">Guardar</button>
        </div>
    </div>
</div>

@if (!$editOnlyPhoto)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const codeInput = document.getElementById('cod_especial');
            const weightInput = document.getElementById('peso');
            const helpBox = document.getElementById('cn33-summary-help');
            const qrFileInputs = document.querySelectorAll('.js-factura-file');
            const qrResultBox = document.getElementById('bitacora-qr-result');
            const scannerStartButton = document.getElementById('start_qr_scanner');
            const scannerStopButton = document.getElementById('stop_qr_scanner');
            const scannerPanel = document.getElementById('bitacora-scanner-panel');
            const scannerVideo = document.getElementById('bitacora-scanner-video');
            const endpoint = @json(route('bitacoras.cn33-summary'));
            const qrEndpoint = @json(route('bitacoras.extract-factura-qr'));
            const qrTextEndpoint = @json(route('bitacoras.extract-factura-qr-text'));
            let debounceTimer = null;
            let lastFetchedCode = '';
            let scannerStream = null;
            let scannerDetector = null;
            let scannerFrameId = null;
            let scannerBusy = false;

            if (!codeInput || !weightInput || !helpBox) {
                return;
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

            const setQrMessage = (message, type = 'is-success') => {
                if (!qrResultBox) {
                    return;
                }

                qrResultBox.textContent = message;
                qrResultBox.className = 'bitacora-qr-result';
                if (message) {
                    qrResultBox.classList.add(type);
                }
            };

            const fillField = (id, value, transform = null) => {
                if (value === undefined || value === null || value === '') {
                    return;
                }

                const field = document.getElementById(id);
                if (!field) {
                    return;
                }

                field.value = typeof transform === 'function' ? transform(value) : value;
            };

            const toDateTimeLocal = (value) => {
                if (!value) {
                    return '';
                }

                const normalized = String(value).trim().replace(' ', 'T');
                return normalized.length >= 16 ? normalized.slice(0, 16) : normalized;
            };

            const applyQrPayload = (payload) => {
                const data = payload.data || {};
                fillField('factura', data.factura);
                fillField('precio_total', data.precio_total);
                fillField('qr_url', data.qr_url);
                fillField('qr_texto', data.qr_texto);
                fillField('factura_fecha_emision', data.factura_fecha_emision, toDateTimeLocal);
                fillField('factura_nit_emisor', data.factura_nit_emisor);
                fillField('factura_cuf', data.factura_cuf);
                fillField('factura_razon_social', data.factura_razon_social);
                fillField('factura_cliente', data.factura_cliente);
                fillField('factura_direccion', data.factura_direccion);

                const verificationText = data.verificado
                    ? 'Se verificaron datos desde SIAT.'
                    : 'El QR solo trae NIT, CUF y numero de factura; los demas campos se llenan cuando SIAT devuelve el detalle.';
                setQrMessage(`${payload.message} ${verificationText}`.trim(), data.verificado ? 'is-success' : 'is-warning');
            };

            const processQrFile = async (fileInput) => {
                if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                    return;
                }

                qrFileInputs.forEach((input) => {
                    input.removeAttribute('name');
                });
                fileInput.setAttribute('name', 'imagen_factura');

                const formData = new FormData();
                formData.append('imagen_factura', fileInput.files[0]);
                formData.append('_token', @json(csrf_token()));
                setQrMessage('Leyendo QR del archivo...', 'is-success');

                try {
                    const response = await fetch(qrEndpoint, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    const payload = await response.json();
                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'No se pudo leer el QR del archivo.');
                    }

                    applyQrPayload(payload);
                } catch (error) {
                    setQrMessage(error.message || 'No se pudo leer el QR del archivo.', 'is-warning');
                }
            };

            const stopScanner = () => {
                if (scannerFrameId) {
                    window.cancelAnimationFrame(scannerFrameId);
                    scannerFrameId = null;
                }

                if (scannerStream) {
                    scannerStream.getTracks().forEach((track) => track.stop());
                    scannerStream = null;
                }

                scannerBusy = false;
                if (scannerPanel) {
                    scannerPanel.classList.remove('is-active');
                }
            };

            const processQrText = async (qrText) => {
                if (!qrText || scannerBusy) {
                    return;
                }

                scannerBusy = true;
                setQrMessage('QR detectado. Consultando datos de factura...', 'is-success');

                try {
                    const formData = new FormData();
                    formData.append('qr_text', qrText);
                    formData.append('_token', @json(csrf_token()));

                    const response = await fetch(qrTextEndpoint, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    const payload = await response.json();
                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'No se pudo procesar el QR detectado.');
                    }

                    stopScanner();
                    applyQrPayload(payload);
                } catch (error) {
                    scannerBusy = false;
                    setQrMessage(error.message || 'No se pudo procesar el QR detectado.', 'is-warning');
                }
            };

            const scanFrame = async () => {
                if (!scannerDetector || !scannerVideo || scannerBusy || !scannerStream) {
                    return;
                }

                try {
                    if (scannerVideo.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
                        const codes = await scannerDetector.detect(scannerVideo);
                        if (codes && codes.length > 0) {
                            const value = codes[0].rawValue || codes[0].rawData || '';
                            if (value) {
                                await processQrText(value);
                                return;
                            }
                        }
                    }
                } catch (error) {
                    setQrMessage('No se pudo leer el QR en vivo. Intenta acercar la camara o usar la subida de imagen.', 'is-warning');
                }

                scannerFrameId = window.requestAnimationFrame(scanFrame);
            };

            const startScanner = async () => {
                if (!('BarcodeDetector' in window)) {
                    setQrMessage('Tu navegador no soporta lector QR en vivo. Usa Chrome en Android o sube la imagen/PDF.', 'is-warning');
                    return;
                }

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    setQrMessage('El navegador no permite acceder a la camara. Usa la subida de imagen/PDF.', 'is-warning');
                    return;
                }

                try {
                    scannerDetector = new BarcodeDetector({ formats: ['qr_code'] });
                    scannerStream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: { ideal: 'environment' },
                            width: { ideal: 1280 },
                            height: { ideal: 720 }
                        },
                        audio: false
                    });

                    scannerVideo.srcObject = scannerStream;
                    scannerPanel.classList.add('is-active');
                    await scannerVideo.play();
                    setQrMessage('Lector activo. Apunta la camara al QR de la factura.', 'is-success');
                    scannerFrameId = window.requestAnimationFrame(scanFrame);
                } catch (error) {
                    stopScanner();
                    setQrMessage('No se pudo abrir la camara. En celular puede requerir HTTPS; si falla, usa la subida de imagen/PDF.', 'is-warning');
                }
            };

            qrFileInputs.forEach((input) => {
                input.addEventListener('change', function () {
                    processQrFile(input);
                });
            });

            if (scannerStartButton) {
                scannerStartButton.addEventListener('click', startScanner);
            }

            if (scannerStopButton) {
                scannerStopButton.addEventListener('click', stopScanner);
            }
        });
    </script>
@endif
