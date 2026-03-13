@extends('adminlte::page')
@section('title', 'Nuevo Contrato con Tarifa')
@section('template_title')
    Nuevo Contrato con Tarifa
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
        .section-title {
            color: #20539A;
            font-weight: 800;
            font-size: 13px;
            letter-spacing: .03em;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .required-star {
            color: #dc3545;
            margin-left: 3px;
        }
        .origin-input {
            background: #eef2ff;
            border-color: #c7d2fe;
            color: #1e3a8a;
            font-weight: 700;
        }
        .hint-box {
            background: #f0f7ff;
            border: 1px dashed #9ac5ff;
            color: #234;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 14px;
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

    @if(($serviciosTarifa ?? collect())->isEmpty())
        <div class="alert alert-warning">
            No hay tarifas registradas para tu empresa. Primero registra/importa tarifas en "IMPORTAR TARIFAS CONTRATOS".
        </div>
    @endif

    <div class="contrato-wrap">
        <div class="card contrato-card">
            <div class="contrato-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Crear contrato con tarifa</h5>
                    <small>Asigna la tarifa ahora y calcula el precio cuando se registre el peso.</small>
                </div>
                <div class="d-flex" style="gap:8px;">
                    <a href="{{ route('paquetes-contrato.create', [], false) }}" class="btn btn-light btn-sm">Crear normal</a>
                    <a href="{{ route('paquetes-contrato.index', [], false) }}" class="btn btn-light btn-sm">Volver</a>
                </div>
            </div>

            <div class="card-body">
                <div class="hint-box">
                    El contrato se crea sin peso y sin precio.
                    Cuando se registre el peso (Anadir peso contrato), el sistema calcula automatico:
                    de 0.001 a 1.000 kg usa <strong>kilo</strong>, y desde 1.001 suma <strong>kilo_extra</strong> por cada kg adicional.
                </div>

                <form method="POST" action="{{ route('paquetes-contrato.store-con-tarifa', [], false) }}">
                    @csrf

                    <div class="section-block">
                        <div class="section-title">Tarifa</div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Origen (usuario logueado)</label>
                                    <input type="text" class="form-control origin-input" value="{{ $origen }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Servicio<span class="required-star">*</span></label>
                                    <select id="servicioSelect" name="servicio" class="form-control" required>
                                        <option value="">Seleccione...</option>
                                        @foreach($serviciosTarifa as $servicio)
                                            <option value="{{ $servicio }}" {{ old('servicio') === $servicio ? 'selected' : '' }}>
                                                {{ $servicio }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="section-block">
                        <div class="section-title">Remitente</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nombre remitente<span class="required-star">*</span></label>
                                    <input type="text" name="nombre_r" class="form-control" value="{{ old('nombre_r') }}" required>
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
                                <div class="form-group">
                                    <label>Contenido<span class="required-star">*</span></label>
                                    <textarea name="contenido" rows="2" class="form-control" required>{{ old('contenido') }}</textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
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
                                    <select id="destinoSelect" name="destino" class="form-control" required>
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
                                    <label id="provinciaLabel">Provincia (opcional)</label>
                                    <select id="provinciaSelect" class="form-control">
                                        <option value="">Seleccione...</option>
                                    </select>
                                    <input type="hidden" name="provincia" id="provinciaHidden" value="{{ old('provincia') }}">

                                    <div id="provinciaOtroWrap" class="mt-2" style="display:none;">
                                        <input
                                            id="provinciaOtroInput"
                                            type="text"
                                            class="form-control"
                                            placeholder="Escribe la provincia"
                                            value=""
                                        >
                                    </div>
                                    <small id="provinciaHelp" class="text-muted">Selecciona una provincia del combo.</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Direccion<span class="required-star">*</span></label>
                                    <input type="text" name="direccion" class="form-control" value="{{ old('direccion') }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label>Mapa (opcional)</label>
                                    <input type="text" name="mapa" class="form-control" value="{{ old('mapa') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($canContratoCreateTarifaSubmit ?? false)
                    <button type="submit" class="btn btn-primary" {{ ($serviciosTarifa ?? collect())->isEmpty() ? 'disabled' : '' }}>
                        Guardar contrato con tarifa
                    </button>
                    @endif
                </form>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script>
    (function () {
        const provinciasPorDestino = @json($provinciasPorDestino ?? []);
        const servicio = document.getElementById('servicioSelect');
        const destino = document.getElementById('destinoSelect');
        const provinciaSelect = document.getElementById('provinciaSelect');
        const provinciaHidden = document.getElementById('provinciaHidden');
        const provinciaOtroWrap = document.getElementById('provinciaOtroWrap');
        const provinciaOtroInput = document.getElementById('provinciaOtroInput');
        const provinciaLabel = document.getElementById('provinciaLabel');
        const provinciaHelp = document.getElementById('provinciaHelp');
        const form = servicio.closest('form');
        const oldProvincia = String(@json(old('provincia', '')) || '').toUpperCase().trim();
        const OTRO_VALUE = '__OTRO__';

        if (!servicio || !destino || !provinciaSelect || !provinciaHidden || !provinciaOtroWrap || !provinciaOtroInput || !provinciaLabel || !provinciaHelp || !form) return;

        const isInterprovincial = (value) => {
            const normalized = String(value || '')
                .toUpperCase()
                .trim()
                .replace(/\s+/g, ' ')
                .replace('LOCAL (REGULAR)', 'LOCAL(REGULAR)')
                .replace('LOCAL (EXPRESS)', 'LOCAL(EXPRESS)');

            return normalized === 'INTERPROVINCIAL';
        };

        const renderProvinciaOptions = (selectedProvincia = '') => {
            const key = String(destino.value || '').toUpperCase().trim();
            const sugerencias = provinciasPorDestino[key] || [];
            const selected = String(selectedProvincia || '').toUpperCase().trim();

            provinciaSelect.innerHTML = '';
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = 'Seleccione...';
            provinciaSelect.appendChild(empty);

            sugerencias.forEach((provincia) => {
                const option = document.createElement('option');
                option.value = provincia;
                option.textContent = provincia;
                if (selected !== '' && selected === String(provincia).toUpperCase().trim()) {
                    option.selected = true;
                }
                provinciaSelect.appendChild(option);
            });

            const otro = document.createElement('option');
            otro.value = OTRO_VALUE;
            otro.textContent = 'OTRO (escribir manualmente)';
            provinciaSelect.appendChild(otro);

            if (selected !== '' && !sugerencias.map((item) => String(item).toUpperCase().trim()).includes(selected)) {
                provinciaSelect.value = OTRO_VALUE;
                provinciaOtroInput.value = selected;
            } else if (selected === '') {
                provinciaSelect.value = '';
                provinciaOtroInput.value = '';
            }
        };

        const updateProvinciaUi = () => {
            const required = isInterprovincial(servicio.value);
            const isOtro = provinciaSelect.value === OTRO_VALUE;

            provinciaSelect.required = required;
            provinciaOtroWrap.style.display = isOtro ? '' : 'none';
            provinciaOtroInput.required = required && isOtro;

            if (!isOtro) {
                provinciaOtroInput.value = '';
            }

            provinciaLabel.innerHTML = required
                ? 'Provincia<span class="required-star">*</span>'
                : 'Provincia (opcional)';
            provinciaHelp.textContent = required
                ? 'Obligatorio cuando el servicio es INTERPROVINCIAL.'
                : 'Opcional.';
        };

        const syncProvinciaHidden = () => {
            if (provinciaSelect.value === OTRO_VALUE) {
                provinciaHidden.value = String(provinciaOtroInput.value || '').trim().toUpperCase();
                return;
            }
            provinciaHidden.value = String(provinciaSelect.value || '').trim().toUpperCase();
        };

        servicio.addEventListener('change', () => {
            updateProvinciaUi();
            syncProvinciaHidden();
        });
        destino.addEventListener('change', () => {
            renderProvinciaOptions('');
            updateProvinciaUi();
            syncProvinciaHidden();
        });
        provinciaSelect.addEventListener('change', () => {
            updateProvinciaUi();
            syncProvinciaHidden();
        });
        provinciaOtroInput.addEventListener('input', syncProvinciaHidden);
        form.addEventListener('submit', syncProvinciaHidden);

        renderProvinciaOptions(oldProvincia);
        updateProvinciaUi();
        syncProvinciaHidden();
    })();
</script>
@endsection
