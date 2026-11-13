@extends('adminlte::page')
@section('title', $deliveryOnly ? 'Entrega de paquetes | IPS' : (in_array($workflowAction ?? '', ['EMR', 'EMG', 'CUSTOMS_RETURN'], true) ? 'Recepción de paquetes | IPS' : 'IPS'))
@section('content')
@include('paquetes_ips.partials.offices')
@php($workbenchMode = $deliveryOnly ? 'delivery' : (in_array($workflowAction ?? '', ['EMR', 'EMG', 'CUSTOMS_RETURN'], true) ? 'reception' : ''))
<div class="ips-workspace" data-mode="{{ $workbenchMode }}" data-receipt-preferred-office="{{ $link?->ips_office_cd ?? '' }}" data-upu-destination="{{ auth()->user()?->ciudad ?? '' }}" data-combined-offices="{{ empty($combinedOffices) ? '' : implode('_', array_column($combinedOffices, 'office_cd')) }}" data-delivery-only="{{ $deliveryOnly ? '1' : '0' }}" data-courier-only="{{ $courierOnly ? '1' : '0' }}" data-workflow-action="{{ $workflowAction ?? '' }}" data-scan-enabled="{{ $readOnly ? '0' : '1' }}" data-preferred-receptacle="{{ $preferredReceptaclePid ?? '' }}" data-add-label="{{ ($workflowAction ?? null) ? 'Agregar al paso' : (($deliveryOnly ?? false) ? 'Agregar a entrega' : 'Agregar') }}" data-selection-add-url="{{ $selectionAddUrl }}" data-operate-url="{{ $operateUrl }}">
    @if($workbenchMode)
        @include('paquetes_ips.partials.workbench-heading')
    @elseif(!$warehouseView && ($workflowAction ?? null) !== 'EDN')
    <header class="ips-heading">
        <div>
            <span class="ips-eyebrow">GESTIÓN POSTAL INTERNACIONAL</span>
            <h1>{{ $pageTitle }}</h1>
            <p>{{ $pageDescription }}</p>
        </div>
        @if($link && $link->ips_office_name)
            <div class="ips-identity"><i class="fas fa-building"></i><span><strong>{{ $link->ips_office_name }}</strong><small>Oficina IPS asignada</small></span></div>
        @endif
    </header>
    @endif
    @if(($workflowAction ?? '') === 'EMG' || ($warehouseView === 'por-entregar' && $deliveryOnly))
        @include('paquetes_ips.partials.receipt-delivery-navigation')
    @endif
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<p id="ips-listing-status" class="ips-help" role="status" aria-live="polite" hidden></p>
    @if($missingPackageCode && in_array($workflowAction, ['EMR', 'EMG'], true) && !$readOnly)
        @php($destinationReceipt = $workflowAction === 'EMG')
        <section class="ips-panel mb-3">
            <div class="ips-panel-header"><h2><i class="fas fa-plus-circle mr-2"></i>{{ $destinationReceipt ? 'Registrar y recibir envío L en IPS' : 'Registrar envío recibido que aún no existe en IPS' }}</h2></div>
            <form id="ips-inbound-create" method="POST" action="{{ route($destinationReceipt ? 'internacional.destination.create' : 'internacional.inbound.create', \App\Support\IpsOfficeContext::parameters(request(), [])) }}" class="p-3">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $inboundCreateKey }}">
                <input type="hidden" name="destination_country" value="BO">
                @unless($destinationReceipt && !$inboundPending)<input type="hidden" name="physical_receipt_confirmed" value="1">@endunless
                @if($inboundPending)
                    @if($destinationReceipt && !empty($combinedOffices))<input type="hidden" name="office_cd" value="{{ $inboundPending['office_cd'] }}">@endif
                    <input type="hidden" name="codigo" value="{{ $missingPackageCode }}">
                    <div class="alert alert-info">La respuesta de la operacion original no se confirmo. Este boton consulta la misma solicitud con la misma clave; no crea un segundo paquete.</div>
                <p><strong>Código:</strong> {{ $missingPackageCode }}@unless($destinationReceipt) · <strong>Envase:</strong> {{ data_get($inboundPending, 'receptacle_pid') }}@endunless</p>
                    <button class="btn btn-primary" type="submit">Consultar operacion pendiente</button>
                @else
                <p class="alert alert-warning">{{ $destinationReceipt ? 'Este envío no existe en IPS. Completa los datos de la etiqueta para crearlo y registrar su llegada a esta oficina. Después podrás entregarlo desde Almacén internacional → Por entregar.' : 'Verifica primero que el código esté bien escaneado. Este formulario crea el paquete con la recepción de importación y lo vincula al envase abierto seleccionado.' }}</p>
                @if(!$destinationReceipt && empty($catalog['open_inbound_receptacles']))
                    <p class="alert alert-warning">Primero registra la llegada y apertura de la saca en esta oficina. No hay una saca abierta disponible para asociar el paquete.</p>
                @endif
                <div class="row">
                    @if($destinationReceipt && !empty($combinedOffices))
                        <div class="col-md-4 form-group"><label for="inbound-office">Oficina que recibe el paquete</label><select id="inbound-office" name="office_cd" class="form-control" required><option value="">Selecciona dónde llegó físicamente</option>@foreach($combinedOffices as $assignedOffice)<option value="{{ $assignedOffice['office_cd'] }}" @selected((string) old('office_cd') === (string) $assignedOffice['office_cd'])>{{ $assignedOffice['office_name'] }} · {{ $assignedOffice['office_fcd'] }}</option>@endforeach</select></div>
                    @endif
                    <div class="col-md-4 form-group"><label for="inbound-code">Código del paquete</label><input id="inbound-code" name="codigo" class="form-control" maxlength="35" value="{{ old('codigo', $missingPackageCode) }}" required autofocus></div>
                    <div class="col-md-4 form-group"><label for="inbound-weight">Peso registrado en IPS (kg)</label><input id="inbound-weight" name="weight_kg" class="form-control" type="number" min="0.001" max="999.999" step="0.001" value="{{ old('weight_kg') }}" required></div>
                    <div class="col-md-4 form-group"><label for="inbound-class">Clase postal</label><select id="inbound-class" name="mail_class" class="form-control" required><option value="">Seleccionar clase</option>@foreach(($catalog['mail_classes'] ?? []) as $class)<option value="{{ trim(data_get($class, 'MAIL_CLASS_CD')) }}" @selected(old('mail_class') === trim(data_get($class, 'MAIL_CLASS_CD')))>{{ data_get($class, 'MAIL_CLASS_NM') }}</option>@endforeach</select></div>
                    @unless($destinationReceipt)
                    <div class="col-md-4 form-group"><label for="inbound-condition">Condición física del paquete</label><select id="inbound-condition" name="condition_cd" class="form-control" required><option value="">Revisar y seleccionar</option>@foreach(($catalog['item_conditions'] ?? []) as $condition)<option value="{{ trim(data_get($condition, 'ITEM_CONDITION_CD')) }}" @selected((string) old('condition_cd') === trim(data_get($condition, 'ITEM_CONDITION_CD')))>{{ data_get($condition, 'ITEM_CONDITION_NM') }}</option>@endforeach</select></div>
                    @endunless
                    <div class="col-md-4 form-group"><label for="inbound-origin">País de origen</label><select id="inbound-origin" name="origin_country" class="form-control" required><option value="">Seleccionar país</option>@foreach(($catalog['countries'] ?? []) as $country)<option value="{{ trim(data_get($country, 'COUNTRY_CD')) }}" @selected(old('origin_country', $destinationReceipt ? substr($missingPackageCode, -2) : null) === trim(data_get($country, 'COUNTRY_CD')))>{{ data_get($country, 'COUNTRY_NM') }}</option>@endforeach</select></div>
                    @unless($destinationReceipt)
                    <div class="col-md-8 form-group"><label for="inbound-receptacle">Envase abierto que contiene el paquete</label><select id="inbound-receptacle" name="receptacle_pid" class="form-control" required><option value="">Seleccionar envase</option>@foreach(($catalog['open_inbound_receptacles'] ?? []) as $receptacle)<option value="{{ data_get($receptacle, 'RECPTCL_PID') }}" @selected((string) old('receptacle_pid', $preferredReceptaclePid) === (string) data_get($receptacle, 'RECPTCL_PID'))>{{ trim(data_get($receptacle, 'RECPTCL_FID')) }} · {{ data_get($receptacle, 'RECPTCL_MAILITMS_NO') !== null ? data_get($receptacle, 'RECPTCL_MAILITMS_NO').' declarados' : 'cantidad no declarada' }}</option>@endforeach</select></div>
                    @endunless
                </div>
                @foreach(['sender' => 'Remitente', 'recipient' => 'Destinatario'] as $party => $label)
                    <fieldset class="border rounded p-3 mb-3"><legend class="w-auto px-2 h6">{{ $label }}</legend><div class="row">
                        <div class="col-md-4 form-group"><label for="{{ $party }}-name">Nombre</label><input id="{{ $party }}-name" name="{{ $party }}[name]" class="form-control" maxlength="64" value="{{ old($party.'.name') }}" required></div>
                        <div class="col-md-4 form-group"><label for="{{ $party }}-address">Dirección</label><input id="{{ $party }}-address" name="{{ $party }}[address]" class="form-control" maxlength="105" value="{{ old($party.'.address') }}" required></div>
                        <div class="col-md-4 form-group"><label for="{{ $party }}-city">Ciudad</label><input id="{{ $party }}-city" name="{{ $party }}[city]" class="form-control" maxlength="32" value="{{ old($party.'.city') }}" required></div>
                        <div class="col-md-4 form-group"><label for="{{ $party }}-country">País</label><select id="{{ $party }}-country" name="{{ $party }}[country]" class="form-control" required><option value="">Seleccionar país</option>@foreach(($catalog['countries'] ?? []) as $country)<option value="{{ trim(data_get($country, 'COUNTRY_CD')) }}" @selected(old($party.'.country', $destinationReceipt && $party === 'recipient' ? 'BO' : null) === trim(data_get($country, 'COUNTRY_CD')))>{{ data_get($country, 'COUNTRY_NM') }}</option>@endforeach</select></div>
                        <div class="col-md-4 form-group"><label for="{{ $party }}-postcode">Código postal</label><input id="{{ $party }}-postcode" name="{{ $party }}[postcode]" class="form-control" maxlength="20" value="{{ old($party.'.postcode') }}"></div>
                        <div class="col-md-4 form-group"><label for="{{ $party }}-phone">Teléfono</label><input id="{{ $party }}-phone" name="{{ $party }}[phone]" class="form-control" maxlength="64" value="{{ old($party.'.phone') }}"></div>
                    </div></fieldset>
                @endforeach
                @if($destinationReceipt)
                    <label class="d-block mb-3"><input type="checkbox" name="physical_receipt_confirmed" value="1" required> Confirmo que este envío llegó físicamente a esta oficina.</label>
                @endif
                <button class="btn btn-primary" type="submit">{{ $destinationReceipt ? 'Crear y recibir envío L en IPS' : 'Crear paquete y registrar recepción' }}</button>
                @endif
            </form>
        </section>
    @endif
    @if($courierConfigurationMissing)
        <section class="ips-panel ips-setup-state" role="status">
            <div class="ips-panel-header"><h2><i class="fas fa-motorcycle mr-2"></i>Reparto sin configurar</h2></div>
            <div class="ips-tray-body">
                <p>{{ $courierConfigurationMessage }}</p>
                @can('admin-only-menu')
                    <a class="btn btn-primary" href="{{ route('users.ips-links.index') }}">Configurar vínculo IPS y cartero</a>
                @else
                    <p class="mb-0">Solicita a administración que vincule tu cuenta Bolipost con el cartero correspondiente.</p>
                @endcan
            </div>
        </section>
    @else
    <div class="ips-layout {{ $readOnly ? 'ips-layout--readonly' : '' }}">
        <main class="ips-main" id="ips-listing">
            @include('paquetes_ips.partials.listing')
        </main>
        @unless($readOnly)
        <aside class="ips-panel ips-tray" aria-label="{{ $workbenchMode === 'delivery' ? 'Bandeja de entrega' : ($workbenchMode === 'reception' ? 'Lote de recepción' : 'Bandeja de trabajo') }}">
            <div class="ips-panel-header"><h2><i class="fas fa-clipboard-list mr-2"></i><span id="ips-tray-title">{{ $courierOnly ? 'Mi reparto' : ($deliveryOnly ? 'Procesar entregas' : (($workflowAction ?? null) ? $operationLabel.' · por registrar' : 'Tu bandeja')) }}</span></h2><span id="ips-tray-count">{{ count($selection) }}/50 en bandeja</span></div>
            <div class="ips-tray-body">
                @if(!$deliveryOnly && !($workflowAction ?? null))
                    <p>{{ ($workflowAction ?? null) ? 'Agrega envíos habilitados para este paso. La bandeja se conserva mientras escaneas o consultas otros códigos.' : 'Agrega los resultados que quieras trabajar. La lista se conserva mientras buscas otros paquetes.' }}</p>
                @endif
                @if($deliveryOnly)
                    @if($courierOnly)<p>Registra la salida al iniciar el recorrido. Al finalizar, confirma la entrega o registra el intento fallido.</p>@endif
                    @php($deliveryActionGroups = $courierOnly
                        ? ['Reparto' => [['action' => 'EDG', 'label' => 'Registrar salida a reparto'], ['action' => 'EMH', 'label' => 'Registrar intento fallido']], 'Entrega final' => [['action' => 'EMI', 'label' => 'Confirmar entrega al destinatario']]]
                        : ['Entrega final' => [['action' => 'EMI', 'label' => 'Confirmar entrega al destinatario']]])
                    <section class="ips-delivery-actions" aria-label="Acciones disponibles según la etapa">
                        <h3 class="ips-action-heading">{{ $courierOnly ? 'Elige el siguiente paso' : 'Registrar entrega de paquetes recibidos' }}</h3>
                        <div id="ips-delivery-action-groups" class="ips-delivery-action-groups">
                            @foreach($deliveryActionGroups as $groupLabel => $groupActions)
                                <div class="ips-delivery-action-group" data-action-group>
                                    <h4>{{ $groupLabel }}</h4>
                                    <div class="ips-delivery-action-options">
                                        @foreach($groupActions as $actionDefinition)
                                            <button type="button" class="ips-delivery-action" data-action="{{ $actionDefinition['action'] }}" data-event-filter="{{ $actionDefinition['event'] ?? '' }}" aria-pressed="false">
                                                <span>{{ $actionDefinition['label'] }}</span>
                                                <small data-action-count>0 paquetes compatibles</small>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @else
                    @if(in_array($operationGroup ?? '', ['recepcion', 'ruta'], true))
                        <h3 class="ips-action-heading">Paquetes del lote</h3>
                    @else
                        <label for="ips-action">{{ ($workflowAction ?? null) ? 'Paso del recorrido' : 'Movimiento a registrar' }}</label>
                    @endif
                @endif
                <select id="ips-action" class="form-control" @if($deliveryOnly || in_array($operationGroup ?? '', ['recepcion', 'ruta'], true)) hidden aria-hidden="true" tabindex="-1" @endif>
                    @if($deliveryOnly)<option value="">Elige una acción</option>@endif
                    @foreach($actions as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                </select>
                @if(in_array($workflowAction ?? '', ['EDN', 'EIB', 'EMR'], true))
                    <div id="ips-batch-target-fields" class="ips-batch-fields mt-3" hidden>
                        <label id="ips-batch-target-label" for="ips-batch-target" class="ips-field-label">Destino del lote</label>
                        <select id="ips-batch-target" class="form-control form-control-sm"><option value="">Selecciona un destino</option></select>
                        <small id="ips-batch-target-help" class="ips-help"></small>
                    </div>
                @endif
                @if(in_array($workflowAction ?? '', ['EMR', 'EMG', 'CUSTOMS_RETURN'], true))
                    <div id="ips-batch-physical-fields" class="ips-batch-fields mt-3" hidden>
                        <label class="ips-receipt-confirm-label"><input id="ips-batch-physical-confirm" type="checkbox" class="mr-1"><span id="ips-batch-physical-label">Confirmo la recepción física del lote.</span></label>
                    </div>
                @endif
                @if(($workflowAction ?? '') === 'EMG')
                    <div id="ips-batch-route-mismatch-fields" class="ips-batch-fields mt-3" hidden>
                        <label class="ips-receipt-confirm-label"><input id="ips-batch-route-mismatch-confirm" type="checkbox" class="mr-1"><span>Confirmo que los paquetes con ruta distinta llegaron físicamente a esta oficina. Se intentará solo el evento 32, sin agregar un evento de cambio de ruta; si IPS lo rechaza, no se registrará la recepción.</span></label>
                    </div>
                @endif
                @if(($workflowAction ?? '') === 'EMR')
                    <div id="ips-batch-condition-fields" class="ips-batch-fields mt-3" hidden>
                        <label for="ips-batch-condition" class="ips-field-label">Condición común (opcional)</label>
                        <select id="ips-batch-condition" class="form-control form-control-sm">
                            <option value="">Aplicar una condición a varios paquetes</option>
                            @foreach(($catalog['item_conditions'] ?? []) as $condition)
                                <option value="{{ data_get($condition, 'ITEM_CONDITION_CD') }}">{{ data_get($condition, 'ITEM_CONDITION_NM') }}</option>
                            @endforeach
                        </select>
                        <small class="ips-help">Puedes cambiar la condición de cada paquete individualmente si es distinta.</small>
                    </div>
                @endif
                <div class="ips-tray-items">
                    @forelse($selection as $code=>$entry)
                        <article class="ips-tray-item" data-code="{{ $code }}" data-event="{{ (int) ($entry['package']['operational_event_cd'] ?? $entry['package']['event_cd'] ?? 0) }}" data-actions="{{ implode(',', $entry['package']['allowed_actions'] ?? []) }}" data-status="{{ $entry['status'] }}" data-payload-action="{{ $entry['payload']['event'] ?? '' }}" data-customs-return="{{ in_array((int) ($entry['package']['operational_event_cd'] ?? $entry['package']['event_cd'] ?? 0), [31, 34, 1251], true) && (int) ($entry['package']['state_cd'] ?? -1) === 1 ? '1' : '0' }}" data-route-mismatch="{{ !empty($entry['route_mismatch_receipt']) ? '1' : '0' }}" data-receipt-offices="{{ json_encode($entry['package']['receipt_office_options'] ?? []) }}" data-receipt-locked-office="{{ $entry['package']['receipt_office_locked_cd'] ?? '' }}" data-workbench-office-cd="{{ $entry['office_cd'] ?? $entry['package']['workbench_office_cd'] ?? '' }}" data-upu-receipt="{{ json_encode($entry['upu_receipt'] ?? ['destinatario' => $entry['package']['recipient'] ?? '', 'telefono' => $entry['package']['phone'] ?? '', 'zona' => $entry['package']['address'] ?? '', 'peso' => $entry['package']['weight_kg'] ?? '']) }}">
                            <div class="d-flex align-items-center"><input class="ips-choice mr-2" type="checkbox" aria-label="Seleccionar {{ $code }}" @if($deliveryOnly) hidden @endif><strong>{{ $code }}</strong>
                                @unless(in_array($entry['status'],['processing','uncertain'],true))<form class="ml-auto ips-selection-remove" method="POST" action="{{ route($selectionRemoveRouteName, \App\Support\IpsOfficeContext::parameters(request(), ($workflowAction ?? null) ? (['section' => $section, 'codigo' => $code] + (($workflowAction === 'CUSTOMS_RETURN' && !empty($entry['receptacle_pid'])) ? ['receptacle_pid' => $entry['receptacle_pid']] : [])) : ['codigo' => $code])) }}">@csrf @method('DELETE')<button type="submit" class="btn btn-sm text-muted" aria-label="Quitar {{ $code }}"><i class="fas fa-times"></i></button></form>@endunless
                            </div>
                            <small class="ips-item-stage">Etapa actual · {{ $entry['package']['stage']['label'] }}</small>
                            @if(!empty($entry['package']['workbench_office_name']))<small class="ips-item-office"><i class="fas fa-building mr-1" aria-hidden="true"></i>{{ !empty($entry['needs_receipt_office']) ? 'Oficina de recepción asignada automáticamente' : $entry['package']['workbench_office_name'] }}</small>@endif
                            @if(($workflowAction ?? '') === 'EMG')
                                <div class="ips-upu-zone-fields mt-2">
                                    <label class="ips-field-label">Zona / dirección
                                        <textarea class="form-control form-control-sm ips-upu-zone" rows="2" maxlength="1000" @disabled(!empty($entry['payload']))>{{ isset($entry['upu_receipt']) ? ($entry['upu_receipt']['zona'] ?? '') : ($entry['package']['address'] ?? '') }}</textarea>
                                    </label>
                                    <small>Dirección de IPS. Puedes editarla para guardarla en Bolipost al recibir.</small>
                                </div>
                            @endif
                            @if(!empty($entry['route_mismatch_receipt']))<p class="alert alert-warning py-2 px-2 mt-2 mb-2">Ruta IPS: {{ $entry['package']['next_office_name'] ?: 'otra oficina' }}. Se intentará registrar evento 32 aquí; no se agregará un evento de cambio de ruta. La operación depende de la respuesta IPS.</p>@endif
                            <div class="ips-physical-receipt mt-2" @if(($workflowAction ?? '') !== 'EMR') hidden @endif>
                                <label class="ips-receipt-confirm-label"><input class="ips-physical-receipt-confirm mr-1" type="checkbox" @checked(!empty($entry['payload']['physical_receipt_confirmed'])) @disabled(!empty($entry['payload']))> Confirmo que el envío llegó físicamente a esta oficina.</label>
                            </div>
                            <div class="ips-exchange-receptacle-fields mt-2" @if(($workflowAction ?? '') !== 'EMR') hidden @endif><label class="ips-field-label">Envase internacional recibido y abierto</label><select class="form-control form-control-sm ips-exchange-receptacle" @disabled(!empty($entry['payload']))><option value="">Seleccionar envase</option>@foreach(($entry['package']['exchange_receptacles'] ?? []) as $receptacle)<option value="{{ data_get($receptacle, 'RECPTCL_PID') }}" @selected((string) ($entry['payload']['receptacle_pid'] ?? '') === (string) data_get($receptacle, 'RECPTCL_PID'))>{{ data_get($receptacle, 'RECPTCL_FID') }} · {{ data_get($receptacle, 'RECPTCL_MAILITMS_NO') !== null ? data_get($receptacle, 'RECPTCL_MAILITMS_NO').' declarados' : 'cantidad no declarada' }}</option>@endforeach</select></div>
                            <div class="ips-condition-fields mt-2" @if(($workflowAction ?? '') !== 'EMR') hidden @endif><label class="ips-field-label">Condicion fisica del paquete</label><select class="form-control form-control-sm ips-package-condition" @disabled(!empty($entry['payload']))><option value="">Revisar y seleccionar</option>@foreach(($catalog['item_conditions'] ?? []) as $condition)<option value="{{ data_get($condition, 'ITEM_CONDITION_CD') }}" @selected((string) ($entry['payload']['condition_cd'] ?? '') === (string) data_get($condition, 'ITEM_CONDITION_CD'))>{{ data_get($condition, 'ITEM_CONDITION_NM') }}</option>@endforeach</select></div>
                                                        <div class="ips-route-fields mt-2" hidden><label class="ips-field-label">Siguiente oficina de la ruta</label><select class="form-control form-control-sm ips-next-office" @disabled(!empty($entry['payload']))><option value="">Seleccionar oficina</option>@foreach(($entry['package']['next_offices'] ?? []) as $office)<option value="{{ data_get($office, 'OWN_OFFICE_CD') }}" @selected((string) ($entry['payload']['next_office_cd'] ?? '') === (string) data_get($office, 'OWN_OFFICE_CD'))>{{ data_get($office, 'OFFICE_NM') }}{{ data_get($office, 'OFFICE_FCD') ? ' · '.data_get($office, 'OFFICE_FCD') : '' }}</option>@endforeach</select></div>
                            <div class="ips-sack-fields mt-2" hidden><label class="ips-field-label">Saca nacional abierta</label><select class="form-control form-control-sm ips-domestic-receptacle" @disabled(!empty($entry['payload']))><option value="">Seleccionar saca</option>@foreach(($entry['package']['domestic_receptacles'] ?? []) as $receptacle)<option value="{{ data_get($receptacle, 'DOM_REC_PID') }}" @selected((string) ($entry['payload']['domestic_receptacle_pid'] ?? '') === (string) data_get($receptacle, 'DOM_REC_PID'))>{{ data_get($receptacle, 'DOM_REC_FID') }} · destino {{ data_get($receptacle, 'DEST_OWN_OFFICE_CD') }} · {{ data_get($receptacle, 'MAILITEMCNT', 0) }} envíos</option>@endforeach</select></div>
                            <small class="ips-item-message" role="status" @if(empty($entry['message'])) hidden @endif>{{ $entry['message'] }}</small>
                            <input class="form-control form-control-sm ips-signatory mt-2" maxlength="64" value="{{ $entry['payload']['signatory'] ?? '' }}" placeholder="Persona que recibió (opcional)" aria-label="Receptor real de {{ $code }}" hidden @disabled(!empty($entry['payload']))>
                            <div class="ips-delivery-fields mt-2" hidden><label class="ips-field-label">Modalidad efectiva de entrega</label>@if($courierOnly)<input type="hidden" class="ips-delivery-mode" value="DOMICILIARIA"><span class="d-block">Cartero · domiciliaria</span>@else<select class="form-control form-control-sm ips-delivery-mode" @disabled(!empty($entry['payload']))><option value="">Seleccionar modalidad</option><option value="NO_DOMICILIARIA" @selected(($entry['payload']['delivery_mode'] ?? '') === 'NO_DOMICILIARIA')>Ventanilla · no domiciliaria</option><option value="DOMICILIARIA" @selected(($entry['payload']['delivery_mode'] ?? '') === 'DOMICILIARIA')>Cartero · domiciliaria</option></select>@endif</div>
                            <div class="ips-attempt-fields mt-2" hidden><label class="ips-field-label">Motivo del intento fallido</label><select class="form-control form-control-sm ips-nondelivery-reason" @disabled(!empty($entry['payload']))><option value="">Seleccionar motivo</option>@foreach(($catalog['non_delivery_reasons'] ?? []) as $reason)<option value="{{ data_get($reason, 'NON_DELIVERY_REASON_CD') }}" @selected((string) ($entry['payload']['non_delivery_reason'] ?? '') === (string) data_get($reason, 'NON_DELIVERY_REASON_CD'))>{{ data_get($reason, 'NON_DELIVERY_REASON_NM') }}</option>@endforeach</select><label class="ips-field-label mt-2">Medida aplicada</label><select class="form-control form-control-sm ips-nondelivery-measure" @disabled(!empty($entry['payload']))><option value="">Seleccionar medida</option>@foreach(($catalog['non_delivery_measures'] ?? []) as $measure)<option value="{{ data_get($measure, 'NON_DELIVERY_MEASURE_CD') }}" @selected((string) ($entry['payload']['non_delivery_measure'] ?? '') === (string) data_get($measure, 'NON_DELIVERY_MEASURE_CD'))>{{ data_get($measure, 'NON_DELIVERY_MEASURE_NM') }}</option>@endforeach</select></div>
                        </article>
                    @empty<div class="ips-empty"><i class="fas fa-inbox"></i><p>Tu bandeja está vacía.<br>Busca un paquete y pulsa Agregar.</p></div>@endforelse
                </div>
                @if($deliveryOnly || ($workflowAction ?? '') === 'HANDOVER_AGENT')
                    @php($pendingDeliveryAgents = collect($selection)->pluck('payload.delivery_agent_cd')->filter()->unique()->values())
                    <div id="ips-delivery-agent-fields" class="mt-3" hidden>
                        <label for="ips-delivery-agent" class="ips-field-label">Cartero que recibe los paquetes</label>
                        <select id="ips-delivery-agent" class="form-control form-control-sm">
                            <option value="">Seleccionar cartero</option>
                            @foreach(($catalog['delivery_agents'] ?? []) as $agent)
                                @php($agentCode = str_pad((string) data_get($agent, 'DELIVERY_AGENT_CD'), 2, '0', STR_PAD_LEFT))
                                <option value="{{ $agentCode }}" @selected($pendingDeliveryAgents->count() === 1 && (string) $pendingDeliveryAgents->first() === $agentCode)>{{ data_get($agent, 'DELIVERY_AGENT_NM') }}{{ data_get($agent, 'DELIVERY_AGENT_FULL_CD') ? ' · '.data_get($agent, 'DELIVERY_AGENT_FULL_CD') : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if($deliveryOnly && !$courierOnly)
                    <div id="ips-customs-fields" class="mt-3" hidden>
                        <p class="ips-help">Estos datos se aplicarán a todos los paquetes seleccionados para Aduana.</p>
                        <label for="ips-customs-office" class="ips-field-label">Oficina IPS de Aduana</label>
                        <select id="ips-customs-office" class="form-control form-control-sm" disabled>
                            <option value="">Seleccionar oficina</option>
                            @foreach(($catalog['offices'] ?? []) as $office)
                                <option value="{{ data_get($office, 'OWN_OFFICE_CD') }}">{{ data_get($office, 'OFFICE_NM') }}{{ data_get($office, 'OFFICE_FCD') ? ' · '.data_get($office, 'OFFICE_FCD') : '' }}</option>
                            @endforeach
                        </select>
                        <label for="ips-customs-office-type" class="ips-field-label mt-2">Modalidad de Aduana</label>
                        <select id="ips-customs-office-type" class="form-control form-control-sm" disabled>
                            <option value="">Seleccionar modalidad</option>
                            @foreach(($catalog['customs_office_types'] ?? []) as $type)
                                @php($typeCode = strtoupper(trim((string) data_get($type, 'CUSTOMS_OFFICE_TYPE_CD'))))
                                <option value="{{ $typeCode }}">{{ $typeCode }} · {{ match($typeCode) {
                                    'OE' => 'En oficina de cambio (valor predeterminado IPS)',
                                    'R' => 'Aduana remota, sin retorno a oficina de cambio',
                                    'ROE' => 'Aduana remota, con retorno a oficina de cambio',
                                    default => data_get($type, 'CUSTOMS_OFFICE_TYPE_NM'),
                                } }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button id="ips-process" class="btn btn-primary btn-block mt-3" disabled>Selecciona paquetes compatibles</button>
                @if($workflowAction)<button id="ips-clear-completed" class="btn btn-outline-primary btn-block mt-2" type="button" hidden>Preparar siguiente lote</button>@endif
                <p id="ips-progress" role="status" aria-live="polite" class="ips-help"></p>
                <button id="ips-receipt-summary-open" class="btn btn-outline-primary btn-block" type="button" hidden>{{ $deliveryOnly ? 'Ver resumen de entrega' : 'Ver resumen de recepción' }}</button>
                <button id="ips-refresh" class="btn btn-outline-primary btn-block" hidden onclick="location.reload()">Actualizar resultados</button>
            </div>
        </aside>
        @endunless
    </div>
    @endif
</div>
@unless($readOnly)
<dialog id="ips-already-received" aria-labelledby="ips-already-received-title" aria-describedby="ips-already-received-message">
    <form method="dialog">
        <div class="ips-received-alert-heading">
            <button class="ips-received-alert-close" type="submit" aria-label="Cerrar aviso"><i class="fas fa-times" aria-hidden="true"></i></button>
            <span class="ips-received-alert-icon" aria-hidden="true"><i class="fas fa-exclamation-triangle"></i></span>
            <p class="ips-received-alert-eyebrow">RECEPCIÓN YA REGISTRADA</p>
            <h3 id="ips-already-received-title">Este paquete ya está recibido</h3>
        </div>
        <div class="ips-received-alert-body">
            <div class="ips-received-alert-package"><span>Código del paquete</span><p id="ips-already-received-code"></p></div>
            <p id="ips-already-received-message">La recepción ya fue registrada. Continúa en Almacén internacional → Por entregar.</p>
            <button class="btn btn-primary ips-received-alert-done" type="submit" autofocus><i class="fas fa-check mr-2" aria-hidden="true"></i>Entendido</button>
        </div>
    </form>
</dialog>
<dialog id="ips-receipt-summary" aria-labelledby="ips-receipt-summary-title" aria-describedby="ips-receipt-summary-description">
    <div class="ips-receipt-summary-heading">
        <span id="ips-receipt-summary-icon" class="ips-receipt-summary-icon" aria-hidden="true"><i class="fas fa-check"></i></span>
        <div><p id="ips-receipt-summary-eyebrow" class="ips-receipt-summary-eyebrow">RESULTADO DEL LOTE</p><h3 id="ips-receipt-summary-title">Resumen de recepción</h3></div>
        <button id="ips-receipt-summary-close" type="button" class="ips-receipt-summary-close" aria-label="Cerrar resumen"><i class="fas fa-times" aria-hidden="true"></i></button>
    </div>
    <div class="ips-receipt-summary-body">
    <p id="ips-receipt-summary-description"></p>
    <dl class="ips-receipt-summary-counts">
        <div class="ips-receipt-summary-received"><dt id="ips-receipt-summary-success-label">Recibidos</dt><dd id="ips-receipt-summary-received">0</dd></div>
        <div><dt>Sin confirmar</dt><dd id="ips-receipt-summary-pending">0</dd></div>
        <div><dt id="ips-receipt-summary-rejected-label">No recibidos</dt><dd id="ips-receipt-summary-rejected">0</dd></div>
    </dl>
    <p id="ips-receipt-summary-help" class="ips-receipt-summary-help"></p>
    <details class="ips-receipt-summary-details">
        <summary>Ver detalle de los paquetes</summary>
        <ul id="ips-receipt-summary-items"></ul>
    </details>
    <div class="ips-receipt-summary-actions">
        <button id="ips-receipt-summary-review" class="btn btn-outline-primary" type="button" hidden>Revisar paquetes</button>
        <button id="ips-receipt-summary-done" class="btn btn-primary" type="button" autofocus>Entendido</button>
    </div>
    </div>
</dialog>
@endunless
<dialog id="ips-confirm" aria-labelledby="ips-confirm-title" aria-describedby="ips-confirm-description">
    <form method="dialog">
        <div class="ips-delivery-confirm-heading"><span aria-hidden="true"><i class="fas fa-hand-holding-heart"></i></span><p>ENTREGA AL DESTINATARIO</p></div>
        <h3 id="ips-confirm-title">Confirmar movimiento</h3>
        <p id="ips-confirm-description"></p>
        <p id="ips-confirm-codes"></p>
        <fieldset id="ips-receipt-confirm-fields" hidden disabled>
            <div id="ips-confirm-receipt-offices"></div>
            <section class="ips-upu-confirm-section" aria-labelledby="ips-upu-confirm-title">
                <h4 id="ips-upu-confirm-title">Registro de paquetes UPU</h4>
                <p class="small text-muted">Revisa y completa estos datos para guardarlos en el registro UPU de Bolipost cuando se confirme la recepción.</p>
                <div id="ips-confirm-upu-fields"></div>
            </section>
            <label class="d-block mb-3"><input id="ips-confirm-physical-receipt" type="checkbox" class="mr-2" required> Confirmo que los paquetes seleccionados llegaron físicamente a las oficinas indicadas.</label>
            <label id="ips-confirm-route-fields" class="d-block alert alert-warning" hidden><input id="ips-confirm-receipt-route" type="checkbox" class="mr-2"> Confirmo la recepción física en una oficina distinta a la ruta que figura en IPS.</label>
        </fieldset>
        <p class="ips-delivery-confirm-note">Confirma únicamente si el destinatario ya recibió físicamente el paquete.</p>
        <p>Cada paquete tendrá su propio resultado. Revisa el resumen al terminar.</p>
        <div class="ips-confirm-actions d-flex justify-content-end mt-3"><button value="cancel" formnovalidate class="btn btn-light mr-2">Volver</button><button id="ips-confirm-submit" value="confirm" class="btn btn-primary">Confirmar y registrar</button></div>
    </form>
</dialog>
@endsection
@section('css')
<style>
.ips-workspace[data-mode="reception"],.ips-workspace[data-mode="delivery"]{--ips-mode-accent:#245b99;--ips-mode-dark:#173e6b;--ips-mode-soft:#eaf3ff;--ips-mode-border:#a8c8ee}
.ips-workbench-heading{display:flex;align-items:center;gap:22px;margin:0 0 24px;padding:28px;border:1px solid var(--ips-mode-border);border-left:8px solid var(--ips-mode-accent);border-radius:18px;background:linear-gradient(120deg,var(--ips-mode-soft),#fff);box-shadow:0 6px 22px #17314d0a}
.ips-workbench-icon{display:grid;place-items:center;flex:0 0 82px;height:82px;border-radius:20px;background:var(--ips-mode-accent);color:#fff;font-size:2.2rem}
.ips-workbench-heading-content{flex:1;min-width:0}
.ips-workbench-mode{display:inline-block;padding:6px 12px;margin:0 0 10px;border-radius:8px;background:var(--ips-mode-accent);color:#fff;font-size:.8rem;letter-spacing:.08em;font-weight:800}
.ips-workbench-heading h1{font-size:1.9rem;line-height:1.2;font-weight:800;color:var(--ips-mode-dark);margin:0 0 10px}
.ips-workbench-heading p{font-size:.98rem;line-height:1.55;color:#506177;margin:0;max-width:800px}
.ips-workbench-office{display:flex;align-items:center;gap:10px;padding:14px 18px;border:1px solid var(--ips-mode-border);border-radius:12px;background:#ffffffbf;color:var(--ips-mode-dark);max-width:250px}
.ips-workbench-office small{display:block;margin-bottom:4px;font-size:.65rem;font-weight:700;letter-spacing:.06em}
.ips-workbench-office strong{font-size:.9rem}
.ips-workspace[data-mode] .ips-workbench-heading~.ips-layout .ips-panel-header{background:var(--ips-mode-accent)}
.ips-workspace[data-mode="delivery"] #ips-process,.ips-workspace[data-mode="delivery"] .ips-search .btn-primary{background:#245b99;border-color:#245b99}
.ips-workspace[data-mode="delivery"] #ips-process:not(:disabled):hover,.ips-workspace[data-mode="delivery"] .ips-search .btn-primary:hover{background:#1a477b;border-color:#1a477b}
.ips-workspace[data-mode="delivery"] .ips-selection-add .btn-outline-primary,.ips-workspace[data-mode="delivery"] #ips-receipt-summary-open{color:#245b99;border-color:#245b99}
.ips-workspace[data-mode="delivery"] .ips-selection-add .btn-outline-primary:hover,.ips-workspace[data-mode="delivery"] #ips-receipt-summary-open:hover{background:#245b99;color:#fff}
.ips-workspace[data-mode="delivery"] .ips-delivery-action.is-active{background:#eaf3ff;border-color:#7fa9de;color:#1a477b}
.ips-workspace[data-mode="delivery"] .ips-delivery-action.is-active small{color:#245b99}
.ips-workspace[data-mode="delivery"] .ips-tray{border-top:5px solid #245b99}
.ips-workspace[data-mode="reception"] .ips-tray{border-top:5px solid #245b99}
@media(max-width:1100px){.ips-workbench-heading{flex-wrap:wrap}.ips-workbench-office{max-width:none}}
@media(max-width:600px){.ips-workbench-heading{gap:16px;padding:20px}.ips-workbench-icon{flex-basis:58px;height:58px;border-radius:14px;font-size:1.6rem}.ips-workbench-heading-content{flex-basis:calc(100% - 80px)}.ips-workbench-heading h1{font-size:1.4rem}.ips-workbench-mode{font-size:.65rem;letter-spacing:.04em}.ips-workbench-heading p{font-size:.9rem}.ips-workbench-office{width:100%;box-sizing:border-box}}
#ips-already-received{box-sizing:border-box;border:0;border-top:8px solid #f4ad18;border-radius:22px;max-width:760px;width:calc(100% - 32px);max-height:calc(100dvh - 32px);overflow:auto;padding:0;color:#263e59;background:#fff;box-shadow:0 28px 100px #09192b77}
#ips-already-received::backdrop{background:#0b1b30c7;backdrop-filter:blur(5px)}
.ips-received-alert-heading{position:relative;text-align:center;padding:38px 54px 30px;background:linear-gradient(135deg,#fff8e7,#ffedbd);border-bottom:1px solid #f1d799}
.ips-received-alert-close{position:absolute;top:12px;right:12px;display:grid;place-items:center;width:44px;height:44px;border:0;border-radius:50%;background:#ffffffb3;color:#79500a;font-size:1.15rem;cursor:pointer}
.ips-received-alert-close:hover{background:#fff}
.ips-received-alert-icon{display:grid;place-items:center;width:90px;height:90px;margin:0 auto 20px;border:2px solid #e8a51c;border-radius:50%;background:#fff5d6;color:#996000;font-size:2.6rem;box-shadow:0 0 0 9px #f4ad181a}
.ips-received-alert-eyebrow{margin:0 0 10px;font-size:.8rem;font-weight:800;letter-spacing:.12em;color:#825308}
#ips-already-received h3{max-width:560px;margin:0 auto;font-size:2.15rem;line-height:1.2;font-weight:800;color:#573900;text-wrap:balance}
.ips-received-alert-body{padding:30px 42px 36px;text-align:center}
.ips-received-alert-package{padding:18px 20px;margin-bottom:22px;border:1px solid #dce6f1;border-radius:12px;background:#f3f6fa}
.ips-received-alert-package>span{display:block;margin-bottom:7px;font-size:.8rem;font-weight:700;color:#63778e}
#ips-already-received-code{margin:0;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:1.65rem;font-weight:800;letter-spacing:.04em;color:#234f8b;overflow-wrap:anywhere}
#ips-already-received-message{margin:0 0 26px;font-size:1.12rem;line-height:1.65;color:#43566d}
#ips-already-received .ips-received-alert-done{width:100%;min-height:56px;border-radius:12px;font-size:1.1rem;font-weight:700}
#ips-already-received button:focus-visible{outline:3px solid #234f8b;outline-offset:4px}
@media(max-width:600px){.ips-received-alert-heading{padding:32px 24px 24px}.ips-received-alert-body{padding:24px 20px}.ips-received-alert-icon{width:76px;height:76px;font-size:2.2rem}#ips-already-received h3{font-size:1.65rem}#ips-already-received-code{font-size:1.25rem}#ips-already-received-message{font-size:1rem}}
.ips-heading{display:flex;justify-content:space-between;gap:20px;padding:10px 5px 20px;align-items:center}.ips-heading h1{font-weight:800;color:#183352;margin:3px 0}.ips-heading p,.ips-footnote{color:#697b90;margin:0;font-size:.9rem}.ips-eyebrow{font-size:.68rem;color:#47698e;letter-spacing:.14em;font-weight:700}.ips-identity{display:flex;gap:12px;align-items:center;padding:12px 18px;background:white;border:1px solid #dce6f1;border-radius:12px;color:#254e80}.ips-identity small{display:block;color:#718096}
.ips-operation-nav{background:#fff;border:1px solid #dce6f1;border-radius:12px;padding:12px 14px;margin:0 0 14px;box-shadow:0 4px 16px #17314d08}.ips-operation-groups,.ips-operation-tabs,.ips-warehouse-nav{display:flex;gap:7px;align-items:center;overflow-x:auto}.ips-operation-groups{padding-bottom:9px;border-bottom:1px solid #edf1f6}.ips-operation-groups a,.ips-operation-tabs a,.ips-warehouse-nav a{display:inline-flex;align-items:center;min-height:36px;padding:7px 12px;border-radius:8px;color:#47617d;font-size:.8rem;text-decoration:none;white-space:nowrap}.ips-operation-groups a{font-weight:700}.ips-operation-groups a.active,.ips-operation-tabs a.active,.ips-warehouse-nav a.active{background:#eaf1fc;color:#214c84;font-weight:700}.ips-operation-tabs{padding-top:8px}.ips-operation-description{font-size:.76rem;color:#718096;margin:8px 3px 0}.ips-warehouse-nav{background:#fff;border:1px solid #dce6f1;border-radius:12px;padding:8px;margin:0 0 14px;box-shadow:0 4px 16px #17314d08}
.ips-flow{background:#fff;border:1px solid #dce6f1;border-radius:14px;padding:18px 20px;margin:0 0 18px;box-shadow:0 5px 20px #17314d08}.ips-flow-heading{display:flex;justify-content:space-between;gap:16px;align-items:center}.ips-flow-heading h2{font-size:1rem;font-weight:700;color:#234f8b;margin:0 0 3px}.ips-flow-heading p,.ips-flow-heading>span{font-size:.78rem;color:#697b90;margin:0}.ips-flow-heading>span{white-space:nowrap;font-weight:700}.ips-flow-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));list-style:none;padding:0;margin:18px 0;gap:8px}.ips-flow-steps li{min-width:0}.ips-flow-steps a{display:flex;gap:9px;align-items:center;height:100%;padding:10px;border:1px solid #e1e8f0;border-radius:10px;color:#64758a;font-size:.78rem;text-decoration:none}.ips-flow-steps li.is-current a{background:#edf4ff;border-color:#7fa9de;color:#1e4d87;font-weight:700}.ips-flow-number{display:grid;place-items:center;flex:0 0 28px;height:28px;border-radius:50%;background:#edf1f6;font-weight:700}.ips-flow-steps li.is-current .ips-flow-number{background:#234f8b;color:#fff}.ips-flow-steps small{display:block;font-size:.68rem;color:#7a8ba0;margin-top:2px}.ips-flow-rules{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;border-top:1px solid #edf1f6;padding-top:12px}.ips-flow-rules p{font-size:.76rem;color:#60748a;margin:0}.ips-flow-rules strong{color:#294c72}.ips-receipt-confirm-label{display:flex;gap:4px;align-items:flex-start;font-size:.78rem;color:#284e75;cursor:pointer}.ips-receipt-confirm-label input{margin-top:3px}
.ips-workspace{padding:24px 5px 24px}
.ips-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:20px;align-items:start}.ips-main{min-width:0}.ips-panel{background:white;border:1px solid #e0e7ef;border-radius:14px;box-shadow:0 5px 20px #17314d08;overflow:hidden}.ips-panel-header{display:flex;justify-content:space-between;align-items:center;padding:18px 20px;background:#234f8b;color:white;gap:12px}.ips-panel-header h2{font-size:1.05rem;font-weight:700;margin:0}.ips-panel-header>span{font-size:.78rem;white-space:nowrap}.ips-search{padding:20px;display:flex;gap:10px}.ips-search input{flex:1;min-width:100px}.ips-tabs{display:flex;gap:5px;padding:0 20px 15px;overflow:auto}.ips-tabs a{padding:8px 12px;border-radius:8px;color:#54687e;font-size:.8rem;white-space:nowrap}.ips-tabs a.is-active{background:#eaf1fc;color:#214c84;font-weight:700}.ips-table{margin:0}.ips-table th{background:#f0f4f9;font-size:.75rem;color:#315279;border-top:1px solid #e3eaf2;white-space:nowrap}.ips-table td{font-size:.8rem;padding:15px 12px;vertical-align:middle;border-color:#edf1f6}.ips-table small{display:block;color:#76869a;margin-top:5px;min-width:85px}.ips-code{color:#234f8b;white-space:nowrap}.ips-table .badge{white-space:normal;text-align:left;font-size:.74rem;line-height:1.5}.ips-pagination{display:flex;justify-content:space-between;align-items:center;padding:18px 20px;gap:10px;flex-wrap:wrap;font-size:.8rem;color:#5b6d82}.ips-pagination .pagination{margin:0}.ips-footnote{padding:14px 2px;font-size:.78rem}
.ips-setup-state{max-width:760px;margin:36px auto}.ips-setup-state .ips-tray-body>p{font-size:.9rem;color:#596e84}
.ips-tray{position:sticky;top:75px}.ips-tray-body{padding:18px}.ips-tray-body>p{font-size:.82rem;color:#697b90}.ips-tray-body label{font-size:.82rem;color:#324c69}.ips-tray-items{max-height:480px;overflow:auto}.ips-tray-item{padding:12px 0;border-bottom:1px solid #e8eef4;font-size:.8rem}.ips-tray-item small{display:block;color:#738297;margin:5px 0}.ips-tray-item .ips-item-stage{font-weight:700;color:#435d79}.ips-tray-item.is-ineligible{opacity:.65}.ips-help{font-size:.77rem;color:#63778e;margin:10px 0}.ips-action-heading{font-size:.83rem;font-weight:700;color:#324c69;margin:14px 0 8px}.ips-delivery-action-group{border-top:1px solid #edf1f6;padding:8px 0}.ips-delivery-action-group[hidden],.ips-delivery-action[hidden]{display:none!important}.ips-delivery-action-group h4{font-size:.67rem;text-transform:uppercase;letter-spacing:.06em;color:#718096;margin:0 0 6px}.ips-delivery-action-options{display:grid;gap:5px}.ips-delivery-action{display:flex;justify-content:space-between;align-items:center;gap:8px;width:100%;padding:8px 10px;border:1px solid #dbe4ef;border-radius:8px;background:#fff;color:#315279;text-align:left;font-size:.76rem;line-height:1.25;cursor:pointer}.ips-delivery-action:hover{background:#f5f8fc;border-color:#9db5d2}.ips-delivery-action:focus-visible{outline:3px solid #9dc2f1;outline-offset:1px}.ips-delivery-action.is-active{background:#eaf1fc;border-color:#6d96c5;color:#1f4d83;font-weight:700}.ips-delivery-action small{flex:0 0 auto;color:#718096;font-size:.66rem;font-weight:500;white-space:nowrap;margin:0}.ips-delivery-action.is-active small{color:#315b88}.ips-empty{text-align:center;padding:35px 20px!important;color:#7e8da0}.ips-empty>i{font-size:1.8rem;opacity:.5;margin-bottom:10px}.ips-empty p{margin:8px 0}.ips-tray-item[data-status=succeeded] .ips-item-message{color:#18794e}.ips-tray-item[data-status=rejected] .ips-item-message{color:#b33c38}
#ips-confirm{border:0;border-radius:15px;max-width:520px;width:90%;padding:28px;color:#263e59;box-shadow:0 20px 70px #10233e55}#ips-confirm::backdrop{background:#10233e88}#ips-confirm h3{font-size:1.3rem;font-weight:700}#ips-confirm p{font-size:.9rem}#ips-confirm-codes{max-height:160px;overflow:auto;background:#f0f4f9;padding:10px;border-radius:8px}.ips-customs-confirm-label{display:flex;gap:8px;align-items:flex-start;padding:12px;background:#fff7dc;border:1px solid #f0d580;border-radius:8px;font-size:.84rem;color:#5b4a17;cursor:pointer}.ips-customs-confirm-label input{margin-top:4px;flex:0 0 auto}#ips-customs-confirm-fields[hidden]{display:none!important}
.ips-field-label{display:block;font-size:.74rem;font-weight:700;color:#385574;margin-bottom:4px}
.ips-search--operation{flex-wrap:wrap;align-items:stretch;gap:12px;background:#f5f9ff;border-bottom:1px solid #dce6f1}
.ips-search--operation .ips-search-label{flex-basis:100%;margin:0;font-size:1rem;font-weight:700;color:#173e6b}
.ips-search--operation #ips-code{min-height:54px;height:auto;border-radius:10px;font-size:1.05rem}
.ips-search--operation .ips-search-submit{display:inline-flex;align-items:center;justify-content:center;gap:4px;min-width:210px;min-height:54px;padding:12px 22px;border-radius:10px;font-size:1.05rem;font-weight:700;background:#245b99;border-color:#245b99;box-shadow:0 4px 10px #245b9926}
.ips-search--operation .ips-search-submit:hover{background:#1a477b;border-color:#1a477b}
.ips-search--operation .ips-search-submit:focus-visible{outline:3px solid #7fa9de;outline-offset:3px}
.ips-search--operation>a{display:inline-flex;align-items:center;justify-content:center;border-radius:10px}
@media(max-width:600px){.ips-search--operation #ips-code{flex-basis:100%;width:100%}.ips-search--operation .ips-search-submit{flex:1;min-width:0}.ips-search--operation .ips-search-label{font-size:.95rem}}
.ips-delivery-confirm-heading,.ips-delivery-confirm-note{display:none}
#ips-receipt-confirm-fields[hidden],#ips-confirm-route-fields[hidden]{display:none!important}
#ips-confirm[data-operation="receipt"]{max-width:680px;max-height:calc(100dvh - 32px);overflow:auto;border-top:6px solid #245b99}
#ips-confirm[data-operation="receipt"] fieldset{border:0;padding:0;margin:16px 0;text-align:left;font-size:.9rem}
#ips-confirm .ips-upu-confirm-section{margin:20px 0;padding:16px;border:1px solid #dce6f1;border-radius:10px;background:#f7faff}
#ips-confirm .ips-upu-confirm-section h4{font-size:1rem;font-weight:700;color:#294c72}
#ips-confirm .ips-upu-confirm-section legend{font-size:.9rem;font-weight:700}
#ips-confirm[data-operation="delivery"]{box-sizing:border-box;max-width:760px;width:calc(100% - 32px);max-height:calc(100dvh - 32px);overflow:auto;border-top:8px solid #245b99;border-radius:22px;padding:34px 40px;text-align:center}
#ips-confirm[data-operation="delivery"]::backdrop{background:#0b1b30c7;backdrop-filter:blur(5px)}
#ips-confirm[data-operation="delivery"] .ips-delivery-confirm-heading{display:block}
.ips-delivery-confirm-heading>span{display:grid;place-items:center;width:86px;height:86px;margin:0 auto 20px;border:2px solid #7fa9de;border-radius:50%;background:#eaf3ff;color:#245b99;font-size:2.6rem;box-shadow:0 0 0 9px #245b9912}
#ips-confirm .ips-delivery-confirm-heading>p{font-size:.8rem;letter-spacing:.12em;font-weight:800;color:#245b99}
#ips-confirm[data-operation="delivery"] h3{font-size:2.1rem;line-height:1.2;color:#173e6b}
#ips-confirm[data-operation="delivery"] p{font-size:1.05rem;line-height:1.6}
#ips-confirm[data-operation="delivery"] #ips-confirm-codes{padding:18px;font-size:1.3rem;font-weight:800;background:#eaf3ff;color:#1a477b;overflow-wrap:anywhere}
#ips-confirm[data-operation="delivery"] .ips-delivery-confirm-note{display:block;padding:14px 18px;border:1px solid #a8c8ee;border-radius:12px;background:#f5f9ff;color:#1a477b;font-weight:700}
#ips-confirm[data-operation="delivery"] .ips-confirm-actions{gap:12px}
#ips-confirm[data-operation="delivery"] .ips-confirm-actions button{flex:1;min-width:0;min-height:56px;border-radius:12px;font-size:1rem;font-weight:700;white-space:normal}
#ips-confirm[data-operation="delivery"] #ips-confirm-submit{background:#245b99;border-color:#245b99}
#ips-confirm[data-operation="delivery"] #ips-confirm-submit:hover{background:#1a477b;border-color:#1a477b}
@media(max-width:600px){#ips-confirm[data-operation="delivery"]{padding:28px 20px}#ips-confirm[data-operation="delivery"] h3{font-size:1.65rem}#ips-confirm[data-operation="delivery"] .ips-confirm-actions{flex-direction:column}#ips-confirm[data-operation="delivery"] .ips-confirm-actions button{margin-right:0!important}}
#ips-receipt-summary{box-sizing:border-box;border:0;border-top:8px solid #245b99;border-radius:22px;width:calc(100% - 32px);max-width:760px;max-height:calc(100dvh - 32px);overflow:auto;padding:0;color:#263e59;background:#fff;box-shadow:0 28px 100px #09192b77}
#ips-receipt-summary::backdrop{background:#0b1b30c7;backdrop-filter:blur(5px)}
.ips-receipt-summary-heading{position:relative;display:flex;flex-direction:column;align-items:center;gap:18px;text-align:center;padding:32px 54px 28px;background:linear-gradient(135deg,#f5f9ff,#dbeafe);border-bottom:1px solid #a8c8ee}
.ips-receipt-summary-icon{display:grid;place-items:center;width:86px;height:86px;border:2px solid #7fa9de;border-radius:50%;background:#eaf3ff;color:#245b99;font-size:2.6rem;box-shadow:0 0 0 9px #245b9912}
.ips-receipt-summary-eyebrow{font-size:.8rem;font-weight:800;letter-spacing:.12em;color:#245b99;margin:0 0 10px}
.ips-receipt-summary-heading h3{font-size:2.15rem;line-height:1.2;font-weight:800;color:#173e6b;margin:0;text-wrap:balance}
.ips-receipt-summary-close{position:absolute;top:12px;right:12px;display:grid;place-items:center;width:44px;height:44px;border:0;background:#ffffffb3;color:#245b99;border-radius:50%;font-size:1.15rem;cursor:pointer}
.ips-receipt-summary-close:hover{background:#fff}
.ips-receipt-summary-body{padding:28px 40px 34px}
#ips-receipt-summary-description{font-size:1.4rem;line-height:1.4;font-weight:750;text-align:center;margin:0 0 24px}
.ips-receipt-summary-counts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:0 0 22px}
.ips-receipt-summary-counts>div{display:flex;flex-direction:column-reverse;text-align:center;background:#f0f4f9;border:1px solid #dce6f1;border-radius:14px;padding:18px 8px}
.ips-receipt-summary-counts dt{font-size:.9rem;font-weight:600;color:#52677e}
.ips-receipt-summary-counts dd{font-size:2.75rem;line-height:1.2;font-weight:800;margin:0 0 6px}
.ips-receipt-summary-counts .ips-receipt-summary-received{background:#e7f5ed;border-color:#b9dfca;color:#18794e}
.ips-receipt-summary-counts .ips-receipt-summary-received dt{color:#18794e}
.ips-receipt-summary-help{font-size:1.05rem;line-height:1.6;color:#52677e;margin:0 0 22px}
.ips-receipt-summary-details{border:1px solid #dce6f1;border-radius:12px;padding:16px 18px}
.ips-receipt-summary-details summary{cursor:pointer;font-weight:700;font-size:1rem;color:#234f8b}
.ips-receipt-summary-details ul{list-style:none;max-height:260px;overflow:auto;padding:0;margin:14px 0 0}
.ips-receipt-summary-details li{padding:14px 0;border-top:1px solid #edf1f6;font-size:.95rem;overflow-wrap:anywhere}
.ips-receipt-summary-details li p{margin:7px 0 0;line-height:1.5;color:#52677e}
.ips-receipt-summary-item-heading{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px}
.ips-receipt-summary-state{font-weight:700;color:#8a5b06}
.ips-receipt-summary-state.is-received{color:#18794e}
.ips-receipt-summary-state.is-rejected{color:#b33c38}
.ips-receipt-summary-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:26px}
.ips-receipt-summary-actions button{flex:1;min-width:0;min-height:56px;border-radius:12px;font-size:1.05rem;font-weight:700;white-space:normal}
#ips-receipt-summary button:focus-visible,#ips-receipt-summary summary:focus-visible{outline:3px solid #234f8b;outline-offset:4px}
#ips-receipt-summary[data-needs-review="true"]{border-top-color:#f4ad18}
#ips-receipt-summary #ips-receipt-summary-done{background:#245b99;border-color:#245b99}
#ips-receipt-summary #ips-receipt-summary-done:hover{background:#1a477b;border-color:#1a477b}
#ips-receipt-summary[data-needs-review="true"] .ips-receipt-summary-heading{background:linear-gradient(135deg,#fff8e7,#ffedbd);border-bottom-color:#f1d799}
#ips-receipt-summary[data-needs-review="true"] .ips-receipt-summary-icon{border-color:#e8a51c;background:#fff5d6;color:#996000;box-shadow:0 0 0 9px #f4ad181a}
#ips-receipt-summary[data-needs-review="true"] .ips-receipt-summary-heading h3{color:#573900}
#ips-receipt-summary[data-needs-review="true"] .ips-receipt-summary-eyebrow,#ips-receipt-summary[data-needs-review="true"] .ips-receipt-summary-close{color:#825308}
@media(max-width:600px){.ips-receipt-summary-heading{padding:28px 24px 24px}.ips-receipt-summary-heading h3{font-size:1.65rem}.ips-receipt-summary-icon{width:72px;height:72px;font-size:2.2rem}.ips-receipt-summary-body{padding:24px 20px}.ips-receipt-summary-counts{gap:7px}.ips-receipt-summary-counts>div{padding:14px 4px}.ips-receipt-summary-counts dd{font-size:2rem}.ips-receipt-summary-counts dt{font-size:.75rem}#ips-receipt-summary-description{font-size:1.2rem}.ips-receipt-summary-help{font-size:1rem}.ips-receipt-summary-actions{flex-direction:column}}
.ips-batch-fields .ips-help{display:block;margin:6px 0 0}
.ips-layout--readonly{grid-template-columns:minmax(0,1fr)}
#ips-action[hidden]{display:none!important}
.ips-tray-item small[hidden]{display:none!important}
@media(max-width:1100px){.ips-layout{grid-template-columns:minmax(0,1fr)}.ips-tray{position:static}.ips-tray-items{max-height:300px}}@media(max-width:600px){.ips-heading{display:block}.ips-identity{margin-top:12px}.ips-search{flex-wrap:wrap}.ips-journey{gap:12px}.ips-operation-groups a,.ips-operation-tabs a,.ips-warehouse-nav a{font-size:.75rem;padding:7px 9px}}
</style>
@endsection
@section('js')
<script src="{{ asset('js/ips-receipt-confirmation.js') }}?v={{ filemtime(public_path('js/ips-receipt-confirmation.js')) }}" defer></script>
<script src="{{ asset('js/ips-listing.js') }}?v={{ filemtime(public_path('js/ips-listing.js')) }}" defer></script>
@unless($readOnly)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const action = document.getElementById('ips-action'), process = document.getElementById('ips-process'), dialog = document.getElementById('ips-confirm');
    const clearCompleted = document.getElementById('ips-clear-completed');
    const receiptSummary = document.getElementById('ips-receipt-summary');
    const receiptSummaryOpen = document.getElementById('ips-receipt-summary-open');
    let receiptRowsToReview = [];
    receiptSummaryOpen.addEventListener('click', () => receiptSummary.showModal());
    document.getElementById('ips-receipt-summary-close').addEventListener('click', () => receiptSummary.close());
    document.getElementById('ips-receipt-summary-done').addEventListener('click', () => receiptSummary.close());
    document.getElementById('ips-receipt-summary-review').addEventListener('click', () => {
        receiptSummary.close();
        const row = receiptRowsToReview.find(candidate => candidate.isConnected);
        if (row) {
            row.tabIndex = -1;
            row.focus({preventScroll: true});
            row.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
    });
    function showReceiptSummary(batchRows, summaryAction = 'EMG') {
        const delivery = summaryAction === 'EMI';
        const received = batchRows.filter(row => row.dataset.status === 'succeeded').length;
        const pending = batchRows.filter(row => ['processing', 'uncertain'].includes(row.dataset.status)).length;
        const rejected = batchRows.length - received - pending;
        receiptRowsToReview = batchRows.filter(row => row.dataset.status !== 'succeeded');
        receiptSummary.dataset.needsReview = String(receiptRowsToReview.length > 0);
        receiptSummary.dataset.operation = delivery ? 'delivery' : 'reception';
        document.getElementById('ips-receipt-summary-eyebrow').textContent = delivery ? 'ENTREGA AL DESTINATARIO' : 'RECEPCIÓN EN OFICINA';
        document.getElementById('ips-receipt-summary-title').textContent = delivery
            ? (receiptRowsToReview.length ? 'Entrega por revisar' : 'Entrega completada')
            : (receiptRowsToReview.length ? 'Recepción por revisar' : 'Recepción completada');
        document.getElementById('ips-receipt-summary-icon').firstElementChild.className = receiptRowsToReview.length ? 'fas fa-exclamation-triangle' : 'fas fa-check';
        document.getElementById('ips-receipt-summary-description').textContent = received + ' de ' + batchRows.length + (delivery
            ? (batchRows.length === 1 ? ' paquete entregado.' : ' paquetes entregados.')
            : (batchRows.length === 1 ? ' paquete recibido.' : ' paquetes recibidos.'));
        document.getElementById('ips-receipt-summary-success-label').textContent = delivery ? 'Entregados' : 'Recibidos';
        document.getElementById('ips-receipt-summary-rejected-label').textContent = delivery ? 'No entregados' : 'No recibidos';
        document.getElementById('ips-receipt-summary-received').textContent = received;
        document.getElementById('ips-receipt-summary-pending').textContent = pending;
        document.getElementById('ips-receipt-summary-rejected').textContent = rejected;
        document.getElementById('ips-receipt-summary-help').textContent = delivery
            ? (pending ? 'Hay entregas que aún no se han confirmado. Consulta su resultado antes de volver a registrarlas.'
                : (rejected ? 'Algunos paquetes no se entregaron. Revisa el detalle y corrige lo indicado para continuar.' : 'La entrega al destinatario quedó registrada. Puedes continuar con el siguiente paquete.'))
            : (pending ? 'Hay recepciones que aún no se han confirmado. Consulta su resultado antes de intentar otro movimiento.'
                : (rejected ? 'Algunos paquetes no se recibieron. Revisa el detalle y corrige lo indicado para continuar.' : 'La recepción quedó registrada. Puedes preparar el siguiente lote.'));
        document.getElementById('ips-receipt-summary-review').hidden = receiptRowsToReview.length === 0;
        const items = document.getElementById('ips-receipt-summary-items');
        items.replaceChildren();
        batchRows.forEach(row => {
            const item = document.createElement('li');
            const heading = document.createElement('div');
            heading.className = 'ips-receipt-summary-item-heading';
            const code = document.createElement('strong');
            code.textContent = row.dataset.code;
            const state = document.createElement('span');
            const succeeded = row.dataset.status === 'succeeded';
            const awaiting = ['processing', 'uncertain'].includes(row.dataset.status);
            state.className = 'ips-receipt-summary-state' + (succeeded ? ' is-received' : (awaiting ? '' : ' is-rejected'));
            state.textContent = succeeded ? (delivery ? 'Entregado' : 'Recibido') : (awaiting ? 'Sin confirmar' : (delivery ? 'No entregado' : 'No recibido'));
            const message = document.createElement('p');
            message.textContent = row.querySelector('.ips-item-message').textContent;
            heading.append(code, state);
            item.append(heading, message);
            items.appendChild(item);
        });
        receiptSummary.querySelector('details').open = receiptRowsToReview.length > 0;
        receiptSummaryOpen.textContent = delivery ? 'Ver resumen de entrega' : 'Ver resumen de recepción';
        receiptSummaryOpen.hidden = false;
        receiptSummary.showModal();
    }
    let rows = [...document.querySelectorAll('.ips-tray-item')], progress = document.getElementById('ips-progress');
    let handoverEventFilter = null;
    const deliveryActionLabels = @json($deliveryActionLabels);
    const deliveryActionButtons = [...document.querySelectorAll('.ips-delivery-action')];
    const trayCount = document.getElementById('ips-tray-count');
    const trayTitle = document.getElementById('ips-tray-title');
    const deliveryAgentFields = document.getElementById('ips-delivery-agent-fields');
    const deliveryAgentSelect = document.getElementById('ips-delivery-agent');
    const customsFields = document.getElementById('ips-customs-fields');
    const customsOfficeSelect = document.getElementById('ips-customs-office');
    const customsOfficeTypeSelect = document.getElementById('ips-customs-office-type');
    const workspaceRoot = document.querySelector('.ips-workspace');
    const courierOnly = workspaceRoot?.dataset.courierOnly === '1';
    const fixedWorkflow = workspaceRoot?.dataset.workflowAction || '';
    const batchTargetFields = document.getElementById('ips-batch-target-fields');
    const batchTarget = document.getElementById('ips-batch-target');
    const batchTargetLabel = document.getElementById('ips-batch-target-label');
    const batchTargetHelp = document.getElementById('ips-batch-target-help');
    const batchPhysicalFields = document.getElementById('ips-batch-physical-fields');
    const batchPhysicalConfirm = document.getElementById('ips-batch-physical-confirm');
    const batchPhysicalLabel = document.getElementById('ips-batch-physical-label');
    const routeMismatchFields = document.getElementById('ips-batch-route-mismatch-fields');
    const routeMismatchConfirm = document.getElementById('ips-batch-route-mismatch-confirm');
    const batchConditionFields = document.getElementById('ips-batch-condition-fields');
    const batchCondition = document.getElementById('ips-batch-condition');
    const batchTargetConfig = {
        EDN: {selector: '.ips-next-office', label: 'Siguiente oficina para todo el lote', placeholder: 'Selecciona la oficina de destino', key: 'OWN_OFFICE_CD'},
        EIB: {selector: '.ips-domestic-receptacle', label: 'Saca abierta para todo el lote', placeholder: 'Selecciona la saca nacional', key: 'DOM_REC_PID'},
        EMR: {selector: '.ips-exchange-receptacle', label: 'Envase abierto para todo el lote', placeholder: 'Selecciona el envase recibido', key: 'RECPTCL_PID'},
    };
    function isReadyForWorkflow(row) {
        return row.dataset.status === 'ready' && row.dataset.actions.split(',').includes(fixedWorkflow);
    }
    function compatibleBatch(rowsToCheck) {
        const config = batchTargetConfig[fixedWorkflow];
        if (!config) return rowsToCheck;
        const group = [];
        let common = null;
        rowsToCheck.forEach(row => {
            const select = row.querySelector(config.selector);
            const values = new Set([...(select?.options || [])].filter(option => option.value).map(option => option.value));
            const nextCommon = common === null ? values : new Set([...common].filter(value => values.has(value)));
            if (nextCommon.size) {
                group.push(row);
                common = nextCommon;
            }
        });
        return group;
    }
    function selectNextWorkflowBatch() {
        if (!fixedWorkflow) return;
        rows.forEach(row => { row.querySelector('.ips-choice').checked = false; });
        const readyRows = rows.filter(isReadyForWorkflow);
        const nextRows = fixedWorkflow === 'EMG' ? readyRows.slice(0, 1) : compatibleBatch(readyRows);
        nextRows.forEach(row => { row.querySelector('.ips-choice').checked = true; });
    }
    function canJoinSelectedWorkflowBatch(row) {
        const selectedRows = rows.filter(candidate => candidate.querySelector('.ips-choice').checked && isReadyForWorkflow(candidate));
        return !selectedRows.length || compatibleBatch([...selectedRows, row]).includes(row);
    }
    let running = false;
    let confirmationRows = [], confirmationAction = '';
    const receiptSearchQueue = window.IpsReceiptConfirmation.createSearchQueue({
        find: code => rows.find(row => row.dataset.code === code &&
            (isReadyForWorkflow(row) || (['processing', 'uncertain'].includes(row.dataset.status) && row.dataset.payloadAction === 'EMG'))),
        isBlocked: () => running || confirmationRows.length > 0 || Boolean(document.querySelector('dialog[open]')),
        open: row => openOperationConfirmation([row]),
        onIdle: () => { if (fixedWorkflow === 'EMG') document.getElementById('ips-code')?.focus(); },
    });
    window.addEventListener('ips:receipt-requested', event => {
        if (fixedWorkflow === 'EMG') receiptSearchQueue.request(event.detail?.code);
    });
    document.querySelectorAll('dialog').forEach(modal => {
        if (modal !== dialog) modal.addEventListener('close', () => receiptSearchQueue.resume());
    });
    let lastPhysicalSelection = null;
    let lastRouteMismatchSelection = null;
    function syncBatchFields(workflowAction, selectedRows) {
        const config = batchTargetConfig[workflowAction];
        let missingTarget = false;
        if (batchTargetFields && batchTarget && batchTargetLabel && batchTargetHelp) {
            batchTargetFields.hidden = !config || selectedRows.length === 0;
            batchTargetLabel.textContent = config?.label || '';
            batchTarget.required = Boolean(config && selectedRows.length);
            batchTarget.disabled = running || !config || selectedRows.length === 0;
            if (config) {
                const previous = batchTarget.value;
                let common = null;
                let optionLabels = new Map();
                selectedRows.forEach((row, index) => {
                    const local = row.querySelector(config.selector);
                    const options = [...local.options].filter(option => option.value);
                    const values = new Set(options.map(option => option.value));
                    if (index === 0) optionLabels = new Map(options.map(option => [option.value, option.textContent.trim()]));
                    common = common === null ? values : new Set([...common].filter(value => values.has(value)));
                });
                common = common || new Set();
                const previousValue = previous;
                batchTarget.innerHTML = '<option value="">' + (config.placeholder || 'Selecciona una opción') + '</option>';
                [...common].forEach(value => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = optionLabels.get(value) || value;
                    batchTarget.appendChild(option);
                });
                const preferred = workflowAction === 'EMR' ? (workspaceRoot?.dataset.preferredReceptacle || '') : '';
                const chosen = common.has(previousValue) ? previousValue
                    : (common.has(preferred) ? preferred : (common.size === 1 ? [...common][0] : ''));
                batchTarget.value = chosen;
                selectedRows.forEach(row => {
                    const local = row.querySelector(config.selector);
                    local.value = chosen && [...local.options].some(option => option.value === chosen) ? chosen : '';
                });
                missingTarget = selectedRows.length > 0 && (!common.size || !chosen);
                batchTargetHelp.textContent = selectedRows.length === 0 ? ''
                    : (!common.size ? 'Los paquetes necesitan destinos o envases distintos. Sepáralos en lotes compatibles.'
                        : (!chosen ? 'Elige una opción común para los paquetes seleccionados.' : 'La opción se aplicará a todos los paquetes seleccionados.'));
                batchTargetHelp.hidden = selectedRows.length === 0;
            } else {
                batchTargetHelp.textContent = '';
                batchTarget.value = '';
            }
        }
        const needsPhysicalReceipt = ['EMR', 'CUSTOMS_RETURN'].includes(workflowAction);
        let missingPhysicalReceipt = false;
        if (batchPhysicalFields && batchPhysicalConfirm && batchPhysicalLabel) {
            const selectionKey = selectedRows.map(row => row.dataset.code).sort().join('|');
            if (selectionKey !== lastPhysicalSelection) {
                batchPhysicalConfirm.checked = workflowAction === 'EMR' && selectedRows.length > 0
                    && selectedRows.every(row => row.dataset.scanned === '1');
                lastPhysicalSelection = selectionKey;
            }
            batchPhysicalFields.hidden = !needsPhysicalReceipt || selectedRows.length === 0;
            batchPhysicalConfirm.disabled = running || !needsPhysicalReceipt || selectedRows.length === 0;
            batchPhysicalConfirm.required = needsPhysicalReceipt && selectedRows.length > 0;
            batchPhysicalLabel.textContent = workflowAction === 'CUSTOMS_RETURN'
                ? 'Confirmo que Aduana devolvió físicamente estos paquetes a esta oficina.'
                : (workflowAction === 'EMG'
                    ? 'Confirmo que estos paquetes llegaron físicamente a esta oficina de entrega.'
                    : 'Confirmo que estos paquetes están físicamente en el envase recibido y abierto.');
            selectedRows.forEach(row => {
                const perItemConfirm = row.querySelector('.ips-physical-receipt-confirm');
                perItemConfirm.checked = batchPhysicalConfirm.checked;
            });
            missingPhysicalReceipt = needsPhysicalReceipt && selectedRows.length > 0 && !batchPhysicalConfirm.checked;
        }
        const routeMismatchRows = selectedRows.filter(row => row.dataset.routeMismatch === '1');
        let missingRouteMismatchConfirmation = false;
        if (routeMismatchFields && routeMismatchConfirm) {
            const selectionKey = routeMismatchRows.map(row => row.dataset.code).sort().join('|');
            if (selectionKey !== lastRouteMismatchSelection) {
                routeMismatchConfirm.checked = false;
                lastRouteMismatchSelection = selectionKey;
            }
            routeMismatchFields.hidden = true;
            routeMismatchConfirm.disabled = true;
            routeMismatchConfirm.required = false;
        }
        return {missingTarget, missingPhysicalReceipt, missingRouteMismatchConfirmation};
    }
    function update() {
        const workspace = document.querySelector('.ips-workspace');
        const deliveryOnly = workspace?.dataset.deliveryOnly === '1';
        const workflowAction = workspace?.dataset.workflowAction || '';
        const workflowTitles = {EMR: 'Registrar paquetes de la saca', EDN: 'Clasificar paquetes por destino', EIB: 'Agrupar en saca nacional', EMG: 'Recibir paquetes en destino', CUSTOMS_RETURN: 'Recibir devolución de Aduana'};
        if (!deliveryOnly && trayTitle && workflowTitles[workflowAction]) trayTitle.textContent = workflowTitles[workflowAction];
        const candidateRows = rows.filter(row => ['ready','processing','uncertain'].includes(row.dataset.status));
        const availableOptions = [...action.options].map(option => option.value).filter(Boolean);
        const allowedLabels = deliveryOnly ? deliveryActionLabels : Object.fromEntries(availableOptions.map(code => [code, code]));
        const eligibleRowsForAction = (code, eventFilter = null) => candidateRows.filter(row => {
            const allowed = row.dataset.actions.split(',').includes(code);
            const retry = ['processing','uncertain'].includes(row.dataset.status) && row.dataset.payloadAction === code;
            const matchingEvent = !eventFilter || Number(row.dataset.event) === Number(eventFilter);
            return matchingEvent && (retry || (row.dataset.status === 'ready' && allowed));
        });
        const deliveryChoices = deliveryOnly ? deliveryActionButtons.map(button => ({
            button,
            action: button.dataset.action,
            eventFilter: button.dataset.eventFilter || null,
            rows: eligibleRowsForAction(button.dataset.action, button.dataset.eventFilter || null),
        })).filter(choice => choice.rows.length > 0) : [];
        const candidateActions = deliveryOnly
            ? availableOptions.filter(code => Object.hasOwn(allowedLabels, code) && eligibleRowsForAction(code).length > 0)
            : [...new Set(candidateRows.flatMap(row => row.dataset.actions.split(',').filter(Boolean)))].filter(value => Object.hasOwn(allowedLabels, value));
        const selectedDeliveryFilter = action.value === 'HANDOVER_AGENT' ? String(handoverEventFilter || '') : '';
        const selectedDeliveryChoiceIsValid = deliveryChoices.some(choice => choice.action === action.value && String(choice.eventFilter || '') === selectedDeliveryFilter);
        if (deliveryOnly && action.value && !selectedDeliveryChoiceIsValid) {
            action.value = '';
            handoverEventFilter = null;
        }
        if (deliveryOnly && !action.value && deliveryChoices.length === 1) {
            const [choice] = deliveryChoices;
            action.value = choice.action;
            handoverEventFilter = choice.action === 'HANDOVER_AGENT' ? choice.eventFilter : null;
            const eligible = new Set(choice.rows);
            rows.forEach(row => { row.querySelector('.ips-choice').checked = eligible.has(row); });
        } else if (!deliveryOnly && candidateActions.length === 1 && !workflowAction) {
            action.value = candidateActions[0];
        }
        if (deliveryAgentSelect && action.value !== 'HANDOVER_AGENT') deliveryAgentSelect.value = '';
        const automaticAction = workflowAction || (candidateActions.length === 1 ? candidateActions[0] : null);
        action.disabled = running || Boolean(workflowAction) || (deliveryOnly && deliveryChoices.length === 1) || (!deliveryOnly && Boolean(automaticAction));
        if (deliveryOnly) {
            deliveryActionButtons.forEach(button => {
                const eventFilter = button.dataset.eventFilter || null;
                const count = eligibleRowsForAction(button.dataset.action, eventFilter).length;
                button.hidden = count === 0;
                button.disabled = running || count === 0;
                const selectedFilter = button.dataset.action === 'HANDOVER_AGENT' ? String(handoverEventFilter || '') : '';
                const active = button.dataset.action === action.value && String(eventFilter || '') === selectedFilter && count > 0;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
                button.querySelector('[data-action-count]').textContent = count === 1 ? '1 paquete compatible' : count + ' paquetes compatibles';
            });
            document.querySelectorAll('[data-action-group]').forEach(group => {
                group.hidden = !group.querySelector('.ips-delivery-action:not([hidden])');
            });
            document.querySelector('.ips-delivery-actions').hidden = deliveryChoices.length <= 1;
        }
        rows.forEach(row => {
            const allowed = row.dataset.actions.split(',').includes(action.value);
            const retry = ['processing','uncertain'].includes(row.dataset.status) && row.dataset.payloadAction === action.value;
            const eventMatches = action.value !== 'HANDOVER_AGENT' || !handoverEventFilter || Number(row.dataset.event) === Number(handoverEventFilter);
            const enabled = eventMatches && (retry || (row.dataset.status === 'ready' && allowed));
            const check = row.querySelector('.ips-choice');
            check.hidden = deliveryOnly;
            check.disabled = running || !enabled;
            if (!enabled) check.checked = false;
            row.classList.toggle('is-ineligible', !enabled);
            row.querySelector('.ips-signatory').hidden = action.value !== 'EMI';
            row.querySelector('.ips-delivery-fields').hidden = action.value !== 'EMI';
            row.querySelector('.ips-attempt-fields').hidden = action.value !== 'EMH';
            const receiptFields = row.querySelector('.ips-physical-receipt');
            const receiptConfirm = row.querySelector('.ips-physical-receipt-confirm');
            receiptFields.hidden = true;
            receiptConfirm.required = false;
            receiptConfirm.disabled = true;
            row.querySelector('.ips-exchange-receptacle-fields').hidden = true;
            row.querySelector('.ips-exchange-receptacle').required = false;
            row.querySelector('.ips-condition-fields').hidden = action.value !== 'EMR';
            row.querySelector('.ips-package-condition').required = action.value === 'EMR';
            row.querySelector('.ips-route-fields').hidden = true;
            row.querySelector('.ips-sack-fields').hidden = true;
            row.querySelector('.ips-next-office').required = false;
            row.querySelector('.ips-domestic-receptacle').required = false;
        });
        const selectedRows = rows.filter(row => row.querySelector('.ips-choice').checked);
        const count = selectedRows.length;
        if (clearCompleted) clearCompleted.hidden = !rows.some(row => row.dataset.status === 'succeeded');
        const batchState = syncBatchFields(workflowAction, selectedRows);
        if (batchConditionFields) batchConditionFields.hidden = workflowAction !== 'EMR' || count === 0;
        if (deliveryAgentFields && deliveryAgentSelect) {
            const needsAgent = action.value === 'HANDOVER_AGENT';
            deliveryAgentFields.hidden = !needsAgent;
            deliveryAgentSelect.required = needsAgent && count > 0;
            deliveryAgentSelect.disabled = running || !needsAgent;
        }
        const needsCustoms = action.value === 'CUSTOMS_PRESENT';
        const customsDetailsMissing = needsCustoms && (!customsOfficeSelect?.value || !customsOfficeTypeSelect?.value);
        if (customsFields && customsOfficeSelect && customsOfficeTypeSelect) {
            customsFields.hidden = !needsCustoms;
            customsOfficeSelect.required = needsCustoms && count > 0;
            customsOfficeTypeSelect.required = needsCustoms && count > 0;
            customsOfficeSelect.disabled = running || !needsCustoms;
            customsOfficeTypeSelect.disabled = running || !needsCustoms;
        }
        process.disabled = running || count === 0 || batchState.missingTarget || batchState.missingPhysicalReceipt || batchState.missingRouteMismatchConfirmation || Boolean(deliveryAgentSelect && action.value === 'HANDOVER_AGENT' && !deliveryAgentSelect.value) || Boolean(customsDetailsMissing);
        if (deliveryOnly) {
            const selectedRows = rows.filter(row => row.querySelector('.ips-choice').checked);
            let label = deliveryActionLabels[action.value] || 'Registrar movimiento';
            if (action.value === 'HANDOVER_AGENT' && selectedRows.length) {
                const selectedEvents = selectedRows.map(row => Number(row.dataset.event));
                label = selectedEvents.every(event => event === 36) ? 'Asignar cartero'
                    : (selectedEvents.every(event => event === 32) ? 'Entregar al cartero' : 'Asignar al cartero');
            }
            process.textContent = count
                ? (action.value === 'HANDOVER_AGENT' && !deliveryAgentSelect?.value ? 'Selecciona un cartero'
                    : (customsDetailsMissing ? 'Selecciona oficina y modalidad de Aduana' : label + ' · ' + count + (count === 1 ? ' paquete' : ' paquetes')))
                : (action.value ? 'Selecciona al menos un paquete' : 'Elige un paso para continuar');
            process.disabled = running || count === 0 || (action.value === 'HANDOVER_AGENT' && !deliveryAgentSelect?.value) || Boolean(customsDetailsMissing);
            if (trayTitle) {
                trayTitle.textContent = action.value === 'HANDOVER_AGENT' && selectedRows.length && selectedRows.every(row => Number(row.dataset.event) === 36)
                    ? 'Asignar para reintento'
                    : 'Procesar entregas';
            }
            if (trayCount) trayCount.textContent = rows.length + '/50 en bandeja';
        } else {
            const batchLabels = {EMR: 'Registrar paquetes', EDN: 'Registrar despacho', EIB: 'Asociar a saca', EMG: 'Registrar recepción en IPS', CUSTOMS_RETURN: 'Registrar devolución de Aduana (38)', HANDOVER_AGENT: 'Asignar cartero', EDG: 'Registrar salida a reparto', EMH: 'Registrar intento fallido'};
            process.textContent = count
                ? (batchState.missingRouteMismatchConfirmation
                    ? 'Confirma recepción con ruta distinta · ' + count + (count === 1 ? ' paquete' : ' paquetes')
                    : (batchState.missingPhysicalReceipt
                        ? (action.value === 'CUSTOMS_RETURN' ? 'Confirma devolución física de Aduana · ' : 'Confirma llegada física · ') + count + (count === 1 ? ' paquete' : ' paquetes')
                        : (batchLabels[action.value] || 'Registrar movimiento') + ' · ' + count + (count === 1 ? ' paquete' : ' paquetes')))
                : 'Selecciona paquetes compatibles';
        }
    }
    action.addEventListener('change', () => {
        if (action.value !== 'HANDOVER_AGENT') handoverEventFilter = null;
        update();
    });
    batchTarget?.addEventListener('change', update);
    batchPhysicalConfirm?.addEventListener('change', update);
    routeMismatchConfirm?.addEventListener('change', update);
    batchCondition?.addEventListener('change', () => {
        if (batchCondition.value) {
            rows.filter(row => row.querySelector('.ips-choice').checked)
                .forEach(row => { row.querySelector('.ips-package-condition').value = batchCondition.value; });
        }
        update();
    });
    deliveryAgentSelect?.addEventListener('change', update);
    customsOfficeSelect?.addEventListener('change', update);
    customsOfficeTypeSelect?.addEventListener('change', update);
    document.getElementById('ips-delivery-action-groups')?.addEventListener('click', event => {
        const button = event.target.closest('.ips-delivery-action');
        if (!button || button.disabled || running) return;
        const code = button.dataset.action;
        const eventFilter = button.dataset.eventFilter || null;
        handoverEventFilter = code === 'HANDOVER_AGENT' ? eventFilter : null;
        const eligible = new Set(rows.filter(row => {
            const allowed = row.dataset.actions.split(',').includes(code);
            const retry = ['processing','uncertain'].includes(row.dataset.status) && row.dataset.payloadAction === code;
            return (!eventFilter || Number(row.dataset.event) === Number(eventFilter)) && (retry || (row.dataset.status === 'ready' && allowed));
        }));
        if (!eligible.size) return;
        action.value = code;
        rows.forEach(row => {
            row.querySelector('.ips-choice').checked = eligible.has(row);
        });
        action.dispatchEvent(new Event('change', {bubbles: true}));
    });
    function choiceChanged(event) {
        if (fixedWorkflow === 'EMG' && event.target.checked) {
            rows.forEach(row => {
                const checkbox = row.querySelector('.ips-choice');
                if (checkbox !== event.target) checkbox.checked = false;
            });
        }
        update();
    }
    rows.forEach(row => row.querySelector('.ips-choice').addEventListener('change', choiceChanged));
    window.addEventListener('ips:selection-added', event => {
        const data = event.detail || {}, packageData = data.package || {}, tray = document.querySelector('.ips-tray-items');
        if (!tray || !data.code) return;
        if (rows.some(row => row.dataset.code === data.code)) {
            if (fixedWorkflow === 'EMG') receiptSearchQueue.request(data.code);
            return;
        }
        const article = document.createElement('article');
        article.className = 'ips-tray-item'; article.dataset.code = data.code; article.dataset.event = Number(packageData.operational_event_cd ?? packageData.event_cd ?? 0); article.dataset.routeMismatch = data.route_mismatch_receipt ? '1' : '0';
        article.dataset.actions = (packageData.allowed_actions || []).join(','); article.dataset.status = 'ready'; article.dataset.payloadAction = ''; article.dataset.scanned = data.scanned ? '1' : '0';
        article.dataset.receiptOffices = JSON.stringify(packageData.receipt_office_options || []);
        article.dataset.receiptLockedOffice = String(packageData.receipt_office_locked_cd ?? '');
        article.dataset.workbenchOfficeCd = String(packageData.workbench_office_cd ?? '');
        article.dataset.upuReceipt = JSON.stringify({destinatario: packageData.recipient ?? '', telefono: packageData.phone ?? '', zona: packageData.address ?? '', peso: packageData.weight_kg ?? ''});
        article.dataset.customsReturn = [31, 34, 1251].includes(Number(packageData.operational_event_cd ?? packageData.event_cd)) && Number(packageData.state_cd) === 1 ? '1' : '0';
        const deliveryModeField = courierOnly
            ? '<div class="ips-delivery-fields mt-2" hidden><label class="ips-field-label">Modalidad efectiva de entrega</label><input type="hidden" class="ips-delivery-mode" value="DOMICILIARIA"><span class="d-block">Cartero · domiciliaria</span></div>'
            : '<div class="ips-delivery-fields mt-2" hidden><label class="ips-field-label">Modalidad efectiva de entrega</label><select class="form-control form-control-sm ips-delivery-mode"><option value="">Seleccionar modalidad</option><option value="NO_DOMICILIARIA">Ventanilla · no domiciliaria</option><option value="DOMICILIARIA">Cartero · domiciliaria</option></select></div>';
        article.innerHTML = '<div class="d-flex align-items-center"><input class="ips-choice mr-2" type="checkbox" aria-label="Seleccionar '+data.code+'"><strong>'+data.code+'</strong><form class="ml-auto ips-selection-remove" method="POST" action="'+(data.remove_url || '')+'"><input type="hidden" name="_token" value="'+document.querySelector('meta[name="csrf-token"]')?.content+'"><input type="hidden" name="_method" value="DELETE"><button type="submit" class="btn btn-sm text-muted" aria-label="Quitar '+data.code+'"><i class="fas fa-times"></i></button></form></div>'
            + (fixedWorkflow === 'EMG' ? '<div class="ips-upu-zone-fields mt-2"><label class="ips-field-label">Zona / dirección<textarea class="form-control form-control-sm ips-upu-zone" rows="2" maxlength="1000"></textarea></label><small>Dirección de IPS. Puedes editarla para guardarla en Bolipost al recibir.</small></div>' : '')
            + '<small class="ips-item-stage"></small><div class="ips-physical-receipt mt-2" hidden><label class="ips-receipt-confirm-label"><input class="ips-physical-receipt-confirm mr-1" type="checkbox"> Confirmo que el envío llegó físicamente a esta oficina.</label></div><small class="ips-item-message" role="status" hidden></small>'
            + '<input class="form-control form-control-sm ips-signatory mt-2" maxlength="64" placeholder="Persona que recibió (opcional)" aria-label="Receptor real de '+data.code+'" hidden>'
            + '<div class="ips-delivery-fields mt-2" hidden><label class="ips-field-label">Modalidad efectiva de entrega</label><select class="form-control form-control-sm ips-delivery-mode"><option value="">Seleccionar modalidad</option><option value="NO_DOMICILIARIA">Ventanilla · no domiciliaria</option><option value="DOMICILIARIA">Cartero · domiciliaria</option></select></div>'
            + '<div class="ips-attempt-fields mt-2" hidden><label class="ips-field-label">Motivo del intento fallido</label><select class="form-control form-control-sm ips-nondelivery-reason"><option value="">Seleccionar motivo</option></select><label class="ips-field-label mt-2">Medida aplicada</label><select class="form-control form-control-sm ips-nondelivery-measure"><option value="">Seleccionar medida</option></select></div>'
            + '<div class="ips-route-fields mt-2" hidden><label class="ips-field-label">Siguiente oficina de la ruta</label><select class="form-control form-control-sm ips-next-office" required><option value="">Seleccionar oficina</option></select></div>'
            + '<div class="ips-sack-fields mt-2" hidden><label class="ips-field-label">Saca nacional abierta</label><select class="form-control form-control-sm ips-domestic-receptacle" required><option value="">Seleccionar saca</option></select></div>'
            + '<div class="ips-exchange-receptacle-fields mt-2" hidden><label class="ips-field-label">Envase internacional recibido y abierto</label><select class="form-control form-control-sm ips-exchange-receptacle" required><option value="">Seleccionar envase</option></select></div>'
            + '<div class="ips-condition-fields mt-2" hidden><label class="ips-field-label">Condicion fisica del paquete</label><select class="form-control form-control-sm ips-package-condition"><option value="">Revisar y seleccionar</option></select></div>';
        if (courierOnly) article.querySelector('.ips-delivery-fields').outerHTML = deliveryModeField;
        const upuZone = article.querySelector('.ips-upu-zone');
        if (upuZone) upuZone.value = packageData.address ?? '';
        article.querySelector('.ips-item-stage').textContent = 'Etapa actual · ' + ((packageData.stage && packageData.stage.label) || 'Paquete IPS');
        if (packageData.workbench_office_name) {
            const officeLabel = document.createElement('small');
            officeLabel.className = 'ips-item-office';
            officeLabel.textContent = packageData.needs_receipt_office ? 'Oficina de recepción asignada automáticamente' : packageData.workbench_office_name;
            article.querySelector('.ips-item-stage').after(officeLabel);
        }
        if (data.route_mismatch_receipt) {
            const routeNote = document.createElement('p');
            routeNote.className = 'alert alert-warning py-2 px-2 mt-2 mb-2';
            routeNote.textContent = 'Ruta IPS: ' + (packageData.next_office_name || 'otra oficina') + '. Se intentará registrar evento 32 aquí; no se agregará un evento de cambio de ruta. La operación depende de la respuesta IPS.';
            article.querySelector('.ips-item-stage').insertAdjacentElement('afterend', routeNote);
        }
        const reasonCatalog = @json($catalog['non_delivery_reasons'] ?? []);
        const measureCatalog = @json($catalog['non_delivery_measures'] ?? []);
        const itemConditionCatalog = @json($catalog['item_conditions'] ?? []);
        const appendOptions = (select, items, idKey, labelKey) => items.forEach(item => {
            const option = document.createElement('option'); option.value = item[idKey]; option.textContent = item[labelKey]; select.appendChild(option);
        });
        appendOptions(article.querySelector('.ips-nondelivery-reason'), reasonCatalog, 'NON_DELIVERY_REASON_CD', 'NON_DELIVERY_REASON_NM');
        appendOptions(article.querySelector('.ips-nondelivery-measure'), measureCatalog, 'NON_DELIVERY_MEASURE_CD', 'NON_DELIVERY_MEASURE_NM');
        appendOptions(article.querySelector('.ips-package-condition'), itemConditionCatalog, 'ITEM_CONDITION_CD', 'ITEM_CONDITION_NM');
        (packageData.next_offices || []).forEach(office => {
            const option = document.createElement('option');
            option.value = office.OWN_OFFICE_CD;
            option.textContent = office.OFFICE_NM + (office.OFFICE_FCD ? ' · ' + office.OFFICE_FCD : '');
            article.querySelector('.ips-next-office').appendChild(option);
        });
        (packageData.domestic_receptacles || []).forEach(receptacle => {
            const option = document.createElement('option');
            option.value = receptacle.DOM_REC_PID;
            option.textContent = receptacle.DOM_REC_FID + ' · destino ' + receptacle.DEST_OWN_OFFICE_CD + ' · ' + (receptacle.MAILITEMCNT || 0) + ' envíos';
            article.querySelector('.ips-domestic-receptacle').appendChild(option);
        });
        (packageData.exchange_receptacles || []).forEach(receptacle => {
            const option = document.createElement('option');
            option.value = receptacle.RECPTCL_PID;
            option.textContent = receptacle.RECPTCL_FID + ' · ' + (receptacle.RECPTCL_MAILITMS_NO == null ? 'cantidad no declarada' : receptacle.RECPTCL_MAILITMS_NO + ' declarados');
            article.querySelector('.ips-exchange-receptacle').appendChild(option);
        });
        tray.querySelector('.ips-empty')?.remove(); tray.appendChild(article); rows.push(article);
        article.querySelector('.ips-choice').addEventListener('change', choiceChanged);
        if (fixedWorkflow && fixedWorkflow !== 'EMG') {
            const hasActiveBatch = rows.some(row => row !== article && row.querySelector('.ips-choice').checked && isReadyForWorkflow(row));
            if (!hasActiveBatch) {
                selectNextWorkflowBatch();
            } else if (isReadyForWorkflow(article) && canJoinSelectedWorkflowBatch(article)) {
                article.querySelector('.ips-choice').checked = true;
            } else {
                const message = article.querySelector('.ips-item-message');
                message.textContent = 'En bandeja para otro lote: su destino o saca no coincide.';
                message.hidden = false;
            }
        }
        update();
        if (fixedWorkflow === 'EMG') receiptSearchQueue.request(data.code);
    });
    document.addEventListener('submit', async event => {
        const form = event.target.closest('.ips-selection-remove');
        if (!form) return;
        event.preventDefault();
        const article = form.closest('.ips-tray-item'), button = form.querySelector('button');
        button.disabled = true;
        try {
            const response = await fetch(form.action, {method:'DELETE', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''}, credentials:'same-origin'});
            const result = await response.json();
            if (!response.ok || !result.removed) throw new Error(result.message || 'No se pudo quitar el paquete.');
            rows = rows.filter(row => row !== article); article.remove();
            if (trayCount) trayCount.textContent = result.count + '/50 en bandeja';
            window.dispatchEvent(new CustomEvent('ips:selection-removed', {detail: result}));
            update();
        } catch (error) { button.disabled = false; progress.textContent = error.message; }
    });
    clearCompleted?.addEventListener('click', async () => {
        const completed = rows.filter(row => row.dataset.status === 'succeeded');
        if (!completed.length) return;
        clearCompleted.disabled = true;
        clearCompleted.textContent = 'Preparando...';
        running = true;
        update();
        const scanInput = document.getElementById('ips-code');
        if (scanInput) scanInput.disabled = true;
        let removed = 0;
        for (const row of completed) {
            const form = row.querySelector('.ips-selection-remove');
            if (!form) continue;
            try {
                const response = await fetch(form.action, {
                    method: 'DELETE',
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''},
                    credentials: 'same-origin',
                });
                const result = await response.json();
                if (!response.ok || !result.removed) throw new Error(result.message || 'No se pudo retirar un paquete confirmado.');
                rows = rows.filter(candidate => candidate !== row);
                row.remove();
                if (trayCount) trayCount.textContent = result.count + '/50 en bandeja';
                window.dispatchEvent(new CustomEvent('ips:selection-removed', {detail: result}));
                removed++;
            } catch (error) {
                progress.textContent = error.message;
            }
        }
        if (!rows.length) {
            const empty = document.createElement('div');
            empty.className = 'ips-empty';
            empty.innerHTML = '<i class="fas fa-inbox"></i><p>Tu bandeja está vacía.<br>Escanea los paquetes del siguiente lote.</p>';
            document.querySelector('.ips-tray-items')?.appendChild(empty);
        }
        running = false;
        if (fixedWorkflow && !rows.some(row => row.querySelector('.ips-choice').checked)) selectNextWorkflowBatch();
        if (scanInput) { scanInput.disabled = false; scanInput.focus(); }
        clearCompleted.disabled = false;
        clearCompleted.textContent = 'Preparar siguiente lote';
        update();
        if (removed) progress.textContent = removed + ' paquetes confirmados retirados de la bandeja. Sus eventos siguen en IPS.';
    });
    process.addEventListener('click', () => {
        const selected = rows.filter(row => row.querySelector('.ips-choice').checked);
        openOperationConfirmation(fixedWorkflow === 'EMG' ? selected.slice(0, 1) : selected);
    });
    function openOperationConfirmation(selected) {
        if (running || dialog.open || !selected.length) return;
        if (fixedWorkflow === 'EMG') {
            action.value = 'EMG';
            rows.forEach(row => { row.querySelector('.ips-choice').checked = selected.includes(row); });
            update();
        }
        if (batchTarget && !batchTargetFields.hidden && !batchTarget.value) {
            batchTarget.reportValidity();
            return;
        }
        if (batchPhysicalConfirm && !batchPhysicalFields.hidden && !batchPhysicalConfirm.checked) {
            batchPhysicalConfirm.reportValidity();
            return;
        }
        if (routeMismatchConfirm && !routeMismatchFields.hidden && !routeMismatchConfirm.checked) {
            routeMismatchConfirm.reportValidity();
            return;
        }
        for (const row of selected) {
            if (action.value === 'EMR' && !row.querySelector('.ips-package-condition').value) { row.querySelector('.ips-package-condition').reportValidity(); return; }
        }
        if (deliveryAgentSelect && action.value === 'HANDOVER_AGENT' && !deliveryAgentSelect.value) {
            deliveryAgentSelect?.reportValidity();
            return;
        }
        if (action.value === 'CUSTOMS_PRESENT') {
            if (!customsOfficeSelect?.value) { customsOfficeSelect?.reportValidity(); return; }
            if (!customsOfficeTypeSelect?.value) { customsOfficeTypeSelect?.reportValidity(); return; }
        }
        const targetDescription = batchTarget && !batchTargetFields.hidden
            ? ' Destino común: ' + batchTarget.options[batchTarget.selectedIndex]?.textContent.trim() + '.'
            : '';
        dialog.dataset.operation = action.value === 'EMI' ? 'delivery' : (action.value === 'EMG' ? 'receipt' : '');
        window.IpsReceiptConfirmation.reset(dialog);
        if (action.value === 'EMG') window.IpsReceiptConfirmation.prepare(dialog, selected,
            workspaceRoot?.dataset.upuDestination || '', workspaceRoot?.dataset.receiptPreferredOffice || '');
        document.getElementById('ips-confirm-title').textContent = action.value === 'EMI' ? 'Confirmar entrega al destinatario' : (action.value === 'EMG' ? 'Confirmar recepción de paquetes' : 'Confirmar movimiento');
        document.getElementById('ips-confirm-submit').textContent = action.value === 'EMI' ? 'Sí, registrar entrega' : (action.value === 'EMG' ? 'Sí, recibir y registrar UPU' : 'Confirmar y registrar');
        document.getElementById('ips-confirm-description').textContent = (action.value === 'CUSTOMS_PRESENT'
                ? 'Se registrará el envío a Aduana para los paquetes seleccionados.'
                : (deliveryActionLabels[action.value] || action.options[action.selectedIndex]?.textContent || 'Se registrará el movimiento seleccionado en IPS.'));
        if (targetDescription) document.getElementById('ips-confirm-description').textContent += targetDescription;
        if (action.value === 'EMG') document.getElementById('ips-confirm-description').textContent = 'Revisa los paquetes seleccionados y confirma su llegada física. Cada recepción se registrará en su oficina correspondiente.';
        document.getElementById('ips-confirm-codes').textContent = selected.map(row=>row.dataset.code).join(', ');
        if (fixedWorkflow && action.value !== 'EMG') {
            executeBatch(selected, action.value);
        } else {
            confirmationRows = [...selected];
            confirmationAction = action.value;
            dialog.returnValue = '';
            dialog.showModal();
        }
    }
    async function executeBatch(selected, chosenAction, receiptConfirmation = null) {
        if (running) return;
        running = true; action.disabled = true; update();
        let successes = 0;
        for (let i = 0; i < selected.length; i++) {
            const row = selected[i];
            progress.textContent = chosenAction === 'EMG'
                ? 'Registrando recepción en IPS (' + (i+1) + ' de ' + selected.length + ')…'
                : 'Procesando ' + (i+1) + ' de ' + selected.length + '…';
            try {
                const operation = {codigo:row.dataset.code,action:chosenAction,confirmed:true,signatory:row.querySelector('.ips-signatory').value || null};
                if (chosenAction === 'EMR') operation.physical_receipt_confirmed = batchPhysicalConfirm?.checked || false;
                if (chosenAction === 'CUSTOMS_RETURN') {
                    operation.physical_receipt_confirmed = batchPhysicalConfirm?.checked || false;
                    operation.receptacle_pid = workspaceRoot?.dataset.preferredReceptacle || '';
                }
                if (chosenAction === 'EMG') operation.physical_receipt_confirmed = receiptConfirmation?.physical ?? batchPhysicalConfirm?.checked ?? false;
                const upuZone = row.querySelector('.ips-upu-zone');
                if (chosenAction === 'EMG' && upuZone) {
                    operation.zona = upuZone.value;
                    upuZone.disabled = true;
                }
                if (chosenAction === 'EMG') {
                    const upuReceipt = receiptConfirmation?.upu[row.dataset.code];
                    if (upuReceipt) operation.upu_receipt = upuReceipt;
                    const receiptOffice = receiptConfirmation?.offices[row.dataset.code];
                    if (receiptOffice) operation.receipt_office_cd = receiptOffice;
                    if (row.dataset.routeMismatch === '1' || receiptOffice || window.IpsReceiptConfirmation.officeOptions(row).length) operation.route_mismatch_confirmed = receiptConfirmation?.routeMismatch ?? false;
                }
                if (chosenAction === 'EMI') operation.delivery_mode = row.querySelector('.ips-delivery-mode').value;
                if (chosenAction === 'EDN') operation.next_office_cd = row.querySelector('.ips-next-office').value;
                if (chosenAction === 'EIB') operation.domestic_receptacle_pid = row.querySelector('.ips-domestic-receptacle').value;
                if (chosenAction === 'EMR') { operation.exchange_receptacle_pid = row.querySelector('.ips-exchange-receptacle').value; operation.condition_cd = row.querySelector('.ips-package-condition').value; }
                if (chosenAction === 'HANDOVER_AGENT') {
                    operation.delivery_agent_cd = deliveryAgentSelect.value;
                }
                if (chosenAction === 'CUSTOMS_PRESENT') {
                    operation.customs_office_cd = customsOfficeSelect.value;
                    operation.customs_office_type_cd = customsOfficeTypeSelect.value;
                }
                if (chosenAction === 'EMH') {
                    operation.non_delivery_reason = row.querySelector('.ips-nondelivery-reason').value;
                    operation.non_delivery_measure = row.querySelector('.ips-nondelivery-measure').value;
                }
                const response = await fetch(document.querySelector('.ips-workspace')?.dataset.operateUrl || @json(route('ips.operate', \App\Support\IpsOfficeContext::parameters(request(), []))), {
                    method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},
                    body:JSON.stringify(operation)
                });
                const result = await response.json();
                const validationOnly = response.status === 422;
                const editableRejection = ['needs_reason','needs_physical_confirmation'].includes(result.status) || validationOnly;
                if (editableRejection && upuZone) upuZone.disabled = false;
                row.dataset.status = editableRejection ? 'ready' : (result.status || 'rejected');
                row.dataset.payloadAction = editableRejection ? '' : chosenAction;
                row.querySelector('.ips-item-message').textContent = result.message || 'Revise la respuesta antes de continuar.';
                row.querySelector('.ips-item-message').hidden = false;
                if (!editableRejection) row.querySelectorAll('input:not(.ips-choice), select, textarea').forEach(field => field.disabled = true);
                if (result.status === 'succeeded') successes++;
                if (result.status === 'succeeded' && result.selection_removed) {
                    rows = rows.filter(candidate => candidate !== row);
                    row.remove();
                    if (trayCount) trayCount.textContent = rows.length + '/50 en bandeja';
                    window.dispatchEvent(new CustomEvent('ips:selection-removed', {detail: {...result, received: true}}));
                }
            } catch(e) {
                row.dataset.status = 'uncertain'; row.dataset.payloadAction = chosenAction;
                row.querySelector('.ips-item-message').hidden = false;
                row.querySelector('.ips-item-message').textContent = 'Sin confirmación. Actualiza y reintenta la misma acción.';
            }
            row.querySelector('.ips-choice').checked = false;
        }
        running = false; action.disabled = false;
        if (chosenAction === 'EMG' && !rows.length && !document.querySelector('.ips-tray-items .ips-empty')) {
            const empty = document.createElement('div');
            empty.className = 'ips-empty';
            empty.innerHTML = '<i class="fas fa-inbox"></i><p>Tu bandeja está vacía.<br>Escanea los paquetes del siguiente lote.</p>';
            document.querySelector('.ips-tray-items')?.appendChild(empty);
        }
        if (fixedWorkflow && !rows.some(row => row.querySelector('.ips-choice').checked)) selectNextWorkflowBatch();
        update();
        progress.textContent = chosenAction === 'EMG'
            ? successes + ' recepciones registradas en IPS de ' + selected.length + '. Revisa el resultado de cada paquete.'
            : (chosenAction === 'EMI'
                ? successes + ' entregas registradas de ' + selected.length + '. Revisa el resultado de cada paquete.'
                : successes + ' confirmados de ' + selected.length + '. Revisa el resultado de cada paquete.');
        document.getElementById('ips-refresh').hidden = false;
        receiptSummaryOpen.hidden = !['EMG', 'EMI'].includes(chosenAction);
        if (['EMG', 'EMI'].includes(chosenAction)) showReceiptSummary(selected, chosenAction);
    }
    dialog.addEventListener('close', () => {
        const selected = confirmationRows, chosenAction = confirmationAction;
        confirmationRows = [];
        confirmationAction = '';
        if (dialog.returnValue !== 'confirm') {
            receiptSearchQueue.complete();
            return;
        }
        const confirmation = chosenAction === 'EMG' ? window.IpsReceiptConfirmation.read(dialog) : null;
        executeBatch(selected, chosenAction, confirmation).finally(() => receiptSearchQueue.complete());
    });
    if (fixedWorkflow) selectNextWorkflowBatch();
    update();
});
</script>
@endunless
@endsection
