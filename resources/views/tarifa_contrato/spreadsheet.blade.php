<div id="tarifas-sheet" data-max-rows="50">
    <h2 class="sheet-fullscreen-title">Tarifas de contrato</h2>
    <div class="sheet-setup">
        <div class="sheet-step-heading">
            <span class="sheet-step-number" aria-hidden="true">1</span>
            <div><h2>Elige el tarifario</h2><p>Selecciona el año y el tarifario donde guardarás los precios.</p></div>
        </div>
        @include('tarifa_contrato.tarifario-select')
    </div>
    <div class="sheet-entry-heading">
        <div class="sheet-step-heading">
            <span class="sheet-step-number" aria-hidden="true">2</span>
            <div><h2>Completa la tabla</h2><p>Una tarifa por fila. Escribe aquí o pega tus datos desde Excel.</p></div>
        </div>
        <div class="sheet-counter"><strong id="sheet-filled-count">0</strong> filas con datos <span aria-hidden="true">·</span> <span id="sheet-row-count">9</span> filas disponibles</div>
    </div>
    <div class="sheet-toolbar">
        <div class="sheet-toolbar-main">
            <div class="sheet-toolbar-actions">
                <button type="button" class="btn btn-success btn-sm" data-sheet-action="add"><i class="fas fa-plus mr-1" aria-hidden="true"></i><span data-add-rows-label>Agregar 9 filas</span></button>
                @aclcan('export', null, 'tarifa-contrato')
                    <a href="{{ route('tarifa-contrato.template-excel') }}" data-template-link class="btn btn-outline-success btn-sm" id="sheet-download-template" download="plantilla_tarifa_contrato.xlsx">
                        <i class="fas fa-file-excel mr-1" aria-hidden="true"></i>Descargar plantilla Excel
                    </a>
                @endaclcan
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="sheet-fullscreen" aria-controls="tarifas-sheet" aria-pressed="false">
                <i class="fas fa-expand mr-1" aria-hidden="true"></i><span>Pantalla completa</span>
            </button>
        </div>
        <div class="sheet-edit-tools">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-sheet-action="undo" title="Deshacer (Ctrl+Z)" disabled><i class="fas fa-undo mr-1" aria-hidden="true"></i>Deshacer</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-sheet-action="redo" title="Rehacer (Ctrl+Y)" disabled><i class="fas fa-redo mr-1" aria-hidden="true"></i>Rehacer</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-sheet-action="duplicate" title="Copia la fila de la celda seleccionada"><i class="far fa-copy mr-1" aria-hidden="true"></i>Duplicar fila</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-sheet-action="fill" title="Repite los valores en las filas seleccionadas">Copiar hacia abajo</button>
            <button type="button" class="btn btn-outline-danger btn-sm" data-sheet-action="remove" title="Elimina la fila de la celda seleccionada"><i class="far fa-trash-alt mr-1" aria-hidden="true"></i>Eliminar fila</button>
        </div>
    </div>
    <p id="sheet-help" class="small text-muted my-2">
        <span class="text-success font-weight-bold">* Campos obligatorios</span> · Las filas vacías no se guardan · Hasta 50 tarifas por guardado.
    </p>
    <details class="sheet-guide">
        <summary>¿Cómo pegar desde Excel? Ver ayuda y atajos</summary>
        <ol>
            <li>Descarga la plantilla Excel y llénala desde la fila 2.</li>
            <li>Copia solo los datos, sin los encabezados; hasta 50 filas por vez.</li>
            <li>Haz clic en la primera celda de <strong>Origen</strong> y pega con <strong>Ctrl+V</strong>.</li>
        </ol>
        <p><strong>Tab:</strong> siguiente celda · <strong>Enter:</strong> siguiente fila · <strong>Ctrl+Z:</strong> deshacer · <strong>Ctrl+Y:</strong> rehacer.</p>
        <p>Para copiar un valor hacia abajo, arrastra el cuadrito verde de la celda. También puedes seleccionar varias celdas con Mayús+clic y usar «Copiar hacia abajo» o Ctrl+D.</p>
        <p>Para ampliar una columna, arrastra el borde de su encabezado. La retención es opcional; vacía se guarda como 0 %.</p>
    </details>
    <div class="sheet-formula-bar"><span id="sheet-cell-name">A1</span><span id="sheet-cell-value" class="text-truncate">Selecciona una celda para comenzar</span></div>
    <div class="sheet-viewport" tabindex="-1" aria-label="Hoja de tarifas de contrato" aria-describedby="sheet-help">
        <table class="sheet-table" data-no-responsive aria-label="Registro de varias tarifas">
            <caption class="sr-only">Tarifas de contrato. Una tarifa por fila.</caption>
            <colgroup id="sheet-columns"></colgroup>
            <thead id="sheet-head"></thead>
            <tbody id="sheet-body"></tbody>
        </table>
    </div>
    <div class="sheet-status"><span id="sheet-status" role="status" aria-live="polite">Tienes 9 filas para empezar. Selecciona una celda para escribir.</span><span>Tarifas de contrato</span></div>
    <div id="sheet-datalists"></div>
    <div class="sheet-save-area">
        <div class="sheet-step-heading">
            <span class="sheet-step-number" aria-hidden="true">3</span>
            <div><h2>Revisa y guarda</h2><p>Comprueba los precios y guarda tus tarifas juntas.</p></div>
        </div>
        <div class="sheet-toolbar-actions">
            <a href="{{ route('tarifa-contrato.index') }}" class="btn btn-outline-secondary">Volver a tarifas</a>
            @aclcan('save', null, 'tarifa-contrato')
                <button type="submit" class="btn btn-success" id="sheet-save"><i class="fas fa-save mr-1" aria-hidden="true"></i>Guardar tarifas</button>
            @endaclcan
        </div>
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
