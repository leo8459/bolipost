@extends('adminlte::page')

@section('title', 'Facturacion por servicio')

@section('content_header')
@stop

@section('content')
    @php
        $cajaEstado = strtoupper(trim((string) data_get($cajaContext ?? [], 'estado', 'SIN_APERTURA')));
        $isCajaAbierta = in_array($cajaEstado, ['ABIERTA', 'ABIERTO'], true);
        $cajaMensaje = trim((string) data_get($cajaContext ?? [], 'mensaje', ''));
        $activeDraftItems = collect($activeDraft?->items ?? []);
        $pendingConceptos = collect($pendingConceptos ?? [])->values();
        $pendingConceptosTotal = round((float) $pendingConceptos->sum(fn ($item) => (float) ($item['total'] ?? 0)), 2);
    @endphp

    @if(is_array($result))
        <div class="alert alert-{{ $result['type'] === 'success' ? 'success' : ($result['type'] === 'warning' ? 'warning' : 'danger') }} d-none" id="facturacionServicioResultAlert">
            <strong>{{ $result['title'] ?? 'Resultado' }}</strong>
            <div>{{ $result['message'] ?? '' }}</div>
            @if(!empty($result['detail']))
                <div class="small mt-1">{{ $result['detail'] }}</div>
            @endif
            @if(!empty($result['pdf_url']))
                <div class="mt-2">
                    <a href="{{ $result['pdf_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-file-pdf mr-1"></i> PDF original
                    </a>
                </div>
            @endif
        </div>
    @endif

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <strong>Elegir empresa</strong>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="empresa_selector" class="font-weight-bold">Empresa registrada</label>
                        <select id="empresa_selector" class="form-control" {{ (!$isCajaAbierta || $hasBlockingDraft) ? 'disabled' : '' }}>
                            <option value="">Selecciona una empresa</option>
                            @foreach($empresas as $empresa)
                                <option
                                    value="{{ $empresa->id }}"
                                    data-codigo-cliente="{{ (string) ($empresa->codigo_cliente ?? '') }}"
                                    data-tipo-documento="{{ (string) ($empresa->cliente_tipo_documento ?? '') }}"
                                    data-numero-documento="{{ (string) ($empresa->cliente_numero_documento ?? '') }}"
                                    data-complemento="{{ (string) ($empresa->cliente_complemento ?? '') }}"
                                    data-razon-social="{{ (string) ($empresa->cliente_razon_social ?? $empresa->nombre ?? '') }}"
                                    data-correo="{{ (string) ($empresa->cliente_email ?? '') }}"
                                >
                                    {{ $empresa->codigo_cliente }} | {{ $empresa->nombre }} @if((string) ($empresa->sigla ?? '') !== '') | {{ $empresa->sigla }} @endif
                                </option>
                            @endforeach
                        </select>
                        @error('empresa_id')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        <div class="small text-muted mt-2">
                            Al elegir una empresa, se recupera su codigo cliente para emitir como cuenta por cobrar.
                        </div>
                    </div>

                    <div class="border rounded p-3 bg-light">
                        <div class="font-weight-bold mb-2">Datos recuperados</div>
                        <div class="small text-muted mb-1">Codigo cliente</div>
                        <div id="empresaPreviewCodigo">-</div>
                        <div class="small text-muted mt-3 mb-1">Modalidad</div>
                        <div>Cuenta por cobrar</div>
                    </div>

                    @if(!$isCajaAbierta)
                        <div class="alert alert-warning mt-3 mb-3">
                            Primero debes abrir caja para poder facturar.
                        </div>
                        <form method="POST" action="{{ route('facturacion.cart.caja.abrir') }}">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-lg btn-block py-3 font-weight-bold">
                                <i class="fas fa-lock-open mr-2"></i> Abrir caja
                            </button>
                        </form>
                    @elseif($hasBlockingDraft)
                        <div class="alert alert-warning mt-3 mb-0">
                            Tienes {{ $activeDraftItems->count() }} item(s) pendientes en la facturacion principal.
                        </div>
                    @elseif($cajaMensaje !== '')
                        <div class="small text-muted mt-3 mb-0">
                            {{ $cajaMensaje }}
                        </div>
                    @endif

                    @if($isCajaAbierta)
                        <form method="POST" action="{{ route('facturacion.cart.caja.cerrar') }}" class="mt-3">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-lg btn-block py-3 font-weight-bold">
                                <i class="fas fa-lock mr-2"></i> Cerrar caja
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <strong>Nueva facturacion</strong>
                </div>
                <div class="card-body">
                    @if(!$isCajaAbierta)
                        <div class="alert alert-warning mb-3">
                            Debes abrir caja antes de facturar.
                        </div>
                        <form method="POST" action="{{ route('facturacion.cart.caja.abrir') }}" class="mb-4">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-lg px-5 py-3 font-weight-bold">
                                <i class="fas fa-lock-open mr-2"></i> Abrir caja
                            </button>
                        </form>
                    @endif

                    @if($hasBlockingDraft)
                        <div class="alert alert-warning">
                            Tienes una venta pendiente en la facturacion principal. Para continuar, primero debes terminarla o limpiarla.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('facturacion-servicio.store') }}">
                        @csrf
                        <input type="hidden" id="empresa_id_hidden" name="empresa_id" value="{{ old('empresa_id') }}">
                        <input type="hidden" id="empresa_codigo_cliente_hidden" name="empresa_codigo_cliente" value="">
                        <input type="hidden" id="empresa_nombre_hidden" name="empresa_nombre" value="">
                        <input type="hidden" id="empresa_sigla_hidden" name="empresa_sigla" value="">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="concepto_facturacion_id" class="font-weight-bold">Servicio</label>
                                <select id="concepto_facturacion_id" name="concepto_facturacion_id" class="form-control @error('concepto_facturacion_id') is-invalid @enderror" {{ (!$isCajaAbierta || $hasBlockingDraft) ? 'disabled' : '' }}>
                                    <option value="">Selecciona un servicio facturable</option>
                                    @foreach($conceptos as $concepto)
                                        <option
                                            value="{{ $concepto->id }}"
                                            data-precio="{{ number_format((float) $concepto->precio_base, 2, '.', '') }}"
                                            data-codigo="{{ (string) ($concepto->codigo ?? '') }}"
                                            data-nombre="{{ (string) ($concepto->nombre ?? '') }}"
                                            data-descripcion="{{ (string) ($concepto->descripcion ?? $concepto->nombre ?? '') }}"
                                            {{ ((string) old('concepto_facturacion_id') === (string) $concepto->id || (old('concepto_facturacion_id') === null && $conceptos->count() === 1)) ? 'selected' : '' }}
                                        >
                                            {{ $concepto->nombre }} | {{ $concepto->codigo }} | Bs {{ number_format((float) $concepto->precio_base, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('concepto_facturacion_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="cantidad" class="font-weight-bold">Cantidad</label>
                                <input type="number" min="1" max="999" id="cantidad" name="cantidad" value="{{ old('cantidad', 1) }}" class="form-control @error('cantidad') is-invalid @enderror" {{ (!$isCajaAbierta || $hasBlockingDraft) ? 'disabled' : '' }}>
                                @error('cantidad')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 mb-3">
                                <label for="concepto_descripcion" class="font-weight-bold">Descripcion</label>
                                <textarea id="concepto_descripcion" class="form-control" rows="2" {{ (!$isCajaAbierta || $hasBlockingDraft) ? 'disabled' : '' }}>{{ old('concepto_descripcion') }}</textarea>
                                <div class="small text-muted mt-1">Este texto saldra en la factura y puedes ajustarlo antes de añadir el servicio.</div>
                            </div>


                            <div class="col-12 mb-3">
                                <button type="button" id="facturacionServicioAdd" class="btn btn-outline-primary" {{ (!$isCajaAbierta || $hasBlockingDraft) ? 'disabled' : '' }}>
                                    <i class="fas fa-plus mr-1"></i> Añadir
                                </button>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="tipo_documento" class="font-weight-bold">Tipo documento</label>
                                <select id="tipo_documento" name="tipo_documento" class="form-control @error('tipo_documento') is-invalid @enderror" {{ (!$isCajaAbierta || $hasBlockingDraft) ? 'disabled' : '' }}>
                                    <option value="">Selecciona</option>
                                    @foreach($billingDocumentTypes as $code => $label)
                                        <option value="{{ $code }}" {{ (string) old('tipo_documento', 'CI') === (string) $code ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('tipo_documento')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="numero_documento" class="font-weight-bold">Numero documento</label>
                                <input type="text" id="numero_documento" name="numero_documento" value="{{ old('numero_documento') }}" class="form-control @error('numero_documento') is-invalid @enderror" {{ (!$isCajaAbierta || $hasBlockingDraft) ? 'disabled' : '' }}>
                                @error('numero_documento')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="complemento_documento" class="font-weight-bold">Complemento</label>
                                <input type="text" id="complemento_documento" name="complemento_documento" value="{{ old('complemento_documento') }}" class="form-control @error('complemento_documento') is-invalid @enderror" {{ (!$isCajaAbierta || $hasBlockingDraft) ? 'disabled' : '' }}>
                                @error('complemento_documento')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-8 mb-3">
                                <label for="razon_social" class="font-weight-bold">Razon social</label>
                                <input type="text" id="razon_social" name="razon_social" value="{{ old('razon_social') }}" class="form-control @error('razon_social') is-invalid @enderror" {{ (!$isCajaAbierta || $hasBlockingDraft) ? 'disabled' : '' }}>
                                @error('razon_social')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="correo_facturacion" class="font-weight-bold">Correo</label>
                                <input type="email" id="correo_facturacion" name="correo_facturacion" value="{{ old('correo_facturacion', 'safe@correos.gob.bo') }}" class="form-control @error('correo_facturacion') is-invalid @enderror" {{ (!$isCajaAbierta || $hasBlockingDraft) ? 'disabled' : '' }}>
                                @error('correo_facturacion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="card border mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <strong>Servicios añadidos</strong>
                                <span class="text-muted small" id="facturacionServicioItemsCount">{{ $pendingConceptos->count() }} item(s)</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Servicio</th>
                                                <th class="text-center">Cantidad</th>
                                                <th>Descripcion</th>
                                                <th class="text-right">Precio</th>
                                                <th class="text-right">Total</th>
                                                <th class="text-center">Accion</th>
                                            </tr>
                                        </thead>
                                        <tbody id="facturacionServicioItemsBody">
                                            @forelse($pendingConceptos as $index => $item)
                                                <tr data-line-index="{{ $index }}">
                                                    <td>
                                                        <strong>{{ $item['nombre'] }}</strong>
                                                        @if(($item['codigo'] ?? '') !== '')
                                                            <div class="small text-muted" data-base-code="{{ $item['codigo'] }}">{{ $item['codigo'] }}</div>
                                                        @endif
                                                        <input type="hidden" name="conceptos[{{ $index }}][concepto_facturacion_id]" value="{{ $item['concepto_facturacion_id'] }}">
                                                        <input type="hidden" name="conceptos[{{ $index }}][cantidad]" value="{{ $item['cantidad'] }}">
                                                        <input type="hidden" name="conceptos[{{ $index }}][codigo]" value="{{ $item['codigo'] }}">
                                                        <input type="hidden" name="conceptos[{{ $index }}][descripcion]" value="{{ $item['descripcion'] ?? '' }}">
                                                        <input type="hidden" name="conceptos[{{ $index }}][precio]" value="{{ number_format((float) $item['precio_base'], 2, '.', '') }}">
                                                    </td>
                                                    <td class="text-center">{{ $item['cantidad'] }}</td>
                                                    <td>
                                                        <textarea class="form-control form-control-sm" rows="2" data-line-description {{ (!$isCajaAbierta || $hasBlockingDraft) ? 'disabled' : '' }}>{{ $item['descripcion'] ?? '' }}</textarea>
                                                    </td>
                                                    <td class="text-right">
                                                        <input type="number" min="0" max="999999.99" step="0.01" class="form-control form-control-sm text-right" value="{{ number_format((float) $item['precio_base'], 2, '.', '') }}" data-line-price {{ (!$isCajaAbierta || $hasBlockingDraft) ? 'disabled' : '' }}>
                                                    </td>
                                                    <td class="text-right">Bs {{ number_format((float) $item['total'], 2) }}</td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-xs btn-outline-danger" data-remove-line="{{ $index }}">
                                                            Quitar
                                                        </button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr id="facturacionServicioEmptyRow">
                                                    <td colspan="6" class="text-center text-muted py-3">Aun no añadiste servicios.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer d-flex justify-content-between align-items-center">
                                <span class="text-muted small">Total acumulado</span>
                                <strong id="facturacionServicioItemsTotal">Bs {{ number_format($pendingConceptosTotal, 2) }}</strong>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center border rounded p-3 bg-light">
                            <div>
                                <div class="text-muted small">Vista rapida</div>
                                <div class="font-weight-bold" id="facturacionServicioPreview">
                                    @if($pendingConceptos->isNotEmpty())
                                        {{ $pendingConceptos->count() }} servicio(s) listos para emitir | Bs {{ number_format($pendingConceptosTotal, 2) }}
                                    @else
                                        Selecciona un servicio
                                    @endif
                                </div>
                            </div>
                            <button type="button" id="facturacionServicioSubmitTrigger" class="btn btn-primary mt-2 mt-md-0" {{ (!$isCajaAbierta || $hasBlockingDraft) ? 'disabled' : '' }}>
                                <i class="fas fa-file-invoice-dollar mr-1"></i> Emitir factura
                            </button>
                            <button type="submit" id="facturacionServicioSubmitReal" class="d-none" aria-hidden="true" tabindex="-1"></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="facturacionServicioConfirmModal" tabindex="-1" role="dialog" aria-labelledby="facturacionServicioConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="facturacionServicioConfirmTitle">Confirmar facturacion</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Se enviara esta factura para su emision.</p>
                    <div class="small text-muted">
                        Revisa los servicios, el cliente y el total antes de continuar.
                    </div>
                    <div class="mt-3 p-3 border rounded bg-light">
                        <div><strong>Resumen:</strong> <span id="facturacionServicioConfirmPreview">Sin datos</span></div>
                        <div class="mt-1"><strong>Total:</strong> <span id="facturacionServicioConfirmTotal">Bs 0.00</span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" id="facturacionServicioConfirmAccept" class="btn btn-primary">
                        Si, emitir factura
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if(is_array($result))
        <div class="modal fade" id="facturacionServicioResultModal" tabindex="-1" role="dialog" aria-labelledby="facturacionServicioResultTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="facturacionServicioResultTitle">{{ $result['title'] ?? 'Resultado' }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-{{ $result['type'] === 'success' ? 'success' : ($result['type'] === 'warning' ? 'warning' : 'danger') }} mb-3">
                            {{ $result['message'] ?? '' }}
                        </div>
                        @if(!empty($result['detail']))
                            <div class="small text-muted">{{ $result['detail'] }}</div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        @if(!empty($result['pdf_url']))
                            <a href="{{ $result['pdf_url'] }}" target="_blank" rel="noopener" class="btn btn-outline-primary">
                                <i class="fas fa-file-pdf mr-1"></i> PDF original
                            </a>
                        @endif
                        <button type="button" class="btn btn-primary" data-dismiss="modal">Entendido</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@stop

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const empresaSelector = document.getElementById('empresa_selector');
            const empresaPreviewCodigo = document.getElementById('empresaPreviewCodigo');
            const empresaIdHidden = document.getElementById('empresa_id_hidden');
            const empresaCodigoClienteHidden = document.getElementById('empresa_codigo_cliente_hidden');
            const empresaNombreHidden = document.getElementById('empresa_nombre_hidden');
            const empresaSiglaHidden = document.getElementById('empresa_sigla_hidden');
            const select = document.getElementById('concepto_facturacion_id');
            const quantity = document.getElementById('cantidad');
            const descriptionInput = document.getElementById('concepto_descripcion');
            const preview = document.getElementById('facturacionServicioPreview');
            const addButton = document.getElementById('facturacionServicioAdd');
            const itemsBody = document.getElementById('facturacionServicioItemsBody');
            const itemsCount = document.getElementById('facturacionServicioItemsCount');
            const itemsTotal = document.getElementById('facturacionServicioItemsTotal');
            const submitTrigger = document.getElementById('facturacionServicioSubmitTrigger');
            const submitReal = document.getElementById('facturacionServicioSubmitReal');
            const confirmPreview = document.getElementById('facturacionServicioConfirmPreview');
            const confirmTotal = document.getElementById('facturacionServicioConfirmTotal');
            const confirmAccept = document.getElementById('facturacionServicioConfirmAccept');
            const confirmModal = document.getElementById('facturacionServicioConfirmModal');
            const resultModal = document.getElementById('facturacionServicioResultModal');

            if (!select || !quantity || !descriptionInput || !preview || !addButton || !itemsBody || !itemsCount || !itemsTotal) {
                return;
            }

            const syncEmpresaPreviewFromFields = () => {
                if (empresaPreviewCodigo instanceof HTMLElement) {
                    const selected = empresaSelector instanceof HTMLSelectElement
                        ? empresaSelector.options[empresaSelector.selectedIndex]
                        : null;
                    empresaPreviewCodigo.textContent = String(selected?.dataset?.codigoCliente || '').trim() || '-';
                }

                if (empresaIdHidden instanceof HTMLInputElement) {
                    empresaIdHidden.value = empresaSelector instanceof HTMLSelectElement
                        ? String(empresaSelector.value || '').trim()
                        : '';
                }

                if (empresaCodigoClienteHidden instanceof HTMLInputElement) {
                    const selected = empresaSelector instanceof HTMLSelectElement
                        ? empresaSelector.options[empresaSelector.selectedIndex]
                        : null;
                    empresaCodigoClienteHidden.value = String(selected?.dataset?.codigoCliente || '').trim();
                }

                if (empresaNombreHidden instanceof HTMLInputElement) {
                    const selected = empresaSelector instanceof HTMLSelectElement
                        ? empresaSelector.options[empresaSelector.selectedIndex]
                        : null;
                    const optionText = String(selected?.text || '');
                    const parts = optionText.split('|').map((part) => part.trim()).filter(Boolean);
                    empresaNombreHidden.value = parts.length >= 2 ? parts[1] : '';
                }

                if (empresaSiglaHidden instanceof HTMLInputElement) {
                    const selected = empresaSelector instanceof HTMLSelectElement
                        ? empresaSelector.options[empresaSelector.selectedIndex]
                        : null;
                    const optionText = String(selected?.text || '');
                    const parts = optionText.split('|').map((part) => part.trim()).filter(Boolean);
                    empresaSiglaHidden.value = parts.length >= 3 ? parts[2] : '';
                }
            };

            const applyEmpresaSelection = () => {
                if (!(empresaSelector instanceof HTMLSelectElement)) {
                    syncEmpresaPreviewFromFields();
                    return;
                }

                const selected = empresaSelector.options[empresaSelector.selectedIndex];
                if (!selected || !selected.value) {
                    syncEmpresaPreviewFromFields();
                    return;
                }

                syncEmpresaPreviewFromFields();
            };

            const formatMoney = (value) => `Bs ${Number(value || 0).toFixed(2)}`;
            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
            const normalizePrice = (value) => {
                const numeric = Number.parseFloat(String(value ?? '0').replace(',', '.'));
                if (!Number.isFinite(numeric) || numeric < 0) {
                    return 0;
                }

                return Number(numeric.toFixed(2));
            };
            const getCurrentTotal = () => Array.from(itemsBody.querySelectorAll('tr[data-line-index]')).reduce((carry, row) => {
                const totalCell = row.children[4];
                return carry + Number(String(totalCell?.textContent || '0').replace(/[^\d.-]/g, ''));
            }, 0);
            const getSelectedBaseDescription = () => {
                const selected = select.options[select.selectedIndex];
                return String(selected?.dataset?.descripcion || '').trim();
            };
            const canSubmitFacturacion = () => {
                const hasEmpresa = empresaSelector instanceof HTMLSelectElement && String(empresaSelector.value || '').trim() !== '';
                const totalLines = itemsBody.querySelectorAll('tr[data-line-index]').length;
                const total = getCurrentTotal();

                return hasEmpresa && totalLines > 0 && total > 0;
            };
            const syncSubmitAvailability = () => {
                if (!(submitTrigger instanceof HTMLButtonElement)) {
                    return;
                }

                submitTrigger.disabled = !canSubmitFacturacion();
            };
            const buildAlternateCode = (baseCode, position) => {
                const trimmedBase = String(baseCode || '').trim();
                if (trimmedBase === '') {
                    return '';
                }

                if (position <= 1) {
                    return trimmedBase;
                }

                return `${trimmedBase}.${Math.max(1, position - 1)}`;
            };

            const readLines = () => Array.from(itemsBody.querySelectorAll('tr[data-line-index]')).map((row) => {
                const idInput = row.querySelector('input[name$="[concepto_facturacion_id]"]');
                const qtyInput = row.querySelector('input[name$="[cantidad]"]');
                const codeInput = row.querySelector('input[name$="[codigo]"]');
                const priceInput = row.querySelector('input[name$="[precio]"]');
                const descriptionInput = row.querySelector('input[name$="[descripcion]"]');
                const totalCell = row.children[4];

                return {
                    row,
                    conceptoId: Number(idInput?.value || 0),
                    cantidad: Number(qtyInput?.value || 0),
                    codigo: String(codeInput?.value || ''),
                    descripcion: String(descriptionInput?.value || '').trim(),
                    precio: priceInput ? normalizePrice(priceInput.value) : 0,
                    total: Number(String(totalCell?.textContent || '0').replace(/[^\d.-]/g, '')),
                };
            });

            const refreshVisibleCodes = () => {
                const groupedRows = new Map();

                Array.from(itemsBody.querySelectorAll('tr[data-line-index]')).forEach((row) => {
                    const idInput = row.querySelector('input[name$="[concepto_facturacion_id]"]');
                    const codeElement = row.querySelector('.small.text-muted');
                    if (!(idInput instanceof HTMLInputElement) || !(codeElement instanceof HTMLElement)) {
                        return;
                    }

                    const conceptId = String(Number(idInput.value || 0));
                    if (!groupedRows.has(conceptId)) {
                        groupedRows.set(conceptId, []);
                    }

                    groupedRows.get(conceptId).push(row);
                });

                groupedRows.forEach((rows) => {
                    rows.forEach((row, index) => {
                        const codeElement = row.querySelector('.small.text-muted');
                        const codeInput = row.querySelector('input[name$="[codigo]"]');
                        if (!(codeElement instanceof HTMLElement)) {
                            return;
                        }

                        const baseCode = String(codeElement.dataset.baseCode || codeElement.textContent || '').trim();
                        codeElement.dataset.baseCode = baseCode;
                        const visibleCode = buildAlternateCode(baseCode, index + 1);
                        codeElement.textContent = visibleCode;
                        if (codeInput instanceof HTMLInputElement) {
                            codeInput.value = visibleCode;
                        }
                    });
                });
            };

            const reindexLines = () => {
                const rows = Array.from(itemsBody.querySelectorAll('tr[data-line-index]'));
                rows.forEach((row, index) => {
                    row.dataset.lineIndex = String(index);
                    const idInput = row.querySelector('input[name$="[concepto_facturacion_id]"]');
                    const qtyInput = row.querySelector('input[name$="[cantidad]"]');
                    const codeInput = row.querySelector('input[name$="[codigo]"]');
                    const descriptionInput = row.querySelector('input[name$="[descripcion]"]');
                    const priceInput = row.querySelector('input[name$="[precio]"]');
                    const descriptionEditor = row.querySelector('[data-line-description]');
                    const priceEditor = row.querySelector('[data-line-price]');
                    const removeButton = row.querySelector('[data-remove-line]');

                    if (idInput) {
                        idInput.name = `conceptos[${index}][concepto_facturacion_id]`;
                    }
                    if (qtyInput) {
                        qtyInput.name = `conceptos[${index}][cantidad]`;
                    }
                    if (codeInput) {
                        codeInput.name = `conceptos[${index}][codigo]`;
                    }
                    if (descriptionInput) {
                        descriptionInput.name = `conceptos[${index}][descripcion]`;
                    }
                    if (priceInput) {
                        priceInput.name = `conceptos[${index}][precio]`;
                    }
                    if (descriptionEditor instanceof HTMLTextAreaElement && descriptionInput instanceof HTMLInputElement) {
                        descriptionEditor.value = String(descriptionInput.value || '').trim();
                    }
                    if (priceEditor instanceof HTMLInputElement && priceInput instanceof HTMLInputElement) {
                        priceEditor.value = normalizePrice(priceInput.value).toFixed(2);
                    }
                    if (removeButton) {
                        removeButton.dataset.removeLine = String(index);
                    }
                });

                const totalLines = rows.length;
                const grandTotal = getCurrentTotal();

                itemsCount.textContent = `${totalLines} item(s)`;
                itemsTotal.textContent = formatMoney(grandTotal);
                preview.textContent = totalLines > 0
                    ? `${totalLines} servicio(s) listos para emitir | ${formatMoney(grandTotal)}`
                    : 'Selecciona un servicio';

                const emptyRow = document.getElementById('facturacionServicioEmptyRow');
                if (emptyRow) {
                    emptyRow.hidden = totalLines > 0;
                }

                refreshVisibleCodes();
                syncSubmitAvailability();
            };

            const syncPreview = () => {
                const selected = select.options[select.selectedIndex];
                const quantityValue = Math.max(1, parseInt(quantity.value || '1', 10) || 1);
                const priceValue = normalizePrice(selected?.dataset?.precio || '0');
                const label = String(selected?.text || '').trim();
                const descriptionValue = String(descriptionInput.value || '').trim();
                const totalLines = itemsBody.querySelectorAll('tr[data-line-index]').length;

                if (totalLines > 0) {
                    reindexLines();
                    return;
                }

                if (!selected || !selected.value) {
                    preview.textContent = 'Selecciona un servicio';
                    return;
                }

                preview.textContent = `${label} | Cantidad: ${quantityValue} | Precio: ${formatMoney(priceValue)} | Total: ${formatMoney(quantityValue * priceValue)}${descriptionValue !== '' ? ` | Descripcion: ${descriptionValue}` : ''}`;
            };

            if (empresaSelector instanceof HTMLSelectElement) {
                empresaSelector.addEventListener('change', function () {
                    applyEmpresaSelection();
                    syncSubmitAvailability();
                });
            }

            syncEmpresaPreviewFromFields();

            if (descriptionInput instanceof HTMLTextAreaElement && String(descriptionInput.value || '').trim() === '') {
                descriptionInput.value = getSelectedBaseDescription();
            }

            const addCurrentSelection = () => {
                const selected = select.options[select.selectedIndex];
                const conceptoId = Number(selected?.value || 0);
                const cantidadValue = Math.max(1, parseInt(quantity.value || '1', 10) || 1);
                const priceValue = normalizePrice(selected?.dataset?.precio || '0');
                const codigoBase = String(selected?.dataset?.codigo || selected?.text.split('|')[1] || '').trim();
                const nombreBase = String(selected?.dataset?.nombre || selected?.text.split('|')[0] || 'Servicio').trim();
                const descripcionValue = String(descriptionInput.value || '').trim() || getSelectedBaseDescription();

                if (!conceptoId) {
                    select.focus();
                    return;
                }

                const existingLine = readLines().find((line) =>
                    line.conceptoId === conceptoId
                    && normalizePrice(line.precio) === priceValue
                    && String(line.descripcion || '').trim() === descripcionValue
                );
                if (existingLine) {
                    const qtyInput = existingLine.row.querySelector('input[name$="[cantidad]"]');
                    const qtyCell = existingLine.row.children[1];
                    const totalCell = existingLine.row.children[4];
                    const nextQty = existingLine.cantidad + cantidadValue;
                    const nextTotal = priceValue * nextQty;

                    if (qtyInput) {
                        qtyInput.value = String(nextQty);
                    }
                    if (qtyCell) {
                        qtyCell.textContent = String(nextQty);
                    }
                    if (totalCell) {
                        totalCell.textContent = formatMoney(nextTotal);
                    }

                    reindexLines();
                    return;
                }

                const descripcionEscapada = escapeHtml(descripcionValue);
                const nombreEscapado = escapeHtml(nombreBase);
                const codigoEscapado = escapeHtml(codigoBase);
                const lineIndex = itemsBody.querySelectorAll('tr[data-line-index]').length;
                const total = priceValue * cantidadValue;
                const row = document.createElement('tr');
                row.dataset.lineIndex = String(lineIndex);
                row.innerHTML = `
                    <td>
                        <strong>${nombreEscapado}</strong>
                        <div class="small text-muted" data-base-code="${codigoEscapado}">${codigoEscapado}</div>
                        <input type="hidden" name="conceptos[${lineIndex}][concepto_facturacion_id]" value="${conceptoId}">
                        <input type="hidden" name="conceptos[${lineIndex}][cantidad]" value="${cantidadValue}">
                        <input type="hidden" name="conceptos[${lineIndex}][codigo]" value="${codigoEscapado}">
                        <input type="hidden" name="conceptos[${lineIndex}][descripcion]" value="${descripcionEscapada}">
                        <input type="hidden" name="conceptos[${lineIndex}][precio]" value="${priceValue.toFixed(2)}">
                    </td>
                    <td class="text-center">${cantidadValue}</td>
                    <td>
                        <textarea class="form-control form-control-sm" rows="2" data-line-description>${descripcionEscapada}</textarea>
                    </td>
                    <td class="text-right">
                        <input type="number" min="0" max="999999.99" step="0.01" class="form-control form-control-sm text-right" value="${priceValue.toFixed(2)}" data-line-price>
                    </td>
                    <td class="text-right">${formatMoney(total)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-xs btn-outline-danger" data-remove-line="${lineIndex}">
                            Quitar
                        </button>
                    </td>
                `;
                itemsBody.appendChild(row);
                reindexLines();
            };

            const mergeDuplicateLines = () => {
                const seen = new Map();

                Array.from(itemsBody.querySelectorAll('tr[data-line-index]')).forEach((row) => {
                    const idInput = row.querySelector('input[name$="[concepto_facturacion_id]"]');
                    const qtyInput = row.querySelector('input[name$="[cantidad]"]');
                    const descriptionInput = row.querySelector('input[name$="[descripcion]"]');
                    const priceInput = row.querySelector('input[name$="[precio]"]');

                    if (!(idInput instanceof HTMLInputElement) || !(qtyInput instanceof HTMLInputElement) || !(priceInput instanceof HTMLInputElement)) {
                        return;
                    }

                    const key = `${Number(idInput.value || 0)}|${normalizePrice(priceInput.value).toFixed(2)}|${String(descriptionInput?.value || '').trim()}`;
                    if (!seen.has(key)) {
                        seen.set(key, row);
                        return;
                    }

                    const targetRow = seen.get(key);
                    const targetQtyInput = targetRow?.querySelector('input[name$="[cantidad]"]');
                    const targetQtyCell = targetRow?.children[1];
                    const targetTotalCell = targetRow?.children[4];
                    const mergedQty = Number(targetQtyInput?.value || 0) + Number(qtyInput.value || 0);
                    const mergedPrice = normalizePrice(priceInput.value);

                    if (targetQtyInput instanceof HTMLInputElement) {
                        targetQtyInput.value = String(mergedQty);
                    }
                    if (targetQtyCell) {
                        targetQtyCell.textContent = String(mergedQty);
                    }
                    if (targetTotalCell) {
                        targetTotalCell.textContent = formatMoney(mergedQty * mergedPrice);
                    }

                    row.remove();
                });

                reindexLines();
            };

            const syncRowPrice = (row) => {
                if (!(row instanceof HTMLTableRowElement)) {
                    return;
                }

                const titleElement = row.querySelector('td strong');
                const codeElement = row.querySelector('td .small.text-muted');
                const idInput = row.querySelector('input[name$="[concepto_facturacion_id]"]');
                const qtyInput = row.querySelector('input[name$="[cantidad]"]');
                const descriptionInput = row.querySelector('input[name$="[descripcion]"]');
                const descriptionEditor = row.querySelector('[data-line-description]');
                const priceInput = row.querySelector('input[name$="[precio]"]');
                const priceEditor = row.querySelector('[data-line-price]');
                const qtyCell = row.children[1];
                const totalCell = row.children[4];

                if (!(idInput instanceof HTMLInputElement) || !(qtyInput instanceof HTMLInputElement) || !(priceInput instanceof HTMLInputElement) || !(priceEditor instanceof HTMLInputElement)) {
                    return;
                }

                const qty = Math.max(1, parseInt(qtyInput.value || '1', 10) || 1);
                const originalPrice = normalizePrice(priceInput.value || '0');
                const normalizedPrice = normalizePrice(priceEditor.value || priceInput.value || '0');
                const currentDescription = descriptionEditor instanceof HTMLTextAreaElement
                    ? String(descriptionEditor.value || '').trim()
                    : String(descriptionInput?.value || '').trim();

                if (normalizedPrice === originalPrice) {
                    priceEditor.value = normalizedPrice.toFixed(2);
                    if (totalCell) {
                        totalCell.textContent = formatMoney(qty * normalizedPrice);
                    }
                    reindexLines();
                    return;
                }

                if (qty > 1) {
                    const remainingQty = qty - 1;

                    qtyInput.value = String(remainingQty);
                    if (qtyCell) {
                        qtyCell.textContent = String(remainingQty);
                    }
                    priceInput.value = originalPrice.toFixed(2);
                    priceEditor.value = originalPrice.toFixed(2);
                    if (totalCell) {
                        totalCell.textContent = formatMoney(remainingQty * originalPrice);
                    }

                    const lineIndex = itemsBody.querySelectorAll('tr[data-line-index]').length;
                    const nextTitle = escapeHtml(titleElement ? titleElement.textContent.trim() : 'Servicio');
                    const nextBaseCode = escapeHtml(codeElement ? String(codeElement.dataset.baseCode || codeElement.textContent || '').trim() : '');
                    const nextDescription = escapeHtml(currentDescription);
                    const newRow = document.createElement('tr');
                    newRow.dataset.lineIndex = String(lineIndex);
                    newRow.innerHTML = `
                        <td>
                            <strong>${nextTitle}</strong>
                            <div class="small text-muted" data-base-code="${nextBaseCode}">${nextBaseCode}</div>
                            <input type="hidden" name="conceptos[${lineIndex}][concepto_facturacion_id]" value="${Number(idInput.value || 0)}">
                            <input type="hidden" name="conceptos[${lineIndex}][cantidad]" value="1">
                            <input type="hidden" name="conceptos[${lineIndex}][codigo]" value="${nextBaseCode}">
                            <input type="hidden" name="conceptos[${lineIndex}][descripcion]" value="${nextDescription}">
                            <input type="hidden" name="conceptos[${lineIndex}][precio]" value="${normalizedPrice.toFixed(2)}">
                        </td>
                        <td class="text-center">1</td>
                        <td>
                            <textarea class="form-control form-control-sm" rows="2" data-line-description>${nextDescription}</textarea>
                        </td>
                        <td class="text-right">
                            <input type="number" min="0" max="999999.99" step="0.01" class="form-control form-control-sm text-right" value="${normalizedPrice.toFixed(2)}" data-line-price>
                        </td>
                        <td class="text-right">${formatMoney(normalizedPrice)}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-xs btn-outline-danger" data-remove-line="${lineIndex}">
                                Quitar
                            </button>
                        </td>
                    `;
                    itemsBody.appendChild(newRow);
                    mergeDuplicateLines();
                    return;
                }

                priceEditor.value = normalizedPrice.toFixed(2);
                priceInput.value = normalizedPrice.toFixed(2);

                if (totalCell) {
                    totalCell.textContent = formatMoney(qty * normalizedPrice);
                }

                mergeDuplicateLines();
            };

            const syncRowDescription = (row) => {
                if (!(row instanceof HTMLTableRowElement)) {
                    return;
                }

                const descriptionInput = row.querySelector('input[name$="[descripcion]"]');
                const descriptionEditor = row.querySelector('[data-line-description]');

                if (!(descriptionInput instanceof HTMLInputElement) || !(descriptionEditor instanceof HTMLTextAreaElement)) {
                    return;
                }

                descriptionInput.value = String(descriptionEditor.value || '').trim();
                mergeDuplicateLines();
            };

            select.addEventListener('change', syncPreview);
            quantity.addEventListener('input', syncPreview);
            descriptionInput.addEventListener('input', syncPreview);
            select.addEventListener('change', function () {
                if (!(descriptionInput instanceof HTMLTextAreaElement)) {
                    return;
                }

                descriptionInput.value = getSelectedBaseDescription();
                syncPreview();
            });
            addButton.addEventListener('click', addCurrentSelection);
            itemsBody.addEventListener('click', function (event) {
                const trigger = event.target;
                if (!(trigger instanceof HTMLElement)) {
                    return;
                }

                const removeIndex = trigger.dataset.removeLine;
                if (typeof removeIndex === 'undefined') {
                    return;
                }

                const row = trigger.closest('tr[data-line-index]');
                row?.remove();
                reindexLines();
            });
            itemsBody.addEventListener('change', function (event) {
                const trigger = event.target;
                if (!(trigger instanceof HTMLInputElement) || !trigger.matches('[data-line-price]')) {
                    return;
                }

                syncRowPrice(trigger.closest('tr[data-line-index]'));
            });
            itemsBody.addEventListener('input', function (event) {
                const trigger = event.target;
                if (!(trigger instanceof HTMLTextAreaElement) || !trigger.matches('[data-line-description]')) {
                    return;
                }

                syncRowDescription(trigger.closest('tr[data-line-index]'));
            });

            if (submitTrigger instanceof HTMLButtonElement && submitReal instanceof HTMLButtonElement && confirmAccept instanceof HTMLButtonElement && confirmPreview instanceof HTMLElement && confirmTotal instanceof HTMLElement && confirmModal instanceof HTMLElement) {
                submitTrigger.addEventListener('click', function () {
                    const totalLines = itemsBody.querySelectorAll('tr[data-line-index]').length;
                    const total = getCurrentTotal();
                    const hasEmpresa = empresaSelector instanceof HTMLSelectElement && String(empresaSelector.value || '').trim() !== '';

                    if (!hasEmpresa) {
                        empresaSelector?.focus();
                        return;
                    }

                    if (totalLines <= 0 || total <= 0) {
                        preview.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return;
                    }

                    confirmPreview.textContent = preview.textContent.trim() || 'Sin datos';
                    confirmTotal.textContent = itemsTotal.textContent.trim() || 'Bs 0.00';
                    if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                        window.jQuery(confirmModal).modal('show');
                    }
                });

                confirmAccept.addEventListener('click', function () {
                    if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                        window.jQuery(confirmModal).modal('hide');
                    }
                    submitReal.click();
                });
            }

            if (resultModal instanceof HTMLElement && window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                window.jQuery(resultModal).modal('show');
            }

            reindexLines();
            syncPreview();
            syncSubmitAvailability();
        });
    </script>
@stop
