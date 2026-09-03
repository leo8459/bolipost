@extends('adminlte::page')

@section('title', 'Generacion de CN')
@section('template_title', 'Generacion de CN')

@section('content')
    <div class="cn-generator py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h3 mb-1">Generacion de CN</h1>
                <p class="text-muted mb-0">Complete los datos de la hoja de ruta y agregue uno o varios paises de destino.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary mt-2 mt-md-0">
                <i class="fas fa-arrow-left mr-1"></i> Dashboard
            </a>
        </div>

        @if (isset($errors) && $errors->any())
            <div class="alert alert-danger">
                <strong>No se pudo generar el reporte.</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('dashboard.generacion-cn.pdf') }}" id="cnGenerationForm">
            @csrf
            <div class="card cn-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-alt mr-2"></i>Datos de expedicion</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-4 col-md-6 form-group">
                            <label>Administracion expedidora</label>
                            <input name="administracion_expedidora" class="form-control text-uppercase" value="{{ old('administracion_expedidora', 'BO - BOLIVIA') }}" required maxlength="80">
                        </div>
                        <div class="col-lg-4 col-md-6 form-group">
                            <label>Oficina de cambio expedidora</label>
                            <input name="oficina_cambio" class="form-control text-uppercase" value="{{ old('oficina_cambio', 'LPB - LA PAZ') }}" required maxlength="80">
                        </div>
                        <div class="col-lg-2 col-md-6 form-group">
                            <label>Fecha</label>
                            <input type="date" name="fecha" class="form-control" value="{{ old('fecha', $defaultDate) }}" required>
                        </div>
                        <div class="col-lg-2 col-md-6 form-group">
                            <label>Hoja de ruta</label>
                            <input name="hoja_ruta" class="form-control text-uppercase" value="{{ old('hoja_ruta') }}" placeholder="Ej: CP-87" required maxlength="30">
                        </div>
                        <div class="col-lg-3 col-md-6 form-group">
                            <label>Despacho</label>
                            <input name="despacho" class="form-control text-uppercase" value="{{ old('despacho') }}" placeholder="Ej: 29" required maxlength="30">
                        </div>
                        <div class="col-lg-3 col-md-6 form-group">
                            <label>Servicio</label>
                            <input name="servicio" class="form-control text-uppercase" value="{{ old('servicio', 'ENDA. INT. AEREO') }}" required maxlength="60">
                        </div>
                        <div class="col-lg-3 col-md-6 form-group">
                            <label>Transporte</label>
                            <input name="transporte" class="form-control text-uppercase" value="{{ old('transporte') }}" placeholder="Ej: BOA / AEREO" maxlength="60">
                        </div>
                        <div class="col-lg-3 col-md-6 form-group">
                            <label>Boletin</label>
                            <input name="boletin" class="form-control text-uppercase" value="{{ old('boletin', 'CN-44') }}" maxlength="30">
                        </div>
                        <div class="col-md-8 form-group mb-md-0">
                            <label>Itinerario</label>
                            <input name="itinerario" id="itineraryInput" class="form-control text-uppercase" value="{{ old('itinerario') }}" placeholder="Ej: LPB-LIM" maxlength="120">
                            <small class="form-text text-muted">Ejemplo: LPB-LIM completa todas las filas con origen LPB, oficina LIM y destino LIM.</small>
                        </div>
                        <div class="col-md-4 form-group mb-0">
                            <label>Observaciones generales</label>
                            <input name="observaciones_globales" class="form-control" value="{{ old('observaciones_globales') }}" maxlength="500">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card cn-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h3 class="card-title mb-1"><i class="fas fa-globe-americas mr-2"></i>Paises y envios de destino</h3>
                        <div class="text-muted small">Cada fila se incluira en la inscripcion detallada de la hoja de ruta.</div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm ml-auto" id="addDestination">
                        <i class="fas fa-plus mr-1"></i> Añadir destino
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 cn-table">
                            <thead>
                                <tr>
                                    <th class="country-column">Pais de destino</th>
                                    <th>Oficina</th>
                                    <th>Envio</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Peso kg</th>
                                    <th>Valor declarado</th>
                                    <th>Obs.</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="destinationRows"></tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="5" class="text-right">Totales</th>
                                    <th id="totalWeight">0,000</th>
                                    <th id="totalValue">0,00</th>
                                    <th colspan="2"><span id="rowCount">0</span> envio(s)</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center">
                    <small class="text-muted"><i class="fas fa-info-circle mr-1"></i>Puede repetir un pais para registrar varios envios.</small>
                    <button type="submit" class="btn btn-danger mt-2 mt-sm-0">
                        <i class="fas fa-file-pdf mr-1"></i> Generar reporte PDF
                    </button>
                </div>
            </div>
        </form>
    </div>

    <template id="destinationRowTemplate">
        <tr class="destination-row">
            <td>
                <select name="rows[__INDEX__][pais_codigo]" class="form-control form-control-sm country-select" required>
                    <option value="">Seleccione...</option>
                    @foreach ($countries as $code => $country)
                        <option value="{{ $code }}" data-envio-prefix="{{ $countryDispatchCodes[$code] ?? $code }}">{{ $country }} ({{ $code }})</option>
                    @endforeach
                </select>
            </td>
            <td><input name="rows[__INDEX__][oficina_destino]" class="form-control form-control-sm text-uppercase office-input" placeholder="LIM" required maxlength="40"></td>
            <td><input name="rows[__INDEX__][envio]" class="form-control form-control-sm text-uppercase" placeholder="Ej: MEX 07/1F" title="Este codigo se llena manualmente" required maxlength="40"></td>
            <td><input name="rows[__INDEX__][origen]" class="form-control form-control-sm text-uppercase" value="LPB" required maxlength="15"></td>
            <td><input name="rows[__INDEX__][destino]" class="form-control form-control-sm text-uppercase" placeholder="LIM" required maxlength="15"></td>
            <td><input type="number" name="rows[__INDEX__][peso]" class="form-control form-control-sm weight-input" min="0.001" max="999999.999" step="0.001" placeholder="0,000" required></td>
            <td>
                <input type="number" name="rows[__INDEX__][valor_declarado]" class="form-control form-control-sm value-input" min="0" max="999999999.99" step="0.01" value="0">
                <input type="hidden" name="rows[__INDEX__][porte_expedidor]" value="0">
                <input type="hidden" name="rows[__INDEX__][porte_destinatario]" value="0">
            </td>
            <td><input name="rows[__INDEX__][observacion]" class="form-control form-control-sm" maxlength="120"></td>
            <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm remove-row" title="Quitar destino"><i class="fas fa-trash"></i></button></td>
        </tr>
    </template>

@endsection

@section('css')
    <style>
        .cn-generator { max-width: 1500px; margin: 0 auto; }
        .cn-card { border: 1px solid #dce4ef; border-radius: 9px; box-shadow: 0 8px 20px rgba(15, 23, 42, .06); }
        .cn-card .card-header { background: #fff; border-bottom-color: #e5edf6; }
        .cn-table { min-width: 1180px; }
        .cn-table thead th { background: #0f4c81; border-color: #28618f; color: #fff; font-size: .78rem; vertical-align: middle; white-space: nowrap; }
        .cn-table td { vertical-align: middle; padding: .45rem; }
        .cn-table .country-column { min-width: 210px; }
        .cn-table input, .cn-table select { min-width: 90px; }
        .cn-table .country-select { min-width: 200px; }
        .cn-table tfoot { background: #f3f6fa; }
    </style>
@endsection

@section('js')
    <script>
        (() => {
            const body = document.getElementById('destinationRows');
            const template = document.getElementById('destinationRowTemplate');
            const oldRows = @json(array_values(old('rows', [])));
            const itineraryInput = document.getElementById('itineraryInput');
            let nextIndex = 0;

            const itineraryRoute = () => {
                const parts = itineraryInput.value
                    .toUpperCase()
                    .split(/\s*(?:-|–|—|\/|>)\s*/)
                    .map(part => part.trim())
                    .filter(Boolean);
                if (parts.length < 2) return null;
                return { origin: parts[0], destination: parts[parts.length - 1] };
            };

            const applyItineraryToRow = row => {
                const route = itineraryRoute();
                if (!route) return;
                row.querySelector('[name$="[origen]"]').value = route.origin;
                row.querySelector('.office-input').value = route.destination;
                row.querySelector('[name$="[destino]"]').value = route.destination;
            };

            const applyItineraryToAllRows = () => body
                .querySelectorAll('.destination-row')
                .forEach(applyItineraryToRow);

            const applyCountryToShipment = row => {
                const countrySelect = row.querySelector('.country-select');
                const selectedOption = countrySelect.options[countrySelect.selectedIndex];
                const newPrefix = selectedOption?.dataset.envioPrefix || '';
                const shipmentInput = row.querySelector('[name$="[envio]"]');
                const oldPrefix = row.dataset.envioPrefix || '';
                if (!newPrefix) return;

                if (!shipmentInput.value.trim()) {
                    shipmentInput.value = `${newPrefix} `;
                } else if (oldPrefix && shipmentInput.value.toUpperCase().startsWith(oldPrefix)) {
                    shipmentInput.value = newPrefix + shipmentInput.value.slice(oldPrefix.length);
                }
                row.dataset.envioPrefix = newPrefix;
            };

            const updateTotals = () => {
                const rows = [...body.querySelectorAll('.destination-row')];
                const weight = rows.reduce((sum, row) => sum + (parseFloat(row.querySelector('.weight-input').value) || 0), 0);
                const value = rows.reduce((sum, row) => sum + (parseFloat(row.querySelector('.value-input').value) || 0), 0);
                document.getElementById('totalWeight').textContent = weight.toLocaleString('es-BO', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
                document.getElementById('totalValue').textContent = value.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                document.getElementById('rowCount').textContent = rows.length;
                rows.forEach(row => row.querySelector('.remove-row').disabled = rows.length === 1);
            };

            const addRow = (values = {}) => {
                const html = template.innerHTML.replaceAll('__INDEX__', nextIndex++);
                body.insertAdjacentHTML('beforeend', html);
                const row = body.lastElementChild;
                Object.entries(values || {}).forEach(([key, value]) => {
                    const field = row.querySelector(`[name$="[${key}]"]`);
                    if (field) field.value = value ?? '';
                });
                applyItineraryToRow(row);
                updateTotals();
            };

            document.getElementById('addDestination').addEventListener('click', () => addRow());
            body.addEventListener('click', event => {
                const button = event.target.closest('.remove-row');
                if (!button || body.children.length === 1) return;
                button.closest('tr').remove();
                updateTotals();
            });
            body.addEventListener('input', updateTotals);
            body.addEventListener('change', event => {
                const row = event.target.closest('.destination-row');
                if (row && event.target.matches('.country-select')) applyCountryToShipment(row);
            });
            itineraryInput.addEventListener('input', applyItineraryToAllRows);
            itineraryInput.addEventListener('change', applyItineraryToAllRows);

            if (oldRows.length) oldRows.forEach(addRow); else addRow();
            applyItineraryToAllRows();
        })();
    </script>
@endsection
