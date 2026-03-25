@php
    $defaults = $defaults ?? [];
    $isCreate = $isCreate ?? false;

    $selectedEmpresa = (string) old('empresa_id', $tarifaContrato->empresa_id ?? ($defaults['empresa_id'] ?? ''));
    $selectedServicio = old('servicio', $tarifaContrato->servicio ?? ($defaults['servicio'] ?? ''));
    $selectedOrigen = old('origen', $tarifaContrato->origen ?? ($defaults['origen'] ?? ''));
    $selectedDestino = old('destino', $tarifaContrato->destino ?? ($defaults['destino'] ?? ''));
    $selectedProvincia = old('provincia', $tarifaContrato->provincia ?? ($defaults['provincia'] ?? ''));
    $provinciasPorDepartamento = $provinciasPorDepartamento ?? [];
    $provinciasDestino = $provinciasPorDepartamento[$selectedDestino] ?? [];
@endphp

<div class="box box-info padding-1">
    <div class="box-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="empresa_id">Empresa</label>
                    <select id="empresa_id" name="empresa_id" class="form-control @error('empresa_id') is-invalid @enderror" required>
                        <option value="">Seleccione...</option>
                        @foreach($empresas as $empresa)
                            <option value="{{ $empresa->id }}" {{ $selectedEmpresa === (string) $empresa->id ? 'selected' : '' }}>
                                {{ $empresa->codigo_cliente }} - {{ $empresa->nombre }} ({{ $empresa->sigla }})
                            </option>
                        @endforeach
                    </select>
                    @error('empresa_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="servicio">Servicio</label>
                    <select id="servicio" name="servicio" class="form-control @error('servicio') is-invalid @enderror" required>
                        <option value="">Seleccione...</option>
                        @foreach($servicios as $servicio)
                            <option value="{{ $servicio }}" {{ $selectedServicio === $servicio ? 'selected' : '' }}>
                                {{ $servicio }}
                            </option>
                        @endforeach
                    </select>
                    @error('servicio')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="origen">Origen (Departamento)</label>
                    <select id="origen" name="origen" class="form-control @error('origen') is-invalid @enderror" required>
                        <option value="">Seleccione...</option>
                        @foreach($departamentos as $departamento)
                            <option value="{{ $departamento }}" {{ $selectedOrigen === $departamento ? 'selected' : '' }}>
                                {{ $departamento }}
                            </option>
                        @endforeach
                    </select>
                    @error('origen')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="destino">Destino (Departamento)</label>
                    <select id="destino" name="destino" class="form-control @error('destino') is-invalid @enderror" required>
                        <option value="">Seleccione...</option>
                        @foreach($departamentos as $departamento)
                            <option value="{{ $departamento }}" {{ $selectedDestino === $departamento ? 'selected' : '' }}>
                                {{ $departamento }}
                            </option>
                        @endforeach
                    </select>
                    @error('destino')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label for="direccion">Direccion (Opcional)</label>
                    <input
                        type="text"
                        id="direccion"
                        name="direccion"
                        value="{{ old('direccion', $tarifaContrato->direccion ?? ($defaults['direccion'] ?? '')) }}"
                        class="form-control @error('direccion') is-invalid @enderror"
                    >
                    @error('direccion')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label for="zona">Zona (Opcional)</label>
                    <input
                        type="text"
                        id="zona"
                        name="zona"
                        value="{{ old('zona', $tarifaContrato->zona ?? ($defaults['zona'] ?? '')) }}"
                        class="form-control @error('zona') is-invalid @enderror"
                    >
                    @error('zona')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label for="peso">Peso (Opcional)</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="peso"
                        name="peso"
                        value="{{ old('peso', $tarifaContrato->peso ?? ($defaults['peso'] ?? '')) }}"
                        class="form-control @error('peso') is-invalid @enderror"
                    >
                    @error('peso')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="kilo">Kilo</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="kilo"
                        name="kilo"
                        value="{{ old('kilo', $tarifaContrato->kilo ?? ($defaults['kilo'] ?? '')) }}"
                        class="form-control @error('kilo') is-invalid @enderror"
                        required
                    >
                    @error('kilo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="kilo_extra">Kilo Extra</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="kilo_extra"
                        name="kilo_extra"
                        value="{{ old('kilo_extra', $tarifaContrato->kilo_extra ?? ($defaults['kilo_extra'] ?? '')) }}"
                        class="form-control @error('kilo_extra') is-invalid @enderror"
                        required
                    >
                    @error('kilo_extra')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="retencion">Retencion (%)</label>
                    <div class="input-group">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            id="retencion"
                            name="retencion"
                            value="{{ old('retencion', $tarifaContrato->retencion ?? ($defaults['retencion'] ?? '')) }}"
                            class="form-control @error('retencion') is-invalid @enderror"
                            required
                        >
                        <div class="input-group-append">
                            <span class="input-group-text">%</span>
                        </div>
                        @error('retencion')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="provincia">Provincia (Opcional)</label>
                    <select
                        id="provincia"
                        name="provincia"
                        class="form-control @error('provincia') is-invalid @enderror"
                    >
                        <option value="">Seleccione...</option>
                        @foreach($provinciasDestino as $provinciaItem)
                            <option value="{{ $provinciaItem }}" {{ $selectedProvincia === $provinciaItem ? 'selected' : '' }}>
                                {{ $provinciaItem }}
                            </option>
                        @endforeach
                        @if($selectedProvincia !== '' && !in_array($selectedProvincia, $provinciasDestino, true))
                            <option value="{{ $selectedProvincia }}" selected>{{ $selectedProvincia }}</option>
                        @endif
                    </select>
                    @error('provincia')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="horas_entrega">Horas de Entrega</label>
                    <input
                        type="number"
                        min="0"
                        step="1"
                        id="horas_entrega"
                        name="horas_entrega"
                        value="{{ old('horas_entrega', $tarifaContrato->horas_entrega ?? ($defaults['horas_entrega'] ?? '')) }}"
                        class="form-control @error('horas_entrega') is-invalid @enderror"
                        required
                    >
                    @error('horas_entrega')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="box-footer mt20">
        <div class="d-flex justify-content-between">
            <a href="{{ route('tarifa-contrato.index') }}" class="btn btn-secondary">Volver</a>
            <div class="d-flex" style="gap:8px;">
                @if($isCreate)
                    <button type="submit" name="action" value="save_and_new" class="btn btn-info">
                        Guardar y crear otro
                    </button>
                @endif
                <button type="submit" name="action" value="save" class="btn btn-primary">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const destino = document.getElementById('destino');
        const provincia = document.getElementById('provincia');
        const provinciasPorDepartamento = @json($provinciasPorDepartamento);

        if (!destino || !provincia) return;

        const renderProvincias = (departamento, selected = '') => {
            const key = (departamento || '').toUpperCase().trim();
            const provincias = provinciasPorDepartamento[key] || [];
            const selectedText = (selected || '').toUpperCase().trim();

            provincia.innerHTML = '';
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = 'Seleccione...';
            provincia.appendChild(empty);

            provincias.forEach((nombre) => {
                const option = document.createElement('option');
                option.value = nombre;
                option.textContent = nombre;
                if (nombre === selectedText) {
                    option.selected = true;
                }
                provincia.appendChild(option);
            });
        };

        destino.addEventListener('change', function () {
            renderProvincias(this.value, '');
        });

        renderProvincias(destino.value, provincia.value);
    })();
</script>
