@php
    $incidentTypes = [
        'receptacle_damaged' => 'Saca abierta, cierre violado o saca dañada',
        'item_damaged' => 'Paquete abierto o dañado',
        'item_missing' => 'Paquete esperado que no apareció',
        'item_missing_theft' => 'Faltante con evidencia de robo',
        'item_excess' => 'Paquete físico no declarado',
        'item_missent' => 'Paquete mal encaminado',
        'bulk_count_difference' => 'Diferencia de cantidad en envío a granel',
        'other' => 'Otra irregularidad',
    ];
    $comparisonTypes = ['item_missing', 'item_missing_theft', 'item_excess', 'item_missent', 'bulk_count_difference'];
    $packageIncidentTypes = ['item_damaged', 'item_missing', 'item_missing_theft', 'item_excess', 'item_missent'];
@endphp

@if($isCurrentReceptacleOffice && ($isReceivedClosed || $isPhysicallyOpened))
    <section class="card border-0 shadow-sm mt-3 ips-verification-notes" id="verification-notes">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h2 class="h6 font-weight-bold mb-1"><i class="fas fa-clipboard-check text-primary mr-1" aria-hidden="true"></i>Incidencias y nota de verificación</h2>
                <small class="text-muted">@if($isPhysicallyOpened)Documenta las novedades al cotejar el contenido con la lista o el documento de despacho. La apertura IPS 131 es un evento separado.@else Registra aquí solo el daño exterior visible al recibir la saca. Abre físicamente y registra IPS 131 antes de informar novedades de paquetes.@endif</small>
            </div>
            <span class="badge badge-light">{{ $incidents->count() }} {{ $incidents->count() === 1 ? 'incidencia' : 'incidencias' }}</span>
        </div>
        <div class="card-body">
            @if($declaredItemsCount === null && $linkedItemsCount === 0)
                <div class="alert alert-info py-2 small" role="note">
                    <strong>IPS no informó una lista ni una cantidad declarada.</strong>
                    Un paquete no se clasifica como faltante o excedente solo porque aquí figure «0 asociados». Coteja primero el documento de despacho.
                </div>
            @endif

            @if($incidents->isNotEmpty())
                <div class="list-group mb-3">
                    @foreach($incidents as $note)
                        @php($statusClass = match($note->status) { 'resolved' => 'success', 'sent' => 'info', default => 'warning' })
                        <article class="list-group-item ips-verification-note-row">
                            <div class="d-flex flex-wrap justify-content-between align-items-start">
                                <div>
                                    <div class="font-weight-bold">{{ $note->typeLabel() }}</div>
                                    <div class="small text-muted mt-1">
                                        {{ $note->document_type }} · Código UPU {{ $note->summary_code ?? '—' }}
                                        · S9 {{ $note->receptacle_code }}
                                        @if($note->dispatch_code) · S8 {{ $note->dispatch_code }}@endif
                                        @if($note->package_code) · S10 {{ $note->package_code }}@endif
                                        · Registrada {{ $note->created_at->timezone('America/La_Paz')->format('d/m/Y H:i') }}
                                    </div>
                                </div>
                                <span class="badge badge-{{ $statusClass }}">{{ $note->statusLabel() }}</span>
                            </div>
                            <p class="mb-2 mt-2">{{ $note->description }}</p>
                            @if($note->declared_count !== null || $note->observed_count !== null)
                                <div class="small text-muted mb-2">Declarados: {{ $note->declared_count ?? 'Sin dato' }} · Encontrados: {{ $note->observed_count ?? 'Sin dato' }}</div>
                            @endif
                            @if($note->cn24_required)
                                <div class="alert alert-warning py-1 px-2 small mb-2"><strong>Preparar también CN 24:</strong> se indicó que el contenido pudo haberse perdido o dañado.</div>
                            @endif
                            @if($note->resolution)
                                <div class="small mb-2"><strong>Resolución:</strong> {{ $note->resolution }}</div>
                            @endif
                            @if(count($note->evidence_paths ?? []))
                                <div class="small mb-2">
                                    <strong>Evidencia:</strong>
                                    @foreach($note->evidence_paths as $index => $path)
                                        <a class="mr-2" href="{{ route('internacional.sacas.incidents.evidence', ['codigo' => $receptacle['codigo'], 'note' => $note->id, 'index' => $index]) }}">Archivo {{ $index + 1 }}</a>
                                    @endforeach
                                </div>
                            @endif
                            <div class="d-flex flex-wrap align-items-center">
                                <a class="btn btn-sm btn-outline-primary mr-2 mb-1" target="_blank" rel="noopener" href="{{ route('internacional.sacas.incidents.print', ['codigo' => $receptacle['codigo'], 'note' => $note->id]) }}"><i class="fas fa-print mr-1"></i>Preparar impresión</a>
                                @if($note->status === 'pending_transmission')
                                    <details class="ips-vn-transition mb-1">
                                        <summary>Registrar transmisión externa</summary>
                                        <form class="border rounded p-2 mt-2" method="POST" action="{{ route('internacional.sacas.incidents.sent', ['codigo' => $receptacle['codigo'], 'note' => $note->id]) }}">
                                            @csrf
                                            <div class="form-row">
                                                <div class="form-group col-md-4 mb-2"><label for="vn-number-{{ $note->id }}">N.º de VN externa</label><input id="vn-number-{{ $note->id }}" name="external_vn_number" class="form-control form-control-sm" maxlength="40" required></div>
                                                <div class="form-group col-md-4 mb-2"><label for="vn-method-{{ $note->id }}">Canal utilizado</label><select id="vn-method-{{ $note->id }}" name="transmission_method" class="form-control form-control-sm" required><option value="">Seleccionar</option><option value="PIMS">PIMS</option><option value="eVN">eVN / M42</option><option value="correo">Correo electrónico</option><option value="papel">Documento físico</option><option value="otro">Otro canal autorizado</option></select></div>
                                                <div class="form-group col-md-4 mb-2"><label for="vn-date-{{ $note->id }}">Fecha de envío</label><input id="vn-date-{{ $note->id }}" type="date" name="sent_at" class="form-control form-control-sm" max="{{ now()->toDateString() }}" required></div>
                                            </div>
                                            <div class="custom-control custom-checkbox mb-2"><input class="custom-control-input" type="checkbox" id="vn-sent-confirm-{{ $note->id }}" name="confirmed_transmission" value="1" required><label class="custom-control-label small" for="vn-sent-confirm-{{ $note->id }}">Confirmo que la nota ya fue transmitida por el canal indicado.</label></div>
                                            <button class="btn btn-sm btn-primary" type="submit">Guardar envío</button>
                                        </form>
                                    </details>
                                @elseif($note->status === 'sent')
                                    <span class="small text-muted mr-2 mb-1">{{ $note->transmission_method }} · VN {{ $note->external_vn_number }} · {{ $note->sent_at?->timezone('America/La_Paz')->format('d/m/Y') }}</span>
                                    <details class="ips-vn-transition mb-1">
                                        <summary>Registrar respuesta y cerrar</summary>
                                        <form class="border rounded p-2 mt-2" method="POST" action="{{ route('internacional.sacas.incidents.resolve', ['codigo' => $receptacle['codigo'], 'note' => $note->id]) }}">
                                            @csrf
                                            <label for="vn-resolution-{{ $note->id }}">Respuesta o resolución recibida</label>
                                            <textarea id="vn-resolution-{{ $note->id }}" name="resolution" class="form-control form-control-sm mb-2" rows="2" minlength="10" maxlength="3000" required></textarea>
                                            <button class="btn btn-sm btn-success" type="submit">Cerrar incidencia</button>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="ips-vn-empty mb-3"><i class="fas fa-check-circle text-muted mr-2" aria-hidden="true"></i>Todavía no hay incidencias registradas para esta saca.</div>
            @endif

            <details class="ips-vn-create" @if($errors->hasAny(['incident_type', 'document_type', 'package_code', 'comparison_source', 'comparison_reference', 'basis_confirmed', 'declared_count', 'observed_count', 'description', 'outer_condition', 'contents_condition', 'contents_at_risk', 'evidence'])) open @endif>
                <summary class="btn btn-outline-primary"><i class="fas fa-plus mr-1" aria-hidden="true"></i>Registrar incidencia</summary>
                <form class="ips-vn-form border rounded mt-3 p-3" method="POST" enctype="multipart/form-data" action="{{ route('internacional.sacas.incidents.store', ['codigo' => $receptacle['codigo']]) }}" data-comparison-types="{{ implode(',', $comparisonTypes) }}" data-package-types="{{ implode(',', $packageIncidentTypes) }}">
                    @csrf
                    <div class="alert alert-light border small mb-3">
                        Se guardará una ficha local pendiente de transmisión. Esta acción no envía un evento ni una VN a IPS automáticamente.
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="incident-type">¿Qué encontraste?</label>
                            <select id="incident-type" name="incident_type" class="form-control" required>
                                <option value="">Seleccionar incidencia</option>
                                @foreach($incidentTypes as $value => $label)
                                    <option value="{{ $value }}" @disabled(!$isPhysicallyOpened && $value !== 'receptacle_damaged') @selected(old('incident_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @unless($isPhysicallyOpened)<small class="form-text text-muted">Antes de abrir, solo documenta daños visibles en la saca. Los casos de paquetes se habilitan después del evento IPS 131.</small>@endunless
                            @error('incident_type')<small class="text-danger">{{ $message }}</small>@enderror
                            <div class="alert alert-info py-2 px-3 small mt-2 mb-0" data-case-guidance hidden></div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="document-type">Formulario según el tipo de correo</label>
                            <select id="document-type" name="document_type" class="form-control" required>
                                <option value="CP78" @selected(old('document_type', 'CP78') === 'CP78')>CP 78 · Paquetería</option>
                                <option value="CN43" @selected(old('document_type') === 'CN43')>CN 43 · Correo de carta</option>
                            </select>
                            @error('document_type')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="form-row" data-package-group hidden>
                        <div class="form-group col-md-6">
                            <label for="package-code">Marbete del paquete</label>
                            <input id="package-code" name="package_code" class="form-control" maxlength="35" pattern="[A-Za-z0-9-]+" value="{{ old('package_code') }}" placeholder="Escanea el código S10">
                            @error('package_code')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="form-row" data-comparison-group hidden>
                        <div class="form-group col-md-6">
                            <label for="comparison-source">¿Con qué lo cotejaste?</label>
                            <select id="comparison-source" name="comparison_source" class="form-control">
                                <option value="">Seleccionar respaldo</option>
                                <option value="ips" @selected(old('comparison_source') === 'ips')>Lista de paquetes asociada en IPS</option>
                                <option value="dispatch_document" @selected(old('comparison_source') === 'dispatch_document')>Documento de despacho</option>
                                <option value="other" @selected(old('comparison_source') === 'other')>Otra verificación documentada</option>
                            </select>
                            <small class="form-text text-muted" data-source-help>Para faltantes, IPS solo sirve si muestra el marbete exacto como asociado. Para excedentes o ruta incorrecta, usa el documento de despacho.</small>
                            @error('comparison_source')<small class="text-danger d-block">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group col-md-6" data-reference-group hidden>
                            <label for="comparison-reference">Documento o referencia consultada</label>
                            <input id="comparison-reference" name="comparison_reference" class="form-control" maxlength="120" value="{{ old('comparison_reference') }}" placeholder="Ej.: CP 87, número de despacho y fecha">
                            @error('comparison_reference')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group col-md-6" data-count-group hidden>
                            <div class="form-row">
                                <div class="col"><label for="declared-count">Cantidad declarada</label><input id="declared-count" type="number" min="0" max="999999" name="declared_count" class="form-control" value="{{ old('declared_count', $declaredItemsCount) }}"></div>
                                <div class="col"><label for="observed-count">Cantidad encontrada</label><input id="observed-count" type="number" min="0" max="999999" name="observed_count" class="form-control" value="{{ old('observed_count') }}"></div>
                            </div>
                            @error('declared_count')<small class="text-danger">{{ $message }}</small>@enderror
                            @error('observed_count')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="col-12" data-basis-group>
                            <div class="custom-control custom-checkbox mb-2">
                                <input class="custom-control-input" type="checkbox" id="basis-confirmed" name="basis_confirmed" value="1" @checked(old('basis_confirmed'))>
                                <label class="custom-control-label" for="basis-confirmed">Confirmo que cotejé el marbete o las cantidades con el respaldo seleccionado.</label>
                            </div>
                            @error('basis_confirmed')<small class="text-danger d-block">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="incident-description">Descripción observada</label>
                            <textarea id="incident-description" name="description" class="form-control" rows="3" minlength="10" maxlength="5000" required placeholder="Describe qué observaste, dónde y cómo llegó.">{{ old('description') }}</textarea>
                            @error('description')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group col-md-6">
                            <label for="outer-condition">Estado exterior y cierre</label>
                            <textarea id="outer-condition" name="outer_condition" class="form-control" rows="3" maxlength="2000" placeholder="Sello, cordón, etiqueta, rasgaduras, humedad…">{{ old('outer_condition') }}</textarea>
                            @error('outer_condition')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="form-row" data-content-group hidden>
                        <div class="form-group col-md-8">
                            <label for="contents-condition">Resultado de inspeccionar el contenido</label>
                            <textarea id="contents-condition" name="contents_condition" class="form-control" rows="2" maxlength="2000" placeholder="Indica si el contenido estaba completo, afectado o no se pudo verificar.">{{ old('contents_condition') }}</textarea>
                        </div>
                        <div class="col-md-4 d-flex align-items-center">
                            <div class="custom-control custom-checkbox mb-3">
                                <input class="custom-control-input" type="checkbox" id="contents-at-risk" name="contents_at_risk" value="1" @checked(old('contents_at_risk'))>
                                <label class="custom-control-label" for="contents-at-risk">El contenido pudo haberse extraído o dañado</label>
                                <small class="d-block text-muted">Si el contenido pudo perderse o dañarse, preparar también un CN 24.</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="incident-evidence">Evidencia (hasta 4 archivos, 5 MB cada uno)</label>
                        <input id="incident-evidence" type="file" name="evidence[]" class="form-control-file" accept="image/jpeg,image/png,image/webp,application/pdf" multiple>
                        @error('evidence')<small class="text-danger d-block">{{ $message }}</small>@enderror
                        @foreach($errors->get('evidence.*') as $messages)
                            @foreach($messages as $message)<small class="text-danger d-block">{{ $message }}</small>@endforeach
                        @endforeach
                        <small class="form-text text-muted">Para usar «faltante con evidencia de robo», adjunta al menos una prueba del indicio.</small>
                    </div>

                    <button class="btn btn-primary" type="submit"><i class="fas fa-save mr-1" aria-hidden="true"></i>Guardar incidencia y preparar VN</button>
                    <small class="d-block text-muted mt-2">Códigos UPU: saca/paquete dañado 35 · faltante 40 · faltante con evidencia de robo 42 · excedente 41 · mal encaminado 43. Una diferencia de cantidad a granel se detalla en CP 78.</small>
                </form>
            </details>
        </div>
    </section>

    <style>
        .ips-vn-empty{background:#f7f9fc;border:1px dashed #cbd8e8;border-radius:.5rem;color:#5d7390;padding:.9rem}
        .ips-vn-transition{display:inline-block}
        .ips-vn-transition summary,.ips-vn-create summary{cursor:pointer;list-style:none}
        .ips-vn-transition summary::-webkit-details-marker,.ips-vn-create summary::-webkit-details-marker{display:none}
        .ips-vn-form{background:#fbfcfe}
        .ips-verification-note-row{border-color:#e2e9f1}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('.ips-vn-form');
        if (!form) return;
        const type = form.querySelector('[name="incident_type"]');
        const source = form.querySelector('[name="comparison_source"]');
        const packageGroup = form.querySelector('[data-package-group]');
        const comparisonGroup = form.querySelector('[data-comparison-group]');
        const referenceGroup = form.querySelector('[data-reference-group]');
        const countGroup = form.querySelector('[data-count-group]');
        const packageInput = form.querySelector('[name="package_code"]');
        const sourceInput = form.querySelector('[name="comparison_source"]');
        const referenceInput = form.querySelector('[name="comparison_reference"]');
        const declaredInput = form.querySelector('[name="declared_count"]');
        const observedInput = form.querySelector('[name="observed_count"]');
        const basisInput = form.querySelector('[name="basis_confirmed"]');
        const contentGroup = form.querySelector('[data-content-group]');
        const evidenceInput = form.querySelector('[name="evidence[]"]');
        const sourceHelp = form.querySelector('[data-source-help]');
        const caseGuidance = form.querySelector('[data-case-guidance]');
        const contentsInput = form.querySelector('[name="contents_condition"]');
        const contentsAtRiskInput = form.querySelector('[name="contents_at_risk"]');
        const documentTypeInput = form.querySelector('[name="document_type"]');
        const needsComparison = new Set(form.dataset.comparisonTypes.split(','));
        const needsPackage = new Set(form.dataset.packageTypes.split(','));
        const sync = () => {
            const currentType = type.value;
            const comparison = needsComparison.has(currentType);
            const bulk = currentType === 'bulk_count_difference';
            const requiresDispatchBasis = ['item_excess', 'item_missent'].includes(currentType);
            const packageIncident = needsPackage.has(currentType);
            const hasContentInspection = ['receptacle_damaged', 'item_damaged', 'item_missing_theft'].includes(currentType);
            packageGroup.hidden = !packageIncident;
            comparisonGroup.hidden = !comparison;
            countGroup.hidden = !bulk;
            const ipsOption = source.querySelector('option[value="ips"]');
            ipsOption.disabled = requiresDispatchBasis;
            if (requiresDispatchBasis && source.value === 'ips') source.value = '';
            const cn43Option = documentTypeInput.querySelector('option[value="CN43"]');
            cn43Option.disabled = bulk;
            if (bulk && documentTypeInput.value === 'CN43') documentTypeInput.value = 'CP78';
            referenceGroup.hidden = !comparison || source.value === 'ips';
            contentGroup.hidden = !hasContentInspection;
            packageInput.required = packageIncident;
            sourceInput.required = comparison;
            referenceInput.required = comparison && (source.value !== 'ips' || requiresDispatchBasis);
            declaredInput.required = bulk;
            observedInput.required = bulk;
            basisInput.required = comparison;
            evidenceInput.required = currentType === 'item_missing_theft';
            if (!packageIncident) packageInput.value = '';
            if (!comparison) {
                source.value = '';
                referenceInput.value = '';
            } else if (source.value === 'ips') {
                referenceInput.value = '';
            }
            if (!bulk) {
                declaredInput.value = '';
                observedInput.value = '';
            }
            if (!hasContentInspection) {
                contentsInput.value = '';
                contentsAtRiskInput.checked = false;
            }
            sourceHelp.textContent = requiresDispatchBasis
                ? 'Para declarar un excedente o un mal encaminamiento, coteja el documento de despacho y la ruta.'
                : bulk
                    ? 'Usa IPS como fuente solo si informa la cantidad declarada. Si no la informa, compara con el documento de despacho.'
                    : 'Para faltantes, IPS solo sirve si muestra el marbete exacto como asociado. La ausencia en la lista no prueba que falte.';
            const guidance = {
                receptacle_damaged: 'Registra el estado del cierre, etiquetas y daños al recibir o abrir la saca. Si pudo afectar el contenido, marca la casilla para preparar también un CN 24.',
                item_damaged: 'Se registra después de abrir la saca y localizar el paquete. Describe el daño; si el contenido pudo perderse o dañarse, prepara también un CN 24.',
                item_missing: 'Decláralo solo si un documento o la lista de IPS espera ese marbete y ya revisaste el contenido. Que IPS muestre cero asociaciones no prueba un faltante.',
                item_missing_theft: 'Usa el código 42 solo con indicios concretos de robo. Describe el sello o señales observadas y adjunta evidencia; prepara también un CN 24.',
                item_excess: 'Compara el paquete físico con el CP 87 o documento de despacho. Una lista IPS incompleta o vacía no basta para declararlo excedente.',
                item_missent: 'Usa el código 43 si el documento de despacho demuestra que el paquete corresponde a otra ruta u oficina. Registra la referencia del documento.',
                bulk_count_difference: 'Compara el total declarado en IPS o en el documento de despacho con el recuento físico. Se documenta en la tabla de totales del CP 78 junto con el CP 87; no uses el número de asociaciones como cantidad declarada.',
                other: 'Describe el hecho observado y adjunta la evidencia disponible. La ficha queda pendiente de preparar y transmitir por el canal postal autorizado.'
            };
            caseGuidance.hidden = !currentType;
            caseGuidance.textContent = guidance[currentType] || '';
        };
        type.addEventListener('change', sync);
        source.addEventListener('change', sync);
        sync();
    });
    </script>
@endif
