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
            background: linear-gradient(90deg, #34447C, #2c3766);
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
            color: #34447C;
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
                <a href="{{ route('paquetes-contrato.index') }}" class="btn btn-light btn-sm">Volver</a>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('paquetes-contrato.store') }}" id="formContratoCreate">
                    @csrf

                    <div id="envioFrecuenteStatus" class="alert d-none mb-3" role="alert"></div>

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
                                            required
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
                                    <input type="text" name="telefono_r" class="form-control" value="{{ old('telefono_r') }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label>Contenido<span class="required-star">*</span></label>
                                    <textarea name="contenido" rows="2" class="form-control" required>{{ old('contenido') }}</textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label>Direccion remitente<span class="required-star">*</span></label>
                                    <input type="text" name="direccion_r" class="form-control" value="{{ old('direccion_r') }}" required>
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
                                    <input type="text" name="nombre_d" class="form-control" value="{{ old('nombre_d') }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Telefono destinatario (opcional)</label>
                                    <input type="text" name="telefono_d" class="form-control" value="{{ old('telefono_d') }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Destino (departamento)<span class="required-star">*</span></label>
                                    <select name="destino" class="form-control" required>
                                        <option value="">Seleccione...</option>
                                        @foreach($departamentos as $dep)
                                            <option value="{{ $dep }}" {{ old('destino') === $dep ? 'selected' : '' }}>
                                                {{ $dep }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Direccion<span class="required-star">*</span></label>
                                    <input type="text" name="direccion" class="form-control" value="{{ old('direccion') }}" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Mapa (opcional)</label>
                                    <input type="text" name="mapa" class="form-control" value="{{ old('mapa') }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-0">
                                    <label>Provincia (opcional)</label>
                                    <input type="text" name="provincia" class="form-control" value="{{ old('provincia') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="top-actions">
                        <button type="button" class="btn btn-guardar-frecuente" id="btnGuardarFrecuente">
                            Guardar envio frecuente
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Guardar contrato
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script>
    (function () {
        const form = document.getElementById('formContratoCreate');
        if (!form) return;

        const userId = @json((string) (auth()->id() ?? 'guest'));
        const storageKey = `paquetes_contrato_envios_frecuentes_${userId}`;
        const statusBox = document.getElementById('envioFrecuenteStatus');
        const saveBtn = document.getElementById('btnGuardarFrecuente');
        const remitenteInput = document.getElementById('nombreRemitenteInput');
        const dropdown = document.getElementById('frecuentesDropdown');
        const fields = [
            'nombre_r',
            'telefono_r',
            'contenido',
            'direccion_r',
            'nombre_d',
            'telefono_d',
            'destino',
            'direccion',
            'mapa',
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

        const getField = (name) => form.querySelector(`[name="${name}"]`);
        const normalizeText = (value) => String(value || '').trim().toUpperCase();

        const getFrecuentes = () => {
            try {
                const raw = localStorage.getItem(storageKey);
                const parsed = raw ? JSON.parse(raw) : [];
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                console.error(error);
                return [];
            }
        };

        const saveFrecuentes = (items) => {
            localStorage.setItem(storageKey, JSON.stringify(items));
        };

        const fillForm = (item) => {
            fields.forEach((name) => {
                const input = getField(name);
                if (!input) return;
                input.value = item[name] || '';
            });
        };

        const hideDropdown = () => {
            if (!dropdown) return;
            dropdown.innerHTML = '';
            dropdown.style.display = 'none';
        };

        const showSuggestions = (query) => {
            if (!dropdown || !remitenteInput) return;
            const frecuentes = getFrecuentes();
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
                    <div><strong>${item.nombre_r || '-'}</strong></div>
                    <small>Destinatario: ${item.nombre_d || '-'}</small>
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

        if (form) {
            form.addEventListener('submit', () => {
                hideDropdown();
            });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                const payload = {};
                fields.forEach((name) => {
                    const input = getField(name);
                    payload[name] = input ? input.value : '';
                });

                if (!payload.nombre_r || !payload.nombre_d) {
                    setStatus('Para guardar envio frecuente, completa nombre de remitente y destinatario.', 'warning');
                    return;
                }

                try {
                    const frecuentes = getFrecuentes();
                    const key = `${normalizeText(payload.nombre_r)}|${normalizeText(payload.nombre_d)}`;

                    const withoutCurrent = frecuentes.filter((item) => {
                        const currentKey = `${normalizeText(item.nombre_r)}|${normalizeText(item.nombre_d)}`;
                        return currentKey !== key;
                    });

                    withoutCurrent.unshift(payload);
                    saveFrecuentes(withoutCurrent.slice(0, 100));
                    setStatus('Envio frecuente guardado en cache de esta computadora.', 'success');
                } catch (error) {
                    console.error(error);
                    setStatus('No se pudo guardar el envio frecuente en cache local.', 'danger');
                }
            });
        }
    })();
</script>
@endsection
