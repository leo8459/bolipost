@once('facturacion-shortcut-global')
@php
    $canOpenFacturacionShortcut = auth()->check() && (auth()->user()?->can('feature.dashboard.facturacion') ?? false);
    try {
        $facturacionContext = $canOpenFacturacionShortcut
            ? app(\App\Services\FacturacionCartService::class)->getRemoteContextForUser(auth()->user())
            : ['draft' => null, 'last' => null];
    } catch (\Throwable $e) {
        $facturacionContext = ['draft' => null, 'last' => null];
    }
    try {
        $facturacionCajaContext = $canOpenFacturacionShortcut
            ? app(\App\Services\FacturacionCartService::class)->fetchCajaEstado(auth()->user())
            : ['estado' => 'SIN_APERTURA', 'mensaje' => '', 'caja' => []];
    } catch (\Throwable $e) {
        $facturacionCajaContext = ['estado' => 'SIN_APERTURA', 'mensaje' => 'No se pudo consultar el estado de caja.', 'caja' => []];
    }
    $activeFacturacionCart = $facturacionContext['draft'] ?? null;
    $ultimaFacturacionEmitida = $facturacionContext['last'] ?? null;
    $facturacionItems = collect($activeFacturacionCart?->items ?? []);
    $facturacionItemsCount = (int) ($activeFacturacionCart?->cantidad_items ?? $facturacionItems->count());
    $facturacionCartTotal = (float) ($activeFacturacionCart?->total ?? 0);
    $billingDocumentTypes = \App\Models\Cliente::tiposDocumentoIdentidad();
    $activeBillingMode = (string) ($activeFacturacionCart?->modalidad_facturacion ?? 'con_datos');
    $activeInvoiceChannel = (string) ($activeFacturacionCart?->canal_emision ?? 'factura_electronica');
    if (!in_array($activeInvoiceChannel, ['factura_electronica', 'qr'], true)) {
        $activeInvoiceChannel = 'factura_electronica';
    }
    $activeDocumentType = (string) ($activeFacturacionCart?->tipo_documento ?? '');
    $defaultBillingEmail = 'safe@correos.gob.bo';
    $storedBillingEmail = trim((string) ($activeFacturacionCart?->correo_facturacion ?? ''));
    $loggedUserEmail = strtolower(trim((string) (auth()->user()?->email ?? '')));
    $hasCustomBillingEmail = $storedBillingEmail !== ''
        && strtolower($storedBillingEmail) !== $defaultBillingEmail
        && strtolower($storedBillingEmail) !== $loggedUserEmail;
    $activeBillingEmail = $hasCustomBillingEmail ? $storedBillingEmail : $defaultBillingEmail;
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
                'message' => $message !== '' ? $message : 'El valor enviado no fue aceptado por Facturacion.',
            ];
        })
        ->filter()
        ->values();
    $facturaEmitida = (array) ($resultadoEmision['factura'] ?? []);
    $facturacionFeedback = session('facturacion_feedback');
    $facturacionDownloadPdf = session('facturacion_download_pdf');
    $facturacionQrData = session('facturacion_qr_data');
    $facturacionFeedbackAction = is_array($facturacionFeedback) ? (string) ($facturacionFeedback['action'] ?? '') : '';
    $isCajaShortcutFeedback = in_array($facturacionFeedbackAction, ['caja_abrir', 'caja_cerrar'], true);
    $shouldRenderShortcutFeedback = is_array($facturacionFeedback) && !$isCajaShortcutFeedback;
    $shouldOpenShortcutWithFeedback = $shouldRenderShortcutFeedback && $facturacionFeedbackAction !== 'consultar';
    $shouldOpenConsultFeedbackModal = is_array($facturacionFeedback) && $facturacionFeedbackAction === 'consultar';
    $isRejectedDraft = $activeFacturacionCart && ($activeFacturacionCart->estado_emision ?? null) === 'RECHAZADA';
    $lastEmissionReason = (string) ($resultadoEmision['razon'] ?? '');
    $lastEmissionAttempts = (int) ($resultadoEmision['_meta']['intentos'] ?? 0);
    $hasActiveFacturacionItems = $facturacionItems->isNotEmpty();
    $draftEstado = strtolower(trim((string) ($activeFacturacionCart?->estado ?? 'borrador')));
    $draftEstadoPago = strtolower(trim((string) ($activeFacturacionCart?->estado_pago ?? ($activeInvoiceChannel === 'qr' ? 'pendiente' : 'pagado'))));
    $draftEstadoEmision = strtoupper(trim((string) ($activeFacturacionCart?->estado_emision ?? '')));
    $hasLastEmissionData = $ultimaFacturacionEmitida
        && (
            trim((string) ($ultimaFacturacionEmitida->estado_emision ?? '')) !== ''
            || trim((string) ($ultimaFacturacionEmitida->mensaje_emision ?? '')) !== ''
            || trim((string) ($ultimaFacturacionEmitida->codigo_orden ?? '')) !== ''
            || trim((string) ($ultimaFacturacionEmitida->codigo_seguimiento ?? '')) !== ''
            || trim((string) ($facturaEmitida['nroFactura'] ?? '')) !== ''
        );
    $isQrFlowShortcut = $activeInvoiceChannel === 'qr';
    $emitActionLabel = $isQrFlowShortcut
        ? ($draftEstado === 'pendiente_pago' && !empty($activeFacturacionCart?->qr_transaction_id)
            ? 'Reabrir QR vigente'
            : ($draftEstadoPago === 'cancelado' ? 'Generar nuevo QR' : 'Generar QR de cobro'))
        : ($isRejectedDraft ? 'Reintentar emision' : 'Emitir factura electronica');
    $emitConfirmTitle = $isQrFlowShortcut
        ? 'Preparar cobro QR'
        : ($isRejectedDraft ? 'Reintentar emision' : 'Emitir factura');
    $emitConfirmMessage = $isQrFlowShortcut
        ? 'Se generara o reutilizara un QR de cobro para esta venta.'
        : 'La venta se enviara al flujo de facturacion electronica.';
    $emitConfirmNote = $isQrFlowShortcut
        ? 'El pago QR no sumara a caja. Solo quedara como cobro referencial hasta que el proveedor confirme el pago.'
        : 'La factura electronica sumara a caja si la emision concluye correctamente.';
    $emitConfirmCta = $isQrFlowShortcut ? 'Si, continuar con QR' : ($isRejectedDraft ? 'Si, reenviar' : 'Si, emitir');
    $workflowTitle = $isQrFlowShortcut ? 'Cobro QR guiado' : 'Facturacion electronica guiada';
    $workflowNextStep = $isQrFlowShortcut
        ? match (true) {
            !$hasActiveFacturacionItems => 'Agrega items al carrito para generar el QR.',
            $draftEstadoPago === 'pagado' => 'El pago ya fue confirmado. Puedes revisar la venta en el historial.',
            $draftEstado === 'pendiente_pago' && !empty($activeFacturacionCart?->qr_transaction_id) => 'Comparte o reabre el QR vigente y luego actualiza el pago.',
            default => 'Genera el QR y espera la confirmacion del proveedor.',
        }
        : match (true) {
            !$hasActiveFacturacionItems => 'Agrega items al carrito antes de emitir.',
            $draftEstadoEmision === 'RECHAZADA' => 'Corrige los datos observados y reintenta la emision.',
            $draftEstadoEmision === 'PENDIENTE' => 'Actualiza el estado de emision en unos segundos.',
            default => 'Verifica cliente, documento y correo antes de emitir.',
        };
    $workflowAccountingText = $isQrFlowShortcut
        ? 'Cobro fuera de caja. Se reporta por separado como QR referencial.'
        : 'Venta en caja. Se contabiliza economicamente cuando la emision concluye.';
    $workflowStatusLabel = $isQrFlowShortcut
        ? match ($draftEstadoPago) {
            'pagado' => 'Pago confirmado',
            'cancelado' => 'QR cancelado',
            default => !empty($activeFacturacionCart?->qr_transaction_id) ? 'QR vigente' : 'Listo para generar QR',
        }
        : match ($draftEstadoEmision) {
            'FACTURADA' => 'Factura emitida',
            'PENDIENTE' => 'Factura en proceso',
            'RECHAZADA' => 'Requiere correccion',
            default => 'Listo para emitir',
        };
    $workflowSteps = $isQrFlowShortcut
        ? [
            ['label' => 'Carrito listo', 'state' => $hasActiveFacturacionItems ? 'done' : 'active'],
            ['label' => 'QR generado', 'state' => !empty($activeFacturacionCart?->qr_transaction_id) ? 'done' : ($hasActiveFacturacionItems ? 'active' : 'pending')],
            ['label' => 'Pago confirmado', 'state' => $draftEstadoPago === 'pagado' ? 'done' : ($draftEstado === 'pendiente_pago' ? 'active' : 'pending')],
        ]
        : [
            ['label' => 'Carrito listo', 'state' => $hasActiveFacturacionItems ? 'done' : 'active'],
            ['label' => 'Enviado a facturacion', 'state' => in_array($draftEstadoEmision, ['PENDIENTE', 'FACTURADA'], true) ? 'done' : ($hasActiveFacturacionItems ? 'active' : 'pending')],
            ['label' => 'Factura emitida', 'state' => $draftEstadoEmision === 'FACTURADA' ? 'done' : ($draftEstadoEmision === 'PENDIENTE' ? 'active' : 'pending')],
        ];
    $estadoCaja = strtoupper(trim((string) ($facturacionCajaContext['estado'] ?? 'SIN_APERTURA')));
    $isCajaAbierta = in_array($estadoCaja, ['ABIERTA', 'ABIERTO'], true);
    $activeBillingMode = 'con_datos';
@endphp

@if ($canOpenFacturacionShortcut)
    <button
        type="button"
        class="global-facturacion-fab"
        id="openFacturacionShortcut"
        aria-controls="facturacionShortcutModal"
        aria-expanded="false"
        aria-label="Abrir accesos de Facturacion"
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
                    <h3 id="facturacionShortcutTitle" class="global-shortcut-modal__title">Facturacion</h3>
                </div>
                <button type="button" class="global-shortcut-modal__close" id="closeFacturacionShortcut" aria-label="Cerrar modal de Facturacion">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            @if ($shouldRenderShortcutFeedback)
                <div class="global-shortcut-feedback global-shortcut-feedback--{{ $facturacionFeedback['type'] ?? 'info' }}" id="facturacionFeedbackAlert">
                    <div class="global-shortcut-feedback__icon">
                        <i class="fas @if(($facturacionFeedback['type'] ?? '') === 'success') fa-check-circle @elseif(($facturacionFeedback['type'] ?? '') === 'warning') fa-exclamation-triangle @elseif(($facturacionFeedback['type'] ?? '') === 'error') fa-times-circle @else fa-info-circle @endif"></i>
                    </div>
                    <div class="global-shortcut-feedback__body">
                        <strong>{{ $facturacionFeedback['title'] ?? 'Resultado de Facturacion' }}</strong>
                        <p>{{ $facturacionFeedback['message'] ?? '' }}</p>
                        @if (!empty($facturacionFeedback['detail']))
                            <span>{{ $facturacionFeedback['detail'] }}</span>
                        @endif
                    </div>
                </div>
            @endif

            @if (!$isCajaAbierta)
                <section class="global-shortcut-closed-mode">
                    <div class="global-shortcut-closed-mode__icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h4>Caja cerrada</h4>
                    <p>La interfaz de Facturacion esta deshabilitada. Para continuar, abre caja diaria.</p>
                </section>
            @endif

            @if ($isCajaAbierta && $isRejectedDraft)
                <div class="global-shortcut-edit-hint">
                    <strong>Corrige antes de reenviar</strong>
                    <p>Puedes editar cliente, emision y los items del borrador antes de volver a intentar.</p>
                    <span>Cuando aparezca el mensaje de cambios guardados, ya puedes usar <b>Reintentar emision</b>.</span>
                </div>
            @endif

            @if ($isCajaAbierta && $draftEmissionIssues->isNotEmpty())
                <div class="global-shortcut-issue-box">
                    <strong>Campos observados por Facturacion</strong>
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

            <div class="global-shortcut-operacion @if(!$isCajaAbierta) is-hidden-locked @endif">
            <form
                method="POST"
                action="{{ route('facturacion.cart.billing.update') }}"
                class="global-shortcut-billing-inline"
                id="facturacionBillingInlineForm"
                data-autosave="true"
            >
                @csrf
                @method('PUT')
                <input type="hidden" name="modalidad_facturacion" id="facturacionBillingModeInput" value="con_datos">
                <input type="hidden" name="canal_emision" id="facturacionInvoiceChannelInput" value="{{ $activeInvoiceChannel }}">
                <input type="hidden" name="tipo_documento" id="facturacionBillingDocumentTypeHidden" value="{{ (string) ($activeFacturacionCart?->tipo_documento ?? '') }}">

                <div class="global-shortcut-selector-block">
                    <div class="global-shortcut-selector-group">
                        <span class="global-shortcut-selector-label">Facturacion</span>
                        <div class="global-shortcut-choice-row" role="tablist" aria-label="Flujo de Facturacion">
                            <button type="button" class="global-shortcut-choice-btn is-active" disabled aria-disabled="true">
                                Con documento
                            </button>
                        </div>
                    </div>

                    <div class="global-shortcut-selector-group">
                        <span class="global-shortcut-selector-label">Emision</span>
                        <div class="global-shortcut-choice-row" role="tablist" aria-label="Tipo de salida de factura">
                            <button type="button" class="global-shortcut-choice-btn @if($activeInvoiceChannel === 'factura_electronica') is-active @endif" data-invoice-channel-choice="factura_electronica" @disabled(!$isCajaAbierta)>
                                Factura electronica
                            </button>
                            <button type="button" class="global-shortcut-choice-btn @if($activeInvoiceChannel === 'qr') is-active @endif" data-invoice-channel-choice="qr" @disabled(!$isCajaAbierta)>
                                QR
                            </button>
                        </div>
                    </div>
                </div>

                <div class="global-shortcut-billing-inline__grid" id="facturacionBillingFields">
                    <div class="global-shortcut-field">
                        <label for="facturacionBillingDocumentNumber">Numero</label>
                        <input
                            type="text"
                            id="facturacionBillingDocumentNumber"
                            name="numero_documento"
                            value="{{ $activeFacturacionCart?->numero_documento }}"
                            placeholder="Ej. 1003579028"
                            @disabled(!$isCajaAbierta)
                        >
                    </div>
                    <div class="global-shortcut-field" id="facturacionBillingComplementField">
                        <label for="facturacionBillingDocumentComplement">Complemento</label>
                        <input
                            type="text"
                            id="facturacionBillingDocumentComplement"
                            name="complemento_documento"
                            value="{{ $activeFacturacionCart?->complemento_documento }}"
                            @disabled(!$isCajaAbierta)
                        >
                    </div>
                    <div class="global-shortcut-field global-shortcut-field--full">
                        <label for="facturacionBillingName">Razon social</label>
                        <input
                            type="text"
                            id="facturacionBillingName"
                            name="razon_social"
                            value="{{ $activeFacturacionCart?->razon_social }}"
                            placeholder="NOMBRE O RAZON SOCIAL"
                            style="text-transform: uppercase;"
                            @readonly(trim((string) ($activeFacturacionCart?->numero_documento ?? '')) === '99002')
                            @disabled(!$isCajaAbierta)
                        >
                    </div>
                    <div class="global-shortcut-field global-shortcut-field--full">
                        <div class="global-shortcut-field-head">
                            <label for="facturacionBillingEmail">Correo factura</label>
                            <label class="global-shortcut-email-toggle" for="facturacionBillingEmailToggle">
                                <input
                                    type="checkbox"
                                    id="facturacionBillingEmailToggle"
                                    data-billing-email-toggle="true"
                                    @checked($hasCustomBillingEmail)
                                    @disabled(!$isCajaAbierta)
                                >
                                <span>Editar correo cliente</span>
                            </label>
                        </div>
                        <input
                            type="email"
                            id="facturacionBillingEmail"
                            name="correo_facturacion"
                            value="{{ $activeBillingEmail }}"
                            placeholder="correo@dominio.com"
                            autocomplete="email"
                            data-default-email="{{ $defaultBillingEmail }}"
                            @readonly(!$hasCustomBillingEmail)
                            @disabled(!$isCajaAbierta)
                        >
                    </div>
                    <div class="global-shortcut-field global-shortcut-field--full">
                        <label for="facturacionBillingDocumentType">Documentacion</label>
                        <select id="facturacionBillingDocumentType" disabled aria-disabled="true">
                            <option value="">Selecciona</option>
                            @foreach ($billingDocumentTypes as $value => $label)
                                <option value="{{ $value }}" @selected(($activeFacturacionCart?->tipo_documento ?? null) == $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>

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
                                data-confirm-message="Se eliminaran todos los items del borrador de Facturacion. Puedes volver a agregarlos despues si hace falta."
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
                        <div class="global-shortcut-cart-empty__icon" aria-hidden="true">
                            <i class="fas fa-shopping-basket"></i>
                        </div>
                        <strong>Carrito vacio</strong>
                        <p>No agregaste items para Facturacion.</p>
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
                                        data-confirm-message="Este item se quitara del borrador de Facturacion. No afectara ninguna factura final."
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

            </div>

            <div class="global-shortcut-footer-action">
                @if (!$isCajaAbierta)
                    <div class="global-shortcut-footer-action__row global-shortcut-footer-action__row--closed">
                        <form
                            method="POST"
                            action="{{ route('facturacion.cart.caja.abrir') }}"
                            class="global-shortcut-confirm-form"
                            data-confirm-title="Abrir caja"
                            data-confirm-message="Se abrira una caja diaria."
                            data-confirm-cta="Si, abrir caja"
                            data-confirm-icon="fa-lock-open"
                            data-processing-pill="Caja diaria"
                            data-processing-title="Abriendo caja"
                            data-processing-text="Estamos preparando la caja diaria, espera un momento..."
                        >
                            @csrf
                            <button type="submit" class="global-shortcut-secondary-btn global-shortcut-secondary-btn--open-caja">
                                <i class="fas fa-lock-open" aria-hidden="true"></i>
                                <span>Abrir caja</span>
                            </button>
                        </form>
                    </div>
                @else
                    @if ($ultimaFacturacionEmitida && !empty($ultimaFacturacionEmitida->codigo_seguimiento))
                        @if ($facturacionItems->isNotEmpty())
                            <form
                                method="POST"
                                action="{{ route('facturacion.cart.emitir') }}"
                                class="global-shortcut-confirm-form"
                                data-confirm-title="{{ $emitConfirmTitle }}"
                                data-confirm-message="{{ $emitConfirmMessage }}"
                                data-confirm-note="{{ $emitConfirmNote }}"
                                data-confirm-cta="{{ $emitConfirmCta }}"
                                data-processing-pill="Facturacion en curso"
                                data-processing-title="Emitiendo factura"
                                data-processing-text="Procesando emision, espera un momento..."
                            >
                                @csrf
                                <input type="hidden" name="modalidad_facturacion" value="con_datos" data-emit-sync-field="modalidad_facturacion">
                                <input type="hidden" name="canal_emision" value="{{ $activeInvoiceChannel }}" data-emit-sync-field="canal_emision">
                                <input type="hidden" name="tipo_documento" value="{{ (string) ($activeFacturacionCart?->tipo_documento ?? '') }}" data-emit-sync-field="tipo_documento">
                                <input type="hidden" name="numero_documento" value="{{ (string) ($activeFacturacionCart?->numero_documento ?? '') }}" data-emit-sync-field="numero_documento">
                                <input type="hidden" name="complemento_documento" value="{{ (string) ($activeFacturacionCart?->complemento_documento ?? '') }}" data-emit-sync-field="complemento_documento">
                                <input type="hidden" name="razon_social" value="{{ (string) ($activeFacturacionCart?->razon_social ?? '') }}" data-emit-sync-field="razon_social">
                                <input type="hidden" name="correo_facturacion" value="{{ (string) $activeBillingEmail }}" data-emit-sync-field="correo_facturacion">
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
                        @if ($facturacionItems->isNotEmpty())
                            <form
                                method="POST"
                                action="{{ route('facturacion.cart.emitir') }}"
                                class="global-shortcut-confirm-form"
                                data-confirm-title="{{ $emitConfirmTitle }}"
                                data-confirm-message="{{ $emitConfirmMessage }}"
                                data-confirm-note="{{ $emitConfirmNote }}"
                                data-confirm-cta="{{ $emitConfirmCta }}"
                                data-processing-pill="Facturacion en curso"
                                data-processing-title="Emitiendo factura"
                                data-processing-text="Procesando emision, espera un momento..."
                            >
                                @csrf
                                <input type="hidden" name="modalidad_facturacion" value="con_datos" data-emit-sync-field="modalidad_facturacion">
                                <input type="hidden" name="canal_emision" value="{{ $activeInvoiceChannel }}" data-emit-sync-field="canal_emision">
                                <input type="hidden" name="tipo_documento" value="{{ (string) ($activeFacturacionCart?->tipo_documento ?? '') }}" data-emit-sync-field="tipo_documento">
                                <input type="hidden" name="numero_documento" value="{{ (string) ($activeFacturacionCart?->numero_documento ?? '') }}" data-emit-sync-field="numero_documento">
                                <input type="hidden" name="complemento_documento" value="{{ (string) ($activeFacturacionCart?->complemento_documento ?? '') }}" data-emit-sync-field="complemento_documento">
                                <input type="hidden" name="razon_social" value="{{ (string) ($activeFacturacionCart?->razon_social ?? '') }}" data-emit-sync-field="razon_social">
                                <input type="hidden" name="correo_facturacion" value="{{ (string) $activeBillingEmail }}" data-emit-sync-field="correo_facturacion">
                                <button
                                    type="submit"
                                    class="global-shortcut-emit-btn"
                                >
                                    {{ $emitActionLabel }}
                                </button>
                            </form>
                        @else
                            <button
                                type="button"
                                class="global-shortcut-emit-btn"
                                disabled
                                aria-disabled="true"
                                title="Agrega al menos un item al carrito para emitir."
                            >
                                {{ $emitActionLabel }}
                            </button>
                        @endif
                    @endif
                @endif
            </div>

        </div>
    </div>

    <div class="global-shortcut-processing-overlay" id="facturacionProcessingState" aria-live="polite" data-preview-mode="false" hidden>
        <div class="global-shortcut-processing-overlay__card" role="status" aria-label="Procesando emision">
            <div class="global-shortcut-processing-overlay__art" aria-hidden="true">
                <div class="global-shortcut-processing-overlay__glow"></div>
                <svg viewBox="0 0 360 180" class="global-shortcut-processing-overlay__svg" focusable="false">
                    <defs>
                        <linearGradient id="facturacionStageBg" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#f7fbff"/>
                            <stop offset="100%" stop-color="#eef4fb"/>
                        </linearGradient>
                        <linearGradient id="facturacionStageLine" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#20539a"/>
                            <stop offset="100%" stop-color="#4f7ec0"/>
                        </linearGradient>
                        <linearGradient id="facturacionStageAccent" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#ffd36b"/>
                            <stop offset="100%" stop-color="#ffb53f"/>
                        </linearGradient>
                    </defs>
                    <rect x="28" y="24" width="304" height="132" rx="28" fill="url(#facturacionStageBg)"/>
                    <rect x="52" y="112" width="256" height="10" rx="5" fill="#dbe6f3"/>
                    <g class="global-shortcut-processing-overlay__doc global-shortcut-processing-overlay__doc--source">
                        <rect x="80" y="54" width="78" height="94" rx="18" fill="#ffffff" stroke="#d7e3f1" stroke-width="3"/>
                        <rect x="98" y="74" width="42" height="8" rx="4" fill="#20539a" opacity=".16"/>
                        <rect x="98" y="90" width="34" height="8" rx="4" fill="#20539a" opacity=".11"/>
                        <rect x="98" y="106" width="46" height="8" rx="4" fill="#20539a" opacity=".11"/>
                        <rect x="98" y="124" width="28" height="10" rx="5" fill="url(#facturacionStageAccent)"/>
                    </g>
                    <g class="global-shortcut-processing-overlay__flow">
                        <path d="M158 102h44" fill="none" stroke="url(#facturacionStageLine)" stroke-width="6" stroke-linecap="round" opacity=".22"/>
                        <path d="M163 102h34" fill="none" stroke="url(#facturacionStageLine)" stroke-width="6" stroke-linecap="round" stroke-dasharray="12 12"/>
                        <circle cx="180" cy="102" r="8" fill="#20539a"/>
                    </g>
                    <g class="global-shortcut-processing-overlay__doc global-shortcut-processing-overlay__doc--target">
                        <rect x="204" y="44" width="86" height="104" rx="20" fill="#ffffff" stroke="#d7e3f1" stroke-width="3"/>
                        <rect x="224" y="66" width="48" height="8" rx="4" fill="#20539a" opacity=".16"/>
                        <rect x="224" y="82" width="39" height="8" rx="4" fill="#20539a" opacity=".11"/>
                        <rect x="224" y="98" width="52" height="8" rx="4" fill="#20539a" opacity=".11"/>
                        <rect x="224" y="118" width="32" height="10" rx="5" fill="url(#facturacionStageAccent)"/>
                    </g>
                    <g class="global-shortcut-processing-overlay__seal">
                        <circle cx="282" cy="58" r="20" fill="#eef5ff"/>
                        <circle cx="282" cy="58" r="13" fill="#ffffff"/>
                        <path d="M276 58l4 4 8-10" fill="none" stroke="#20539a" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                    </g>
                    <g class="global-shortcut-processing-overlay__ring">
                        <circle cx="180" cy="44" r="20" fill="none" stroke="#d7e3f1" stroke-width="6"/>
                        <circle cx="180" cy="44" r="20" fill="none" stroke="url(#facturacionStageAccent)" stroke-width="6" stroke-linecap="round" stroke-dasharray="54 72"/>
                    </g>
                </svg>
            </div>
            <div class="global-shortcut-processing-overlay__pill" id="facturacionProcessingPill">
                Facturacion en curso
            </div>
            <strong id="facturacionProcessingTitle">Emitiendo factura</strong>
            <span id="facturacionProcessingText">Procesando emision, espera un momento...</span>
            <small id="facturacionProcessingHint" hidden>Haz clic para cerrar esta vista previa.</small>
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
                        <i class="fas fa-circle-check" id="facturacionActionConfirmIcon"></i>
                    </div>
                    <div class="global-shortcut-confirm__eyebrow">Confirmacion de carrito</div>
                </div>
            <h4 id="facturacionActionConfirmTitle" class="global-shortcut-confirm__title">Confirmar accion</h4>
            <p class="global-shortcut-confirm__message" id="facturacionActionConfirmMessage">
                Esta accion actualizara tu borrador de Facturacion.
            </p>
            <div class="global-shortcut-confirm__note" id="facturacionActionConfirmNoteBox">
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

    @if ($shouldOpenConsultFeedbackModal)
        <div class="facturacion-result-modal" id="facturacionResultModal" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="facturacion-result-modal__backdrop" data-close-facturacion-result="true"></div>
            <div class="facturacion-result-modal__panel" role="document">
                <button type="button" class="facturacion-result-modal__close" id="facturacionResultModalClose" aria-label="Cerrar resultado">x</button>
                <div class="facturacion-result-modal__icon facturacion-result-modal__icon--{{ $facturacionFeedback['type'] ?? 'info' }}">
                    <i class="fas @if(($facturacionFeedback['type'] ?? '') === 'success') fa-check-circle @elseif(($facturacionFeedback['type'] ?? '') === 'warning') fa-exclamation-triangle @elseif(($facturacionFeedback['type'] ?? '') === 'error') fa-times-circle @else fa-info-circle @endif"></i>
                </div>
                <h4 class="facturacion-result-modal__title">{{ $facturacionFeedback['title'] ?? 'Resultado de consulta' }}</h4>
                <p class="facturacion-result-modal__message">{{ $facturacionFeedback['message'] ?? '' }}</p>
                @if (!empty($facturacionFeedback['meta']) && is_array($facturacionFeedback['meta']))
                    <div class="facturacion-result-modal__meta-grid">
                        @foreach ($facturacionFeedback['meta'] as $metaItem)
                            @php
                                $metaLabel = trim((string) ($metaItem['label'] ?? 'Dato'));
                                $metaValue = trim((string) ($metaItem['value'] ?? ''));
                                $metaType = trim((string) ($metaItem['type'] ?? 'text'));
                            @endphp
                            @if ($metaValue !== '')
                                <div class="facturacion-result-modal__meta-card">
                                    <span class="facturacion-result-modal__meta-label">{{ $metaLabel }}</span>
                                    @if ($metaType === 'link')
                                        <a href="{{ $metaValue }}" target="_blank" rel="noopener" class="facturacion-result-modal__meta-link">Abrir documento</a>
                                    @else
                                        <strong class="facturacion-result-modal__meta-value">{{ $metaValue }}</strong>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
                @if (!empty($facturacionFeedback['detail']))
                    <div class="facturacion-result-modal__detail">{{ $facturacionFeedback['detail'] }}</div>
                @endif
                <div class="facturacion-result-modal__actions">
                    <button type="button" class="global-shortcut-primary-btn" id="facturacionResultModalAccept">Entendido</button>
                </div>
            </div>
        </div>
    @endif

    @if (is_array($facturacionQrData) && (!empty($facturacionQrData['image_data']) || !empty($facturacionQrData['transaction_id'])))
        <div class="facturacion-qr-viewer" id="facturacionQrViewer" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="facturacion-qr-viewer__backdrop" data-close-facturacion-qr="true"></div>
            <div class="facturacion-qr-viewer__panel" role="document">
                <button type="button" class="facturacion-qr-viewer__close" id="facturacionQrViewerClose" aria-label="Cerrar QR">x</button>
                <h4 class="facturacion-qr-viewer__title">QR de pago</h4>
                <p class="facturacion-qr-viewer__subtitle">
                    {{ (string) ($facturacionQrData['message'] ?? 'Escanee este QR para completar el pago.') }}
                </p>
                <div class="facturacion-qr-viewer__status">
                    Estado:
                    <strong>{{ strtoupper((string) ($facturacionQrData['payment_status'] ?? 'holding')) }}</strong>
                    @if (!empty($facturacionQrData['transaction_id']))
                        <span class="facturacion-qr-viewer__meta">| Tx: {{ $facturacionQrData['transaction_id'] }}</span>
                    @endif
                    @if (!empty($facturacionQrData['internal_code']))
                        <span class="facturacion-qr-viewer__meta">| Orden: {{ $facturacionQrData['internal_code'] }}</span>
                    @endif
                </div>
                @php
                    $rawQrImage = trim((string) ($facturacionQrData['image_data'] ?? ''));
                    $qrSrc = $rawQrImage;
                    if ($rawQrImage !== '' && !str_starts_with($rawQrImage, 'data:image') && !preg_match('/^https?:\/\//i', $rawQrImage)) {
                        $qrSrc = 'data:image/png;base64,' . $rawQrImage;
                    }
                @endphp
                @if ($rawQrImage !== '')
                    <div class="facturacion-qr-viewer__image-wrap">
                        <img src="{{ $qrSrc }}" alt="QR de pago" class="facturacion-qr-viewer__image">
                    </div>
                @else
                    <div class="facturacion-qr-viewer__empty">
                        El proveedor recibio la venta, pero todavia no devolvio una imagen QR. Usa "Consultar estado" para refrescar la respuesta.
                    </div>
                @endif
                <div class="facturacion-qr-viewer__guide">
                    <div class="facturacion-qr-viewer__guide-step">
                        <span>1</span>
                        <strong>Comparte el QR con el cliente</strong>
                    </div>
                    <div class="facturacion-qr-viewer__guide-step">
                        <span>2</span>
                        <strong>Espera el callback o actualiza el pago</strong>
                    </div>
                    <div class="facturacion-qr-viewer__guide-step">
                        <span>3</span>
                        <strong>El monto se reporta fuera de caja</strong>
                    </div>
                </div>
                <form method="POST" action="{{ route('facturacion.cart.consultar') }}" class="facturacion-qr-viewer__actions">
                    @csrf
                    <button type="submit" class="global-shortcut-primary-btn">Actualizar pago</button>
                </form>
            </div>
        </div>
    @endif

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
            width: min(500px, calc(100vw - 32px));
            margin: 0 24px 24px;
            border-radius: 22px;
            background: #ffffff;
            border: 1px solid rgba(32, 83, 154, .12);
            box-shadow: 0 18px 36px rgba(8, 24, 50, .2);
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
        .facturacion-qr-viewer {
            position: fixed;
            inset: 0;
            z-index: 1400;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .facturacion-qr-viewer.is-open {
            display: flex;
        }
        .facturacion-qr-viewer__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(9, 22, 44, .6);
            backdrop-filter: blur(3px);
        }
        .facturacion-qr-viewer__panel {
            position: relative;
            width: min(520px, calc(100vw - 34px));
            border-radius: 16px;
            background: #fff;
            border: 1px solid #d6e4f4;
            box-shadow: 0 20px 40px rgba(6, 20, 40, .35);
            padding: 18px 18px 16px;
            text-align: center;
        }
        .facturacion-qr-viewer__close {
            position: absolute;
            right: 10px;
            top: 10px;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            background: #eef4fd;
            color: #1d467a;
            font-weight: 800;
        }
        .facturacion-qr-viewer__title {
            margin: 4px 0 4px;
            font-size: 1.3rem;
            color: #133b6e;
            font-weight: 800;
        }
        .facturacion-qr-viewer__subtitle {
            margin: 0 0 10px;
            color: #496386;
            font-size: .92rem;
        }
        .facturacion-qr-viewer__status {
            margin-bottom: 12px;
            color: #2e4f79;
            font-size: .87rem;
        }
        .facturacion-qr-viewer__guide {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin: 0 0 14px;
            text-align: left;
        }
        .facturacion-qr-viewer__guide-step {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 12px 11px;
            border-radius: 13px;
            border: 1px solid #dce7f6;
            background: #f8fbff;
        }
        .facturacion-qr-viewer__guide-step span {
            flex: 0 0 auto;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #20539a;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .78rem;
            font-weight: 800;
        }
        .facturacion-qr-viewer__guide-step strong {
            color: #21456f;
            font-size: .81rem;
            line-height: 1.45;
            font-weight: 700;
        }
        .facturacion-qr-viewer__meta {
            color: #6f83a3;
            font-weight: 600;
        }
        .facturacion-qr-viewer__image-wrap {
            border: 1px solid #d9e7f7;
            border-radius: 12px;
            background: #f8fbff;
            padding: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 280px;
            margin-bottom: 14px;
        }
        .facturacion-qr-viewer__image {
            max-width: min(360px, 80vw);
            width: 100%;
            height: auto;
            border-radius: 8px;
            background: #fff;
        }
        .facturacion-qr-viewer__empty {
            border: 1px dashed #c6d7ec;
            border-radius: 12px;
            background: #f8fbff;
            padding: 18px 16px;
            margin-bottom: 14px;
            color: #496386;
            font-size: .92rem;
            text-align: center;
        }
        .facturacion-qr-viewer__actions {
            display: flex;
            justify-content: center;
        }
        .facturacion-result-modal {
            position: fixed;
            inset: 0;
            z-index: 1390;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .facturacion-result-modal.is-open {
            display: flex;
        }
        .facturacion-result-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(9, 22, 44, .56);
            backdrop-filter: blur(3px);
        }
        .facturacion-result-modal__panel {
            position: relative;
            width: min(460px, calc(100vw - 32px));
            border-radius: 18px;
            background: #fff;
            border: 1px solid #d6e4f4;
            box-shadow: 0 22px 46px rgba(6, 20, 40, .28);
            padding: 24px 22px 20px;
            text-align: center;
        }
        .facturacion-result-modal__close {
            position: absolute;
            right: 10px;
            top: 10px;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            background: #eef4fd;
            color: #1d467a;
            font-weight: 800;
        }
        .facturacion-result-modal__icon {
            width: 58px;
            height: 58px;
            margin: 0 auto 12px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .facturacion-result-modal__icon--success {
            background: #e9f8ee;
            color: #23924d;
        }
        .facturacion-result-modal__icon--warning {
            background: #fff4dd;
            color: #b7791f;
        }
        .facturacion-result-modal__icon--error {
            background: #fde8e8;
            color: #c23d3d;
        }
        .facturacion-result-modal__icon--info {
            background: #eaf2ff;
            color: #2155a3;
        }
        .facturacion-result-modal__title {
            margin: 0 0 8px;
            font-size: 1.24rem;
            color: #133b6e;
            font-weight: 800;
        }
        .facturacion-result-modal__message {
            margin: 0;
            color: #2e4f79;
            font-size: .98rem;
        }
        .facturacion-result-modal__meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
            text-align: left;
        }
        .facturacion-result-modal__meta-card {
            border: 1px solid #d9e7f7;
            border-radius: 12px;
            background: #f8fbff;
            padding: 12px 13px;
        }
        .facturacion-result-modal__meta-label {
            display: block;
            margin-bottom: 4px;
            color: #6d84a5;
            font-size: .76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .facturacion-result-modal__meta-value {
            display: block;
            color: #133b6e;
            font-size: .96rem;
            line-height: 1.25;
            word-break: break-word;
        }
        .facturacion-result-modal__meta-link {
            color: #20539a;
            font-size: .92rem;
            font-weight: 700;
            text-decoration: none;
        }
        .facturacion-result-modal__meta-link:hover,
        .facturacion-result-modal__meta-link:focus {
            text-decoration: underline;
            color: #143d74;
        }
        .facturacion-result-modal__detail {
            margin-top: 12px;
            border-radius: 12px;
            padding: 12px 14px;
            background: #f5f9ff;
            color: #577097;
            font-size: .9rem;
        }
        .facturacion-result-modal__actions {
            display: flex;
            justify-content: center;
            margin-top: 16px;
        }
        .global-shortcut-modal__body {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            padding: 20px 20px 14px;
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
        .global-shortcut-feedback.is-dismissing {
            opacity: 0;
            transform: translateY(-6px);
            transition: opacity .25s ease, transform .25s ease;
            pointer-events: none;
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
        .global-shortcut-workflow-board {
            margin-bottom: 14px;
            padding: 16px;
            border-radius: 18px;
            border: 1px solid #dbe7f5;
            background:
                radial-gradient(circle at top left, rgba(255, 196, 64, .16), transparent 32%),
                linear-gradient(135deg, #ffffff 0%, #f7fbff 100%);
            box-shadow: 0 12px 30px rgba(17, 47, 92, .08);
        }
        .global-shortcut-workflow-board.is-muted {
            opacity: .84;
        }
        .global-shortcut-workflow-board__hero {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
        }
        .global-shortcut-workflow-board__copy strong {
            display: block;
            margin: 0;
            color: #14345f;
            font-size: 1rem;
            font-weight: 800;
        }
        .global-shortcut-workflow-board__copy p {
            margin: 6px 0 0;
            color: #557095;
            font-size: .87rem;
            line-height: 1.5;
        }
        .global-shortcut-workflow-board__eyebrow {
            display: inline-flex;
            align-items: center;
            margin-bottom: 8px;
            padding: 5px 10px;
            border-radius: 999px;
            background: #edf4ff;
            color: #20539a;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .global-shortcut-workflow-board__badge {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: #fff7e8;
            color: #9a5a00;
            border: 1px solid #f3ddb1;
            font-size: .74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .global-shortcut-workflow-board__grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }
        .global-shortcut-workflow-card {
            min-height: 88px;
            padding: 13px 14px;
            border-radius: 15px;
            border: 1px solid #deebf8;
            background: rgba(255, 255, 255, .92);
        }
        .global-shortcut-workflow-card span {
            display: block;
            margin-bottom: 6px;
            color: #7890ae;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .global-shortcut-workflow-card strong {
            display: block;
            color: #14345f;
            font-size: .9rem;
            line-height: 1.45;
            font-weight: 800;
        }
        .global-shortcut-workflow-track {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }
        .global-shortcut-workflow-track__step {
            position: relative;
            padding: 12px 12px 12px 40px;
            border-radius: 14px;
            border: 1px solid #dde8f6;
            background: #f9fbfe;
            min-height: 62px;
        }
        .global-shortcut-workflow-track__step strong {
            display: block;
            color: #24456f;
            font-size: .84rem;
            line-height: 1.4;
            font-weight: 800;
        }
        .global-shortcut-workflow-track__dot {
            position: absolute;
            left: 14px;
            top: 50%;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            transform: translateY(-50%);
            border: 3px solid #b8cbe3;
            background: #fff;
        }
        .global-shortcut-workflow-track__step.is-done {
            background: linear-gradient(135deg, #effaf3 0%, #f8fcfa 100%);
            border-color: #cde8d6;
        }
        .global-shortcut-workflow-track__step.is-done .global-shortcut-workflow-track__dot {
            border-color: #2e9d5f;
            background: #2e9d5f;
            box-shadow: 0 0 0 4px rgba(46, 157, 95, .12);
        }
        .global-shortcut-workflow-track__step.is-active {
            background: linear-gradient(135deg, #fff8e9 0%, #fffdf7 100%);
            border-color: #f1d9a2;
        }
        .global-shortcut-workflow-track__step.is-active .global-shortcut-workflow-track__dot {
            border-color: #f0a400;
            background: #f0a400;
            box-shadow: 0 0 0 4px rgba(240, 164, 0, .15);
        }
        .global-shortcut-workflow-track__step.is-pending .global-shortcut-workflow-track__dot {
            background: #eef4fb;
        }
        .global-shortcut-closed-mode {
            margin-bottom: 12px;
            padding: 18px 16px;
            border-radius: 16px;
            background: linear-gradient(180deg, #fff7ec 0%, #fff2df 100%);
            border: 1px solid #f0ce9f;
            color: #8a5300;
            text-align: center;
        }
        .global-shortcut-closed-mode__icon {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-size: 1rem;
            background: rgba(255, 255, 255, .86);
            color: #9b5e07;
            border: 1px solid #eecb99;
        }
        .global-shortcut-closed-mode h4 {
            margin: 0;
            color: #7f4b00;
            font-size: 1rem;
            font-weight: 800;
        }
        .global-shortcut-closed-mode p {
            margin: 6px 0 0;
            color: #8a5a14;
            font-size: .84rem;
            line-height: 1.45;
        }
        .global-shortcut-operacion.is-hidden-locked {
            display: none;
        }
        .global-shortcut-operacion {
            display: grid;
            gap: 16px;
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
            gap: 14px;
            margin-bottom: 0;
        }
        .global-shortcut-last-emission {
            margin: 0;
            border: 1px solid #e6edf8;
            border-radius: 12px;
            background: #fcfdff;
            overflow: hidden;
        }
        .global-shortcut-last-emission__summary {
            list-style: none;
            cursor: pointer;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .global-shortcut-last-emission__summary::-webkit-details-marker {
            display: none;
        }
        .global-shortcut-last-emission__label {
            color: #60738f;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .global-shortcut-last-emission__state {
            border-radius: 999px;
            padding: 4px 10px;
            font-size: .74rem;
            font-weight: 800;
            background: #eef3fb;
            color: #4f6580;
            white-space: nowrap;
        }
        .global-shortcut-last-emission__state.is-ok {
            background: #e8f7ee;
            color: #1f7a42;
        }
        .global-shortcut-last-emission__state.is-warn {
            background: #fff2e7;
            color: #b86a00;
        }
        .global-shortcut-last-emission__content {
            border-top: 1px solid #edf3fb;
            padding: 10px 14px 12px;
            display: grid;
            gap: 8px;
        }
        .global-shortcut-last-emission__content p {
            margin: 0;
            color: #667b97;
            font-size: .82rem;
            line-height: 1.45;
        }
        .global-shortcut-last-emission__links {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .global-shortcut-last-emission__links a {
            color: #20539a;
            font-size: .79rem;
            font-weight: 700;
            text-decoration: none;
        }
        .global-shortcut-last-emission__links a:hover,
        .global-shortcut-last-emission__links a:focus {
            color: #163f74;
            text-decoration: underline;
            outline: none;
        }
        .global-shortcut-caja-state {
            margin-bottom: 14px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid #e4ecf7;
            background: #fff;
        }
        .global-shortcut-caja-state__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .global-shortcut-caja-state__label {
            color: #647a98;
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .global-shortcut-caja-state__badge {
            border-radius: 999px;
            padding: 5px 10px;
            font-size: .72rem;
            font-weight: 800;
            white-space: nowrap;
            background: #eef3fb;
            color: #506680;
        }
        .global-shortcut-caja-state__badge.is-open {
            background: #e8f7ee;
            color: #1f7a42;
        }
        .global-shortcut-caja-state__badge.is-closed {
            background: #fff2e7;
            color: #b86a00;
        }
        .global-shortcut-caja-state__badge.is-idle {
            background: #eef2f7;
            color: #627389;
        }
        .global-shortcut-caja-state__meta {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            color: #4f6580;
            font-size: .82rem;
            font-weight: 600;
        }
        .global-shortcut-caja-state__message {
            margin: 7px 0 0;
            color: #6e7f95;
            font-size: .8rem;
            line-height: 1.45;
        }
        .global-shortcut-emision-card {
            margin-bottom: 0;
            border: 1px solid #e7eef7;
            border-radius: 12px;
            background: #fbfdff;
            overflow: hidden;
        }
        .global-shortcut-emision-card[open] {
            border-color: #d9e7fa;
        }
        .global-shortcut-emision-card__summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            list-style: none;
            cursor: pointer;
            padding: 16px 18px;
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
            padding: 0 18px 18px;
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
            border-radius: 10px;
            background: #fbfdff;
            border: 1px solid #e7eef7;
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
            margin-bottom: 0;
            padding: 16px;
            border: 1px solid #e3ebf6;
            border-radius: 12px;
            background: #fbfdff;
        }
        .global-shortcut-selector-block {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 16px;
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
            gap: 10px;
        }
        .global-shortcut-choice-row--triple {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .global-shortcut-choice-btn {
            min-height: 46px;
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
            box-shadow: none;
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
        .global-shortcut-sin-cliente {
            margin-bottom: 12px;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #e3ebf6;
            background: #fbfdff;
        }
        .global-shortcut-sin-cliente__head {
            color: #4f627e;
            font-size: .82rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .global-shortcut-billing-inline__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .global-shortcut-field.is-hidden {
            display: none;
        }
        .global-shortcut-billing-inline__grid.is-hidden,
        .global-shortcut-anonymous-note.is-hidden,
        .global-shortcut-sin-cliente.is-hidden {
            display: none;
        }
        .global-shortcut-field--full {
            grid-column: 1 / -1;
        }
        .global-shortcut-field label {
            display: block;
            margin-bottom: 8px;
            color: #4f627e;
            font-size: .83rem;
            font-weight: 700;
        }
        .global-shortcut-field-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }
        .global-shortcut-field-head label {
            margin-bottom: 0;
        }
        .global-shortcut-email-toggle {
            display: inline-flex !important;
            align-items: center;
            gap: 6px;
            color: #20539a !important;
            font-size: .76rem !important;
            font-weight: 800 !important;
            white-space: nowrap;
            cursor: pointer;
        }
        .global-shortcut-email-toggle input {
            width: 15px !important;
            min-height: 15px !important;
            height: 15px;
            padding: 0 !important;
            margin: 0;
            cursor: pointer;
        }
        .global-shortcut-field input[readonly] {
            background: #eef4fb;
            color: #52657f;
            cursor: not-allowed;
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
            margin-bottom: 0;
            border: 1px solid #e7eef7;
            border-radius: 12px;
            background: #fbfdff;
            overflow: hidden;
        }
        .global-shortcut-cart-block__head {
            padding: 16px 18px;
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
            min-height: 112px;
            padding: 16px 18px 18px;
            display: grid;
            place-items: center;
            gap: 6px;
            text-align: center;
            background: linear-gradient(180deg, #fbfdff 0%, #f6f9fe 100%);
        }
        .global-shortcut-cart-empty__icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #edf3fc;
            border: 1px solid #dbe7f7;
            color: #4d6890;
            font-size: .88rem;
        }
        .global-shortcut-cart-empty strong {
            color: #314866;
            font-size: .92rem;
            font-weight: 800;
            letter-spacing: .01em;
        }
        .global-shortcut-cart-empty p {
            margin: 0;
            color: #6f8098;
            font-size: .82rem;
            line-height: 1.45;
        }
        .global-shortcut-cart-list {
            max-height: none;
            overflow: visible;
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
            padding: 14px 20px 20px;
            background: #ffffff;
            border-top: 1px solid #e3ebf6;
        }
        .global-shortcut-processing-overlay {
            position: fixed;
            inset: 0;
            z-index: 1200;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(8, 24, 50, 0.38);
            backdrop-filter: blur(6px);
        }
        .global-shortcut-processing-overlay__card {
            position: relative;
            width: min(100%, 430px);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding: 26px 28px 22px;
            border-radius: 28px;
            border: 1px solid rgba(207, 220, 238, 0.95);
            background:
                radial-gradient(circle at top center, rgba(79, 126, 192, 0.08), transparent 38%),
                linear-gradient(180deg, #ffffff 0%, #f9fbfe 100%);
            box-shadow:
                0 28px 58px rgba(8, 24, 50, 0.18),
                0 10px 24px rgba(28, 61, 108, 0.08);
            text-align: center;
            color: #445872;
            overflow: hidden;
        }
        .global-shortcut-processing-overlay.is-preview {
            cursor: pointer;
        }
        .global-shortcut-processing-overlay__art {
            position: relative;
            width: 100%;
            padding: 8px 0 4px;
        }
        .global-shortcut-processing-overlay__glow {
            position: absolute;
            inset: 6px 44px 18px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(79, 126, 192, 0.14) 0%, rgba(79, 126, 192, 0) 72%);
            filter: blur(14px);
        }
        .global-shortcut-processing-overlay__svg {
            width: 100%;
            height: auto;
            display: block;
        }
        .global-shortcut-processing-overlay__pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            padding: 0 14px;
            border-radius: 999px;
            background: #edf3fb;
            color: #20539a;
            border: 1px solid #dbe6f3;
            font-size: .74rem;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .global-shortcut-processing-overlay__card strong {
            font-size: 1.55rem;
            font-weight: 800;
            color: #173962;
            line-height: 1.08;
        }
        .global-shortcut-processing-overlay__card span:last-child {
            max-width: 26ch;
            font-size: .98rem;
            line-height: 1.6;
            font-weight: 500;
            color: #5d718d;
        }
        .global-shortcut-processing-overlay__card small {
            font-size: .76rem;
            line-height: 1.4;
            color: #7a8ea8;
            font-weight: 700;
        }
        .global-shortcut-processing-overlay__ring {
            transform-origin: 180px 44px;
            animation: facturacion-ring-spin 1.3s linear infinite;
        }
        .global-shortcut-processing-overlay__flow {
            animation: facturacion-flow-move 1.5s linear infinite;
        }
        .global-shortcut-processing-overlay__doc--source {
            animation: facturacion-doc-source 2.2s ease-in-out infinite;
        }
        .global-shortcut-processing-overlay__doc--target {
            animation: facturacion-doc-target 2.2s ease-in-out infinite;
        }
        .global-shortcut-processing-overlay__seal {
            transform-origin: 282px 58px;
            animation: facturacion-seal-pulse 1.8s ease-in-out infinite;
        }
        .global-shortcut-footer-action__row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }
        .global-shortcut-footer-action__row--closed {
            margin-bottom: 0;
        }
        .global-shortcut-footer-action__row--closed .global-shortcut-confirm-form {
            width: 100%;
        }
        .global-shortcut-turno-row {
            width: 100%;
            border: 1px solid #e5edf8;
            border-radius: 12px;
            background: #fbfdff;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .global-shortcut-turno-row__copy {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .global-shortcut-turno-row__copy strong {
            color: #4f627d;
            font-size: .77rem;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .global-shortcut-turno-row__copy span {
            margin-top: 4px;
            color: #637892;
            font-size: .8rem;
            line-height: 1.35;
        }
        .global-shortcut-turno-row__copy small {
            margin-top: 4px;
            color: #9a5a00;
            font-size: .75rem;
            font-weight: 700;
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
        .global-shortcut-secondary-btn--turno-close {
            min-height: 34px;
            border-radius: 10px;
            border-color: #e3c8c6;
            background: #fff7f6;
            color: #8f3b34;
            font-size: .74rem;
            font-weight: 800;
            padding: 0 12px;
            gap: 7px;
        }
        .global-shortcut-secondary-btn--turno-close i {
            font-size: .76rem;
        }
        .global-shortcut-secondary-btn--turno-close:hover,
        .global-shortcut-secondary-btn--turno-close:focus {
            color: #7a2f29;
            border-color: #d5a7a3;
            background: #fff0ee;
            box-shadow: 0 10px 16px rgba(122, 47, 41, .12);
        }
        .global-shortcut-secondary-btn--turno-close:disabled {
            opacity: .55;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }
        .global-shortcut-secondary-btn--open-caja {
            width: 100%;
            min-height: 50px;
            border-radius: 14px;
            border-color: #0f3f78;
            background: linear-gradient(135deg, #20539a 0%, #0f3f78 100%);
            color: #fff;
            font-size: .95rem;
            font-weight: 800;
            letter-spacing: .01em;
            gap: 8px;
            box-shadow: 0 14px 24px rgba(15, 63, 120, .22);
        }
        .global-shortcut-secondary-btn--open-caja i {
            font-size: .9rem;
            opacity: .95;
        }
        .global-shortcut-secondary-btn--open-caja:hover,
        .global-shortcut-secondary-btn--open-caja:focus {
            color: #fff;
            border-color: #0b3261;
            box-shadow: 0 18px 30px rgba(15, 63, 120, .28);
            transform: translateY(-1px);
        }
        .global-shortcut-secondary-btn--open-caja:active {
            transform: translateY(0);
            box-shadow: 0 10px 18px rgba(15, 63, 120, .2);
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
            background: #ffffff;
            border: 1px solid rgba(32, 83, 154, .12);
            box-shadow: 0 18px 36px rgba(8, 24, 50, .2);
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
            .global-shortcut-workflow-board__hero,
            .global-shortcut-workflow-board__grid,
            .global-shortcut-workflow-track,
            .facturacion-qr-viewer__guide {
                grid-template-columns: 1fr;
            }
            .global-shortcut-workflow-board__hero {
                display: grid;
            }
            .global-shortcut-choice-row--triple {
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
            .facturacion-result-modal__meta-grid {
                grid-template-columns: 1fr;
            }
            .global-shortcut-selector-block {
                grid-template-columns: 1fr;
            }
            .global-shortcut-choice-row--triple {
                grid-template-columns: 1fr;
            }
            .global-shortcut-workflow-board {
                padding: 14px;
            }
            .global-shortcut-workflow-track__step {
                padding-left: 38px;
            }
            .facturacion-qr-viewer__guide-step {
                padding: 11px;
            }
            .global-shortcut-choice-btn {
                min-height: 44px;
                font-size: .8rem;
                padding: 7px 8px;
            }
            .global-shortcut-cart-summary {
                grid-template-columns: 1fr;
            }
            .global-shortcut-turno-row {
                flex-direction: column;
                align-items: stretch;
            }
            .global-shortcut-turno-row .global-shortcut-confirm-form {
                width: 100%;
            }
            .global-shortcut-secondary-btn--turno-close {
                width: 100%;
            }
        }
        @keyframes facturacion-spin {
            to {
                transform: rotate(360deg);
            }
        }
        @keyframes facturacion-ring-spin {
            to {
                transform: rotate(360deg);
            }
        }
        @keyframes facturacion-flow-move {
            0%, 100% {
                transform: translateX(0);
                opacity: .85;
            }
            50% {
                transform: translateX(5px);
                opacity: 1;
            }
        }
        @keyframes facturacion-doc-source {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(3px);
            }
        }
        @keyframes facturacion-doc-target {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-3px);
            }
        }
        @keyframes facturacion-seal-pulse {
            0%, 100% {
                transform: scale(1);
                opacity: .96;
            }
            50% {
                transform: scale(1.06);
                opacity: 1;
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
            const facturacionActionConfirmNoteBox = document.getElementById('facturacionActionConfirmNoteBox');
            const facturacionActionConfirmNote = document.getElementById('facturacionActionConfirmNote');
            const facturacionActionConfirmIcon = document.getElementById('facturacionActionConfirmIcon');
            const facturacionActionConfirmAccept = document.getElementById('facturacionActionConfirmAccept');
            const facturacionActionConfirmCancel = document.getElementById('facturacionActionConfirmCancel');
            const facturacionActionConfirmEyebrow = document.querySelector('.global-shortcut-confirm__eyebrow');
            const FACTURACION_CONFIRM_DEFAULTS = {
                eyebrow: 'Confirmacion de carrito',
                title: 'Confirmar accion',
                message: 'Esta accion actualizara tu borrador de Facturacion.',
                note: 'Solo se modificara el borrador actual del carrito.',
            };
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

            if (facturacionActionConfirmEyebrow) {
                facturacionActionConfirmEyebrow.textContent = FACTURACION_CONFIRM_DEFAULTS.eyebrow;
            }
            if (facturacionActionConfirmTitle) {
                facturacionActionConfirmTitle.textContent = FACTURACION_CONFIRM_DEFAULTS.title;
            }
            if (facturacionActionConfirmMessage) {
                facturacionActionConfirmMessage.textContent = FACTURACION_CONFIRM_DEFAULTS.message;
            }
            if (facturacionActionConfirmNote) {
                facturacionActionConfirmNote.textContent = FACTURACION_CONFIRM_DEFAULTS.note;
            }
            const facturacionAutosaveState = document.getElementById('facturacionAutosaveState');
            const facturacionProcessingState = document.getElementById('facturacionProcessingState');
            const facturacionProcessingPill = document.getElementById('facturacionProcessingPill');
            const facturacionProcessingTitle = document.getElementById('facturacionProcessingTitle');
            const facturacionProcessingText = document.getElementById('facturacionProcessingText');
            const facturacionProcessingHint = document.getElementById('facturacionProcessingHint');
            const facturacionFeedbackAlert = document.getElementById('facturacionFeedbackAlert');
            const facturacionQrViewer = document.getElementById('facturacionQrViewer');
            const facturacionQrViewerClose = document.getElementById('facturacionQrViewerClose');
            const facturacionResultModal = document.getElementById('facturacionResultModal');
            const facturacionResultModalClose = document.getElementById('facturacionResultModalClose');
            const facturacionResultModalAccept = document.getElementById('facturacionResultModalAccept');
            const invoiceChannelButtons = document.querySelectorAll('[data-invoice-channel-choice]');
            const confirmForms = document.querySelectorAll('.global-shortcut-confirm-form');
            const facturacionFlashFeedback = @json($facturacionFeedback);
            const facturacionDownloadPdf = @json($facturacionDownloadPdf);
            const facturacionQrData = @json($facturacionQrData);
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
            const FACTURACION_PROCESSING_DEFAULTS = {
                pill: 'Facturacion en curso',
                title: 'Emitiendo factura',
                text: 'Procesando emision, espera un momento...',
            };

            const resolveProcessingCopy = (form = null) => {
                if (!(form instanceof HTMLFormElement)) {
                    return FACTURACION_PROCESSING_DEFAULTS;
                }

                const action = String(form.getAttribute('action') || '').toLowerCase();

                if (action.includes('/facturacion/cart/caja/abrir')) {
                    return {
                        pill: 'Caja diaria',
                        title: 'Abriendo caja',
                        text: 'Estamos preparando la caja diaria, espera un momento...',
                    };
                }

                if (action.includes('/facturacion/cart/caja/cerrar')) {
                    return {
                        pill: 'Caja diaria',
                        title: 'Cerrando caja',
                        text: 'Estamos cerrando la caja diaria, espera un momento...',
                    };
                }

                return {
                    pill: String(form.dataset.processingPill || FACTURACION_PROCESSING_DEFAULTS.pill),
                    title: String(form.dataset.processingTitle || FACTURACION_PROCESSING_DEFAULTS.title),
                    text: String(form.dataset.processingText || FACTURACION_PROCESSING_DEFAULTS.text),
                };
            };

            const setFacturacionProcessingOverlay = (active, options = {}) => {
                const {
                    pill = FACTURACION_PROCESSING_DEFAULTS.pill,
                    title = FACTURACION_PROCESSING_DEFAULTS.title,
                    text = FACTURACION_PROCESSING_DEFAULTS.text,
                    previewMode = false,
                } = options;

                if (facturacionProcessingState) {
                    facturacionProcessingState.hidden = !active;
                    facturacionProcessingState.dataset.previewMode = previewMode ? 'true' : 'false';
                    facturacionProcessingState.classList.toggle('is-preview', active && previewMode);
                }

                if (facturacionProcessingPill) {
                    facturacionProcessingPill.textContent = pill;
                }

                if (facturacionProcessingTitle) {
                    facturacionProcessingTitle.textContent = title;
                }

                if (facturacionProcessingText) {
                    facturacionProcessingText.textContent = text;
                }

                if (facturacionProcessingHint) {
                    facturacionProcessingHint.hidden = !active || !previewMode;
                }
            };

            const setFacturacionSubmittingState = (form, active) => {
                isFacturacionSubmitting = active;
                const processingCopy = active
                    ? resolveProcessingCopy(form)
                    : FACTURACION_PROCESSING_DEFAULTS;

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

                setFacturacionProcessingOverlay(active, {
                    pill: processingCopy.pill,
                    title: processingCopy.title,
                    text: processingCopy.text,
                    previewMode: false,
                });
            };

            const resolveScopedBillingForm = (contextElement) => {
                if (!(contextElement instanceof HTMLElement)) {
                    return facturacionBillingInlineForm;
                }

                const panel = contextElement.closest('.global-shortcut-modal__panel');
                if (!(panel instanceof HTMLElement)) {
                    return facturacionBillingInlineForm;
                }

                return panel.querySelector('#facturacionBillingInlineForm') || facturacionBillingInlineForm;
            };

            const syncEmitFormsWithBillingState = (contextElement = facturacionBillingInlineForm) => {
                const scopedBillingForm = resolveScopedBillingForm(contextElement);
                if (!(scopedBillingForm instanceof HTMLFormElement)) {
                    return;
                }

                const panel = scopedBillingForm.closest('.global-shortcut-modal__panel');
                const scopedConfirmForms = panel instanceof HTMLElement
                    ? panel.querySelectorAll('.global-shortcut-confirm-form')
                    : confirmForms;

                const billingData = {
                    modalidad_facturacion: 'con_datos',
                    canal_emision: 'factura_electronica',
                    tipo_documento: '',
                    numero_documento: '',
                    complemento_documento: '',
                    razon_social: '',
                    correo_facturacion: '',
                };

                const formFieldMap = {
                    canal_emision: 'input[name="canal_emision"]',
                    tipo_documento: 'input[name="tipo_documento"]',
                    numero_documento: 'input[name="numero_documento"]',
                    complemento_documento: 'input[name="complemento_documento"]',
                    razon_social: 'input[name="razon_social"]',
                    correo_facturacion: 'input[name="correo_facturacion"]',
                };

                Object.entries(formFieldMap).forEach(([key, selector]) => {
                    const field = scopedBillingForm.querySelector(selector);
                    if (field instanceof HTMLInputElement) {
                        billingData[key] = String(field.value || '');
                    }
                });

                billingData.canal_emision = String(billingData.canal_emision || 'factura_electronica').toLowerCase() === 'qr'
                    ? 'qr'
                    : 'factura_electronica';

                scopedConfirmForms.forEach((form) => {
                    if (!(form instanceof HTMLFormElement)) {
                        return;
                    }

                    form.querySelectorAll('[data-emit-sync-field]').forEach((field) => {
                        if (!(field instanceof HTMLInputElement)) {
                            return;
                        }

                        const syncField = String(field.dataset.emitSyncField || '').trim();
                        if (syncField === '' || !(syncField in billingData)) {
                            return;
                        }

                        field.value = billingData[syncField];
                    });
                });
            };

            if (facturacionProcessingState instanceof HTMLElement) {
                facturacionProcessingState.addEventListener('click', function () {
                    if (facturacionProcessingState.dataset.previewMode !== 'true' || isFacturacionSubmitting) {
                        return;
                    }

                    setFacturacionProcessingOverlay(false, {
                        pill: FACTURACION_PROCESSING_DEFAULTS.pill,
                        title: FACTURACION_PROCESSING_DEFAULTS.title,
                        text: FACTURACION_PROCESSING_DEFAULTS.text,
                        previewMode: false,
                    });
                });
            }

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

            const openFacturacionQrViewer = () => {
                if (!facturacionQrViewer) {
                    return;
                }
                facturacionQrViewer.classList.add('is-open');
                facturacionQrViewer.setAttribute('aria-hidden', 'false');
                document.body.classList.add('global-shortcut-open');
            };

            const closeFacturacionQrViewer = () => {
                if (!facturacionQrViewer) {
                    return;
                }
                facturacionQrViewer.classList.remove('is-open');
                facturacionQrViewer.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('global-shortcut-open');
            };

            const openFacturacionResultModal = () => {
                if (!facturacionResultModal) {
                    return;
                }
                facturacionResultModal.classList.add('is-open');
                facturacionResultModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('global-shortcut-open');

                window.setTimeout(() => {
                    if (facturacionResultModalAccept instanceof HTMLElement) {
                        facturacionResultModalAccept.focus();
                    }
                }, 30);
            };

            const closeFacturacionResultModal = () => {
                if (!facturacionResultModal) {
                    return;
                }
                facturacionResultModal.classList.remove('is-open');
                facturacionResultModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('global-shortcut-open');
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
                facturacionActionConfirmTitle.textContent = form.dataset.confirmTitle || FACTURACION_CONFIRM_DEFAULTS.title;
                facturacionActionConfirmMessage.textContent = form.dataset.confirmMessage || FACTURACION_CONFIRM_DEFAULTS.message;
                facturacionActionConfirmTitle.textContent = form.dataset.confirmTitle || 'Confirmar accion';
                facturacionActionConfirmMessage.textContent = form.dataset.confirmMessage || 'Esta accion actualizara tu borrador de Facturacion.';
                facturacionActionConfirmTitle.textContent = form.dataset.confirmTitle || FACTURACION_CONFIRM_DEFAULTS.title;
                facturacionActionConfirmMessage.textContent = form.dataset.confirmMessage || FACTURACION_CONFIRM_DEFAULTS.message;
                if (facturacionActionConfirmNote && facturacionActionConfirmNoteBox) {
                    const hasCustomNote = form.hasAttribute('data-confirm-note');
                    const noteText = (form.dataset.confirmNote || '').trim();

                    if (hasCustomNote && noteText !== '') {
                        facturacionActionConfirmNote.textContent = noteText;
                        facturacionActionConfirmNoteBox.hidden = false;
                    } else {
                        facturacionActionConfirmNote.textContent = '';
                        facturacionActionConfirmNoteBox.hidden = true;
                    }
                }
                if (facturacionActionConfirmIcon) {
                    const iconClass = (form.dataset.confirmIcon || 'fa-circle-check').trim();
                    facturacionActionConfirmIcon.className = 'fas ' + iconClass;
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

            if (facturacionFlashFeedback && typeof facturacionFlashFeedback === 'object' && facturacionFlashFeedback.action !== 'consultar') {
                openFacturacionShortcutModal();

                if (facturacionFeedbackAlert instanceof HTMLElement) {
                    window.setTimeout(() => {
                        facturacionFeedbackAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }, 120);

                    window.setTimeout(() => {
                        facturacionFeedbackAlert.classList.add('is-dismissing');
                        window.setTimeout(() => {
                            facturacionFeedbackAlert.remove();
                        }, 260);
                    }, 5200);
                }
            }

            if (facturacionFlashFeedback && typeof facturacionFlashFeedback === 'object' && facturacionFlashFeedback.action === 'consultar') {
                openFacturacionResultModal();
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

            if (facturacionQrData && typeof facturacionQrData === 'object' && (facturacionQrData.image_data || facturacionQrData.transaction_id)) {
                openFacturacionQrViewer();
            }

            if (facturacionQrViewerClose) {
                facturacionQrViewerClose.addEventListener('click', closeFacturacionQrViewer);
            }
            if (facturacionQrViewer) {
                facturacionQrViewer.addEventListener('click', function (event) {
                    const target = event.target;
                    if (!(target instanceof HTMLElement)) {
                        return;
                    }
                    if (target.matches('[data-close-facturacion-qr]')) {
                        closeFacturacionQrViewer();
                    }
                });
            }
            if (facturacionResultModalClose) {
                facturacionResultModalClose.addEventListener('click', closeFacturacionResultModal);
            }
            if (facturacionResultModalAccept) {
                facturacionResultModalAccept.addEventListener('click', closeFacturacionResultModal);
            }
            if (facturacionResultModal) {
                facturacionResultModal.addEventListener('click', function (event) {
                    const target = event.target;
                    if (!(target instanceof HTMLElement)) {
                        return;
                    }
                    if (target.matches('[data-close-facturacion-result]')) {
                        closeFacturacionResultModal();
                    }
                });
            }

            if (facturacionBillingInlineForm) {
                let billingAutosaveTimeout = null;
                let billingAutosavePromise = null;
                const billingFields = facturacionBillingInlineForm.querySelectorAll('input:not([data-billing-email-toggle]), select');
                const billingDocumentTypeField = document.getElementById('facturacionBillingDocumentType');
                const billingDocumentTypeHiddenField = document.getElementById('facturacionBillingDocumentTypeHidden');
                const billingDocumentNumberField = document.getElementById('facturacionBillingDocumentNumber');
                const billingDocumentComplementField = document.getElementById('facturacionBillingDocumentComplement');
                const billingNameField = document.getElementById('facturacionBillingName');
                const billingEmailField = document.getElementById('facturacionBillingEmail');
                const billingEmailToggle = document.getElementById('facturacionBillingEmailToggle');

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

                const submitBillingInlineForm = (targetForm = facturacionBillingInlineForm) => {
                    if (!(targetForm instanceof HTMLFormElement)) {
                        return Promise.resolve(false);
                    }

                    const formData = new FormData(targetForm);
                    setAutosaveState('is-saving', 'Guardando cambios...');

                    billingAutosavePromise = fetch(targetForm.action, {
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
                            return true;
                        })
                        .catch(() => {
                            setAutosaveState('is-error', 'No se pudieron guardar los cambios. Revisa los datos e intenta otra vez.');
                            return false;
                        });

                    return billingAutosavePromise;
                };

                const syncHiddenDocumentType = () => {
                    if (billingDocumentTypeHiddenField && billingDocumentTypeField) {
                        billingDocumentTypeHiddenField.value = billingDocumentTypeField.value || '';
                }
            };

                const syncControlTributarioRule = () => {
                    if (!(billingDocumentNumberField instanceof HTMLInputElement) || !(billingNameField instanceof HTMLInputElement)) {
                        return false;
                    }

                    const numero = String(billingDocumentNumberField.value || '').trim();
                    const mustLock = numero === '99002';
                    let changed = false;

                    if (mustLock) {
                        if (String(billingNameField.value || '').trim().toUpperCase() !== 'CONTROL TRIBUTARIO') {
                            billingNameField.value = 'CONTROL TRIBUTARIO';
                            changed = true;
                        }
                        billingNameField.readOnly = true;
                        billingNameField.setAttribute('aria-readonly', 'true');
                    } else {
                        billingNameField.readOnly = false;
                        billingNameField.setAttribute('aria-readonly', 'false');
                    }

                    return changed;
                };

                const inferDocumentTypeFromNumber = (rawValue) => {
                    const value = String(rawValue || '').trim().toUpperCase();
                    if (value === '') {
                        return '';
                    }

                    const compact = value.replace(/\s+/g, '');
                    const digitsOnly = compact.replace(/\D+/g, '');
                    const hasLetters = /[A-Z]/.test(compact);
                    const nitDocType = '5';

                    if (digitsOnly === '99002' || digitsOnly === '99001') {
                        return nitDocType;
                    }

                    // NIT usual: solo digitos y longitud alta
                    if (/^\d{9,15}$/.test(compact)) {
                        return nitDocType;
                    }

                    // CEX: prefijos tipicos de extranjero
                    if (/^(CEX|EXT|E-)/.test(compact)) {
                        return '2';
                    }

                    // CI: numerico corto
                    if (/^\d{5,8}$/.test(compact)) {
                        return '1';
                    }

                    // PAS: alfanumerico con letras (sin prefijos CEX)
                    if (/^[A-Z0-9]{6,12}$/.test(compact) && hasLetters) {
                        return '3';
                    }

                    // OD: fallback para formatos no estandar
                    return '4';
                };

                const syncComplementVisibility = () => {
                    const currentDocumentType = billingDocumentTypeField ? billingDocumentTypeField.value : '';
                    const shouldEnableComplement = ['1', '2'].includes(currentDocumentType);

                    if (billingDocumentComplementField) {
                        billingDocumentComplementField.disabled = !shouldEnableComplement;
                        billingDocumentComplementField.setAttribute('aria-disabled', shouldEnableComplement ? 'false' : 'true');
                    }

                    if (!shouldEnableComplement && billingDocumentComplementField) {
                        billingDocumentComplementField.value = '';
                    }
                };

                const syncBillingEmailMode = (shouldSubmit = false) => {
                    if (!(billingEmailField instanceof HTMLInputElement)) {
                        return;
                    }

                    const defaultEmail = String(billingEmailField.dataset.defaultEmail || 'safe@correos.gob.bo').trim().toLowerCase();
                    const useClientEmail = billingEmailToggle instanceof HTMLInputElement && billingEmailToggle.checked;

                    if (useClientEmail) {
                        billingEmailField.readOnly = false;
                        billingEmailField.setAttribute('aria-readonly', 'false');
                        if (String(billingEmailField.value || '').trim().toLowerCase() === defaultEmail) {
                            billingEmailField.value = '';
                        }
                        billingEmailField.focus();
                    } else {
                        billingEmailField.value = defaultEmail;
                        billingEmailField.readOnly = true;
                        billingEmailField.setAttribute('aria-readonly', 'true');
                    }

                    if (shouldSubmit) {
                        window.clearTimeout(billingAutosaveTimeout);
                        submitBillingInlineForm();
                    }
                };

                billingFields.forEach((field) => {
                    field.addEventListener('input', function () {
                        syncEmitFormsWithBillingState();
                        window.clearTimeout(billingAutosaveTimeout);
                        billingAutosaveTimeout = window.setTimeout(submitBillingInlineForm, 450);
                    });

                    field.addEventListener('change', function () {
                        syncEmitFormsWithBillingState();
                        window.clearTimeout(billingAutosaveTimeout);
                        submitBillingInlineForm();
                    });
                });

                if (billingDocumentTypeField) {
                    billingDocumentTypeField.addEventListener('change', function () {
                        syncComplementVisibility();
                        syncEmitFormsWithBillingState();
                    });
                }

                if (billingDocumentNumberField) {
                    billingDocumentNumberField.addEventListener('input', function () {
                        if (!billingDocumentTypeField) {
                            return;
                        }

                        const inferredType = inferDocumentTypeFromNumber(billingDocumentNumberField.value);
                        if (inferredType !== '') {
                            billingDocumentTypeField.value = inferredType;
                            syncHiddenDocumentType();
                            syncComplementVisibility();
                        }

                        syncControlTributarioRule();
                        syncEmitFormsWithBillingState();
                    });
                }

                if (billingNameField) {
                    billingNameField.addEventListener('input', function () {
                        const upperName = String(billingNameField.value || '').toUpperCase();
                        if (billingNameField.value !== upperName) {
                            billingNameField.value = upperName;
                        }

                        if (billingNameField.readOnly) {
                            return;
                        }

                        if (!billingDocumentTypeField || !billingDocumentNumberField) {
                            return;
                        }

                        const numberEmpty = String(billingDocumentNumberField.value || '').trim() === '';
                        const rawName = String(billingNameField.value || '').trim().toUpperCase();

                        // Soporte cuando el usuario pega el documento en "Razon social"
                        if (!numberEmpty || rawName === '') {
                            return;
                        }

                        if (!/^[A-Z0-9-]{5,15}$/.test(rawName)) {
                            return;
                        }

                        const inferredType = inferDocumentTypeFromNumber(rawName);
                        if (inferredType !== '') {
                            billingDocumentTypeField.value = inferredType;
                            syncHiddenDocumentType();
                            syncComplementVisibility();
                        }

                        syncEmitFormsWithBillingState();
                    });
                }

                if (billingEmailField) {
                    billingEmailField.addEventListener('input', function () {
                        const normalizedEmail = String(billingEmailField.value || '').trim().toLowerCase();
                        if (billingEmailField.value !== normalizedEmail) {
                            billingEmailField.value = normalizedEmail;
                        }

                        syncEmitFormsWithBillingState();
                    });
                }

                if (billingEmailToggle) {
                    billingEmailToggle.addEventListener('change', function () {
                        syncBillingEmailMode(true);
                    });
                }

                if (facturacionBillingModeInput) {
                    facturacionBillingModeInput.value = 'con_datos';
                }
                if (facturacionInvoiceChannelInput) {
                    const initialChannel = String(facturacionInvoiceChannelInput.value || 'factura_electronica').toLowerCase();
                    facturacionInvoiceChannelInput.value = initialChannel === 'qr' ? 'qr' : 'factura_electronica';
                }

                invoiceChannelButtons.forEach((button) => {
                    if (!(button instanceof HTMLButtonElement)) {
                        return;
                    }
                    button.addEventListener('click', function () {
                        const channel = String(button.dataset.invoiceChannelChoice || '').toLowerCase();
                        if (!['factura_electronica', 'qr'].includes(channel)) {
                            return;
                        }

                        const panel = button.closest('.global-shortcut-modal__panel');
                        const scopedInput = panel instanceof HTMLElement
                            ? panel.querySelector('#facturacionInvoiceChannelInput')
                            : facturacionInvoiceChannelInput;
                        const scopedButtons = panel instanceof HTMLElement
                            ? panel.querySelectorAll('[data-invoice-channel-choice]')
                            : invoiceChannelButtons;
                        const scopedForm = panel instanceof HTMLElement
                            ? panel.querySelector('#facturacionBillingInlineForm')
                            : facturacionBillingInlineForm;

                        if (scopedInput instanceof HTMLInputElement) {
                            scopedInput.value = channel;
                        }

                        scopedButtons.forEach((candidate) => {
                            if (candidate instanceof HTMLElement) {
                                candidate.classList.toggle('is-active', candidate === button);
                            }
                        });

                        syncEmitFormsWithBillingState(scopedForm instanceof HTMLFormElement ? scopedForm : facturacionBillingInlineForm);
                        window.clearTimeout(billingAutosaveTimeout);
                        submitBillingInlineForm(scopedForm instanceof HTMLFormElement ? scopedForm : facturacionBillingInlineForm);
                    });
                });

                syncHiddenDocumentType();
                syncComplementVisibility();
                syncBillingEmailMode(false);
                const controlTributarioAdjustedOnLoad = syncControlTributarioRule();
                syncEmitFormsWithBillingState();
                if (controlTributarioAdjustedOnLoad) {
                    window.clearTimeout(billingAutosaveTimeout);
                    submitBillingInlineForm();
                }
            }

                if (facturacionActionConfirmModal && facturacionActionConfirmCancel && facturacionActionConfirmAccept) {
                facturacionActionConfirmCancel.addEventListener('click', closeFacturacionActionConfirm);

                facturacionActionConfirmAccept.addEventListener('click', async function () {
                    if (!pendingConfirmForm) {
                        closeFacturacionActionConfirm();
                        return;
                    }

                    const form = pendingConfirmForm;
                    pendingConfirmForm = null;
                    facturacionActionConfirmModal.classList.remove('is-open');
                    facturacionActionConfirmModal.setAttribute('aria-hidden', 'true');
                    setFacturacionSubmittingState(form, true);
                    syncEmitFormsWithBillingState(form);

                    if (form.dataset.requiresBillingSync === 'true') {
                        const scopedBillingForm = resolveScopedBillingForm(form);
                        const syncOk = await submitBillingInlineForm(scopedBillingForm instanceof HTMLFormElement ? scopedBillingForm : facturacionBillingInlineForm);
                        if (!syncOk) {
                            setFacturacionSubmittingState(null, false);
                            return;
                        }
                    }

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

                if (event.key === 'Escape' && facturacionQrViewer && facturacionQrViewer.classList.contains('is-open')) {
                    closeFacturacionQrViewer();
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


@endonce






