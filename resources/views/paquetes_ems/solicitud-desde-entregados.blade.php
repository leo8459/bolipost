@extends('adminlte::page')
@section('title', 'Crear Solicitud desde EMS Entregados')
@section('template_title')
    Crear Solicitud
@endsection

@section('content')
    <div class="ems-solicitud-wrap">
        <div class="card ems-solicitud-card">
            <div class="card-header ems-header-bar">
                <div>
                    <div class="ems-solicitud-title">Registrar una solicitud EMS</div>
                    <p class="ems-solicitud-intro">Completa la ruta y los datos del paquete. Los campos marcados como opcionales pueden quedar vacíos.</p>
                </div>
                <a href="{{ route('paquetes-ems.entregados', array_filter(['q' => $returnQuery])) }}" class="ems-back-btn">
                    Volver
                </a>
            </div>
            <div class="card-body">
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger ems-error-summary" role="alert">
                        <strong>Revisa estos datos antes de continuar:</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('paquetes-ems.entregados.solicitud.store') }}" class="row g-3 ems-form-grid" id="ems-solicitud-form">
                    @csrf
                    <input type="hidden" name="return_query" value="{{ $returnQuery }}">
                    <input type="hidden" name="ubicacion_paquete" id="ubicacion_paquete" value="{{ old('ubicacion_paquete') }}">
                    @error('ubicacion_paquete') <div class="col-12"><small class="text-danger">{{ $message }}</small></div> @enderror

                    <section class="col-12 ems-form-section" aria-labelledby="ems-reference-heading">
                        <div class="ems-section-heading">
                            <span class="ems-section-number">1</span>
                            <div>
                                <h2 id="ems-reference-heading">Referencia y empresa</h2>
                                <p>Vincula esta solicitud con su guía madre o selecciona la empresa.</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="codigo_madre" class="form-label">Código de la guía madre <span class="ems-optional">Opcional</span></label>
                                <input
                                    type="text"
                                    id="codigo_madre"
                                    name="codigo_madre"
                                    class="form-control @error('codigo_madre') is-invalid @enderror"
                                    value="{{ old('codigo_madre', $codigoMadreSugerido ?? '') }}"
                                    placeholder="Ej.: código de la guía original"
                                    aria-describedby="codigo_madre_help"
                                >
                                <small id="codigo_madre_help" class="ems-field-help">Si el código coincide, la empresa se seleccionará automáticamente.</small>
                                @error('codigo_madre') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="empresa_id" class="form-label">Empresa</label>
                                <select name="empresa_id" id="empresa_id" class="form-control @error('empresa_id') is-invalid @enderror" required>
                                    <option value="">Selecciona una empresa</option>
                                    @foreach ($empresas as $empresa)
                                        <option
                                            value="{{ $empresa->id }}"
                                            data-codigo-cliente="{{ preg_replace('/\s+/', '', strtoupper(trim((string) $empresa->codigo_cliente))) }}"
                                            @selected((int) old('empresa_id') === (int) $empresa->id)
                                        >
                                            {{ $empresa->nombre }}@if(!empty($empresa->sigla)) ({{ $empresa->sigla }})@endif - {{ $empresa->codigo_cliente }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="ems-company-hint" id="empresa_auto_hint"></small>
                                @error('empresa_id') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                        </div>
                    </section>

                    <section class="col-12 ems-form-section" aria-labelledby="ems-route-heading">
                        <div class="ems-section-heading">
                            <span class="ems-section-number">2</span>
                            <div>
                                <h2 id="ems-route-heading">Ruta del paquete</h2>
                                <p>Indica la ciudad y la provincia donde inicia y termina el envío.</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <div class="ems-route-card ems-route-origin">
                                    <h3>Sale desde</h3>
                                    <div class="form-group">
                                        <label for="origen" class="form-label">Ciudad de origen</label>
                                        <select id="origen" name="origen" class="form-control @error('origen') is-invalid @enderror" required>
                                            <option value="">Selecciona la ciudad de origen</option>
                                            @foreach ($ciudades as $ciudad)
                                                <option value="{{ $ciudad }}" @selected(old('origen') === $ciudad)>{{ $ciudad }}</option>
                                            @endforeach
                                        </select>
                                        @error('origen') <small class="text-danger d-block">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="provincia_origen" class="form-label">Provincia de origen <span class="ems-optional">Opcional</span></label>
                                        <input type="text" id="provincia_origen" name="provincia_origen" class="form-control @error('provincia_origen') is-invalid @enderror" value="{{ old('provincia_origen') }}" placeholder="Escribe la provincia" maxlength="255">
                                        @error('provincia_origen') <small class="text-danger d-block">{{ $message }}</small> @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-3">
                                <div class="ems-route-card ems-route-destination">
                                    <h3>Llega a</h3>
                                    <div class="form-group">
                                        <label for="destino" class="form-label">Ciudad de destino</label>
                                        <select id="destino" name="destino" class="form-control @error('destino') is-invalid @enderror" required>
                                            <option value="">Selecciona la ciudad de destino</option>
                                            @foreach ($ciudades as $ciudad)
                                                <option value="{{ $ciudad }}" @selected(old('destino') === $ciudad)>{{ $ciudad }}</option>
                                            @endforeach
                                        </select>
                                        @error('destino') <small class="text-danger d-block">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="provincia_destino" class="form-label">Provincia de destino <span class="ems-optional">Opcional</span></label>
                                        <input type="text" id="provincia_destino" name="provincia_destino" class="form-control @error('provincia_destino') is-invalid @enderror" value="{{ old('provincia_destino') }}" placeholder="Escribe la provincia" maxlength="255">
                                        @error('provincia_destino') <small class="text-danger d-block">{{ $message }}</small> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="col-12 ems-form-section" aria-labelledby="ems-address-heading">
                        <div class="ems-section-heading">
                            <span class="ems-section-number">3</span>
                            <div>
                                <h2 id="ems-address-heading">Direcciones</h2>
                                <p>Escribe referencias que ayuden a ubicar el punto de salida y de entrega.</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="direccion_r" class="form-label">Dirección de origen</label>
                                <input type="text" id="direccion_r" name="direccion_r" class="form-control @error('direccion_r') is-invalid @enderror" value="{{ old('direccion_r') }}" placeholder="Calle, número o referencia" required maxlength="255">
                                @error('direccion_r') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="direccion_d" class="form-label">Dirección de destino</label>
                                <input type="text" id="direccion_d" name="direccion_d" class="form-control @error('direccion_d') is-invalid @enderror" value="{{ old('direccion_d') }}" placeholder="Calle, número o referencia" required maxlength="255">
                                @error('direccion_d') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                        </div>
                    </section>

                    <section class="col-12 ems-form-section" aria-labelledby="ems-package-heading">
                        <div class="ems-section-heading">
                            <span class="ems-section-number">4</span>
                            <div>
                                <h2 id="ems-package-heading">Datos del paquete</h2>
                                <p>Registra el peso en kilogramos y agrega observaciones si hacen falta.</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="peso" class="form-label">Peso (kg)</label>
                                <input type="number" id="peso" step="0.001" min="0.001" name="peso" class="form-control @error('peso') is-invalid @enderror" value="{{ old('peso') }}" placeholder="Ej.: 1.250" required>
                                @error('peso') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-8 mb-3">
                                <label for="observacion" class="form-label">Observaciones <span class="ems-optional">Opcional</span></label>
                                <textarea id="observacion" name="observacion" rows="3" class="form-control @error('observacion') is-invalid @enderror" placeholder="Escribe un detalle útil sobre el paquete">{{ old('observacion') }}</textarea>
                                @error('observacion') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                        </div>
                    </section>

                    <div class="col-12 ems-form-actions">
                        <a href="{{ route('paquetes-ems.entregados', array_filter(['q' => $returnQuery])) }}" class="ems-cancel-btn">
                            Cancelar
                        </a>
                        <button type="submit" class="ems-submit-btn">
                            Continuar y registrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ubicacionPaqueteModal" tabindex="-1" role="dialog" aria-labelledby="ubicacionPaqueteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content ems-location-modal">
                <div class="modal-header">
                    <h5 class="modal-title" id="ubicacionPaqueteModalLabel">Ubicacion del paquete</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="ems-location-question">El paquete se encuentra en:</p>
                    <div class="ems-location-actions">
                        <button type="button" class="ems-location-choice ems-location-origin" data-location="origen">
                            Origen
                            <span>Se guardara en almacen de origen.</span>
                        </button>
                        <button type="button" class="ems-location-choice ems-location-destination" data-location="destino">
                            Destino
                            <span>Se guardara en almacen de destino.</span>
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ems-modal-cancel" data-dismiss="modal">Cancelar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .ems-solicitud-wrap {
            background: linear-gradient(180deg, #f8faff 0%, #f1f5fe 100%);
            border: 1px solid #e2e8f6;
            border-radius: 14px;
            padding: 14px;
        }

        .ems-solicitud-card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 12px 26px rgba(28, 45, 94, 0.1);
            overflow: hidden;
        }

        .ems-solicitud-card .card-header {
            background: linear-gradient(95deg, #20539A 0%, #43538f 100%);
            color: #fff;
            border-bottom: 0;
            padding: 1rem 1.2rem;
        }

        .ems-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            width: 100%;
            flex-wrap: wrap;
        }

        .ems-solicitud-title {
            font-size: 1.05rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .ems-solicitud-intro {
            margin: 5px 0 0;
            color: rgba(255, 255, 255, 0.88);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .ems-back-btn {
            margin-left: auto;
            align-self: flex-start;
            border: 1px solid rgba(255, 255, 255, 0.65);
            color: #fff;
            background: transparent;
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 700;
            text-decoration: none;
        }

        .ems-back-btn:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.08);
            text-decoration: none;
        }

        .ems-solicitud-card .card-body {
            padding: 1.5rem;
        }

        .ems-solicitud-card .form-control,
        .ems-solicitud-card select.form-control {
            min-height: 44px;
            border-radius: 10px;
            border-color: #cbd5e1;
            box-shadow: none;
        }

        .ems-solicitud-card .form-control:focus,
        .ems-solicitud-card select.form-control:focus {
            border-color: #20539A;
            box-shadow: 0 0 0 0.15rem rgba(32, 83, 154, 0.12);
        }

        .ems-solicitud-card .form-label {
            color: #0f172a;
            font-weight: 800;
            margin-bottom: 0.45rem;
        }

        .ems-form-grid {
            align-items: flex-start;
        }

        .ems-error-summary ul {
            margin: 6px 0 0;
            padding-left: 1.25rem;
        }

        .ems-form-section {
            padding: 1.25rem;
            border: 1px solid #e1e8f2;
            border-radius: 12px;
            background: #fff;
        }

        .ems-section-heading {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 1rem;
        }

        .ems-section-number {
            display: inline-flex;
            flex: 0 0 30px;
            width: 30px;
            height: 30px;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #e9f0fb;
            color: #20539A;
            font-size: 0.9rem;
            font-weight: 900;
        }

        .ems-section-heading h2 {
            margin: 1px 0 3px;
            color: #14233a;
            font-size: 1rem;
            font-weight: 900;
        }

        .ems-section-heading p {
            margin: 0;
            color: #64748b;
            font-size: 0.86rem;
            line-height: 1.4;
        }

        .ems-optional {
            margin-left: 4px;
            color: #64748b;
            font-size: 0.76rem;
            font-weight: 600;
        }

        .ems-field-help {
            display: block;
            margin-top: 5px;
            color: #64748b;
            line-height: 1.4;
        }

        .ems-route-card {
            height: 100%;
            padding: 1rem;
            border: 1px solid #dbe4f0;
            border-radius: 10px;
            background: #f8faff;
        }

        .ems-route-card h3 {
            margin: 0 0 1rem;
            color: #20539A;
            font-size: 0.92rem;
            font-weight: 900;
        }

        .ems-route-destination h3 {
            color: #826000;
        }

        .ems-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .ems-cancel-btn,
        .ems-submit-btn {
            min-height: 44px;
            border-radius: 12px;
            padding: 10px 18px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .ems-cancel-btn {
            background: #fff;
            color: #20539A;
            border: 1px solid rgba(32, 83, 154, 0.22);
        }

        .ems-cancel-btn:hover {
            background: rgba(32, 83, 154, 0.05);
            color: #20539A;
            text-decoration: none;
        }

        .ems-submit-btn {
            background: #FECC36;
            color: #172b4d;
            border: 0;
        }

        .ems-submit-btn:hover {
            background: #f4c21d;
            color: #172b4d;
        }

        .ems-company-hint {
            display: block;
            margin-top: 6px;
            color: #64748b;
            font-weight: 700;
        }

        .ems-company-hint.is-detected {
            color: #166534;
        }

        .ems-company-hint.is-missing {
            color: #b45309;
        }

        #empresa_id:disabled {
            background-color: #e8f5ee;
            color: #14532d;
            cursor: not-allowed;
            opacity: 1;
        }

        .ems-location-modal {
            border: 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
        }

        .ems-location-modal .modal-header {
            background: #20539A;
            color: #fff;
            border-bottom: 0;
        }

        .ems-location-modal .close {
            color: #fff;
            opacity: 0.9;
            text-shadow: none;
        }

        .ems-location-question {
            margin: 0 0 1rem;
            color: #0f172a;
            font-weight: 800;
        }

        .ems-location-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .ems-location-choice {
            min-height: 92px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #fff;
            color: #0f172a;
            font-weight: 900;
            padding: 14px;
            text-align: left;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        }

        .ems-location-choice span {
            display: block;
            margin-top: 6px;
            color: #64748b;
            font-size: 0.86rem;
            font-weight: 700;
            line-height: 1.25;
        }

        .ems-location-choice:hover,
        .ems-location-choice:focus {
            border-color: #20539A;
            box-shadow: 0 10px 20px rgba(32, 83, 154, 0.12);
            outline: none;
            transform: translateY(-1px);
        }

        .ems-location-origin {
            border-top: 4px solid #20539A;
        }

        .ems-location-destination {
            border-top: 4px solid #FECC36;
        }

        .ems-modal-cancel {
            border: 1px solid rgba(32, 83, 154, 0.22);
            border-radius: 10px;
            background: #fff;
            color: #20539A;
            font-weight: 800;
            padding: 8px 16px;
        }

        @media (max-width: 575.98px) {
            .ems-solicitud-wrap {
                padding: 8px;
            }

            .ems-solicitud-card .card-body {
                padding: 1rem;
            }

            .ems-form-section {
                padding: 1rem;
            }

            .ems-back-btn {
                margin-left: 0;
            }

            .ems-location-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection

@section('js')
    <script>
        (function() {
            const form = document.getElementById('ems-solicitud-form');
            const ubicacionInput = document.getElementById('ubicacion_paquete');
            const modalElement = document.getElementById('ubicacionPaqueteModal');
            const empresaSelect = document.getElementById('empresa_id');
            const empresaHint = document.getElementById('empresa_auto_hint');
            let confirmedSubmit = false;
            let autoSelectedCompany = false;

            if (!form) {
                return;
            }

            const codigoMadreInput = form.querySelector('[name="codigo_madre"]');

            function normalizeCompanyCode(value) {
                return String(value || '').trim().toUpperCase().replace(/\s+/g, '');
            }

            function extractCompanyCodeFromCodigoMadre(value) {
                const match = normalizeCompanyCode(value).match(/^C([A-Z0-9]+)A\d{5}BO$/);
                return match ? match[1] : '';
            }

            function setEmpresaHint(message, className) {
                if (!empresaHint) {
                    return;
                }

                empresaHint.textContent = message;
                empresaHint.classList.remove('is-detected', 'is-missing');

                if (className) {
                    empresaHint.classList.add(className);
                }
            }

            function unlockEmpresaSelect(clearAutoSelection) {
                if (!empresaSelect) {
                    return;
                }

                empresaSelect.disabled = false;
                empresaSelect.required = true;

                if (clearAutoSelection && autoSelectedCompany) {
                    empresaSelect.value = '';
                }

                autoSelectedCompany = false;
            }

            function syncEmpresaFromCodigoMadre() {
                if (!codigoMadreInput || !empresaSelect) {
                    return;
                }

                const codigoCliente = extractCompanyCodeFromCodigoMadre(codigoMadreInput.value);

                if (codigoCliente === '') {
                    unlockEmpresaSelect(false);
                    setEmpresaHint('', '');
                    return;
                }

                const option = Array.from(empresaSelect.options).find(function(item) {
                    return normalizeCompanyCode(item.getAttribute('data-codigo-cliente')) === codigoCliente;
                });

                if (!option) {
                    unlockEmpresaSelect(true);
                    setEmpresaHint('No se detecto empresa para el codigo ' + codigoCliente + '. Selecciona una empresa.', 'is-missing');
                    return;
                }

                empresaSelect.value = option.value;
                empresaSelect.required = false;
                empresaSelect.disabled = true;
                autoSelectedCompany = true;
                setEmpresaHint('Empresa detectada por el codigo ' + codigoCliente + '.', 'is-detected');
            }

            function showLocationModal() {
                if (window.jQuery && modalElement) {
                    window.jQuery(modalElement).modal('show');
                    return;
                }

                if (window.bootstrap && window.bootstrap.Modal && modalElement) {
                    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            }

            function hideLocationModal() {
                if (window.jQuery && modalElement) {
                    window.jQuery(modalElement).modal('hide');
                    return;
                }

                if (window.bootstrap && window.bootstrap.Modal && modalElement) {
                    window.bootstrap.Modal.getOrCreateInstance(modalElement).hide();
                }
            }

            form.addEventListener('submit', function(event) {
                if (confirmedSubmit) {
                    return;
                }

                event.preventDefault();
                showLocationModal();
            });

            document.querySelectorAll('[data-location]').forEach(function(button) {
                button.addEventListener('click', function() {
                    ubicacionInput.value = button.getAttribute('data-location');
                    confirmedSubmit = true;
                    hideLocationModal();
                    form.submit();
                });
            });

            if (codigoMadreInput) {
                codigoMadreInput.addEventListener('input', syncEmpresaFromCodigoMadre);
                codigoMadreInput.addEventListener('change', syncEmpresaFromCodigoMadre);
                syncEmpresaFromCodigoMadre();
            }

            form.addEventListener('keydown', function(event) {
                if (event.key !== 'Enter') {
                    return;
                }

                const target = event.target;
                if (target && target.tagName === 'TEXTAREA') {
                    return;
                }

                event.preventDefault();
            });
        })();
    </script>
@endsection
