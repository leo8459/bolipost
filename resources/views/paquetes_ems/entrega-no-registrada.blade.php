@extends('adminlte::page')

@section('title', 'Entregar envio no registrado')

@section('content')
    <div class="ems-delivery-wrap">
        <div class="card ems-delivery-card">
            <div class="card-header ems-hero">
                <div class="ems-hero-copy">
                    <span class="ems-hero-kicker">Paquetes EMS</span>
                    <h3 class="card-title">Entregar envio no registrado</h3>
                    <p>Registra EMS o contrato con resultado de entrega, intento fallido o ida/vuelta.</p>
                </div>
                <a href="{{ route('paquetes-ems.entregados') }}" class="btn btn-light ems-back-btn">
                    <i class="fas fa-arrow-left mr-1"></i> Volver
                </a>
            </div>

            <form method="POST" action="{{ route('paquetes-ems.entregados.no-registrado.store') }}" class="card-body" enctype="multipart/form-data">
                @csrf

                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <strong>Revisa los datos:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="ems-section">
                    <div class="ems-section-head">
                        <span class="ems-section-icon"><i class="fas fa-box"></i></span>
                        <div>
                            <h4>Datos del envio</h4>
                            <p>Identifica el paquete y define si corresponde a EMS.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="custom-control custom-switch ems-switch mb-3">
                                <input type="checkbox" class="custom-control-input" id="es_ems" name="es_ems" value="1"
                                    @checked(old('es_ems'))>
                                <label class="custom-control-label" for="es_ems">Envio EMS</label>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="codigo">Codigo</label>
                                <input type="text" id="codigo" name="codigo" value="{{ old('codigo') }}"
                                    class="form-control text-uppercase" maxlength="50" required autofocus>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="peso">Peso</label>
                                <input type="number" id="peso" name="peso" value="{{ old('peso') }}"
                                    class="form-control" min="0.001" step="0.001" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ems-section">
                    <div class="ems-section-head">
                        <span class="ems-section-icon"><i class="fas fa-clipboard-check"></i></span>
                        <div>
                            <h4>Resultado de entrega</h4>
                            <p>Guarda el comprobante y la persona que recibe o el motivo del intento.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="resultado_entrega">Tipo de registro</label>
                                <select id="resultado_entrega" name="resultado_entrega" class="form-control" required>
                                    <option value="entrega" @selected(old('resultado_entrega', 'entrega') === 'entrega')>Entrega</option>
                                    <option value="intento" @selected(old('resultado_entrega') === 'intento')>Intento fallido</option>
                                    <option value="ida_vuelta" @selected(old('resultado_entrega') === 'ida_vuelta')>Ida y vuelta</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="recibido_por">Recibido por</label>
                                <input type="text" id="recibido_por" name="recibido_por" value="{{ old('recibido_por') }}"
                                    class="form-control text-uppercase" maxlength="255" required>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-12">
                            <div class="form-group">
                                <label for="foto">Foto</label>
                                <label for="foto" class="ems-file-drop mb-2">
                                    <span class="ems-file-icon"><i class="fas fa-camera"></i></span>
                                    <span class="ems-file-text">
                                        <strong id="foto-label">Seleccionar foto</strong>
                                        <small id="foto-name">JPG, PNG, WEBP, HEIC o HEIF</small>
                                    </span>
                                </label>
                                <input type="file" id="foto" name="foto" class="ems-file-input"
                                    accept="image/*,.heic,.heif" required>
                                <small class="form-text text-muted ems-help" id="foto-help">
                                    La foto es obligatoria para entrega, intento e ida/vuelta.
                                </small>
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="form-group mb-0">
                                <label for="descripcion">Descripcion</label>
                                <textarea id="descripcion" name="descripcion" class="form-control" rows="3"
                                    placeholder="Detalle de la entrega, intento o ida/vuelta...">{{ old('descripcion') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ems-section">
                    <div class="ems-section-head">
                        <span class="ems-section-icon"><i class="fas fa-route"></i></span>
                        <div>
                            <h4>Ruta y empresa</h4>
                            <p>Selecciona origen, destino y empresa cuando el envio sea contrato.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="origen">Origen</label>
                                <select id="origen" name="origen" class="form-control" required>
                                    <option value="">Seleccione origen</option>
                                    @foreach ($ciudades as $ciudad)
                                        <option value="{{ $ciudad }}" @selected(old('origen', $origen) === $ciudad)>{{ $ciudad }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="destino">Destino</label>
                                <select id="destino" name="destino" class="form-control" required>
                                    <option value="">Seleccione destino</option>
                                    @foreach ($ciudades as $ciudad)
                                        <option value="{{ $ciudad }}" @selected(old('destino') === $ciudad)>{{ $ciudad }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="provincia">Provincia</label>
                                <select id="provincia" name="provincia" class="form-control" data-selected="{{ old('provincia') }}">
                                    <option value="">Sin provincia</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="form-group" id="empresa-field">
                                <label for="empresa_id">Empresa</label>
                                <select id="empresa_id" name="empresa_id" class="form-control">
                                    <option value="">Sin empresa</option>
                                    @foreach ($empresas as $empresa)
                                        <option value="{{ $empresa->id }}" @selected((string) old('empresa_id') === (string) $empresa->id)>
                                            {{ $empresa->nombre }}{{ $empresa->codigo_cliente ? ' - ' . $empresa->codigo_cliente : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Si el codigo ya identifica una empresa, se usara automaticamente.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ems-actions">
                    <a href="{{ route('paquetes-ems.entregados') }}" class="btn ems-btn-secondary">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </a>
                    <button type="submit" class="btn ems-btn-primary">
                        <i class="fas fa-save mr-1"></i> Guardar registro
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .ems-delivery-wrap {
            background: #eef4fb;
            border: 1px solid #dbe6f4;
            border-radius: 18px;
            padding: 20px;
        }

        .ems-delivery-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(31, 56, 107, 0.14);
            overflow: hidden;
        }

        .ems-hero {
            align-items: center;
            background: #20539A;
            border-bottom: 0;
            color: #fff;
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding: 1.65rem 2rem;
        }

        .ems-hero-copy {
            max-width: 820px;
        }

        .ems-hero-kicker {
            background: rgba(254, 204, 54, 0.18);
            border: 1px solid rgba(254, 204, 54, 0.35);
            border-radius: 999px;
            color: #fff;
            display: inline-flex;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            margin-bottom: 10px;
            padding: 5px 10px;
            text-transform: uppercase;
        }

        .ems-delivery-card .card-title {
            font-weight: 800;
            font-size: 1.7rem;
            line-height: 1.18;
            margin: 0;
        }

        .ems-hero p {
            color: rgba(255, 255, 255, 0.86);
            font-weight: 600;
            line-height: 1.4;
            margin: 8px 0 0;
            max-width: 680px;
        }

        .ems-delivery-card .card-body {
            background: #fff;
            padding: 1.55rem;
        }

        .ems-section {
            background: #fbfdff;
            border: 1px solid #dce6f2;
            border-radius: 14px;
            margin-bottom: 18px;
            padding: 20px 20px 6px;
        }

        .ems-section-head {
            align-items: center;
            border-bottom: 1px solid #e3ebf5;
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
            padding-bottom: 14px;
        }

        .ems-section-icon {
            align-items: center;
            background: #eef4ff;
            border: 1px solid #d7e4f5;
            border-radius: 12px;
            color: #20539A;
            display: inline-flex;
            flex: 0 0 42px;
            height: 42px;
            justify-content: center;
            width: 42px;
        }

        .ems-section h4 {
            color: #072f61;
            font-size: 1.08rem;
            font-weight: 800;
            margin: 0;
        }

        .ems-section-head p {
            color: #667085;
            font-size: 0.9rem;
            font-weight: 600;
            margin: 3px 0 0;
        }

        .ems-delivery-card label {
            color: #10233f;
            font-weight: 800;
            margin-bottom: 7px;
        }

        .ems-delivery-card .form-control {
            border-color: #ccd5e2;
            border-radius: 10px;
            min-height: 46px;
            box-shadow: none;
        }

        .ems-delivery-card .form-control:focus {
            border-color: #20539A;
            box-shadow: 0 0 0 0.16rem rgba(32, 83, 154, 0.14);
        }

        .ems-switch .custom-control-label {
            font-size: 1rem;
        }

        .ems-switch .custom-control-label::before {
            border-color: #aebed2;
        }

        .ems-switch .custom-control-input:checked ~ .custom-control-label::before {
            background-color: #20539A;
            border-color: #20539A;
        }

        .ems-file-input {
            height: 1px;
            opacity: 0;
            overflow: hidden;
            position: absolute;
            width: 1px;
        }

        .ems-file-drop {
            align-items: center;
            background: #fff;
            border: 1px dashed #97abc5;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            gap: 12px;
            min-height: 58px;
            padding: 11px 12px;
            transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
            width: 100%;
        }

        .ems-file-drop:hover {
            background: #f8fbff;
            border-color: #20539A;
            box-shadow: 0 8px 18px rgba(32, 83, 154, 0.08);
        }

        .ems-file-icon {
            align-items: center;
            background: #20539A;
            border-radius: 10px;
            color: #fff;
            display: inline-flex;
            flex: 0 0 38px;
            height: 38px;
            justify-content: center;
            width: 38px;
        }

        .ems-file-text {
            min-width: 0;
        }

        .ems-file-text strong,
        .ems-file-text small {
            display: block;
        }

        .ems-file-text strong {
            color: #10233f;
            font-size: 0.98rem;
            line-height: 1.15;
        }

        .ems-file-text small {
            color: #667085;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ems-help {
            font-weight: 600;
        }

        .ems-actions {
            align-items: center;
            background: #f8fbff;
            border: 1px solid #dce6f2;
            border-radius: 14px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 14px;
        }

        .ems-btn-primary,
        .ems-btn-secondary,
        .ems-back-btn {
            border-radius: 10px;
            font-weight: 800;
            min-height: 42px;
            padding: 0.55rem 1.2rem;
        }

        .ems-btn-primary {
            background: #16a34a;
            border: 0;
            color: #fff;
        }

        .ems-btn-primary:hover {
            background: #15803d;
            color: #fff;
        }

        .ems-btn-secondary {
            background: #fff;
            border: 1px solid #b9c7da;
            color: #20539A;
        }

        .ems-btn-secondary:hover {
            background: #f3f6fb;
            color: #20539A;
        }

        .ems-back-btn {
            background: #fff;
            border: 0;
            color: #20539A;
            flex: 0 0 auto;
            min-width: 120px;
        }

        .ems-back-btn:hover {
            background: #f4f7fb;
            color: #20539A;
        }

        @media (max-width: 767.98px) {
            .ems-delivery-wrap {
                padding: 12px;
            }

            .ems-hero {
                align-items: flex-start;
                flex-direction: column;
                padding: 1.25rem;
            }

            .ems-delivery-card .card-title {
                font-size: 1.35rem;
            }

            .ems-back-btn,
            .ems-actions .btn {
                width: 100%;
            }

            .ems-actions {
                flex-direction: column-reverse;
            }
        }
    </style>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const provinciasPorDestino = @json($provinciasPorDestino);
            const destino = document.getElementById('destino');
            const provincia = document.getElementById('provincia');
            const origen = document.getElementById('origen');
            const esEms = document.getElementById('es_ems');
            const empresaField = document.getElementById('empresa-field');
            const foto = document.getElementById('foto');
            const fotoHelp = document.getElementById('foto-help');
            const fotoLabel = document.getElementById('foto-label');
            const fotoName = document.getElementById('foto-name');
            const selectedProvincia = (provincia.dataset.selected || '').toUpperCase();

            function renderProvincias() {
                const key = (destino.value || '').toUpperCase();
                const provincias = provinciasPorDestino[key] || [];

                provincia.innerHTML = '<option value="">Sin provincia</option>';
                provincias.forEach(function (nombre) {
                    const option = document.createElement('option');
                    option.value = nombre;
                    option.textContent = nombre;
                    if (selectedProvincia === nombre) {
                        option.selected = true;
                    }
                    provincia.appendChild(option);
                });
            }

            destino.addEventListener('change', renderProvincias);
            renderProvincias();

            function toggleEmpresa() {
                if (!esEms || !empresaField) {
                    return;
                }

                empresaField.style.display = esEms.checked ? 'none' : '';
            }

            if (esEms) {
                esEms.addEventListener('change', toggleEmpresa);
                toggleEmpresa();
            }

            function toggleFotoRequired() {
                if (!foto || !fotoHelp) {
                    return;
                }

                const esContratoLaPaz = esEms && !esEms.checked && (origen.value || '').toUpperCase() === 'LA PAZ';
                foto.required = !esContratoLaPaz;
                fotoHelp.textContent = esContratoLaPaz
                    ? 'Foto opcional solo para contrato con origen LA PAZ.'
                    : 'La foto es obligatoria para entrega, intento e ida/vuelta.';
            }

            if (esEms) {
                esEms.addEventListener('change', toggleFotoRequired);
            }
            if (origen) {
                origen.addEventListener('change', toggleFotoRequired);
            }
            if (foto) {
                foto.addEventListener('change', function () {
                    const file = foto.files && foto.files.length ? foto.files[0] : null;

                    if (!file) {
                        if (fotoLabel) {
                            fotoLabel.textContent = 'Seleccionar foto';
                        }
                        if (fotoName) {
                            fotoName.textContent = 'JPG, PNG, WEBP, HEIC o HEIF';
                        }
                        return;
                    }

                    if (fotoLabel) {
                        fotoLabel.textContent = 'Foto seleccionada';
                    }
                    if (fotoName) {
                        fotoName.textContent = file.name;
                    }
                });
            }
            toggleFotoRequired();
        });
    </script>
@endsection
