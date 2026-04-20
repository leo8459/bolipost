@extends('adminlte::page')
@section('title', 'Carteros - Entrega')
@section('template_title')
    Entregar Correspondencia
@endsection

@section('content')
    @php
        $canEntregaDeliver = auth()->user()?->can('feature.carteros.entrega.deliver') ?? false;
        $canEntregaAttempt = auth()->user()?->can('feature.carteros.entrega.attempt') ?? false;
        $forceCameraCapture = in_array($tipo_paquete, ['CONTRATO', 'EMS', 'SOLICITUD'], true);
    @endphp
    <div class="carteros-wrap entrega-wrap">
        <div class="card card-carteros">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h3 class="card-title mb-0">Entregar Correspondencia</h3>
                    <span class="carteros-chip">{{ $tipo_paquete }}</span>
                </div>
            </div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0 pl-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row entrega-metadata mb-4">
                    <div class="col-md-6 col-lg-3 mb-2 mb-lg-0">
                        <div class="entrega-meta-box">
                            <span class="entrega-meta-label">Tipo</span>
                            <strong>{{ $tipo_paquete }}</strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-2 mb-lg-0">
                        <div class="entrega-meta-box">
                            <span class="entrega-meta-label">Codigo</span>
                            <strong>{{ $paquete['codigo'] ?? '' }}</strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-2 mb-lg-0">
                        <div class="entrega-meta-box">
                            <span class="entrega-meta-label">Destinatario</span>
                            <strong>{{ $paquete['destinatario'] ?? '' }}</strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-2 mb-lg-0">
                        <div class="entrega-meta-box">
                            <span class="entrega-meta-label">Ciudad</span>
                            <strong>{{ $paquete['ciudad'] ?? '' }}</strong>
                        </div>
                    </div>
                </div>

                <div class="entrega-actions-panel">
                    <div class="d-flex align-items-center justify-content-between flex-wrap mb-3">
                        <h5 class="mb-2 mb-md-0">Acciones de entrega</h5>
                        <span class="badge badge-pill badge-light entrega-badge">
                            Intentos: {{ (int) ($asignacion->intento ?? 0) }}
                        </span>
                    </div>

                    <div class="form-group mb-3">
                        <label for="descripcion_unica">Descripcion (opcional)</label>
                        <textarea id="descripcion_unica" rows="3" class="form-control"
                            placeholder="Escribe una sola descripcion para entrega o intento..."></textarea>
                    </div>

                    <div class="row">
                        @if ($canEntregaDeliver)
                            <div class="col-lg-7">
                                <form method="POST" action="{{ route('carteros.entrega.store') }}" class="mb-3 mb-lg-0 entrega-upload-form"
                                    enctype="multipart/form-data">
                                    @csrf
                                    <input type="hidden" name="tipo_paquete" value="{{ $tipo_paquete }}">
                                    <input type="hidden" name="id" value="{{ $id }}">
                                    <input type="hidden" name="descripcion" id="descripcion_entrega" value="">

                                    <div class="form-group mb-3">
                                        <label for="recibido_por">Recibido por</label>
                                        <input type="text" name="recibido_por" id="recibido_por" class="form-control"
                                            value="{{ old('recibido_por') }}" required>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="foto_entrega">Foto (obligatoria)</label>
                                        <input type="file" name="foto" id="foto_entrega" class="form-control-file foto-input"
                                            accept="image/*" @if ($forceCameraCapture) capture="environment" @endif data-preview-img="preview_entrega" required>
                                        <small class="text-muted d-block mt-1">
                                            @if ($forceCameraCapture)
                                                En celular se abre directamente la camara trasera. En PC se abre selector de archivos.
                                            @else
                                                En celular puedes elegir camara o galeria/archivos. En PC se abre selector de archivos.
                                            @endif
                                        </small>
                                        <small class="text-muted d-block mt-1 foto-upload-hint">
                                            Si la foto es muy pesada, se reduce automaticamente antes de enviar.
                                        </small>
                                        <small class="text-muted d-block mt-1 foto-size-label"></small>
                                        <div class="foto-preview-wrap mt-2 d-none" id="preview_entrega_wrap">
                                            <img id="preview_entrega" class="foto-preview-img" alt="Vista previa de foto de entrega">
                                        </div>
                                        <div class="small text-info mt-2 d-none foto-upload-status"></div>
                                    </div>

                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="submit" class="btn btn-success px-4">Confirmar entrega</button>
                                        @if (in_array($tipo_paquete, ['CONTRATO', 'EMS', 'SOLICITUD'], true))
                                            <button
                                                type="submit"
                                                formaction="{{ route('carteros.entrega.ida-vuelta') }}"
                                                class="btn btn-primary px-4"
                                            >
                                                Entregar paquete ida y vuelta
                                            </button>
                                        @endif
                                    </div>
                                </form>
                            </div>
                        @endif
                        @if ($canEntregaAttempt)
                            <div class="col-lg-5 d-flex align-items-end">
                                <form method="POST" action="{{ route('carteros.entrega.intento') }}" class="w-100 entrega-upload-form"
                                    enctype="multipart/form-data">
                                    @csrf
                                    <input type="hidden" name="tipo_paquete" value="{{ $tipo_paquete }}">
                                    <input type="hidden" name="id" value="{{ $id }}">
                                    <input type="hidden" name="descripcion" id="descripcion_intento" value="">
                                    <div class="form-group mb-3">
                                        <label for="foto_intento">Foto (obligatoria)</label>
                                        <input type="file" name="foto" id="foto_intento" class="form-control-file foto-input"
                                            accept="image/*" @if ($forceCameraCapture) capture="environment" @endif data-preview-img="preview_intento" required>
                                        <small class="text-muted d-block mt-1">
                                            @if ($forceCameraCapture)
                                                En celular se abre directamente la camara trasera. En PC se abre selector de archivos.
                                            @else
                                                En celular puedes elegir camara o galeria/archivos. En PC se abre selector de archivos.
                                            @endif
                                        </small>
                                        <small class="text-muted d-block mt-1 foto-upload-hint">
                                            Si la foto es muy pesada, se reduce automaticamente antes de enviar.
                                        </small>
                                        <small class="text-muted d-block mt-1 foto-size-label"></small>
                                        <div class="foto-preview-wrap mt-2 d-none" id="preview_intento_wrap">
                                            <img id="preview_intento" class="foto-preview-img" alt="Vista previa de foto de intento">
                                        </div>
                                        <div class="small text-info mt-2 d-none foto-upload-status"></div>
                                    </div>
                                    <button type="submit" class="btn btn-warning px-4">Agregar intento</button>
                                </form>
                            </div>
                        @endif
                        @if (! $canEntregaDeliver && ! $canEntregaAttempt)
                            <div class="col-12">
                                <div class="alert alert-warning mb-0">
                                    Esta ventana esta habilitada, pero tu rol no tiene botones operativos en entrega.
                                </div>
                            </div>
                        @endif
                    </div>

                    @php
                        $imagenAsignacion = $asignacion?->imagen ?? $asignacion?->foto;
                        $imagenDevolucion = $asignacion?->imagen_devolucion;
                    @endphp
                    @if (!empty($imagenAsignacion))
                        <div class="mt-4">
                            <small class="d-block text-muted mb-1">Ultima foto de entrega:</small>
                            <a href="{{ asset('storage/' . $imagenAsignacion) }}" target="_blank" rel="noopener">
                                <img src="{{ asset('storage/' . $imagenAsignacion) }}" class="foto-preview-img"
                                    alt="Foto registrada">
                            </a>
                        </div>
                    @endif
                    @if (!empty($imagenDevolucion))
                        <div class="mt-4">
                            <small class="d-block text-muted mb-1">Ultima foto de devolucion/intento:</small>
                            <a href="{{ asset('storage/' . $imagenDevolucion) }}" target="_blank" rel="noopener">
                                <img src="{{ asset('storage/' . $imagenDevolucion) }}" class="foto-preview-img"
                                    alt="Foto de devolucion">
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    @include('carteros.partials.theme')
    <style>
        .entrega-wrap .card-body {
            background: #f8faff;
        }

        .entrega-meta-box {
            background: #fff;
            border: 1px solid #e3e9f8;
            border-radius: 10px;
            padding: 0.7rem 0.85rem;
            min-height: 72px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: 0 6px 14px rgba(34, 56, 106, 0.06);
        }

        .entrega-meta-label {
            color: #5e6b86;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .entrega-actions-panel {
            background: #fff;
            border: 1px solid #e3e9f8;
            border-radius: 12px;
            padding: 1rem;
        }

        .entrega-badge {
            background: #edf2ff;
            color: #2b3967;
            border: 1px solid #d3ddf4;
            font-size: 0.82rem;
        }

        .foto-preview-wrap {
            border: 1px solid #dbe2f2;
            border-radius: 8px;
            background: #f8faff;
            padding: 0.4rem;
            display: inline-block;
        }

        .foto-preview-img {
            max-width: 220px;
            max-height: 160px;
            border-radius: 6px;
            object-fit: cover;
            display: block;
        }
    </style>
@endsection

@section('js')
    <script>
        (function() {
            const descripcion = document.getElementById('descripcion_unica');
            const entregaInput = document.getElementById('descripcion_entrega');
            const intentoInput = document.getElementById('descripcion_intento');
            const MAX_UPLOAD_BYTES = 4 * 1024 * 1024;
            const TARGET_MAX_BYTES = 1200 * 1024;
            const MAX_DIMENSION = 1600;

            if (!descripcion) return;

            descripcion.value = @json(old('descripcion', ''));

            const syncDescripcion = () => {
                const value = descripcion.value || '';
                if (entregaInput) {
                    entregaInput.value = value;
                }
                if (intentoInput) {
                    intentoInput.value = value;
                }
            };

            const formatBytesToMb = (bytes) => {
                if (!bytes || Number.isNaN(bytes)) {
                    return '';
                }

                return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
            };

            descripcion.addEventListener('input', syncDescripcion);
            syncDescripcion();

            document.querySelectorAll('.foto-input').forEach((input) => {
                input.addEventListener('change', function() {
                    const previewId = this.dataset.previewImg;
                    const img = document.getElementById(previewId);
                    const wrap = document.getElementById(previewId + '_wrap');
                    const file = this.files && this.files[0] ? this.files[0] : null;
                    const form = this.closest('form');
                    const sizeLabel = form ? form.querySelector('.foto-size-label') : null;

                    if (!img || !wrap) return;

                    if (!file) {
                        img.removeAttribute('src');
                        wrap.classList.add('d-none');
                        if (sizeLabel) {
                            sizeLabel.textContent = '';
                        }
                        return;
                    }

                    if (sizeLabel) {
                        sizeLabel.textContent = 'Peso actual: ' + formatBytesToMb(file.size);
                    }

                    const objectUrl = URL.createObjectURL(file);
                    img.src = objectUrl;
                    wrap.classList.remove('d-none');
                    img.onload = function() {
                        URL.revokeObjectURL(objectUrl);
                    };
                });
            });

            const setStatus = (form, message) => {
                const status = form.querySelector('.foto-upload-status');
                if (!status) return;

                if (!message) {
                    status.textContent = '';
                    status.classList.add('d-none');
                    return;
                }

                status.textContent = message;
                status.classList.remove('d-none');
            };

            const dataUrlToBlob = (dataUrl) => {
                const parts = dataUrl.split(',');
                if (parts.length !== 2) {
                    return null;
                }

                const mimeMatch = parts[0].match(/data:(.*?);base64/);
                const mime = mimeMatch ? mimeMatch[1] : 'image/jpeg';
                const binary = atob(parts[1]);
                const bytes = new Uint8Array(binary.length);

                for (let i = 0; i < binary.length; i += 1) {
                    bytes[i] = binary.charCodeAt(i);
                }

                return new Blob([bytes], { type: mime });
            };

            const loadImage = (file) => new Promise((resolve, reject) => {
                const img = new Image();
                const objectUrl = URL.createObjectURL(file);

                img.onload = function() {
                    URL.revokeObjectURL(objectUrl);
                    resolve(img);
                };

                img.onerror = function() {
                    URL.revokeObjectURL(objectUrl);
                    reject(new Error('No se pudo leer la imagen.'));
                };

                img.src = objectUrl;
            });

            const compressImage = async (file) => {
                if (!file.type.startsWith('image/') || !/image\/(jpeg|jpg|png|webp)/i.test(file.type)) {
                    return file;
                }

                const img = await loadImage(file);
                let width = img.naturalWidth || img.width;
                let height = img.naturalHeight || img.height;

                if (!width || !height) {
                    return file;
                }

                if (width > MAX_DIMENSION || height > MAX_DIMENSION) {
                    const scale = Math.min(MAX_DIMENSION / width, MAX_DIMENSION / height);
                    width = Math.max(1, Math.round(width * scale));
                    height = Math.max(1, Math.round(height * scale));
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;

                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    return file;
                }

                ctx.drawImage(img, 0, 0, width, height);

                let quality = 0.82;
                let blob = dataUrlToBlob(canvas.toDataURL('image/jpeg', quality));

                while (blob && blob.size > TARGET_MAX_BYTES && quality > 0.45) {
                    quality -= 0.08;
                    blob = dataUrlToBlob(canvas.toDataURL('image/jpeg', quality));
                }

                if (!blob || blob.size >= file.size) {
                    return file;
                }

                const name = (file.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg';
                return new File([blob], name, {
                    type: 'image/jpeg',
                    lastModified: Date.now(),
                });
            };

            document.querySelectorAll('.entrega-upload-form').forEach((form) => {
                form.addEventListener('submit', async function(event) {
                    if (form.dataset.compressed === '1') {
                        return;
                    }

                    const fileInput = form.querySelector('.foto-input');
                    const sizeLabel = form.querySelector('.foto-size-label');
                    const submitButton = form.querySelector('button[type="submit"]');
                    const file = fileInput && fileInput.files ? fileInput.files[0] : null;

                    if (!file || file.size <= MAX_UPLOAD_BYTES) {
                        return;
                    }

                    event.preventDefault();
                    setStatus(form, 'Reduciendo foto antes de enviar...');

                    try {
                        const processedFile = await compressImage(file);

                        if (processedFile.size > MAX_UPLOAD_BYTES) {
                            setStatus(form, 'La foto sigue siendo muy grande. Toma una foto con menor resolucion.');
                            return;
                        }

                        if (processedFile !== file && typeof DataTransfer !== 'undefined') {
                            const transfer = new DataTransfer();
                            transfer.items.add(processedFile);
                            fileInput.files = transfer.files;
                        }

                        if (sizeLabel) {
                            sizeLabel.textContent = 'Peso actual: ' + formatBytesToMb(processedFile.size);
                        }

                        form.dataset.compressed = '1';
                        setStatus(form, 'Foto lista. Enviando...');

                        if (submitButton) {
                            submitButton.disabled = true;
                        }

                        form.submit();
                    } catch (error) {
                        setStatus(form, 'No se pudo optimizar la foto. Intenta con una imagen mas liviana.');
                    }
                });
            });
        })();
    </script>
@endsection
