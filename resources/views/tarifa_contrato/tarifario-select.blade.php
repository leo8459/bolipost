@php
    $selectedTarifarioId = (string) old('tarifario_contrato_id', $tarifaContrato->tarifario_contrato_id ?? ($defaults['tarifario_contrato_id'] ?? request('tarifario_id', '')));
    $selectedTarifario = $tarifarios->firstWhere('id', (int) $selectedTarifarioId);
    $selectedYear = (string) old('tarifario_anio', $selectedTarifario ? ($selectedTarifario->anio ?? 'sin-anio') : now()->year);
    $years = $tarifarios->pluck('anio')->filter()->push(now()->year, now()->year + 1)->unique()->sortDesc();
@endphp
<div class="row" data-tarifario-year-filter>
    <div class="col-md-3 form-group mb-3">
        <label for="tarifario_anio">Año del tarifario</label>
        <select class="form-control" id="tarifario_anio" name="tarifario_anio" aria-controls="tarifario_contrato_id">
            @foreach($years as $year)
                <option value="{{ $year }}" @selected($selectedYear === (string) $year)>{{ $year }}</option>
            @endforeach
            @if($tarifarios->contains(fn ($item) => $item->anio === null))
                <option value="sin-anio" @selected($selectedYear === 'sin-anio')>Año por definir</option>
            @endif
        </select>
    </div>
    <div class="col-md-9 form-group mb-3">
        <label for="tarifario_contrato_id">Tarifario y año *</label>
        <select class="form-control @error('tarifario_contrato_id') is-invalid @enderror" id="tarifario_contrato_id" name="tarifario_contrato_id" aria-describedby="tarifario-year-empty tarifario-select-help" required>
            <option value="">Selecciona un tarifario en borrador…</option>
            @foreach($tarifarios as $item)
                <option value="{{ $item->id }}" data-anio="{{ $item->anio ?? 'sin-anio' }}" @selected($selectedTarifarioId === (string) $item->id)>{{ $item->etiqueta }}</option>
            @endforeach
        </select>
        @error('tarifario_contrato_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="small text-warning mt-1" id="tarifario-year-empty" role="status" hidden>No hay tarifarios disponibles para el año seleccionado. Puedes crear uno desde el enlace de abajo.</div>
        <small class="text-muted" id="tarifario-select-help">Todas las filas se guardan en este tarifario. <a href="{{ route('tarifa-contrato.tarifarios') }}">Crear tarifario o asignar empresas</a>.</small>
        <small class="d-block text-muted">Los servicios corresponden al año seleccionado. <a href="{{ route('tarifa-contrato.servicios', ['anio' => $selectedYear]) }}" data-services-link target="_blank" rel="noopener">Gestionar servicios por año</a>.</small>
        <button type="button" class="btn btn-sm btn-outline-secondary mt-1" data-refresh-services>Actualizar lista de servicios</button>
        <small class="text-muted" data-services-status role="status">Después de añadir un servicio, actualiza la lista para usarlo aquí.</small>
    </div>
</div>
@once
    <script id="servicios-contrato-config" type="application/json">{!! json_encode($serviciosPorAnio ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @push('js')
        <script src="{{ asset('js/tarifario-contrato-select.js') }}?v={{ filemtime(public_path('js/tarifario-contrato-select.js')) }}" defer></script>
    @endpush
@endonce
