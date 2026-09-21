@extends('adminlte::page')

@section('title', 'Marbetes')
@section('template_title', 'Marbetes')

@section('content')
    <div class="marbete-generator py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h3 mb-1">Marbetes CN 35</h1>
                <p class="text-muted mb-0">Genere identificadores de receptaculo UPU S9 con codigo de barras Code 128.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary mt-2 mt-md-0">
                <i class="fas fa-arrow-left mr-1"></i> Dashboard
            </a>
        </div>

        @if (isset($errors) && $errors->any())
            <div class="alert alert-danger">
                <strong>No se pudo generar el marbete.</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('dashboard.marbetes.pdf') }}" id="marbeteForm">
            @csrf
            <div class="row">
                <div class="col-xl-8">
                    <div class="card marbete-card">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-barcode mr-2"></i>Datos UPU del receptaculo</h3></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 form-group">
                                    <label>IMPC origen</label>
                                    <input name="origen_impc" id="originImpc" class="form-control text-uppercase" value="{{ old('origen_impc', 'BOLPBA') }}" minlength="6" maxlength="6" pattern="[A-Za-z]{6}" required>
                                    <small class="text-muted">6 letras, ej. BOLPBA</small>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Pais destino</label>
                                    <select name="pais_codigo" id="destinationCountry" class="form-control" required>
                                        <option value="">Seleccione...</option>
                                        @foreach ($destinations as $code => $destination)
                                            <option value="{{ $code }}" @selected(old('pais_codigo', 'PE') === $code)>{{ $destination['pais'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>IMPC destino</label>
                                    <input id="destinationImpc" class="form-control text-uppercase" readonly>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Ciudad destino</label>
                                    <input id="destinationCity" class="form-control text-uppercase" readonly>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Fecha</label>
                                    <input type="date" name="fecha" id="dispatchDate" class="form-control" value="{{ old('fecha', $defaultDate) }}" required>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Categoria</label>
                                    <input class="form-control" value="A - AEREO" readonly>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Subclase</label>
                                    <select name="subclase" id="mailSubclass" class="form-control" required>
                                        @foreach ($mailSubclasses as $code => $label)
                                            <option value="{{ $code }}" @selected(old('subclase', 'UN') === $code)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Tipo de despacho</label>
                                    <input name="tipo_correo" class="form-control text-uppercase" value="{{ old('tipo_correo', 'CERTIF. INT. AEREO') }}" maxlength="60" required>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Numero de despacho</label>
                                    <input type="number" name="numero_despacho" id="dispatchNumber" class="form-control" value="{{ old('numero_despacho') }}" min="1" max="9999" required>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Numero de receptaculo</label>
                                    <input type="number" name="numero_receptaculo" id="receptacleNumber" class="form-control" value="{{ old('numero_receptaculo', 1) }}" min="1" max="999" required>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Cantidad de sacas</label>
                                    <input type="number" name="cantidad_envios" class="form-control" value="{{ old('cantidad_envios', 0) }}" min="0" max="999" required>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Peso bruto (kg)</label>
                                    <input type="number" name="peso" id="grossWeight" class="form-control" value="{{ old('peso') }}" min="0.1" max="999.9" step="0.1" required>
                                </div>
                                <div class="col-md-6 form-group mb-md-0">
                                    <input type="hidden" name="ultimo_receptaculo" value="0">
                                    <div class="custom-control custom-checkbox pt-2">
                                        <input type="checkbox" name="ultimo_receptaculo" value="1" id="lastReceptacle" class="custom-control-input" @checked(old('ultimo_receptaculo', '1') == '1')>
                                        <label for="lastReceptacle" class="custom-control-label">Es el ultimo receptaculo del despacho (F)</label>
                                    </div>
                                </div>
                                <div class="col-md-6 form-group mb-0">
                                    <input type="hidden" name="registrado_asegurado" value="0">
                                    <div class="custom-control custom-checkbox pt-2">
                                        <input type="checkbox" name="registrado_asegurado" value="1" id="registeredInsured" class="custom-control-input" @checked(old('registrado_asegurado', '1') == '1')>
                                        <label for="registeredInsured" class="custom-control-label">Contiene sacas registradas o aseguradas</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card marbete-card">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-plane mr-2"></i>Transporte</h3></div>
                        <div class="card-body"><div class="row">
                            <div class="col-md-4 form-group mb-md-0"><label>Vuelo</label><input name="vuelo" class="form-control text-uppercase" value="{{ old('vuelo') }}" placeholder="Ej: OB1746" maxlength="30"></div>
                            <div class="col-md-4 form-group mb-md-0"><label>Tren</label><input name="tren" class="form-control text-uppercase" value="{{ old('tren') }}" maxlength="30"></div>
                            <div class="col-md-4 form-group mb-0"><label>Descarga</label><input id="offloadCode" class="form-control text-uppercase" readonly></div>
                        </div></div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card marbete-card sticky-preview">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-eye mr-2"></i>Identificador UPU S9</h3></div>
                        <div class="card-body">
                            <div id="receptaclePreview" class="receptacle-preview">COMPLETE LOS DATOS</div>
                            <small class="d-block text-muted mt-2">El codigo final tendra exactamente 29 caracteres y se imprimira como Code 128.</small>
                        </div>
                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-danger"><i class="fas fa-file-pdf mr-1"></i> Generar marbete PDF</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('css')
    <style>
        .marbete-generator { max-width: 1450px; margin: 0 auto; }
        .marbete-card { border: 1px solid #dce4ef; border-radius: 9px; box-shadow: 0 8px 20px rgba(15, 23, 42, .06); }
        .marbete-card .card-header { background: #fff; border-bottom-color: #e5edf6; }
        .receptacle-preview { background: #f5f7fb; border: 1px dashed #7b8794; border-radius: 8px; font-family: monospace; font-size: 1.08rem; font-weight: 700; letter-spacing: .04em; overflow-wrap: anywhere; padding: 18px 12px; text-align: center; }
        @media (min-width: 1200px) { .sticky-preview { position: sticky; top: 15px; } }
    </style>
@endsection

@section('js')
    <script>
        (() => {
            const form = document.getElementById('marbeteForm');
            const preview = document.getElementById('receptaclePreview');
            const destinations = @json($destinations);
            const digits = (value, length) => String(value || '').replace(/\D/g, '').padStart(length, '0').slice(-length);
            const letters = value => String(value || '').toUpperCase().replace(/[^A-Z]/g, '');

            const updatePreview = () => {
                const origin = letters(document.getElementById('originImpc').value);
                const selectedDestination = destinations[document.getElementById('destinationCountry').value] || null;
                const destination = selectedDestination?.impc || '';
                document.getElementById('destinationImpc').value = destination;
                document.getElementById('destinationCity').value = selectedDestination?.ciudad || '';
                document.getElementById('offloadCode').value = selectedDestination?.descarga || '';
                const date = document.getElementById('dispatchDate').value;
                const year = date ? date.slice(3, 4) : '';
                const dispatch = digits(document.getElementById('dispatchNumber').value, 4);
                const receptacle = digits(document.getElementById('receptacleNumber').value, 3);
                const weight = Math.round((parseFloat(document.getElementById('grossWeight').value) || 0) * 10);
                const code = origin + destination + 'A' + document.getElementById('mailSubclass').value + year + dispatch + receptacle
                    + (document.getElementById('lastReceptacle').checked ? '1' : '0')
                    + (document.getElementById('registeredInsured').checked ? '1' : '0')
                    + digits(weight, 4);
                preview.textContent = origin.length === 6 && destination.length === 6 && date && document.getElementById('dispatchNumber').value && document.getElementById('grossWeight').value
                    ? code
                    : 'COMPLETE LOS DATOS';
            };

            form.addEventListener('input', updatePreview);
            form.addEventListener('change', updatePreview);
            updatePreview();
        })();
    </script>
@endsection
