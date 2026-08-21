@php
    $selectedOrigen = (string) old('origen_id', $tarifa->origen_id ?? '');
    $selectedDestino = (string) old('destino_id', $tarifa->destino_id ?? '');
    $selectedServicioExtra = (string) old('servicio_extra_id', $tarifa->servicio_extra_id ?? '');
    $regionalNameMap = $regionalNameMap ?? [];
    $regionalLabel = fn ($name) => $regionalNameMap[strtoupper(trim((string) $name))] ?? strtoupper(trim((string) $name));
@endphp

<div class="box box-info padding-1">
    <div class="box-body">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="origen_id">Origen</label>
                    <select id="origen_id" name="origen_id" class="form-control @error('origen_id') is-invalid @enderror" required>
                        <option value="">Seleccione...</option>
                        @foreach($origenes as $origen)
                            <option value="{{ $origen->id }}" {{ $selectedOrigen === (string) $origen->id ? 'selected' : '' }}>
                                {{ $regionalLabel($origen->nombre_origen) }}
                            </option>
                        @endforeach
                    </select>
                    @error('origen_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="destino_id">Destino</label>
                    <select id="destino_id" name="destino_id" class="form-control @error('destino_id') is-invalid @enderror" required>
                        <option value="">Seleccione...</option>
                        @foreach($destinos as $destino)
                            <option value="{{ $destino->id }}" {{ $selectedDestino === (string) $destino->id ? 'selected' : '' }}>
                                {{ $regionalLabel($destino->nombre_destino) }}
                            </option>
                        @endforeach
                    </select>
                    @error('destino_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="servicio_extra_id">Servicio extra</label>
                    <select id="servicio_extra_id" name="servicio_extra_id" class="form-control @error('servicio_extra_id') is-invalid @enderror">
                        <option value="">Seleccione...</option>
                        @foreach($servicioExtras as $extra)
                            <option value="{{ $extra->id }}" {{ $selectedServicioExtra === (string) $extra->id ? 'selected' : '' }}>
                                {{ $extra->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('servicio_extra_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="peso1">Precio fijo</label>
                    <input type="number" step="0.01" id="peso1" name="peso1" value="{{ old('peso1', $tarifa->peso1 ?? 20) }}" class="form-control @error('peso1') is-invalid @enderror" readonly>
                    <small class="form-text text-muted">Bs 15 cuando origen y destino coinciden; Bs 20 para las demas rutas. No depende del peso.</small>
                    @error('peso1')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="peso2">Peso 2 (no utilizado)</label>
                    <input type="number" id="peso2" name="peso2" value="" class="form-control @error('peso2') is-invalid @enderror" readonly>
                    <small class="form-text text-muted">Se guarda como NULL.</small>
                    @error('peso2')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="peso_extra">Peso extra (no utilizado)</label>
                    <input type="number" id="peso_extra" name="peso_extra" value="" class="form-control @error('peso_extra') is-invalid @enderror" readonly>
                    <small class="form-text text-muted">Se guarda como NULL.</small>
                    @error('peso_extra')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="peso3">Peso 3 (no utilizado)</label>
                    <input type="number" id="peso3" name="peso3" value="" class="form-control @error('peso3') is-invalid @enderror" readonly>
                    <small class="form-text text-muted">Se guarda como NULL.</small>
                    @error('peso3')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="tiempo_entrega">Tiempo de entrega (horas)</label>
                    <input type="number" min="0" step="1" id="tiempo_entrega" name="tiempo_entrega" value="{{ old('tiempo_entrega', $tarifa->tiempo_entrega ?? '') }}" class="form-control @error('tiempo_entrega') is-invalid @enderror" required>
                    @error('tiempo_entrega')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="box-footer mt20">
        <div class="d-flex justify-content-between">
            <a href="{{ route('tarifario-tiktoker.index') }}" class="btn btn-secondary">Volver</a>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </div>
</div>
