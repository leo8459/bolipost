<div id="tarifas-sheet" data-max-rows="50">
    <h2 class="sheet-fullscreen-title">Tarifas de contrato</h2>
    @include('tarifa_contrato.tarifario-select')
    <div class="sheet-toolbar">
        <div class="d-flex flex-wrap align-items-center" style="gap:8px">
            @aclcan('export', null, 'tarifa-contrato')
                <a href="{{ route('tarifa-contrato.template-excel') }}" data-template-link class="btn btn-outline-success btn-sm" id="sheet-download-template" download="plantilla_tarifa_contrato.xlsx">
                    <i class="fas fa-file-excel mr-1" aria-hidden="true"></i>Descargar plantilla Excel
                </a>
            @endaclcan
            <button type="button" class="btn btn-outline-secondary btn-sm" data-sheet-action="undo" title="Deshacer (Ctrl+Z)" disabled><i class="fas fa-undo mr-1" aria-hidden="true"></i>Deshacer</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-sheet-action="redo" title="Rehacer (Ctrl+Y)" disabled><i class="fas fa-redo mr-1" aria-hidden="true"></i>Rehacer</button>
            <button type="button" class="btn btn-success btn-sm" data-sheet-action="add"><i class="fas fa-plus mr-1" aria-hidden="true"></i>Agregar 5 filas</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-sheet-action="duplicate">Duplicar fila</button>
            <button type="button" class="btn btn-outline-danger btn-sm" data-sheet-action="remove">Eliminar fila</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-sheet-action="fill">Rellenar hacia abajo</button>
            <button type="button" class="btn btn-outline-success btn-sm" id="sheet-fullscreen" aria-controls="tarifas-sheet" aria-pressed="false">
                <i class="fas fa-expand mr-1" aria-hidden="true"></i><span>Pantalla completa</span>
            </button>
        </div>
        <span class="text-muted small"><span id="sheet-filled-count">0</span> tarifas · máximo 50 filas</span>
    </div>
    <p id="sheet-help" class="small text-muted my-2">
        Cada fila es una tarifa. Descarga la plantilla, llénala desde la fila 2 y copia solo los datos, sin los encabezados (máximo 50 filas por vez).
        Pégalos en la primera celda de Origen con Ctrl+V. Selecciona un rango con Mayús+clic o arrastrando desde el borde de una celda
        y usa Ctrl+D para repetir la primera fila del rango. También puedes arrastrar el cuadrito verde hacia abajo. Usa Tab para avanzar y Enter para bajar.
        Usa Ctrl+Z para deshacer y Ctrl+Y para rehacer los cambios de la hoja.
        Arrastra el borde de un encabezado para ampliar la columna. Primero aparecen los campos obligatorios, marcados con *; las filas vacías se omiten.
        La retención es opcional: si la dejas vacía se guarda como 0 %.
    </p>
    <div class="sheet-formula-bar"><span id="sheet-cell-name">A1</span><span id="sheet-cell-value" class="text-truncate">Selecciona una celda para comenzar</span></div>
    <div class="sheet-viewport" tabindex="-1" aria-label="Hoja de tarifas de contrato" aria-describedby="sheet-help">
        <table class="sheet-table" data-no-responsive aria-label="Registro de varias tarifas">
            <caption class="sr-only">Tarifas de contrato. Una tarifa por fila.</caption>
            <colgroup id="sheet-columns"></colgroup>
            <thead id="sheet-head"></thead>
            <tbody id="sheet-body"></tbody>
        </table>
    </div>
    <div class="sheet-status"><span id="sheet-status" role="status" aria-live="polite">Lista para ingresar tarifas.</span><span>Hoja 1 · Tarifas de contrato</span></div>
    <div id="sheet-datalists"></div>
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3" style="gap:12px">
        <a href="{{ route('tarifa-contrato.index') }}" class="btn btn-secondary">Volver</a>
        @aclcan('save', null, 'tarifa-contrato')
            <button type="submit" class="btn btn-success" id="sheet-save"><i class="fas fa-save mr-1" aria-hidden="true"></i>Guardar tarifas</button>
        @endaclcan
    </div>
</div>
<noscript><div class="alert alert-warning mt-3">Activa JavaScript en tu navegador para utilizar la hoja de tarifas.</div></noscript>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/tarifa-contrato-sheet.css') }}?v={{ filemtime(public_path('css/tarifa-contrato-sheet.css')) }}">
@endpush
@push('js')
    <script id="tarifas-sheet-config" type="application/json">{!! json_encode([
        'rows' => old('tarifas', [$defaults ?? []]),
        'errors' => $errors->toArray(),
        'departments' => $departamentos,
        'provinces' => $provinciasPorDepartamento,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script src="{{ asset('js/tarifa-contrato-sheet.js') }}?v={{ filemtime(public_path('js/tarifa-contrato-sheet.js')) }}" defer></script>
@endpush
