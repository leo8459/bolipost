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
        <label for="tarifario_contrato_id">Tarifario *</label>
        <select class="form-control @error('tarifario_contrato_id') is-invalid @enderror" id="tarifario_contrato_id" name="tarifario_contrato_id" aria-describedby="tarifario-year-empty tarifario-select-help" required>
            <option value="">Elige un tarifario…</option>
            @foreach($tarifarios as $item)
                <option value="{{ $item->id }}" data-anio="{{ $item->anio ?? 'sin-anio' }}" @selected($selectedTarifarioId === (string) $item->id)>{{ $item->etiqueta }}</option>
            @endforeach
        </select>
        @error('tarifario_contrato_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="small text-warning mt-1" id="tarifario-year-empty" role="status" hidden>No hay tarifarios disponibles para el año seleccionado. Puedes crear uno desde el enlace de abajo.</div>
        <small class="text-muted" id="tarifario-select-help">Las filas se guardarán en el tarifario que elijas. <a href="{{ route('tarifa-contrato.tarifarios') }}">Administrar tarifarios</a>.</small>
        <details class="small mt-2 sheet-service-options">
            <summary data-services-help-toggle>¿No encuentras un servicio?</summary>
            <p class="mb-1 mt-2">Solo aparecen los servicios del año seleccionado. Puedes <a href="{{ route('tarifa-contrato.servicios', ['anio' => $selectedYear]) }}" data-services-link target="_blank" rel="noopener">ver o añadir servicios</a>.</p>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-refresh-services>Actualizar lista de servicios</button>
            <small class="text-muted" data-services-status role="status">Si añadiste uno, actualiza esta lista para usarlo.</small>
        </details>
    </div>
</div>
@once
    <script id="servicios-contrato-config" type="application/json">{!! json_encode($serviciosPorAnio ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @push('js')
        <script src="{{ asset('js/tarifario-contrato-select.js') }}?v={{ filemtime(public_path('js/tarifario-contrato-select.js')) }}" defer></script>
    @endpush
@endonce
