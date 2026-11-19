<div id="tarifas-sheet" data-max-rows="{{ $maxSheetRows ?? 50 }}">
    <div class="tarifario-panel sheet-setup">
        <div class="sheet-step-heading">
            <span class="sheet-step-number" aria-hidden="true">1</span>
            <div>
                <h2>Elige el tarifario de destino</h2>
                <p>Selecciona el año y el borrador de la empresa que recibirá las tarifas.</p>
            </div>
        </div>
        <div class="tarifario-panel-contenido">
            @include('tarifa_contrato.tarifario-select', [
                'tarifarioLabel' => 'Tarifario de destino',
                'tarifarioHelp' => 'Si necesitas un borrador para otra empresa, abre',
                'defaultTarifarioYear' => $tarifarios->contains('anio', now()->year)
                    ? now()->year
                    : ($tarifarios->max('anio') ?? ($tarifarios->isEmpty() ? now()->year : 'sin-anio')),
            ])
        </div>
    </div>
    <div class="sheet-entry-heading">
        <div class="sheet-step-heading">
            <span class="sheet-step-number" aria-hidden="true">2</span>
            <div>
                <h2>Carga las tarifas</h2>
                <p>Completa una tarifa por fila. También puedes pegar datos desde Excel.</p>
            </div>
        </div>
        <div class="sheet-counter"><strong id="sheet-filled-count">0</strong> filas con datos <span aria-hidden="true">·</span> <span id="sheet-row-count">9</span> filas disponibles</div>
    </div>
    @if(!empty($tarifarioOrigen))
        <div class="alert tarifario-aviso sheet-prefill-aviso" role="status">
            <strong>{{ count($filasPrecargadas) }} tarifas precargadas de {{ $tarifarioOrigen->etiqueta }}.</strong>
            Las coincidencias se actualizan en el tarifario de destino al guardar.
            <a href="{{ route('tarifa-contrato.create', ['reset' => 1, 'tarifario_id' => old('tarifario_contrato_id', $defaults['tarifario_contrato_id'] ?? null)]) }}">Quitar precarga</a>
        </div>
    @elseif(collect($defaults ?? [])->except('tarifario_contrato_id')->contains(fn ($value) => $value !== null && $value !== ''))
        <div class="alert tarifario-aviso sheet-prefill-aviso" role="status">
            {{ !empty($copySource) ? "La primera fila usa la tarifa #{$copySource->id} como base." : 'La primera fila está precargada.' }}
            Revisa sus datos antes de guardar.
            @aclcan('create', null, 'tarifa-contrato')
                <a href="{{ route('tarifa-contrato.create', ['reset' => 1, 'tarifario_id' => $defaults['tarifario_contrato_id'] ?? request('tarifario_id')]) }}">Limpiar precarga</a>
            @endaclcan
        </div>
    @endif
    @if(!empty($puedePrecargar) || !empty($puedeImportar))
        <div class="sheet-load-options">
            @if(!empty($puedePrecargar))
                <details class="tarifario-panel sheet-load-option">
                    <summary class="tarifario-panel-cabecera">
                        <h2><span class="tarifario-panel-icono" aria-hidden="true"><i class="far fa-copy"></i></span>Precargar otro tarifario</h2>
                    </summary>
                    <div class="tarifario-panel-contenido">
                        <label for="tarifario-origen">Tarifario de origen</label>
                        <select id="tarifario-origen" name="tarifario_origen_id" class="form-control" form="tarifario-preload-form" aria-describedby="tarifario-origen-help" required>
                            <option value="">Elige un tarifario para copiar sus precios…</option>
                            @foreach($tarifariosOrigen as $item)
                                <option value="{{ $item->id }}" @selected(($tarifarioOrigen ?? null)?->is($item))>
                                    {{ $item->etiquetaSeleccion }}{{ !$item->empresa_titular_id && $item->empresasActivas->isNotEmpty() ? ' — '.$item->empresasActivas->pluck('nombre')->join(', ') : '' }} ({{ $item->tarifas_count }} tarifas)
                                </option>
                            @endforeach
                        </select>
                        <p id="tarifario-origen-help" class="small text-muted mt-2 mb-2">La copia reemplaza las filas de la hoja. Al guardar, los cambios se aplican al tarifario de destino.</p>
                        <button type="submit" class="btn btn-sm tarifario-boton-azul" form="tarifario-preload-form">Precargar tarifas</button>
                    </div>
                </details>
            @endif
            @if(!empty($puedeImportar))
                <details class="tarifario-panel sheet-load-option sheet-import" @if($errors->has('archivo')) open @endif>
                    <summary class="tarifario-panel-cabecera">
                        <h2><span class="tarifario-panel-icono" aria-hidden="true"><i class="fas fa-file-excel"></i></span>Importar un archivo Excel</h2>
                    </summary>
                    <div class="tarifario-panel-contenido">
                        <label for="sheet-excel-file">Archivo Excel</label>
                        <input type="file" class="form-control @error('archivo') is-invalid @enderror" id="sheet-excel-file" name="archivo" accept=".xlsx,.xls" form="tarifas-excel-import-form" aria-describedby="sheet-excel-help" required>
                        <p id="sheet-excel-help" class="small text-muted mt-2 mb-2">Usa los encabezados de la plantilla. Al importar, las filas válidas se guardan en el tarifario de destino.</p>
                        <button type="submit" class="btn btn-sm tarifario-boton-azul" form="tarifas-excel-import-form">Importar Excel</button>
                    </div>
                </details>
            @endif
        </div>
    @endif
    <div class="sheet-toolbar">
        <div class="sheet-toolbar-main">
            <div class="sheet-fullscreen-heading">
                <h2 id="sheet-fullscreen-title">Hoja de tarifas</h2>
                <div class="sheet-fullscreen-target">
                    <span>Tarifario de destino</span>
                    <strong id="sheet-fullscreen-destination"></strong>
                </div>
            </div>
            <div class="sheet-toolbar-actions">
                <button type="button" class="btn tarifario-boton-azul btn-sm" data-sheet-action="add"><i class="fas fa-plus mr-1" aria-hidden="true"></i><span data-add-rows-label>Agregar 9 filas</span></button>
                @aclcan('export', null, 'tarifa-contrato')
                    <a href="{{ route('tarifa-contrato.template-excel') }}" data-template-link class="btn tarifario-boton-borde btn-sm" id="sheet-download-template" download="plantilla_tarifa_contrato.xlsx">
                        <i class="fas fa-file-excel mr-1" aria-hidden="true"></i>Descargar plantilla Excel
                    </a>
                @endaclcan
            </div>
            <button type="button" class="btn tarifario-boton-borde btn-sm" id="sheet-fullscreen" aria-controls="tarifas-sheet" aria-pressed="false">
                <i class="fas fa-expand mr-1" aria-hidden="true"></i><span>Pantalla completa</span>
            </button>
        </div>
        <div class="sheet-edit-tools">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-sheet-action="undo" title="Deshacer (Ctrl+Z)" disabled><i class="fas fa-undo" aria-hidden="true"></i><span>Deshacer</span></button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-sheet-action="redo" title="Rehacer (Ctrl+Y)" disabled><i class="fas fa-redo" aria-hidden="true"></i><span>Rehacer</span></button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-sheet-action="duplicate" title="Copia la fila de la celda seleccionada"><i class="far fa-copy" aria-hidden="true"></i><span>Duplicar fila</span></button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-sheet-action="fill" title="Repite los valores en las filas seleccionadas"><i class="fas fa-arrow-down" aria-hidden="true"></i><span>Copiar hacia abajo</span></button>
            <button type="button" class="btn btn-outline-danger btn-sm" data-sheet-action="remove" title="Elimina la fila de la celda seleccionada"><i class="far fa-trash-alt" aria-hidden="true"></i><span>Eliminar fila</span></button>
        </div>
    </div>
    <p id="sheet-help" class="small text-muted mt-2 mb-1">Los campos con <strong>*</strong> son obligatorios. Para precios locales distintos, usa una fila por zona.</p>
    <details class="sheet-guide">
        <summary>Cómo pegar desde Excel y usar los atajos</summary>
        <ol>
            <li>Copia las celdas con datos en Excel. Si usas la plantilla, empieza en la fila 2.</li>
            <li>Haz clic en <strong>Origen</strong> de la fila donde quieres comenzar y pega con <strong>Ctrl+V</strong>.</li>
            <li>Revisa que los valores hayan quedado en la columna correcta.</li>
        </ol>
        <p><strong>Tab</strong> avanza una celda, <strong>Enter</strong> baja una fila, <strong>Ctrl+Z</strong> deshace y <strong>Ctrl+Y</strong> rehace.</p>
        <p>Para copiar un valor hacia abajo, arrastra el controlador de la esquina inferior derecha de la celda. También puedes seleccionar varias celdas con Mayús+clic y usar «Copiar hacia abajo» o Ctrl+D.</p>
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
    <div id="sheet-datalists"></div>
    <datalist id="sheet-zonas-locales">
        @foreach (\App\Models\TarifaContrato::ZONAS_LOCALES as $zona)
            <option value="{{ $zona }}"></option>
        @endforeach
    </datalist>
    <div class="sheet-footer">
        <div class="sheet-status">
            <span id="sheet-status" role="status" aria-live="polite">Tienes 9 filas para empezar. Selecciona una celda para escribir.</span>
            <span class="sheet-status-caption">Tarifas de contrato</span>
        </div>
        <div class="sheet-save-area">
            <div class="sheet-step-heading">
                <span class="sheet-step-number" aria-hidden="true">3</span>
                <div>
                    <h2>Revisa y guarda la hoja</h2>
                    <p>
                        Comprueba el destino y los precios. Si hay celdas marcadas en rojo, corrígelas.
                        @aclcan('save', null, 'tarifa-contrato')
                            Pulsa <strong>Guardar tarifas</strong> para registrar las filas de la hoja.
                        @endaclcan
                    </p>
                </div>
            </div>
            <div class="sheet-toolbar-actions">
                <a href="{{ route('tarifa-contrato.index') }}" class="btn btn-outline-secondary sheet-back-link">Volver a tarifas</a>
                @aclcan('save', null, 'tarifa-contrato')
                    <button type="submit" class="btn tarifario-boton-amarillo" id="sheet-save"><i class="fas fa-save mr-1" aria-hidden="true"></i><span>Guardar tarifas</span></button>
                @endaclcan
            </div>
        </div>
    </div>
    @aclcan('save', null, 'tarifa-contrato')
        <dialog class="sheet-save-dialog" id="sheet-save-dialog" aria-labelledby="sheet-save-title" aria-describedby="sheet-save-description">
            <span class="spinner-border" aria-hidden="true"></span>
            <div>
                <h2 id="sheet-save-title">Cargando tarifas…</h2>
                <p id="sheet-save-description">Espera mientras se guardan los cambios.</p>
            </div>
        </dialog>
    @endaclcan
</div>
<noscript><div class="alert alert-warning mt-3">Activa JavaScript en tu navegador para utilizar la hoja de tarifas.</div></noscript>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/tarifa-contrato-sheet.css') }}?v={{ filemtime(public_path('css/tarifa-contrato-sheet.css')) }}">
@endpush
@push('js')
    <script id="tarifas-sheet-config" type="application/json">{!! json_encode([
        'rows' => old('tarifas', !empty($tarifarioOrigen) ? $filasPrecargadas : [$defaults ?? []]),
        'copyServices' => array_values(array_unique(array_column($filasPrecargadas ?? [], 'servicio'))),
        'copyWeightLabel' => !empty($tarifarioOrigen) ? 'Peso (kg) (opcional)' : null,
        'errors' => $errors->toArray(),
        'departments' => $departamentos,
        'provinces' => $provinciasPorDepartamento,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script src="{{ asset('js/tarifa-contrato-sheet.js') }}?v={{ filemtime(public_path('js/tarifa-contrato-sheet.js')) }}" defer></script>
@endpush
