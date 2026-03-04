@php
    $selectedEmpresa = (string) old('empresa_id', $tarifaContrato->empresa_id ?? '');
    $selectedServicio = old('servicio', $tarifaContrato->servicio ?? '');
    $selectedOrigen = old('origen', $tarifaContrato->origen ?? '');
    $selectedDestino = old('destino', $tarifaContrato->destino ?? '');
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
            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="kilo">Kilo</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="kilo"
                        name="kilo"
                        value="{{ old('kilo', $tarifaContrato->kilo ?? '') }}"
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
                        value="{{ old('kilo_extra', $tarifaContrato->kilo_extra ?? '') }}"
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
                            value="{{ old('retencion', $tarifaContrato->retencion ?? '') }}"
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
                    <label for="provincia">Provincia</label>
                    <input
                        type="text"
                        id="provincia"
                        name="provincia"
                        value="{{ old('provincia', $tarifaContrato->provincia ?? '') }}"
                        class="form-control @error('provincia') is-invalid @enderror"
                        placeholder="Nombre de provincia"
                        required
                    >
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
                        value="{{ old('horas_entrega', $tarifaContrato->horas_entrega ?? '') }}"
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
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </div>
</div>
