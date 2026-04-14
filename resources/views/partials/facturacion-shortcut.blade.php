@php
    $canOpenFacturacionShortcut = auth()->check() && (auth()->user()?->can('feature.dashboard.facturacion') ?? false);
    $activeFacturacionCart = $canOpenFacturacionShortcut
        ? auth()->user()?->facturacionCarts()
            ->where('estado', 'borrador')
            ->with('items')
            ->latest('id')
            ->first()
        : null;
    $ultimaFacturacionEmitida = $canOpenFacturacionShortcut
        ? auth()->user()?->facturacionCarts()
            ->whereNotNull('estado_emision')
            ->latest('emitido_en')
            ->latest('id')
            ->first()
        : null;
    $facturacionItems = $activeFacturacionCart?->items ?? collect();
    $facturacionItemsCount = (int) ($activeFacturacionCart?->cantidad_items ?? $facturacionItems->count());
    $facturacionCartTotal = (float) ($activeFacturacionCart?->total ?? 0);
    $billingDocumentTypes = \App\Models\Cliente::tiposDocumentoIdentidad();
    $activeBillingMode = (string) ($activeFacturacionCart?->modalidad_facturacion ?? 'con_datos');
    $activeInvoiceChannel = (string) ($activeFacturacionCart?->canal_emision ?? 'qr');
    $activeDocumentType = (string) ($activeFacturacionCart?->tipo_documento ?? '');
    $resultadoEmision = (array) ($ultimaFacturacionEmitida?->respuesta_emision ?? []);
    $draftEmissionResponse = (array) ($activeFacturacionCart?->respuesta_emision ?? []);
    $draftEmissionErrors = (array) data_get($draftEmissionResponse, 'errors', []);
    $facturacionItemsForValidation = $facturacionItems->values();
    $validationFieldLabels = [
        'actividadEconomica' => 'Actividad economica',
        'codigoSin' => 'Codigo SIN',
        'codigo' => 'Codigo de producto',
        'descripcion' => 'Descripcion del servicio',
        'unidadMedida' => 'Unidad de medida',
    ];
    $draftEmissionIssues = collect(\Illuminate\Support\Arr::dot($draftEmissionErrors))
        ->map(function ($messages, $path) use ($facturacionItemsForValidation, $validationFieldLabels) {
            if (!preg_match('/^detalle\.(\d+)\.([A-Za-z0-9_]+)$/', (string) $path, $matches)) {
                return null;
            }

            $itemIndex = (int) $matches[1];
            $field = (string) $matches[2];
            $item = $facturacionItemsForValidation->get($itemIndex);

            if (!$item) {
                return null;
            }

            $message = is_array($messages) ? (string) ($messages[0] ?? '') : (string) $messages;

            return [
                'item' => $item,
                'item_index' => $itemIndex,
                'field' => $field,
                'field_label' => $validationFieldLabels[$field] ?? $field,
                'message' => $message !== '' ? $message : 'El valor enviado no fue aceptado por facturacion.',
            ];
        })
        ->filter()
        ->values();
    $facturaEmitida = (array) ($resultadoEmision['factura'] ?? []);
    $facturacionFeedback = session('facturacion_feedback');
    $facturacionDownloadPdf = session('facturacion_download_pdf');
    $isRejectedDraft = $activeFacturacionCart && ($activeFacturacionCart->estado_emision ?? null) === 'RECHAZADA';
    $lastEmissionReason = (string) ($resultadoEmision['razon'] ?? '');
    $lastEmissionAttempts = (int) ($resultadoEmision['_meta']['intentos'] ?? 0);
    $hasActiveFacturacionItems = $facturacionItems->isNotEmpty();
    $emitActionLabel = $isRejectedDraft ? 'Reintentar emision' : 'Emitir factura';
    $emitConfirmTitle = $isRejectedDraft ? 'Reintentar emision' : 'Emitir factura';
    $emitConfirmMessage = $isRejectedDraft
        ? 'Se reenviara el borrador rechazado con los datos actualizados a la API externa de facturacion.'
        : 'Se enviara el borrador actual a la API externa de facturacion para su emision.';
    $emitConfirmNote = $isRejectedDraft
        ? 'Antes de reenviar, corrige cliente, emision o items del borrador. No se creara una venta nueva; se reintentara el mismo borrador con tus cambios.'
        : 'Si la API acepta la venta, este borrador quedara cerrado y marcado como emitido.';
    $emitConfirmCta = $isRejectedDraft ? 'Si, reenviar' : 'Si, emitir';
@endphp

@if ($canOpenFacturacionShortcut)
    <button
        type="button"
        class="global-facturacion-fab"
        id="openFacturacionShortcut"
        aria-controls="facturacionShortcutModal"
        aria-expanded="false"
        aria-label="Abrir accesos de facturacion"
    >
        <span class="global-facturacion-fab__icon">
            <i class="fas fa-file-invoice-dollar"></i>
        </span>
        <span class="global-facturacion-fab__text">Facturacion</span>
        @if ($facturacionItemsCount > 0)
            <span class="global-facturacion-fab__badge">{{ $facturacionItemsCount }}</span>
        @endif
    </button>

    <div
        class="global-shortcut-modal"
        id="facturacionShortcutModal"
        aria-hidden="true"
        role="dialog"
        aria-modal="true"
        aria-labelledby="facturacionShortcutTitle"
    >
        <div class="global-shortcut-modal__backdrop" data-close-facturacion-modal="true"></div>
        <div class="global-shortcut-modal__panel" role="document">
            <div class="global-shortcut-modal__body">
            <div class="global-shortcut-modal__head">
                <div>
                    <p class="global-shortcut-modal__eyebrow">Acceso rapido</p>
                    <h3 id="facturacionShortcutTitle" class="global-shortcut-modal__title">Facturacion</h3>
                </div>
                <button type="button" class="global-shortcut-modal__close" id="closeFacturacionShortcut" aria-label="Cerrar modal de facturacion">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            @if (is_array($facturacionFeedback))
                <div class="global-shortcut-feedback global-shortcut-feedback--{{ $facturacionFeedback['type'] ?? 'info' }}" id="facturacionFeedbackAlert">
                    <div class="global-shortcut-feedback__icon">
                        <i class="fas @if(($facturacionFeedback['type'] ?? '') === 'success') fa-check-circle @elseif(($facturacionFeedback['type'] ?? '') === 'warning') fa-exclamation-triangle @elseif(($facturacionFeedback['type'] ?? '') === 'error') fa-times-circle @else fa-info-circle @endif"></i>
                    </div>
                    <div class="global-shortcut-feedback__body">
                        <strong>{{ $facturacionFeedback['title'] ?? 'Resultado de facturacion' }}</strong>
                        <p>{{ $facturacionFeedback['message'] ?? '' }}</p>
                        @if (!empty($facturacionFeedback['detail']))
                            <span>{{ $facturacionFeedback['detail'] }}</span>
                        @endif
                    </div>
                </div>
            @endif

            @if ($isRejectedDraft)
                <div class="global-shortcut-edit-hint">
                    <strong>Corrige antes de reenviar</strong>
                    <p>Puedes editar cliente, emision y los items del borrador antes de volver a intentar.</p>
                    <span>Cuando aparezca el mensaje de cambios guardados, ya puedes usar <b>Reintentar emision</b>.</span>
                </div>
            @endif

            @if ($draftEmissionIssues->isNotEmpty())
                <div class="global-shortcut-issue-box">
                    <strong>Campos observados por facturacion</strong>
                    <p>Corrige solo los datos marcados abajo. Cada boton abre el item exacto y enfoca el campo rechazado.</p>
                    <div class="global-shortcut-issue-list">
                        @foreach ($draftEmissionIssues as $issue)
                            @php
                                $issueItem = $issue['item'];
                            @endphp
                            <div class="global-shortcut-issue-card">
                                <div class="global-shortcut-issue-card__body">
                                    <span class="global-shortcut-issue-card__label">Item {{ $issue['item_index'] + 1 }} | {{ $issue['field_label'] }}</span>
                                    <strong>{{ $issueItem->codigo ?: ($issueItem->titulo ?: 'Item sin codigo') }}</strong>
                                    <p>{{ $issue['message'] }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="global-shortcut-secondary-btn global-shortcut-secondary-btn--link"
                                    data-edit-facturacion-item="true"
                                    data-focus-field="{{ $issue['field'] }}"
                                    data-item-id="{{ $issueItem->id }}"
                                    data-item-codigo="{{ $issueItem->codigo }}"
                                    data-item-titulo="{{ $issueItem->titulo }}"
                                    data-item-servicio="{{ $issueItem->nombre_servicio }}"
                                    data-item-destinatario="{{ $issueItem->nombre_destinatario }}"
                                    data-item-contenido="{{ (string) data_get($issueItem->resumen_origen, 'contenido', '') }}"
                                    data-item-direccion="{{ (string) data_get($issueItem->resumen_origen, 'direccion', '') }}"
                                    data-item-ciudad="{{ (string) data_get($issueItem->resumen_origen, 'ciudad', '') }}"
                                    data-item-peso="{{ (string) data_get($issueItem->resumen_origen, 'peso', '') }}"
                                    data-item-actividad-economica="{{ (string) data_get($issueItem->resumen_origen, 'actividad_economica', '') }}"
                                    data-item-codigo-sin="{{ (string) data_get($issueItem->resumen_origen, 'codigo_sin', '') }}"
                                    data-item-codigo-producto="{{ (string) data_get($issueItem->resumen_origen, 'codigo_producto', '') }}"
                                    data-item-descripcion-servicio="{{ (string) data_get($issueItem->resumen_origen, 'descripcion_servicio', '') }}"
                                    data-item-unidad-medida="{{ (string) data_get($issueItem->resumen_origen, 'unidad_medida', '') }}"
                                >
                                    Corregir {{ $issue['field_label'] }}
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('facturacion.cart.billing.update') }}"
                class="global-shortcut-billing-inline"
                id="facturacionBillingInlineForm"
                data-autosave="true"
            >
                @csrf
                @method('PUT')
                <input type="hidden" name="modalidad_facturacion" id="facturacionBillingModeInput" value="{{ $activeBillingMode }}">
                <input type="hidden" name="canal_emision" id="facturacionInvoiceChannelInput" value="{{ $activeInvoiceChannel }}">

                <div class="global-shortcut-selector-block">
                    <div class="global-shortcut-selector-group">
                        <span class="global-shortcut-selector-label">Cliente</span>
                        <div class="global-shortcut-choice-row" role="tablist" aria-label="Modo de cliente para facturacion">
                            <button type="button" class="global-shortcut-choice-btn @if($activeBillingMode === 'con_datos') is-active @endif" data-billing-mode-choice="con_datos">
                                Con datos
                            </button>
                            <button type="button" class="global-shortcut-choice-btn @if($activeBillingMode === 'sin_cliente') is-active @endif" data-billing-mode-choice="sin_cliente">
                                Sin cliente
                            </button>
                        </div>
                    </div>

                    <div class="global-shortcut-selector-group">
                        <span class="global-shortcut-selector-label">Emision</span>
                        <div class="global-shortcut-choice-row" role="tablist" aria-label="Tipo de salida de factura">
                            <button type="button" class="global-shortcut-choice-btn @if($activeInvoiceChannel === 'qr') is-active @endif" data-invoice-channel-choice="qr">
                                QR
                            </button>
                            <button type="button" class="global-shortcut-choice-btn @if($activeInvoiceChannel === 'factura_electronica') is-active @endif" data-invoice-channel-choice="factura_electronica">
                                Factura electronica
                            </button>
                        </div>
                    </div>
                </div>

                <div class="global-shortcut-anonymous-note @if($activeBillingMode !== 'sin_cliente') is-hidden @endif" id="facturacionAnonymousNote">
                    Se emitira sin datos del cliente. No se pedira documentacion, numero ni razon social.
                </div>

                <div class="global-shortcut-billing-inline__grid @if($activeBillingMode === 'sin_cliente') is-hidden @endif" id="facturacionBillingFields">
                    <div class="global-shortcut-field global-shortcut-field--full">
                        <label for="facturacionBillingDocumentType">Documentacion</label>
                        <select id="facturacionBillingDocumentType" name="tipo_documento">
                            <option value="">Selecciona</option>
                            @foreach ($billingDocumentTypes as $value => $label)
                                <option value="{{ $value }}" @selected(($activeFacturacionCart?->tipo_documento ?? null) == $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="global-shortcut-field global-shortcut-field--full">
                        <label for="facturacionBillingName">Razon social</label>
                        <input
                            type="text"
                            id="facturacionBillingName"
                            name="razon_social"
                            value="{{ $activeFacturacionCart?->razon_social }}"
                            placeholder="Nombre o razon social"
                        >
                    </div>
                    <div class="global-shortcut-field">
                        <label for="facturacionBillingDocumentNumber">Numero</label>
                        <input
                            type="text"
                            id="facturacionBillingDocumentNumber"
                            name="numero_documento"
                            value="{{ $activeFacturacionCart?->numero_documento }}"
                            placeholder="Ej. 1003579028"
                        >
                    </div>
                    <div class="global-shortcut-field" id="facturacionBillingComplementField">
                        <label for="facturacionBillingDocumentComplement">Complemento</label>
                        <input
                            type="text"
                            id="facturacionBillingDocumentComplement"
                            name="complemento_documento"
                            value="{{ $activeFacturacionCart?->complemento_documento }}"
                            placeholder="Ej. LP"
                        >
                    </div>
                </div>
            </form>

            <div class="global-shortcut-autosave-state" id="facturacionAutosaveState" aria-live="polite">
                Los cambios se guardan automaticamente.
            </div>

            @if ($ultimaFacturacionEmitida)
                <details class="global-shortcut-emision-card @if(!$hasActiveFacturacionItems) is-compact @endif">
                    <summary class="global-shortcut-emision-card__summary">
                        <div class="global-shortcut-emision-card__summary-main">
                            <span class="global-shortcut-emision-card__eyebrow">{{ $hasActiveFacturacionItems ? 'Ultima emision' : 'Factura anterior' }}</span>
                            <strong>
                                {{ $ultimaFacturacionEmitida->estado_emision ?: 'SIN RESPUESTA' }}
                                @if (!empty($facturaEmitida['nroFactura']))
                                    | Factura {{ $facturaEmitida['nroFactura'] }}
                                @endif
                            </strong>
                            @if (!empty($ultimaFacturacionEmitida->mensaje_emision))
                                <span class="global-shortcut-emision-card__summary-text">{{ $ultimaFacturacionEmitida->mensaje_emision }}</span>
                            @endif
                        </div>
                        <div class="global-shortcut-emision-card__summary-side">
                            <span class="global-shortcut-emision-card__badge @if(in_array(($ultimaFacturacionEmitida->estado_emision ?? ''), ['FACTURADA', 'PENDIENTE', 'PROCESADA'])) is-ok @else is-warn @endif">
                                {{ $ultimaFacturacionEmitida->estado_emision ?: 'SIN RESPUESTA' }}
                            </span>
                            <span class="global-shortcut-emision-card__toggle">Ver detalle</span>
                        </div>
                    </summary>

                    <div class="global-shortcut-emision-card__content">
                        @if ($lastEmissionReason !== '')
                            <p class="global-shortcut-emision-card__reason">{{ $lastEmissionReason }}</p>
                        @endif

                        <div class="global-shortcut-emision-card__meta">
                            @if (!empty($ultimaFacturacionEmitida->codigo_orden))
                                <span>Orden: {{ $ultimaFacturacionEmitida->codigo_orden }}</span>
                            @endif
                            @if (!empty($ultimaFacturacionEmitida->codigo_seguimiento))
                                <span>Seguimiento: {{ $ultimaFacturacionEmitida->codigo_seguimiento }}</span>
                            @endif
                            @if (!empty($facturaEmitida['nroFactura']))
                                <span>Factura: {{ $facturaEmitida['nroFactura'] }}</span>
                            @endif
                            @if ($lastEmissionAttempts > 0)
                                <span>Intento: {{ $lastEmissionAttempts }}</span>
                            @endif
                        </div>

                        @if (!empty($facturaEmitida['cuf']))
                            <div class="global-shortcut-emision-card__cuf">
                                CUF: {{ $facturaEmitida['cuf'] }}
                            </div>
                        @endif

                        @if (!empty($facturaEmitida['pdfUrl']) || !empty($facturaEmitida['xmlUrl']))
                            <div class="global-shortcut-emision-card__links">
                                @if (!empty($facturaEmitida['pdfUrl']))
                                    <a href="{{ $facturaEmitida['pdfUrl'] }}" target="_blank" rel="noopener noreferrer">Ver PDF</a>
                                @endif
                                @if (!empty($facturaEmitida['xmlUrl']))
                                    <a href="{{ $facturaEmitida['xmlUrl'] }}" target="_blank" rel="noopener noreferrer">Ver XML</a>
                                @endif
                            </div>
                        @endif
                    </div>
                </details>
            @endif

            <div class="global-shortcut-cart-summary">
                <div class="global-shortcut-cart-summary__metric">
                    <span>Items</span>
                    <strong>{{ $facturacionItemsCount }}</strong>
                </div>
                <div class="global-shortcut-cart-summary__metric">
                    <span>Total</span>
                    <strong>Bs {{ number_format($facturacionCartTotal, 2) }}</strong>
                </div>
            </div>

            <div class="global-shortcut-cart-block">
                <div class="global-shortcut-cart-block__head">
                    <strong>Detalle de venta</strong>
                    <div class="global-shortcut-cart-block__actions">
                        @if ($facturacionItems->isNotEmpty())
                            <form
                                method="POST"
                                action="{{ route('facturacion.cart.clear') }}"
                                class="global-shortcut-confirm-form"
                                data-confirm-title="Vaciar carrito"
                                data-confirm-message="Se eliminaran todos los items del borrador de facturacion. Puedes volver a agregarlos despues si hace falta."
                                data-confirm-note="Se conservaran intactos los registros operativos. Solo se limpiara el borrador actual del carrito."
                                data-confirm-cta="Si, vaciar"
                            >
                                @csrf
                                <button type="submit" class="global-shortcut-link-btn">
                                    Vaciar carrito
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($facturacionItems->isEmpty())
                    <div class="global-shortcut-cart-empty">
                        Aun no hay items en facturacion. Cuando confirmes una admision EMS, aparecera aqui.
                    </div>
                @else
                    <div class="global-shortcut-cart-list">
                        @foreach ($facturacionItems->sortByDesc('id') as $item)
                            <article class="global-shortcut-cart-item">
                                <div class="global-shortcut-cart-item__top">
                                    <strong>{{ $item->titulo }}</strong>
                                    <span class="global-shortcut-cart-item__amount">Bs {{ number_format((float) $item->total_linea, 2) }}</span>
                                </div>
                                <div class="global-shortcut-cart-item__meta">
                                    <span>{{ $item->codigo ?: 'Sin codigo' }}</span>
                                    <span>{{ $item->nombre_servicio ?: 'Servicio no identificado' }}</span>
                                </div>
                                @if (!empty($item->nombre_destinatario))
                                    <div class="global-shortcut-cart-item__recipient">
                                        Destinatario: {{ $item->nombre_destinatario }}
                                    </div>
                                @endif
                                @php
                                    $visibleExtras = collect((array) $item->servicios_extra)
                                        ->filter(fn ($extra) => (float) ($extra['amount'] ?? 0) > 0);
                                @endphp
                                @if ($visibleExtras->isNotEmpty())
                                    <div class="global-shortcut-cart-item__extras">
                                        @foreach ($visibleExtras as $extra)
                                            <span class="global-shortcut-chip">
                                                {{ $extra['name'] ?? 'Extra' }} | Bs {{ number_format((float) ($extra['amount'] ?? 0), 2) }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="global-shortcut-cart-item__actions">
                                    <button
                                        type="button"
                                        class="global-shortcut-link-btn"
                                        data-edit-facturacion-item="true"
                                        data-item-id="{{ $item->id }}"
                                        data-item-codigo="{{ $item->codigo }}"
                                        data-item-titulo="{{ $item->titulo }}"
                                        data-item-servicio="{{ $item->nombre_servicio }}"
                                        data-item-destinatario="{{ $item->nombre_destinatario }}"
                                        data-item-contenido="{{ (string) data_get($item->resumen_origen, 'contenido', '') }}"
                                        data-item-direccion="{{ (string) data_get($item->resumen_origen, 'direccion', '') }}"
                                        data-item-ciudad="{{ (string) data_get($item->resumen_origen, 'ciudad', '') }}"
                                        data-item-peso="{{ (string) data_get($item->resumen_origen, 'peso', '') }}"
                                        data-item-actividad-economica="{{ (string) data_get($item->resumen_origen, 'actividad_economica', '') }}"
                                        data-item-codigo-sin="{{ (string) data_get($item->resumen_origen, 'codigo_sin', '') }}"
                                        data-item-codigo-producto="{{ (string) data_get($item->resumen_origen, 'codigo_producto', '') }}"
                                        data-item-descripcion-servicio="{{ (string) data_get($item->resumen_origen, 'descripcion_servicio', '') }}"
                                        data-item-unidad-medida="{{ (string) data_get($item->resumen_origen, 'unidad_medida', '') }}"
                                    >
                                        Editar
                                    </button>
                                    <form
                                        method="POST"
                                        action="{{ route('facturacion.cart.items.destroy', $item->id) }}"
                                        class="global-shortcut-confirm-form"
                                        data-confirm-title="Quitar item"
                                        data-confirm-message="Este item se quitara del borrador de facturacion. No afectara ninguna factura final."
                                        data-confirm-note="Si fue un error, luego puedes volver a agregarlo desde la operacion correspondiente."
                                        data-confirm-cta="Si, quitar"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="global-shortcut-link-btn global-shortcut-link-btn--danger">
                                            Quitar
                                        </button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>

            </div>

            <div class="global-shortcut-footer-action">
                <div class="global-shortcut-processing" id="facturacionProcessingState" aria-live="polite" hidden>
                    <span class="global-shortcut-processing__spinner" aria-hidden="true"></span>
                    <span id="facturacionProcessingText">Procesando emision, espera un momento...</span>
                </div>
                @if ($ultimaFacturacionEmitida && !empty($ultimaFacturacionEmitida->codigo_seguimiento))
                    @if (($ultimaFacturacionEmitida->estado_emision ?? '') !== 'FACTURADA')
                        <div class="global-shortcut-footer-action__row">
                            <form method="POST" action="{{ route('facturacion.cart.consultar') }}">
                                @csrf
                                <input type="hidden" name="cart_id" value="{{ $ultimaFacturacionEmitida->id }}">
                                <button type="submit" class="global-shortcut-secondary-btn">
                                    Consultar estado
                                </button>
                            </form>
                        </div>
                    @endif
                    @if ($facturacionItems->isNotEmpty())
                        <form
                            method="POST"
                            action="{{ route('facturacion.cart.emitir') }}"
                            class="global-shortcut-confirm-form"
                            data-confirm-title="{{ $emitConfirmTitle }}"
                            data-confirm-message="{{ $emitConfirmMessage }}"
                            data-confirm-note="{{ $emitConfirmNote }}"
                            data-confirm-cta="{{ $emitConfirmCta }}"
                        >
                            @csrf
                            <button
                                type="submit"
                                class="global-shortcut-emit-btn"
                                @disabled($facturacionItems->isEmpty())
                            >
                                {{ $isRejectedDraft ? 'Reintentar emision' : 'Emitir nueva factura' }}
                            </button>
                        </form>
                    @endif
                @else
                    <form
                        method="POST"
                        action="{{ route('facturacion.cart.emitir') }}"
                        class="global-shortcut-confirm-form"
                        data-confirm-title="{{ $emitConfirmTitle }}"
                        data-confirm-message="{{ $emitConfirmMessage }}"
                        data-confirm-note="{{ $emitConfirmNote }}"
                        data-confirm-cta="{{ $emitConfirmCta }}"
                    >
                        @csrf
                        <button
                            type="submit"
                            class="global-shortcut-emit-btn"
                            @disabled($facturacionItems->isEmpty())
                        >
                            {{ $emitActionLabel }}
                        </button>
                    </form>
                @endif
            </div>

        </div>
    </div>

    <div
        class="global-shortcut-confirm"
        id="facturacionItemEditModal"
        aria-hidden="true"
        role="dialog"
        aria-modal="true"
        aria-labelledby="facturacionItemEditTitle"
    >
        <div class="global-shortcut-confirm__backdrop" data-close-facturacion-item-edit="true"></div>
        <div class="global-shortcut-confirm__panel global-shortcut-confirm__panel--wide" role="document">
            <div class="global-shortcut-confirm__header">
                <div class="global-shortcut-confirm__icon">
                    <i class="fas fa-pen"></i>
                </div>
                <div class="global-shortcut-confirm__eyebrow">Correccion de item</div>
            </div>
            <h4 id="facturacionItemEditTitle" class="global-shortcut-confirm__title">Corregir item antes de reenviar</h4>
            <p class="global-shortcut-confirm__message">
                Ajusta los datos observados y guarda el item para volver a intentar la emision.
            </p>
            <form method="POST" action="" id="facturacionItemEditForm" class="global-shortcut-item-edit-form">
                @csrf
                @method('PUT')
                <div class="global-shortcut-item-edit-alert is-hidden" id="facturacionItemEditAlert" aria-live="polite"></div>
                <div class="global-shortcut-item-edit-grid">
                    <div class="global-shortcut-field" data-edit-field-key="codigo_item">
                        <label for="facturacionEditItemCodigo">Codigo de item</label>
                        <input type="text" id="facturacionEditItemCodigo" name="codigo" required>
                    </div>
                    <div class="global-shortcut-field" data-edit-field-key="peso">
                        <label for="facturacionEditItemPeso">Peso</label>
                        <input type="number" id="facturacionEditItemPeso" name="peso" min="0" step="0.001" placeholder="Ej. 0.100">
                    </div>
                    <div class="global-shortcut-field global-shortcut-field--full" data-edit-field-key="titulo">
                        <label for="facturacionEditItemTitulo">Titulo</label>
                        <input type="text" id="facturacionEditItemTitulo" name="titulo" required>
                    </div>
                    <div class="global-shortcut-field" data-edit-field-key="nombre_servicio">
                        <label for="facturacionEditItemServicio">Servicio</label>
                        <input type="text" id="facturacionEditItemServicio" name="nombre_servicio">
                    </div>
                    <div class="global-shortcut-field" data-edit-field-key="nombre_destinatario">
                        <label for="facturacionEditItemDestinatario">Destinatario</label>
                        <input type="text" id="facturacionEditItemDestinatario" name="nombre_destinatario">
                    </div>
                    <div class="global-shortcut-field global-shortcut-field--full" data-edit-field-key="contenido">
                        <label for="facturacionEditItemContenido">Contenido</label>
                        <input type="text" id="facturacionEditItemContenido" name="contenido">
                    </div>
                    <div class="global-shortcut-field global-shortcut-field--full" data-edit-field-key="direccion">
                        <label for="facturacionEditItemDireccion">Direccion</label>
                        <input type="text" id="facturacionEditItemDireccion" name="direccion">
                    </div>
                    <div class="global-shortcut-field global-shortcut-field--full" data-edit-field-key="ciudad">
                        <label for="facturacionEditItemCiudad">Ciudad</label>
                        <input type="text" id="facturacionEditItemCiudad" name="ciudad">
                    </div>
                    <div class="global-shortcut-field" data-edit-field-key="actividadEconomica">
                        <label for="facturacionEditItemActividadEconomica">Actividad economica</label>
                        <input type="text" id="facturacionEditItemActividadEconomica" name="actividad_economica" maxlength="6" placeholder="6 caracteres">
                    </div>
                    <div class="global-shortcut-field" data-edit-field-key="codigoSin">
                        <label for="facturacionEditItemCodigoSin">Codigo SIN</label>
                        <input type="text" id="facturacionEditItemCodigoSin" name="codigo_sin">
                    </div>
                    <div class="global-shortcut-field" data-edit-field-key="codigo">
                        <label for="facturacionEditItemCodigoProducto">Codigo de producto</label>
                        <input type="text" id="facturacionEditItemCodigoProducto" name="codigo_producto">
                    </div>
                    <div class="global-shortcut-field" data-edit-field-key="unidadMedida">
                        <label for="facturacionEditItemUnidadMedida">Unidad de medida</label>
                        <input type="number" id="facturacionEditItemUnidadMedida" name="unidad_medida" min="1" step="1">
                    </div>
                    <div class="global-shortcut-field global-shortcut-field--full" data-edit-field-key="descripcion">
                        <label for="facturacionEditItemDescripcionServicio">Descripcion del servicio</label>
                        <input type="text" id="facturacionEditItemDescripcionServicio" name="descripcion_servicio">
                    </div>
                </div>
                <div class="global-shortcut-confirm__actions">
                    <button type="button" class="global-shortcut-confirm__btn global-shortcut-confirm__btn--ghost" id="facturacionItemEditCancel">
                        Cancelar
                    </button>
                    <button type="submit" class="global-shortcut-confirm__btn global-shortcut-confirm__btn--primary" id="facturacionItemEditSubmit">
                        Guardar item
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        class="global-shortcut-confirm"
        id="facturacionActionConfirmModal"
        aria-hidden="true"
        role="dialog"
        aria-modal="true"
        aria-labelledby="facturacionActionConfirmTitle"
    >
        <div class="global-shortcut-confirm__backdrop" data-close-facturacion-confirm="true"></div>
        <div class="global-shortcut-confirm__panel" role="document">
            <div class="global-shortcut-confirm__header">
                <div class="global-shortcut-confirm__icon">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <div class="global-shortcut-confirm__eyebrow">Confirmacion de carrito</div>
            </div>
            <h4 id="facturacionActionConfirmTitle" class="global-shortcut-confirm__title">Confirmar accion</h4>
            <p class="global-shortcut-confirm__message" id="facturacionActionConfirmMessage">
                Esta accion actualizara tu borrador de facturacion.
            </p>
            <div class="global-shortcut-confirm__note">
                <span class="global-shortcut-confirm__note-label">Importante</span>
                <p id="facturacionActionConfirmNote">
                    Solo se modificara el borrador actual del carrito.
                </p>
            </div>
            <div class="global-shortcut-confirm__actions">
                <button type="button" class="global-shortcut-confirm__btn global-shortcut-confirm__btn--ghost" id="facturacionActionConfirmCancel">
                    Cancelar
                </button>
                <button type="button" class="global-shortcut-confirm__btn global-shortcut-confirm__btn--danger" id="facturacionActionConfirmAccept">
                    Confirmar
                </button>
            </div>
        </div>
    </div>

    <style>
        .global-facturacion-fab {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 1080;
            border: none;
            border-radius: 999px;
            padding: 0 18px 0 14px;
            min-height: 58px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, #20539a 0%, #0f3f78 100%);
            color: #fff;
            box-shadow: 0 18px 38px rgba(15, 63, 120, .28);
            font-weight: 700;
            letter-spacing: .01em;
            transition: transform .18s ease, box-shadow .18s ease, opacity .18s ease;
        }
        .global-facturacion-fab:hover,
        .global-facturacion-fab:focus {
            color: #fff;
            outline: none;
            transform: translateY(-2px);
            box-shadow: 0 22px 44px rgba(15, 63, 120, .34);
        }
        .global-facturacion-fab__icon {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .16);
            font-size: 1rem;
            flex-shrink: 0;
        }
        .global-facturacion-fab__text {
            white-space: nowrap;
        }
        .global-facturacion-fab__badge {
            min-width: 24px;
            height: 24px;
            padding: 0 6px;
            border-radius: 999px;
            background: #fecc36;
            color: #173962;
            font-size: .79rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, .16);
        }
        .global-shortcut-modal {
            position: fixed;
            inset: 0;
            z-index: 1090;
            display: flex;
            align-items: flex-end;
            justify-content: flex-end;
            pointer-events: none;
            opacity: 0;
            transition: opacity .2s ease;
        }
        .global-shortcut-modal.is-open {
            opacity: 1;
            pointer-events: auto;
        }
        .global-shortcut-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(8, 24, 50, .45);
            backdrop-filter: blur(3px);
        }
        .global-shortcut-modal__panel {
            position: relative;
            width: min(430px, calc(100vw - 32px));
            margin: 0 24px 24px;
            border-radius: 22px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid rgba(32, 83, 154, .12);
            box-shadow: 0 28px 60px rgba(8, 24, 50, .28);
            max-height: calc(100vh - 32px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: translateY(18px) scale(.98);
            transition: transform .22s ease;
        }
        .global-shortcut-modal.is-open .global-shortcut-modal__panel {
            transform: translateY(0) scale(1);
        }
        .global-shortcut-modal__body {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            padding: 18px 18px 12px;
        }
        .global-shortcut-modal__head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 14px;
        }
        .global-shortcut-modal__eyebrow {
            margin: 0 0 6px;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #20539a;
        }
        .global-shortcut-modal__title {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 700;
            color: #14345f;
        }
        .global-shortcut-feedback {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 14px;
            padding: 13px 14px;
            border-radius: 16px;
            border: 1px solid transparent;
        }
        .global-shortcut-feedback--success {
            background: #edf9f1;
            border-color: #cfead8;
            color: #1f6a3e;
        }
        .global-shortcut-feedback--warning {
            background: #fff7ec;
            border-color: #f6dec0;
            color: #9a5a00;
        }
        .global-shortcut-feedback--error {
            background: #fff1f0;
            border-color: #f2cfca;
            color: #a33e34;
        }
        .global-shortcut-feedback--info {
            background: #eef5ff;
            border-color: #d5e4fb;
            color: #20539a;
        }
        .global-shortcut-feedback__icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .72);
            font-size: 1rem;
            flex: 0 0 auto;
        }
        .global-shortcut-feedback__body {
            min-width: 0;
        }
        .global-shortcut-feedback__body strong {
            display: block;
            font-size: .94rem;
            font-weight: 800;
        }
        .global-shortcut-feedback__body p {
            margin: 3px 0 0;
            font-size: .88rem;
            line-height: 1.5;
        }
        .global-shortcut-feedback__body span {
            display: block;
            margin-top: 6px;
            font-size: .82rem;
            line-height: 1.45;
            opacity: .92;
        }
        .global-shortcut-edit-hint {
            margin-bottom: 12px;
            padding: 12px 14px;
            border-radius: 16px;
            background: #fff8e8;
            border: 1px solid #f4dfb3;
            color: #8a5300;
        }
        .global-shortcut-edit-hint strong {
            display: block;
            font-size: .92rem;
            font-weight: 800;
        }
        .global-shortcut-edit-hint p {
            margin: 4px 0 0;
            font-size: .87rem;
            line-height: 1.45;
        }
        .global-shortcut-edit-hint span {
            display: block;
            margin-top: 6px;
            font-size: .81rem;
            line-height: 1.4;
        }
        .global-shortcut-autosave-state {
            min-height: 20px;
            margin: 8px 2px 12px;
            font-size: .79rem;
            color: #5f7290;
        }
        .global-shortcut-autosave-state.is-saving {
            color: #9a5a00;
        }
        .global-shortcut-autosave-state.is-saved {
            color: #1f6a3e;
        }
        .global-shortcut-autosave-state.is-error {
            color: #a33e34;
        }
        .global-shortcut-issue-box {
            margin-bottom: 14px;
            padding: 14px 16px;
            border-radius: 18px;
            background: #fff8ef;
            border: 1px solid #ffd8a8;
        }
        .global-shortcut-issue-box strong {
            display: block;
            color: #9a5a00;
            font-size: .94rem;
            font-weight: 800;
        }
        .global-shortcut-issue-box p {
            margin: 6px 0 0;
            color: #856338;
            font-size: .85rem;
            line-height: 1.45;
        }
        .global-shortcut-issue-list {
            display: grid;
            gap: 10px;
            margin-top: 12px;
        }
        .global-shortcut-issue-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 16px;
            background: rgba(255, 255, 255, .92);
            border: 1px solid #ffe3bd;
        }
        .global-shortcut-issue-card__body {
            min-width: 0;
        }
        .global-shortcut-issue-card__label {
            display: inline-flex;
            margin-bottom: 4px;
            color: #b16a0a;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .global-shortcut-issue-card__body strong {
            color: #173962;
            font-size: .9rem;
        }
        .global-shortcut-issue-card__body p {
            margin: 4px 0 0;
            color: #6e5b3b;
            font-size: .82rem;
            line-height: 1.4;
        }
        .global-shortcut-modal__subtitle {
            margin: 6px 0 0;
            color: #607089;
            font-size: .93rem;
            line-height: 1.45;
        }
        .global-shortcut-modal__close {
            width: 40px;
            height: 40px;
            border: 1px solid #d7e3f3;
            border-radius: 50%;
            background: #fff;
            color: #20539a;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .global-shortcut-cart-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .global-shortcut-emision-card {
            margin-bottom: 16px;
            border: 1px solid #e7eef7;
            border-radius: 14px;
            background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
            box-shadow: 0 8px 18px rgba(15, 63, 120, .04);
            overflow: hidden;
        }
        .global-shortcut-emision-card[open] {
            border-color: #d9e7fa;
        }
        .global-shortcut-emision-card__summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            list-style: none;
            cursor: pointer;
            padding: 14px 16px;
        }
        .global-shortcut-emision-card__summary::-webkit-details-marker {
            display: none;
        }
        .global-shortcut-emision-card__summary-main {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .global-shortcut-emision-card__eyebrow {
            display: block;
            color: #73839a;
            font-size: .74rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .global-shortcut-emision-card__summary-main strong {
            color: #1e2f45;
            font-size: .94rem;
            font-weight: 800;
        }
        .global-shortcut-emision-card__summary-text {
            margin-top: 4px;
            color: #6a7d95;
            font-size: .82rem;
            line-height: 1.45;
        }
        .global-shortcut-emision-card__summary-side {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        .global-shortcut-emision-card__badge {
            border-radius: 999px;
            padding: 6px 10px;
            font-size: .74rem;
            font-weight: 800;
            white-space: nowrap;
            background: #eef3fb;
            color: #516780;
        }
        .global-shortcut-emision-card__badge.is-ok {
            background: #e8f7ee;
            color: #1f7a42;
        }
        .global-shortcut-emision-card__badge.is-warn {
            background: #fff2e7;
            color: #b86a00;
        }
        .global-shortcut-emision-card__toggle {
            color: #20539a;
            font-size: .78rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .global-shortcut-emision-card__content {
            padding: 0 16px 16px;
            border-top: 1px solid #edf3fb;
        }
        .global-shortcut-emision-card__reason {
            margin: 12px 0 0;
            color: #b86a00;
            font-size: .85rem;
            font-weight: 600;
            line-height: 1.55;
        }
        .global-shortcut-emision-card__meta {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            color: #657891;
            font-size: .79rem;
        }
        .global-shortcut-emision-card__cuf {
            margin-top: 10px;
            color: #415775;
            font-size: .78rem;
            line-height: 1.45;
            word-break: break-all;
        }
        .global-shortcut-emision-card__links {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .global-shortcut-emision-card__links a {
            color: #20539a;
            font-size: .82rem;
            font-weight: 700;
            text-decoration: none;
        }
        .global-shortcut-emision-card__links a:hover,
        .global-shortcut-emision-card__links a:focus {
            color: #163f74;
            text-decoration: underline;
            outline: none;
        }
        .global-shortcut-emision-card.is-compact .global-shortcut-emision-card__summary {
            padding-top: 12px;
            padding-bottom: 12px;
        }
        .global-shortcut-cart-summary__metric {
            padding: 14px 16px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #e7eef7;
            box-shadow: 0 8px 18px rgba(15, 63, 120, .04);
        }
        .global-shortcut-cart-summary__metric span {
            display: block;
            color: #73839a;
            font-size: .78rem;
            letter-spacing: .02em;
            margin-bottom: 6px;
        }
        .global-shortcut-cart-summary__metric strong {
            font-size: 1.18rem;
            color: #173962;
            font-weight: 800;
        }
        .global-shortcut-billing-inline {
            margin-bottom: 14px;
            padding: 14px;
            border: 1px solid #e3ebf6;
            border-radius: 16px;
            background: #fff;
        }
        .global-shortcut-selector-block {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }
        .global-shortcut-selector-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }
        .global-shortcut-selector-label {
            color: #4f627e;
            font-size: .83rem;
            font-weight: 700;
        }
        .global-shortcut-choice-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }
        .global-shortcut-choice-btn {
            min-height: 42px;
            border-radius: 12px;
            border: 1px solid #d8e4f3;
            background: #f8fbff;
            color: #20539a;
            font-size: .85rem;
            font-weight: 700;
            line-height: 1.2;
            text-align: center;
            padding: 8px 10px;
            white-space: normal;
            word-break: keep-all;
            transition: border-color .16s ease, box-shadow .16s ease, background .16s ease, transform .16s ease;
        }
        .global-shortcut-choice-btn:hover,
        .global-shortcut-choice-btn:focus {
            outline: none;
            transform: translateY(-1px);
            border-color: #aac4e8;
        }
        .global-shortcut-choice-btn.is-active {
            background: linear-gradient(135deg, #20539a 0%, #0f3f78 100%);
            border-color: #0f3f78;
            color: #fff;
            box-shadow: 0 12px 22px rgba(15, 63, 120, .18);
        }
        .global-shortcut-anonymous-note {
            margin-bottom: 12px;
            padding: 11px 12px;
            border-radius: 12px;
            background: #f8fbff;
            border: 1px solid #dce8f8;
            color: #5c6f89;
            font-size: .84rem;
            line-height: 1.45;
        }
        .global-shortcut-billing-inline__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .global-shortcut-field.is-hidden {
            display: none;
        }
        .global-shortcut-billing-inline__grid.is-hidden,
        .global-shortcut-anonymous-note.is-hidden {
            display: none;
        }
        .global-shortcut-field--full {
            grid-column: 1 / -1;
        }
        .global-shortcut-field label {
            display: block;
            margin-bottom: 6px;
            color: #4f627e;
            font-size: .83rem;
            font-weight: 700;
        }
        .global-shortcut-field input,
        .global-shortcut-field select {
            width: 100%;
            min-height: 44px;
            border-radius: 12px;
            border: 1px solid #d8e4f3;
            background: #fbfdff;
            padding: 10px 12px;
            color: #18385f;
            transition: border-color .16s ease, box-shadow .16s ease, background .16s ease;
        }
        .global-shortcut-field input:focus,
        .global-shortcut-field select:focus {
            outline: none;
            border-color: #7ea7dd;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(32, 83, 154, .1);
        }
        .global-shortcut-cart-block {
            margin-bottom: 14px;
            border: 1px solid #e7eef7;
            border-radius: 14px;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 10px 22px rgba(15, 63, 120, .04);
        }
        .global-shortcut-cart-block__head {
            padding: 14px 16px;
            background: #fff;
            border-bottom: 1px solid #edf2f8;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .global-shortcut-cart-block__head strong {
            color: #1e2f45;
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: -.01em;
        }
        .global-shortcut-cart-block__head small {
            color: #6b7a90;
        }
        .global-shortcut-cart-block__actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .global-shortcut-cart-empty {
            padding: 14px;
            color: #6b7a90;
            font-size: .92rem;
            line-height: 1.45;
        }
        .global-shortcut-cart-list {
            max-height: 320px;
            overflow-y: auto;
        }
        .global-shortcut-cart-item {
            padding: 16px;
            border-top: 1px solid #f0f4f9;
        }
        .global-shortcut-cart-item:first-child {
            border-top: none;
        }
        .global-shortcut-cart-item__top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            color: #1f3658;
        }
        .global-shortcut-cart-item__top strong {
            font-size: .98rem;
            font-weight: 800;
            line-height: 1.3;
        }
        .global-shortcut-cart-item__amount {
            font-size: .95rem;
            font-weight: 800;
            color: #173962;
            white-space: nowrap;
        }
        .global-shortcut-cart-item__meta {
            margin-top: 6px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            color: #6f8097;
            font-size: .8rem;
            line-height: 1.45;
        }
        .global-shortcut-cart-item__recipient {
            margin-top: 10px;
            color: #586c87;
            font-size: .84rem;
        }
        .global-shortcut-cart-item__extras {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .global-shortcut-cart-item__actions {
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 14px;
            justify-content: flex-end;
        }
        .global-shortcut-footer-action {
            flex: 0 0 auto;
            padding: 12px 18px 18px;
            background: linear-gradient(180deg, rgba(248, 251, 255, .82) 0%, #f8fbff 100%);
            border-top: 1px solid #e3ebf6;
        }
        .global-shortcut-processing {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(255, 187, 92, 0.4);
            background: #fff8ef;
            color: #9a5a00;
            font-size: .9rem;
            font-weight: 600;
        }
        .global-shortcut-processing__spinner {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid rgba(154, 90, 0, 0.18);
            border-top-color: #ff9800;
            animation: facturacion-spin .85s linear infinite;
            flex: 0 0 auto;
        }
        .global-shortcut-footer-action__row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }
        .global-shortcut-secondary-btn {
            min-height: 42px;
            border: 1px solid #d8e4f3;
            border-radius: 12px;
            padding: 0 14px;
            background: #fff;
            color: #20539a;
            font-size: .84rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
        }
        .global-shortcut-secondary-btn:hover,
        .global-shortcut-secondary-btn:focus {
            outline: none;
            color: #163f74;
            border-color: #b7cae6;
            box-shadow: 0 10px 20px rgba(15, 63, 120, .08);
            transform: translateY(-1px);
            text-decoration: none;
        }
        .global-shortcut-secondary-btn--link {
            cursor: pointer;
        }
        .global-shortcut-emit-btn {
            width: 100%;
            min-height: 50px;
            border: none;
            border-radius: 16px;
            background: linear-gradient(135deg, #fe9d22 0%, #f07c00 100%);
            color: #fff;
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: .01em;
            box-shadow: 0 18px 30px rgba(240, 124, 0, .22);
            transition: transform .16s ease, box-shadow .16s ease, opacity .16s ease;
        }
        .global-shortcut-emit-btn:hover,
        .global-shortcut-emit-btn:focus {
            outline: none;
            transform: translateY(-1px);
            box-shadow: 0 22px 36px rgba(240, 124, 0, .28);
        }
        .global-shortcut-emit-btn:disabled {
            opacity: .55;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .global-shortcut-confirm-form.is-submitting,
        .global-shortcut-confirm-form.is-submitting button {
            pointer-events: none;
        }
        .global-shortcut-confirm-form.is-submitting .global-shortcut-emit-btn,
        .global-shortcut-confirm-form.is-submitting .global-shortcut-secondary-btn,
        .global-shortcut-confirm-form.is-submitting .global-shortcut-link-btn {
            opacity: .68;
            filter: saturate(.75);
        }
        .global-shortcut-chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 9px;
            background: #eef5ff;
            color: #20539a;
            font-size: .75rem;
            font-weight: 600;
        }
        .global-shortcut-link-btn {
            border: none;
            background: transparent;
            color: #20539a;
            font-size: .82rem;
            font-weight: 700;
            padding: 0;
            cursor: pointer;
        }
        .global-shortcut-link-btn:hover,
        .global-shortcut-link-btn:focus {
            color: #163f74;
            outline: none;
            text-decoration: none;
        }
        .global-shortcut-link-btn--danger {
            color: #c44b39;
        }
        .global-shortcut-link-btn--danger:hover,
        .global-shortcut-link-btn--danger:focus {
            color: #a63c2d;
        }
        .global-shortcut-confirm {
            position: fixed;
            inset: 0;
            z-index: 1100;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity .18s ease;
        }
        .global-shortcut-confirm.is-open {
            opacity: 1;
            pointer-events: auto;
        }
        .global-shortcut-confirm__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(8, 24, 50, .45);
            backdrop-filter: blur(4px);
        }
        .global-shortcut-confirm__panel {
            position: relative;
            width: min(420px, calc(100vw - 26px));
            padding: 0;
            border-radius: 24px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid rgba(32, 83, 154, .12);
            box-shadow: 0 28px 60px rgba(8, 24, 50, .28);
            overflow: hidden;
            transform: translateY(10px) scale(.97);
            transition: transform .2s ease;
        }
        .global-shortcut-confirm__panel--wide {
            width: min(620px, calc(100vw - 26px));
        }
        .global-shortcut-confirm.is-open .global-shortcut-confirm__panel {
            transform: translateY(0) scale(1);
        }
        .global-shortcut-confirm__header {
            padding: 20px 22px 14px;
            background:
                radial-gradient(circle at top left, rgba(254, 204, 54, .18), transparent 42%),
                linear-gradient(135deg, #f7fbff 0%, #eef5ff 100%);
            border-bottom: 1px solid #e2ebf7;
            text-align: center;
        }
        .global-shortcut-confirm__icon {
            width: 58px;
            height: 58px;
            margin: 0 auto 12px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #fff2d7 0%, #ffe4ad 100%);
            color: #b86a00;
            font-size: 1.25rem;
            box-shadow: inset 0 0 0 1px rgba(184, 106, 0, .08);
        }
        .global-shortcut-confirm__eyebrow {
            color: #20539a;
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .global-shortcut-confirm__title {
            margin: 0;
            padding: 18px 22px 0;
            color: #173962;
            font-size: 1.24rem;
            font-weight: 800;
            text-align: center;
        }
        .global-shortcut-confirm__message {
            margin: 10px 0 0;
            padding: 0 22px;
            color: #5f718b;
            font-size: .95rem;
            line-height: 1.55;
            text-align: center;
        }
        .global-shortcut-confirm__note {
            margin: 16px 22px 0;
            padding: 14px 15px;
            border-radius: 16px;
            background: #f8fbff;
            border: 1px solid #dce8f8;
        }
        .global-shortcut-confirm__note-label {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 10px;
            background: #e8f1ff;
            color: #20539a;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .global-shortcut-confirm__note p {
            margin: 10px 0 0;
            color: #4f627e;
            font-size: .89rem;
            line-height: 1.5;
            text-align: left;
        }
        .global-shortcut-confirm__actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 18px;
            padding: 0 22px 20px;
        }
        .global-shortcut-confirm__btn {
            min-height: 46px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid transparent;
            transition: transform .16s ease, box-shadow .16s ease, background .16s ease;
        }
        .global-shortcut-confirm__btn:hover,
        .global-shortcut-confirm__btn:focus {
            transform: translateY(-1px);
            outline: none;
        }
        .global-shortcut-confirm__btn--ghost {
            background: #f4f8ff;
            border-color: #dce8f8;
            color: #20539a;
        }
        .global-shortcut-confirm__btn--ghost:hover,
        .global-shortcut-confirm__btn--ghost:focus {
            box-shadow: 0 10px 20px rgba(32, 83, 154, .12);
        }
        .global-shortcut-confirm__btn--danger {
            background: linear-gradient(135deg, #d94b3d 0%, #ba3427 100%);
            color: #fff;
            box-shadow: 0 14px 28px rgba(186, 52, 39, .24);
        }
        .global-shortcut-confirm__btn--danger:hover,
        .global-shortcut-confirm__btn--danger:focus {
            box-shadow: 0 18px 32px rgba(186, 52, 39, .3);
        }
        .global-shortcut-confirm__btn--primary {
            background: linear-gradient(135deg, #20539a 0%, #173d73 100%);
            color: #fff;
            box-shadow: 0 14px 28px rgba(23, 61, 115, .24);
        }
        .global-shortcut-confirm__btn--primary:hover,
        .global-shortcut-confirm__btn--primary:focus {
            box-shadow: 0 18px 32px rgba(23, 61, 115, .3);
        }
        .global-shortcut-item-edit-form {
            padding: 0 22px 22px;
        }
        .global-shortcut-item-edit-alert {
            margin-top: 18px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid #ffd8a8;
            background: #fff8ef;
            color: #9a5a00;
            font-size: .88rem;
            line-height: 1.45;
        }
        .global-shortcut-item-edit-alert.is-hidden {
            display: none;
        }
        .global-shortcut-item-edit-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 18px;
        }
        .global-shortcut-item-edit-grid .global-shortcut-field.is-hidden {
            display: none;
        }
        body.global-shortcut-open {
            overflow: hidden;
        }
        @media (max-width: 768px) {
            .global-facturacion-fab {
                right: 14px;
                bottom: 14px;
                min-height: 52px;
                padding: 0 14px 0 12px;
                gap: 10px;
            }
            .global-facturacion-fab__text {
                font-size: .92rem;
            }
            .global-shortcut-modal {
                align-items: flex-end;
                justify-content: center;
            }
            .global-shortcut-modal__panel {
                width: calc(100vw - 18px);
                margin: 0 9px 9px;
                border-radius: 18px;
                max-height: calc(100vh - 18px);
            }
            .global-shortcut-modal__body {
                padding: 16px 16px 10px;
            }
            .global-shortcut-modal__head {
                gap: 10px;
            }
            .global-shortcut-modal__title {
                font-size: 1.18rem;
            }
            .global-shortcut-selector-block {
                grid-template-columns: 1fr;
            }
            .global-shortcut-issue-card {
                flex-direction: column;
                align-items: stretch;
            }
            .global-shortcut-billing-inline__grid {
                grid-template-columns: 1fr;
            }
            .global-shortcut-field--full {
                grid-column: auto;
            }
            .global-shortcut-confirm__panel {
                width: calc(100vw - 18px);
                border-radius: 20px;
            }
            .global-shortcut-confirm__panel--wide {
                width: calc(100vw - 18px);
            }
            .global-shortcut-confirm__header {
                padding: 18px 16px 12px;
            }
            .global-shortcut-confirm__title {
                padding: 16px 16px 0;
                font-size: 1.14rem;
            }
            .global-shortcut-confirm__message {
                padding: 0 16px;
                font-size: .92rem;
            }
            .global-shortcut-confirm__note {
                margin: 14px 16px 0;
                padding: 12px 13px;
            }
            .global-shortcut-confirm__actions {
                grid-template-columns: 1fr;
                padding: 0 16px 16px;
            }
            .global-shortcut-item-edit-form {
                padding: 0 16px 16px;
            }
            .global-shortcut-item-edit-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .global-shortcut-footer-action {
                padding: 10px 16px 16px;
            }
            .global-shortcut-footer-action__row {
                flex-direction: column;
            }
            .global-shortcut-secondary-btn {
                width: 100%;
            }
            .global-shortcut-emision-card__summary {
                align-items: flex-start;
            }
            .global-shortcut-emision-card__summary-side {
                width: 100%;
                justify-content: space-between;
            }
        }
        @media (max-width: 560px) {
            .global-shortcut-selector-block {
                grid-template-columns: 1fr;
            }
            .global-shortcut-choice-btn {
                min-height: 44px;
                font-size: .8rem;
                padding: 7px 8px;
            }
            .global-shortcut-cart-summary {
                grid-template-columns: 1fr;
            }
        }
        @keyframes facturacion-spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const facturacionShortcutModal = document.getElementById('facturacionShortcutModal');
            const openFacturacionShortcutBtn = document.getElementById('openFacturacionShortcut');
            const closeFacturacionShortcutBtn = document.getElementById('closeFacturacionShortcut');
            const facturacionActionConfirmModal = document.getElementById('facturacionActionConfirmModal');
            const facturacionActionConfirmTitle = document.getElementById('facturacionActionConfirmTitle');
            const facturacionActionConfirmMessage = document.getElementById('facturacionActionConfirmMessage');
            const facturacionActionConfirmNote = document.getElementById('facturacionActionConfirmNote');
            const facturacionActionConfirmAccept = document.getElementById('facturacionActionConfirmAccept');
            const facturacionActionConfirmCancel = document.getElementById('facturacionActionConfirmCancel');
            const facturacionItemEditModal = document.getElementById('facturacionItemEditModal');
            const facturacionItemEditForm = document.getElementById('facturacionItemEditForm');
            const facturacionItemEditCancel = document.getElementById('facturacionItemEditCancel');
            const facturacionItemEditButtons = document.querySelectorAll('[data-edit-facturacion-item="true"]');
            const facturacionItemEditSubmit = document.getElementById('facturacionItemEditSubmit');
            const facturacionItemEditAlert = document.getElementById('facturacionItemEditAlert');
            const facturacionItemEditFields = document.querySelectorAll('[data-edit-field-key]');
            const facturacionBillingInlineForm = document.getElementById('facturacionBillingInlineForm');
            const facturacionBillingModeInput = document.getElementById('facturacionBillingModeInput');
            const facturacionInvoiceChannelInput = document.getElementById('facturacionInvoiceChannelInput');
            const facturacionBillingFields = document.getElementById('facturacionBillingFields');
            const facturacionAnonymousNote = document.getElementById('facturacionAnonymousNote');
            const facturacionAutosaveState = document.getElementById('facturacionAutosaveState');
            const facturacionProcessingState = document.getElementById('facturacionProcessingState');
            const facturacionProcessingText = document.getElementById('facturacionProcessingText');
            const facturacionFeedbackAlert = document.getElementById('facturacionFeedbackAlert');
            const confirmForms = document.querySelectorAll('.global-shortcut-confirm-form');
            const facturacionFlashFeedback = @json($facturacionFeedback);
            const facturacionDownloadPdf = @json($facturacionDownloadPdf);
            const facturacionItemUpdateRouteTemplate = @json(route('facturacion.cart.items.update', ['itemId' => '__ITEM__']));
            const facturacionFieldNames = {
                actividadEconomica: 'Actividad economica',
                codigoSin: 'Codigo SIN',
                codigo: 'Codigo de producto',
                descripcion: 'Descripcion del servicio',
                unidadMedida: 'Unidad de medida',
            };
            const facturacionFieldFocusMap = {
                actividadEconomica: 'facturacionEditItemActividadEconomica',
                codigoSin: 'facturacionEditItemCodigoSin',
                codigo: 'facturacionEditItemCodigoProducto',
                descripcion: 'facturacionEditItemDescripcionServicio',
                unidadMedida: 'facturacionEditItemUnidadMedida',
            };

            if (!facturacionShortcutModal || !openFacturacionShortcutBtn || !closeFacturacionShortcutBtn) {
                return;
            }

            let facturacionShortcutLastFocus = null;
            let pendingConfirmForm = null;
            let isFacturacionSubmitting = false;

            const setFacturacionSubmittingState = (form, active) => {
                isFacturacionSubmitting = active;

                confirmForms.forEach((candidate) => {
                    const submitButton = candidate.querySelector('button[type="submit"]');
                    candidate.classList.toggle('is-submitting', active && candidate === form);

                    if (submitButton instanceof HTMLButtonElement) {
                        submitButton.dataset.originalText = submitButton.dataset.originalText || submitButton.textContent.trim();
                        submitButton.disabled = active;

                        if (active && candidate === form) {
                            submitButton.textContent = 'Procesando...';
                        } else if (!active && submitButton.dataset.originalText) {
                            submitButton.textContent = submitButton.dataset.originalText;
                        }
                    }
                });

                if (facturacionActionConfirmAccept instanceof HTMLButtonElement) {
                    facturacionActionConfirmAccept.dataset.originalText = facturacionActionConfirmAccept.dataset.originalText || facturacionActionConfirmAccept.textContent.trim();
                    facturacionActionConfirmAccept.disabled = active;
                    facturacionActionConfirmAccept.textContent = active
                        ? 'Procesando...'
                        : (facturacionActionConfirmAccept.dataset.originalText || 'Confirmar');
                }

                if (facturacionActionConfirmCancel instanceof HTMLButtonElement) {
                    facturacionActionConfirmCancel.disabled = active;
                }

                if (facturacionProcessingState) {
                    facturacionProcessingState.hidden = !active;
                }

                if (facturacionProcessingText) {
                    facturacionProcessingText.textContent = 'Procesando emision, espera un momento...';
                }
            };

            const openFacturacionShortcutModal = () => {
                facturacionShortcutLastFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
                facturacionShortcutModal.classList.add('is-open');
                facturacionShortcutModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('global-shortcut-open');
                openFacturacionShortcutBtn.setAttribute('aria-expanded', 'true');

                window.setTimeout(() => {
                    closeFacturacionShortcutBtn.focus();
                }, 30);
            };

            const closeFacturacionShortcutModal = () => {
                facturacionShortcutModal.classList.remove('is-open');
                facturacionShortcutModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('global-shortcut-open');
                openFacturacionShortcutBtn.setAttribute('aria-expanded', 'false');

                if (facturacionShortcutLastFocus) {
                    facturacionShortcutLastFocus.focus();
                }
            };

            const openFacturacionItemEditModal = (trigger) => {
                if (!facturacionItemEditModal || !facturacionItemEditForm || !(trigger instanceof HTMLElement)) {
                    return;
                }

                const itemId = trigger.dataset.itemId || '';
                if (!itemId) {
                    return;
                }

                facturacionItemEditForm.action = facturacionItemUpdateRouteTemplate.replace('__ITEM__', itemId);

                const fieldMap = {
                    facturacionEditItemCodigo: trigger.dataset.itemCodigo || '',
                    facturacionEditItemTitulo: trigger.dataset.itemTitulo || '',
                    facturacionEditItemServicio: trigger.dataset.itemServicio || '',
                    facturacionEditItemDestinatario: trigger.dataset.itemDestinatario || '',
                    facturacionEditItemContenido: trigger.dataset.itemContenido || '',
                    facturacionEditItemDireccion: trigger.dataset.itemDireccion || '',
                    facturacionEditItemCiudad: trigger.dataset.itemCiudad || '',
                    facturacionEditItemPeso: trigger.dataset.itemPeso || '',
                    facturacionEditItemActividadEconomica: trigger.dataset.itemActividadEconomica || '',
                    facturacionEditItemCodigoSin: trigger.dataset.itemCodigoSin || '',
                    facturacionEditItemCodigoProducto: trigger.dataset.itemCodigoProducto || '',
                    facturacionEditItemDescripcionServicio: trigger.dataset.itemDescripcionServicio || '',
                    facturacionEditItemUnidadMedida: trigger.dataset.itemUnidadMedida || '',
                };

                const focusField = trigger.dataset.focusField || '';
                const focusFieldId = facturacionFieldFocusMap[focusField] || 'facturacionEditItemCodigo';
                const focusFieldName = facturacionFieldNames[focusField] || '';

                if (facturacionItemEditAlert instanceof HTMLElement) {
                    if (focusFieldName !== '') {
                        facturacionItemEditAlert.textContent = 'Campo observado: ' + focusFieldName + '. Corrigelo y guarda el item antes de reenviar.';
                        facturacionItemEditAlert.classList.remove('is-hidden');
                    } else {
                        facturacionItemEditAlert.textContent = '';
                        facturacionItemEditAlert.classList.add('is-hidden');
                    }
                }

                Object.entries(fieldMap).forEach(([fieldId, value]) => {
                    const input = document.getElementById(fieldId);
                    if (input instanceof HTMLInputElement) {
                        input.value = value;
                    }
                });

                facturacionItemEditFields.forEach((field) => {
                    if (!(field instanceof HTMLElement)) {
                        return;
                    }

                    if (focusField !== '') {
                        field.classList.toggle('is-hidden', field.dataset.editFieldKey !== focusField);
                    } else {
                        field.classList.remove('is-hidden');
                    }
                });

                facturacionItemEditModal.classList.add('is-open');
                facturacionItemEditModal.setAttribute('aria-hidden', 'false');

                window.setTimeout(() => {
                    const firstField = document.getElementById(focusFieldId);
                    if (firstField instanceof HTMLInputElement) {
                        firstField.focus();
                        firstField.select();
                    }
                }, 30);
            };

            const closeFacturacionItemEditModal = () => {
                if (!facturacionItemEditModal) {
                    return;
                }

                facturacionItemEditModal.classList.remove('is-open');
                facturacionItemEditModal.setAttribute('aria-hidden', 'true');

                if (facturacionItemEditAlert instanceof HTMLElement) {
                    facturacionItemEditAlert.textContent = '';
                    facturacionItemEditAlert.classList.add('is-hidden');
                }

                facturacionItemEditFields.forEach((field) => {
                    if (field instanceof HTMLElement) {
                        field.classList.remove('is-hidden');
                    }
                });
            };

            const openFacturacionActionConfirm = (form) => {
                if (!facturacionActionConfirmModal || !facturacionActionConfirmTitle || !facturacionActionConfirmMessage || !facturacionActionConfirmAccept) {
                    form.submit();
                    return;
                }

                pendingConfirmForm = form;
                facturacionActionConfirmTitle.textContent = form.dataset.confirmTitle || 'Confirmar accion';
                facturacionActionConfirmMessage.textContent = form.dataset.confirmMessage || 'Esta accion actualizara tu borrador de facturacion.';
                if (facturacionActionConfirmNote) {
                    facturacionActionConfirmNote.textContent = form.dataset.confirmNote || 'Solo se modificara el borrador actual del carrito.';
                }
                facturacionActionConfirmAccept.textContent = form.dataset.confirmCta || 'Confirmar';
                facturacionActionConfirmModal.classList.add('is-open');
                facturacionActionConfirmModal.setAttribute('aria-hidden', 'false');

                window.setTimeout(() => {
                    facturacionActionConfirmAccept.focus();
                }, 30);
            };

            const closeFacturacionActionConfirm = () => {
                pendingConfirmForm = null;

                if (!facturacionActionConfirmModal) {
                    return;
                }

                facturacionActionConfirmModal.classList.remove('is-open');
                facturacionActionConfirmModal.setAttribute('aria-hidden', 'true');
            };

            openFacturacionShortcutBtn.addEventListener('click', openFacturacionShortcutModal);
            closeFacturacionShortcutBtn.addEventListener('click', closeFacturacionShortcutModal);

            facturacionShortcutModal.addEventListener('click', function (event) {
                const target = event.target;
                if (target instanceof HTMLElement && target.dataset.closeFacturacionModal === 'true') {
                    closeFacturacionShortcutModal();
                }
            });

            confirmForms.forEach((form) => {
                form.addEventListener('submit', function (event) {
                    if (isFacturacionSubmitting) {
                        event.preventDefault();
                        return;
                    }

                    event.preventDefault();
                    openFacturacionActionConfirm(form);
                });
            });

            facturacionItemEditButtons.forEach((button) => {
                button.addEventListener('click', function () {
                    openFacturacionItemEditModal(button);
                });
            });

            if (facturacionItemEditCancel) {
                facturacionItemEditCancel.addEventListener('click', closeFacturacionItemEditModal);
            }

            if (facturacionItemEditForm) {
                facturacionItemEditForm.addEventListener('submit', function () {
                    if (facturacionItemEditSubmit instanceof HTMLButtonElement) {
                        facturacionItemEditSubmit.disabled = true;
                        facturacionItemEditSubmit.textContent = 'Guardando...';
                    }
                });
            }

            if (facturacionItemEditModal) {
                facturacionItemEditModal.addEventListener('click', function (event) {
                    const target = event.target;
                    if (target instanceof HTMLElement && target.dataset.closeFacturacionItemEdit === 'true') {
                        closeFacturacionItemEditModal();
                    }
                });
            }

            if (facturacionFlashFeedback && typeof facturacionFlashFeedback === 'object') {
                openFacturacionShortcutModal();

                if (facturacionFeedbackAlert instanceof HTMLElement) {
                    window.setTimeout(() => {
                        facturacionFeedbackAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }, 120);
                }
            }

            if (facturacionDownloadPdf && typeof facturacionDownloadPdf === 'object' && facturacionDownloadPdf.url) {
                const downloadKey = 'facturacion-pdf:' + (facturacionDownloadPdf.key || facturacionDownloadPdf.url);

                try {
                    if (!window.sessionStorage.getItem(downloadKey)) {
                        window.sessionStorage.setItem(downloadKey, '1');
                        window.setTimeout(() => {
                            const tempLink = document.createElement('a');
                            tempLink.href = facturacionDownloadPdf.url;
                            tempLink.target = '_blank';
                            tempLink.rel = 'noopener noreferrer';
                            tempLink.click();
                        }, 180);
                    }
                } catch (error) {
                    window.setTimeout(() => {
                        window.open(facturacionDownloadPdf.url, '_blank', 'noopener');
                    }, 180);
                }
            }

            if (facturacionBillingInlineForm) {
                let billingAutosaveTimeout = null;
                const billingFields = facturacionBillingInlineForm.querySelectorAll('input, select');
                const billingModeChoices = facturacionBillingInlineForm.querySelectorAll('[data-billing-mode-choice]');
                const invoiceChannelChoices = facturacionBillingInlineForm.querySelectorAll('[data-invoice-channel-choice]');
                const billingDocumentTypeField = document.getElementById('facturacionBillingDocumentType');
                const billingDocumentNumberField = document.getElementById('facturacionBillingDocumentNumber');
                const billingDocumentComplementField = document.getElementById('facturacionBillingDocumentComplement');
                const billingNameField = document.getElementById('facturacionBillingName');

                const setAutosaveState = (state, message) => {
                    if (!facturacionAutosaveState) {
                        return;
                    }

                    facturacionAutosaveState.classList.remove('is-saving', 'is-saved', 'is-error');
                    if (state) {
                        facturacionAutosaveState.classList.add(state);
                    }
                    facturacionAutosaveState.textContent = message;
                };

                const submitBillingInlineForm = () => {
                    const formData = new FormData(facturacionBillingInlineForm);
                    setAutosaveState('is-saving', 'Guardando cambios...');

                    fetch(facturacionBillingInlineForm.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    })
                        .then((response) => {
                            if (!response.ok) {
                                throw new Error('No se pudo guardar');
                            }

                            setAutosaveState('is-saved', 'Cambios guardados. Ya puedes reenviar.');
                        })
                        .catch(() => {
                            setAutosaveState('is-error', 'No se pudieron guardar los cambios. Revisa los datos e intenta otra vez.');
                        });
                };

                const syncBillingModeUi = (mode) => {
                    const isAnonymous = mode === 'sin_cliente';

                    billingModeChoices.forEach((button) => {
                        button.classList.toggle('is-active', button.dataset.billingModeChoice === mode);
                    });

                    if (facturacionBillingFields) {
                        facturacionBillingFields.classList.toggle('is-hidden', isAnonymous);
                    }

                    if (facturacionAnonymousNote) {
                        facturacionAnonymousNote.classList.toggle('is-hidden', !isAnonymous);
                    }

                    if (isAnonymous) {
                        if (billingDocumentTypeField) {
                            billingDocumentTypeField.value = '';
                        }
                        if (billingDocumentNumberField) {
                            billingDocumentNumberField.value = '';
                        }
                        if (billingDocumentComplementField) {
                            billingDocumentComplementField.value = '';
                        }
                        if (billingNameField) {
                            billingNameField.value = '';
                        }
                    }

                    syncComplementVisibility();
                };

                const syncInvoiceChannelUi = (channel) => {
                    invoiceChannelChoices.forEach((button) => {
                        button.classList.toggle('is-active', button.dataset.invoiceChannelChoice === channel);
                    });
                };

                const syncComplementVisibility = () => {
                    const currentMode = facturacionBillingModeInput ? facturacionBillingModeInput.value : 'con_datos';
                    const currentDocumentType = billingDocumentTypeField ? billingDocumentTypeField.value : '';
                    const shouldEnableComplement = currentMode !== 'sin_cliente' && ['1', '2'].includes(currentDocumentType);

                    if (billingDocumentComplementField) {
                        billingDocumentComplementField.disabled = !shouldEnableComplement;
                        billingDocumentComplementField.setAttribute('aria-disabled', shouldEnableComplement ? 'false' : 'true');
                    }

                    if (!shouldEnableComplement && billingDocumentComplementField) {
                        billingDocumentComplementField.value = '';
                    }
                };

                billingFields.forEach((field) => {
                    field.addEventListener('input', function () {
                        window.clearTimeout(billingAutosaveTimeout);
                        billingAutosaveTimeout = window.setTimeout(submitBillingInlineForm, 450);
                    });

                    field.addEventListener('change', function () {
                        window.clearTimeout(billingAutosaveTimeout);
                        submitBillingInlineForm();
                    });
                });

                if (billingDocumentTypeField) {
                    billingDocumentTypeField.addEventListener('change', function () {
                        syncComplementVisibility();
                    });
                }

                billingModeChoices.forEach((button) => {
                    button.addEventListener('click', function () {
                        if (!facturacionBillingModeInput) {
                            return;
                        }

                        facturacionBillingModeInput.value = button.dataset.billingModeChoice || 'con_datos';
                        syncBillingModeUi(facturacionBillingModeInput.value);
                        window.clearTimeout(billingAutosaveTimeout);
                        submitBillingInlineForm();
                    });
                });

                invoiceChannelChoices.forEach((button) => {
                    button.addEventListener('click', function () {
                        if (!facturacionInvoiceChannelInput) {
                            return;
                        }

                        facturacionInvoiceChannelInput.value = button.dataset.invoiceChannelChoice || 'qr';
                        syncInvoiceChannelUi(facturacionInvoiceChannelInput.value);
                        window.clearTimeout(billingAutosaveTimeout);
                        submitBillingInlineForm();
                    });
                });

                syncBillingModeUi(facturacionBillingModeInput ? facturacionBillingModeInput.value : 'con_datos');
                syncInvoiceChannelUi(facturacionInvoiceChannelInput ? facturacionInvoiceChannelInput.value : 'qr');
                syncComplementVisibility();
            }

            if (facturacionActionConfirmModal && facturacionActionConfirmCancel && facturacionActionConfirmAccept) {
                facturacionActionConfirmCancel.addEventListener('click', closeFacturacionActionConfirm);

                facturacionActionConfirmAccept.addEventListener('click', function () {
                    if (!pendingConfirmForm) {
                        closeFacturacionActionConfirm();
                        return;
                    }

                    const form = pendingConfirmForm;
                    pendingConfirmForm = null;
                    facturacionActionConfirmModal.classList.remove('is-open');
                    facturacionActionConfirmModal.setAttribute('aria-hidden', 'true');
                    setFacturacionSubmittingState(form, true);
                    form.submit();
                });

                facturacionActionConfirmModal.addEventListener('click', function (event) {
                    const target = event.target;
                    if (target instanceof HTMLElement && target.dataset.closeFacturacionConfirm === 'true') {
                        closeFacturacionActionConfirm();
                    }
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && facturacionActionConfirmModal && facturacionActionConfirmModal.classList.contains('is-open')) {
                    if (isFacturacionSubmitting) {
                        return;
                    }
                    closeFacturacionActionConfirm();
                    return;
                }

                if (event.key === 'Escape' && facturacionItemEditModal && facturacionItemEditModal.classList.contains('is-open')) {
                    closeFacturacionItemEditModal();
                    return;
                }

                if (event.key === 'Escape' && facturacionShortcutModal.classList.contains('is-open')) {
                    if (isFacturacionSubmitting) {
                        return;
                    }
                    closeFacturacionShortcutModal();
                }
            });

            window.addEventListener('pageshow', function () {
                setFacturacionSubmittingState(null, false);
            });
        });
    </script>
@endif
