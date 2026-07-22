@extends('adminlte::page')
@section('title', 'Nuevo Contrato')
@section('template_title')
    Nuevo Contrato
@endsection

@section('content')
<section class="content container-fluid pt-3">
    <style>
        .contrato-wrap {
            background: #f5f7fb;
            border-radius: 16px;
            padding: 12px;
        }
        .contrato-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, .08);
            overflow: hidden;
        }
        .contrato-header {
            background: linear-gradient(90deg, #20539A, #20539A);
            color: #fff;
            padding: 16px 18px;
        }
        .section-block {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 14px;
            background: #fff;
        }
        .section-block:hover {
            box-shadow: 0 6px 14px rgba(0,0,0,.05);
        }
        .section-title {
            color: #20539A;
            font-weight: 800;
            font-size: 13px;
            letter-spacing: .03em;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .hint-text {
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 0;
        }
        .required-star {
            color: #dc3545;
            margin-left: 3px;
        }
        .top-actions {
            gap: 8px;
            display: flex;
            flex-wrap: wrap;
        }
        .btn-guardar-frecuente {
            background: #0f766e;
            border-color: #0f766e;
            color: #fff;
            font-weight: 700;
        }
        .btn-guardar-frecuente:hover {
            background: #115e59;
            border-color: #115e59;
            color: #fff;
        }
        .origin-input {
            background: #eef2ff;
            border-color: #c7d2fe;
            color: #1e3a8a;
            font-weight: 700;
        }
        .origin-help {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        .frecuente-wrap {
            position: relative;
        }
        .frecuente-dropdown {
            position: absolute;
            z-index: 50;
            width: 100%;
            max-height: 220px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #ced4da;
            border-top: 0;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 8px 16px rgba(0,0,0,.08);
            display: none;
        }
        .frecuente-item {
            display: block;
            width: 100%;
            text-align: left;
            border: 0;
            background: #fff;
            padding: 8px 10px;
            border-bottom: 1px solid #f1f1f1;
            cursor: pointer;
        }
        .frecuente-item:hover {
            background: #f8f9fa;
        }
        .frecuente-item small {
            color: #6b7280;
        }
        .envio-selector-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }
        .envio-selector-copy {
            max-width: 760px;
        }
        .envio-selector-copy h6 {
            margin: 0 0 6px;
            color: #0f172a;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -.02em;
        }
        .envio-selector-copy p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }
        .envio-selector-badge {
            background: #e0ecff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .envio-tipo-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }
        .envio-tipo-option {
            display: block;
            margin: 0;
            cursor: pointer;
        }
        .envio-tipo-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .envio-tipo-card {
            display: block;
            position: relative;
            min-height: 168px;
            border: 1px solid #d7e2f1;
            border-radius: 18px;
            padding: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            cursor: pointer;
            transition: all .2s ease;
            overflow: hidden;
        }
        .envio-tipo-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, .12), transparent 34%),
                linear-gradient(135deg, rgba(255,255,255,.75), rgba(255,255,255,0));
            pointer-events: none;
        }
        .envio-tipo-top {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .envio-tipo-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e8f0ff;
            color: #1d4ed8;
            font-size: 18px;
            font-weight: 800;
            box-shadow: inset 0 0 0 1px rgba(29, 78, 216, .08);
        }
        .envio-tipo-check {
            width: 24px;
            height: 24px;
            border-radius: 999px;
            border: 2px solid #bfd2ea;
            background: #fff;
            transition: all .2s ease;
        }
        .envio-tipo-card strong {
            position: relative;
            z-index: 1;
            display: block;
            color: #0f172a;
            margin-bottom: 8px;
            font-size: 21px;
            line-height: 1.1;
        }
        .envio-tipo-card small {
            position: relative;
            z-index: 1;
            display: block;
            color: #475569;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        .envio-tipo-note {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 999px;
            background: #eef4ff;
            color: #1e40af;
            font-size: 12px;
            font-weight: 700;
        }
        .envio-tipo-option input[type="radio"]:checked + .envio-tipo-card {
            border-color: #2563eb;
            background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
            box-shadow: 0 18px 34px rgba(37, 99, 235, .16);
            transform: translateY(-2px);
        }
        .envio-tipo-option input[type="radio"]:checked + .envio-tipo-card .envio-tipo-check {
            border-color: #2563eb;
            background: #2563eb;
            box-shadow: inset 0 0 0 5px #ffffff;
        }
        .envio-tipo-option:hover .envio-tipo-card {
            border-color: #93c5fd;
            box-shadow: 0 12px 24px rgba(15, 23, 42, .08);
            transform: translateY(-1px);
        }
        @media (max-width: 991.98px) {
            .envio-tipo-grid {
                grid-template-columns: 1fr;
            }
            .envio-selector-head {
                flex-direction: column;
            }
            .envio-selector-copy h6 {
                font-size: 21px;
            }
        }
    </style>

    @if (session()->has('error'))
        <div class="alert alert-danger">
            <p class="mb-0">{{ session('error') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="contrato-wrap">
        <div class="card contrato-card">
            <div class="contrato-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Crear contrato</h5>
                    <p class="hint-text mb-0" style="color:#dbe2ff;">Formulario simplificado para uso rapido</p>
                </div>
                <a href="{{ route('paquetes-contrato.index', [], false) }}" class="btn btn-light btn-sm">Volver</a>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('paquetes-contrato.store', [], false) }}" id="formContratoCreate">
                    @csrf
                    <input type="hidden" name="numero_copias" id="numeroCopiasInput" value="{{ old('numero_copias', 1) }}">

                    <div id="envioFrecuenteStatus" class="alert d-none mb-3" role="alert"></div>

                    <div class="section-block">
                        <div class="section-title">Tipo de envio</div>
                        <div class="envio-selector-head">
                            <div class="envio-selector-copy">
                                <h6>Elige primero como se movera el envio</h6>
                                <p>Selecciona una modalidad para que el sistema te muestre solo los campos necesarios y evites confusiones al registrar el contrato.</p>
                            </div>
                            <div class="envio-selector-badge">Paso 1 de 3</div>
                        </div>
                        <div class="form-group">
                            <label>Tipo de envio<span class="required-star">*</span></label>
                            <div class="envio-tipo-grid">
                                <label class="envio-tipo-option mb-0">
                                    <input type="radio" name="tipo_envio" value="periurbano" {{ old('tipo_envio') === 'periurbano' ? 'checked' : '' }} required>
                                    <span class="envio-tipo-card">
                                        <span class="envio-tipo-top">
                                            <span class="envio-tipo-icon">P</span>
                                            <span class="envio-tipo-check"></span>
                                        </span>
                                        <strong>PERIURBANO</strong>
                                        <small>Bloquea departamento y usa el mismo origen.</small>
                                        <span class="envio-tipo-note">Misma ciudad de origen</span>
                                    </span>
                                </label>
                                <label class="envio-tipo-option mb-0">
                                    <input type="radio" name="tipo_envio" value="departamental" {{ old('tipo_envio') === 'departamental' ? 'checked' : '' }}>
                                    <span class="envio-tipo-card">
                                        <span class="envio-tipo-top">
                                            <span class="envio-tipo-icon">D</span>
                                            <span class="envio-tipo-check"></span>
                                        </span>
                                        <strong>ENVIO DEPARTAMENTAL</strong>
                                        <small>Muestra todo excepto provincia.</small>
                                        <span class="envio-tipo-note">Solo departamento destino</span>
                                    </span>
                                </label>
                                <label class="envio-tipo-option mb-0">
                                    <input type="radio" name="tipo_envio" value="provincial" {{ old('tipo_envio') === 'provincial' ? 'checked' : '' }}>
                                    <span class="envio-tipo-card">
                                        <span class="envio-tipo-top">
                                            <span class="envio-tipo-icon">R</span>
                                            <span class="envio-tipo-check"></span>
                                        </span>
                                        <strong>PROVINCIAL</strong>
                                        <small>Muestra departamento, direccion y provincia.</small>
                                        <span class="envio-tipo-note">Incluye provincia destino</span>
                                    </span>
                                </label>
                            </div>
                            <small class="form-text text-muted">
                                Al seleccionar una opcion se habilitara automaticamente el resto del formulario.
                            </small>
                        </div>
                    </div>

                    <div id="formFieldsBlock" style="display:none;">
                    <div class="section-block">
                        <div class="section-title">Remitente</div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Origen (autollenado y bloqueado)</label>
                                    <input type="text" class="form-control origin-input" value="{{ $origen }}" readonly>
                                    <div class="origin-help">Este dato se toma del origen o departamento del usuario logueado.</div>
                                </div>
                            </div>
                            @if(!empty($provinciaOrigen))
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Provincia origen</label>
                                        <input type="text" class="form-control origin-input" value="{{ $provinciaOrigen }}" readonly>
                                        <div class="origin-help">Este dato se toma de la provincia origen del usuario logueado.</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nombre remitente<span class="required-star">*</span></label>
                                    <div class="frecuente-wrap">
                                        <input
                                            type="text"
                                            name="nombre_r"
                                            id="nombreRemitenteInput"
                                            class="form-control"
                                            value="{{ old('nombre_r') }}"
                                            autocomplete="off"
                                        >
                                        <div id="frecuentesDropdown" class="frecuente-dropdown"></div>
                                    </div>
                                    <small class="form-text text-muted">
                                        Escribe el nombre del remitente para ver envios frecuentes guardados.
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Telefono remitente<span class="required-star">*</span></label>
                                    <input type="text" name="telefono_r" class="form-control" value="{{ old('telefono_r') }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label>Contenido<span class="required-star">*</span></label>
                                    <textarea name="contenido" rows="2" class="form-control">{{ old('contenido') }}</textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label>Direccion remitente<span class="required-star">*</span></label>
                                    <input type="text" name="direccion_r" class="form-control" value="{{ old('direccion_r') }}">
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label>Cantidad<span class="required-star">*</span></label>
                                    <input type="number" name="cantidad" class="form-control" value="{{ old('cantidad') }}" min="1" step="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label>Peso (opcional)</label>
                                    <input type="number" name="peso" class="form-control" value="{{ old('peso') }}" min="0" step="0.001" placeholder="0.000">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="section-block">
                        <div class="section-title">Destinatario</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nombre destinatario<span class="required-star">*</span></label>
                                    <input type="text" name="nombre_d" class="form-control" value="{{ old('nombre_d') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Telefono destinatario<span class="required-star">*</span></label>
                                    <input type="text" name="telefono_d" class="form-control" value="{{ old('telefono_d') }}">
                                </div>
                            </div>
                        </div>
                        <div id="destinoFieldsBlock">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Destino (departamento)<span class="required-star">*</span></label>
                                    <select name="destino" id="destinoSelect" class="form-control">
                                        <option value="">Seleccione...</option>
                                        @foreach($departamentos as $dep)
                                            <option value="{{ $dep }}" {{ old('destino') === $dep ? 'selected' : '' }}>
                                                {{ $dep }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" id="destinoLockedInput" value="">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>Direccion destinatario<span class="required-star">*</span></label>
                                    <textarea name="direccion" id="direccionInput" class="form-control" rows="3"
                                        placeholder="Escribe la direccion completa del destinatario...">{{ old('direccion') }}</textarea>
                                </div>
                            </div>
                        </div>
                        <div class="row" id="provinciaRow">
                            <div class="col-md-4">
                                <div class="form-group mb-0">
                                    <label>Provincia (opcional)</label>
                                    <input type="text" name="provincia" id="provinciaInput" class="form-control" value="{{ old('provincia') }}">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="alert alert-warning mb-0" role="alert">
                                    Toda documentacion o envio sera verificado y certificado por Correos de Bolivia.
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>
                    </div>

                    <div class="top-actions" id="formActionsBlock" style="display:none;">
                        @if ($canContratoCreateFrecuente ?? false)
                        <button type="button" class="btn btn-guardar-frecuente" id="btnGuardarFrecuente">
                            Guardar envio frecuente
                        </button>
                        @endif
                        @if ($canContratoCreateSubmit ?? false)
                        <button type="submit" class="btn btn-primary">
                            Guardar contrato
                        </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="envioFrecuenteSavedModal" tabindex="-1" role="dialog" aria-labelledby="envioFrecuenteSavedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="envioFrecuenteSavedModalLabel">Envio frecuente guardado</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Envio frecuente guardado correctamente.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-dismiss="modal">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="copiasGuiaModal" tabindex="-1" role="dialog" aria-labelledby="copiasGuiaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="copiasGuiaModalLabel">Copias de guia</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-0">
                    <label for="numeroCopiasModalSelect">Cuantas copias quieres imprimir?</label>
                    <select id="numeroCopiasModalSelect" class="form-control">
                        @for($i = 1; $i <= 3; $i++)
                            <option value="{{ $i }}" {{ (int) old('numero_copias', 1) === $i ? 'selected' : '' }}>
                                {{ $i }} {{ $i === 1 ? 'copia' : 'copias' }}
                            </option>
                        @endfor
                    </select>
                    <small class="form-text text-muted">Puedes elegir hasta un maximo de 3 copias.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmarCopiasGuiaBtn">Guardar contrato</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
    (function () {
        const form = document.getElementById('formContratoCreate');
        if (!form) return;

        const userId = @json((string) (auth()->id() ?? 'guest'));
        const storageKeyBase = `paquetes_contrato_envios_frecuentes_${userId}`;
        const statusBox = document.getElementById('envioFrecuenteStatus');
        const savedModal = document.getElementById('envioFrecuenteSavedModal');
        const copiasGuiaModal = document.getElementById('copiasGuiaModal');
        const numeroCopiasInput = document.getElementById('numeroCopiasInput');
        const numeroCopiasModalSelect = document.getElementById('numeroCopiasModalSelect');
        const confirmarCopiasGuiaBtn = document.getElementById('confirmarCopiasGuiaBtn');
        const saveBtn = document.getElementById('btnGuardarFrecuente');
        const remitenteInput = document.getElementById('nombreRemitenteInput');
        const dropdown = document.getElementById('frecuentesDropdown');
        const tipoEnvioInputs = Array.from(form.querySelectorAll('input[name="tipo_envio"]'));
        const formFieldsBlock = document.getElementById('formFieldsBlock');
        const formActionsBlock = document.getElementById('formActionsBlock');
        const destinoFieldsBlock = document.getElementById('destinoFieldsBlock');
        const destinoSelect = document.getElementById('destinoSelect');
        const destinoLockedInput = document.getElementById('destinoLockedInput');
        const direccionInput = document.getElementById('direccionInput');
        const provinciaRow = document.getElementById('provinciaRow');
        const provinciaInput = document.getElementById('provinciaInput');
        const origenValue = @json($origen);
        const requiredFieldNames = [
            'nombre_r',
            'telefono_r',
            'contenido',
            'direccion_r',
            'cantidad',
            'nombre_d',
            'telefono_d',
        ];
        const fields = [
            'tipo_envio',
            'nombre_r',
            'telefono_r',
            'contenido',
            'direccion_r',
            'cantidad',
            'peso',
            'nombre_d',
            'telefono_d',
            'destino',
            'direccion',
            'provincia'
        ];

        const setStatus = (message, type) => {
            if (!statusBox) return;
            statusBox.className = `alert alert-${type}`;
            statusBox.textContent = message;
            statusBox.classList.remove('d-none');
            setTimeout(() => {
                statusBox.classList.add('d-none');
            }, 2500);
        };
        const showSavedModal = () => {
            if (!savedModal || typeof window.jQuery === 'undefined') {
                setStatus('Envio frecuente guardado correctamente.', 'success');
                return;
            }

            window.jQuery(savedModal).modal('show');
        };

        const getField = (name) => form.querySelector(`[name="${name}"]`);
        const getTipoEnvio = () => {
            const selected = form.querySelector('input[name="tipo_envio"]:checked');
            return selected ? selected.value : '';
        };
        const setTipoEnvio = (value) => {
            tipoEnvioInputs.forEach((input) => {
                input.checked = input.value === value;
            });
        };
        const syncDestinoLockedState = (isLocked) => {
            if (!destinoSelect || !destinoLockedInput) return;

            if (isLocked) {
                destinoLockedInput.name = 'destino';
                destinoLockedInput.value = String(destinoSelect.value || '').trim();
                destinoSelect.name = 'destino_visible';
                destinoSelect.style.pointerEvents = 'none';
                destinoSelect.tabIndex = -1;
                destinoSelect.classList.add('bg-light');
                return;
            }

            destinoLockedInput.name = '';
            destinoLockedInput.value = '';
            destinoSelect.name = 'destino';
            destinoSelect.style.pointerEvents = '';
            destinoSelect.tabIndex = 0;
            destinoSelect.classList.remove('bg-light');
        };
        const setFieldsRequired = (enabled) => {
            requiredFieldNames.forEach((name) => {
                const input = getField(name);
                if (!input) return;
                input.required = enabled;
            });
        };
        const applyTipoEnvio = (tipoEnvio) => {
            const mode = String(tipoEnvio || '').trim().toLowerCase();
            const hasSelection = mode !== '';
            const isPeriurbano = mode === 'periurbano';
            const isProvincial = mode === 'provincial';

            if (formFieldsBlock) {
                formFieldsBlock.style.display = hasSelection ? '' : 'none';
            }
            if (formActionsBlock) {
                formActionsBlock.style.display = hasSelection ? '' : 'none';
            }

            if (destinoFieldsBlock) {
                destinoFieldsBlock.style.display = hasSelection ? '' : 'none';
            }

            if (!hasSelection) {
                setFieldsRequired(false);
                if (destinoSelect) {
                    destinoSelect.required = false;
                }
                if (direccionInput) {
                    direccionInput.required = false;
                }
                if (provinciaInput) {
                    provinciaInput.required = false;
                }
                if (provinciaRow) {
                    provinciaRow.style.display = 'none';
                }
                syncDestinoLockedState(false);
                return;
            }

            setFieldsRequired(true);
            if (destinoSelect) {
                destinoSelect.required = true;
            }
            if (direccionInput) {
                direccionInput.required = true;
            }

            if (isPeriurbano) {
                if (destinoSelect) {
                    destinoSelect.value = origenValue;
                }
                syncDestinoLockedState(true);
            } else {
                syncDestinoLockedState(false);
            }

            if (provinciaRow) {
                provinciaRow.style.display = isProvincial ? '' : 'none';
            }
            if (provinciaInput) {
                provinciaInput.required = isProvincial;
                if (!isProvincial) {
                    provinciaInput.value = '';
                }
            }
        };
        const normalizeText = (value) => String(value || '').trim().toUpperCase();
        const escapeHtml = (value) => String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        const storageKeyForTipo = (tipoEnvio = getTipoEnvio()) => {
            const normalized = String(tipoEnvio || '').trim().toLowerCase();
            return normalized !== '' ? `${storageKeyBase}_${normalized}` : '';
        };

        const getFrecuentes = (tipoEnvio = getTipoEnvio()) => {
            const storageKey = storageKeyForTipo(tipoEnvio);
            if (storageKey === '') {
                return [];
            }

            try {
                const raw = localStorage.getItem(storageKey);
                const parsed = raw ? JSON.parse(raw) : [];
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                console.error(error);
                return [];
            }
        };

        const saveFrecuentes = (items, tipoEnvio = getTipoEnvio()) => {
            const storageKey = storageKeyForTipo(tipoEnvio);
            if (storageKey === '') {
                return;
            }

            localStorage.setItem(storageKey, JSON.stringify(items));
        };

        const getFieldValue = (name) => {
            if (name === 'tipo_envio') {
                return getTipoEnvio();
            }

            const input = getField(name);
            return input ? input.value : '';
        };

        const setFieldValue = (name, value) => {
            if (name === 'tipo_envio') {
                setTipoEnvio(value || '');
                applyTipoEnvio(value || '');
                return;
            }

            const input = getField(name);
            if (!input) return;
            input.value = value || '';
        };

        const fillForm = (item) => {
            fields.forEach((name) => {
                setFieldValue(name, item[name] || '');
            });
        };

        const hideDropdown = () => {
            if (!dropdown) return;
            dropdown.innerHTML = '';
            dropdown.style.display = 'none';
        };

        const showSuggestions = (query) => {
            if (!dropdown || !remitenteInput) return;
            const tipoEnvio = getTipoEnvio();
            if (!tipoEnvio) {
                hideDropdown();
                return;
            }

            const frecuentes = getFrecuentes(tipoEnvio);
            const term = String(query || '').trim().toLowerCase();

            if (term.length === 0) {
                hideDropdown();
                return;
            }

            const filtered = frecuentes
                .filter((item) => String(item.nombre_r || '').toLowerCase().includes(term))
                .slice(0, 8);

            if (filtered.length === 0) {
                hideDropdown();
                return;
            }

            dropdown.innerHTML = filtered.map((item, index) => `
                <button type="button" class="frecuente-item" data-index="${index}">
                    <div><strong>${escapeHtml(item.nombre_r || '-')}</strong></div>
                    <small>Destinatario: ${escapeHtml(item.nombre_d || '-')}</small>
                    <small>Direccion destinatario: ${escapeHtml(item.direccion || '-')}</small>
                </button>
            `).join('');

            dropdown.style.display = 'block';

            const buttons = dropdown.querySelectorAll('.frecuente-item');
            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    const idx = Number(button.getAttribute('data-index'));
                    const selected = filtered[idx];
                    if (!selected) return;
                    fillForm(selected);
                    hideDropdown();
                    setStatus('Envio frecuente cargado.', 'info');
                });
            });
        };

        if (remitenteInput) {
            remitenteInput.addEventListener('input', (event) => {
                showSuggestions(event.target.value);
            });

            remitenteInput.addEventListener('focus', (event) => {
                showSuggestions(event.target.value);
            });
        }

        document.addEventListener('click', (event) => {
            if (!dropdown || !remitenteInput) return;
            if (dropdown.contains(event.target) || remitenteInput === event.target) {
                return;
            }
            hideDropdown();
        });

        form.addEventListener('submit', (event) => {
            hideDropdown();
            if (form.dataset.copiasConfirmed === '1') {
                return;
            }

            event.preventDefault();
            if (typeof window.jQuery !== 'undefined' && copiasGuiaModal) {
                window.jQuery(copiasGuiaModal).modal('show');
            }
        });

        if (confirmarCopiasGuiaBtn) {
            confirmarCopiasGuiaBtn.addEventListener('click', () => {
                if (numeroCopiasInput && numeroCopiasModalSelect) {
                    numeroCopiasInput.value = numeroCopiasModalSelect.value || '1';
                }

                form.dataset.copiasConfirmed = '1';
                if (typeof window.jQuery !== 'undefined' && copiasGuiaModal) {
                    window.jQuery(copiasGuiaModal).modal('hide');
                }
                form.requestSubmit();
            });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                const payload = {};
                fields.forEach((name) => {
                    payload[name] = getFieldValue(name);
                });

                if (!payload.tipo_envio) {
                    setStatus('Selecciona primero el tipo de envio para guardar el frecuente.', 'warning');
                    return;
                }

                if (!payload.nombre_r || !payload.nombre_d) {
                    setStatus('Para guardar envio frecuente, completa nombre de remitente y destinatario.', 'warning');
                    return;
                }

                try {
                    const frecuentes = getFrecuentes(payload.tipo_envio);
                    const key = `${normalizeText(payload.nombre_r)}|${normalizeText(payload.nombre_d)}`;

                    const withoutCurrent = frecuentes.filter((item) => {
                        const currentKey = `${normalizeText(item.nombre_r)}|${normalizeText(item.nombre_d)}`;
                        return currentKey !== key;
                    });

                    withoutCurrent.unshift(payload);
                    saveFrecuentes(withoutCurrent.slice(0, 100), payload.tipo_envio);
                    showSavedModal();
                } catch (error) {
                    console.error(error);
                    setStatus('No se pudo guardar el envio frecuente en cache local.', 'danger');
                }
            });
        }

        tipoEnvioInputs.forEach((input) => {
            input.addEventListener('change', () => {
                applyTipoEnvio(input.value);
                hideDropdown();
                if (remitenteInput) {
                    showSuggestions(remitenteInput.value);
                }
            });
        });

        applyTipoEnvio(getTipoEnvio());
    })();
</script>
@endsection

