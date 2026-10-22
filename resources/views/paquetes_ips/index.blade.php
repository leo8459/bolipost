@extends('adminlte::page')
@section('title', 'IPS')
@section('content')
<div class="ips-workspace" data-delivery-only="{{ $deliveryOnly ? '1' : '0' }}" data-courier-only="{{ $courierOnly ? '1' : '0' }}" data-workflow-action="{{ $workflowAction ?? '' }}" data-scan-enabled="{{ $readOnly ? '0' : '1' }}" data-preferred-receptacle="{{ $preferredReceptaclePid ?? '' }}" data-add-label="{{ ($workflowAction ?? null) ? 'Agregar al paso' : (($deliveryOnly ?? false) ? 'Agregar a entrega' : 'Agregar') }}" data-selection-add-url="{{ $selectionAddUrl }}" data-operate-url="{{ $operateUrl }}">
    @if(!$warehouseView && ($workflowAction ?? null) !== 'EDN')
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
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<p id="ips-listing-status" class="ips-help" role="status" aria-live="polite" hidden></p>
    @if($missingPackageCode && $workflowAction === 'EMR')
        <section class="ips-panel mb-3">
            <div class="ips-panel-header"><h2><i class="fas fa-plus-circle mr-2"></i>Registrar envío recibido que aún no existe en IPS</h2></div>
            <form id="ips-inbound-create" method="POST" action="{{ route('internacional.inbound.create') }}" class="p-3">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $inboundCreateKey }}">
                <input type="hidden" name="destination_country" value="BO">
                <input type="hidden" name="physical_receipt_confirmed" value="1">
                @if($inboundPending)
                    <input type="hidden" name="codigo" value="{{ $missingPackageCode }}">
                    <div class="alert alert-info">La respuesta de la operacion original no se confirmo. Este boton consulta la misma solicitud con la misma clave; no crea un segundo paquete.</div>
                    <p><strong>Codigo:</strong> {{ $missingPackageCode }} · <strong>Envase:</strong> {{ data_get($inboundPending, 'receptacle_pid') }}</p>
                    <button class="btn btn-primary" type="submit">Consultar operacion pendiente</button>
                @else
                <p class="alert alert-warning">Verifica primero que el código esté bien escaneado. Este formulario crea el paquete con la recepción de importación y lo vincula al envase abierto seleccionado.</p>
                @if(empty($catalog['open_inbound_receptacles']))
                    <p class="alert alert-warning">Primero registra la llegada y apertura de la saca en esta oficina. No hay una saca abierta disponible para asociar el paquete.</p>
                @endif
                <div class="row">
                    <div class="col-md-4 form-group"><label for="inbound-code">Código del paquete</label><input id="inbound-code" name="codigo" class="form-control" maxlength="35" value="{{ old('codigo', $missingPackageCode) }}" required autofocus></div>
                    <div class="col-md-4 form-group"><label for="inbound-weight">Peso registrado en IPS (kg)</label><input id="inbound-weight" name="weight_kg" class="form-control" type="number" min="0.001" max="999.999" step="0.001" value="{{ old('weight_kg') }}" required></div>
                    <div class="col-md-4 form-group"><label for="inbound-class">Clase postal</label><select id="inbound-class" name="mail_class" class="form-control" required><option value="">Seleccionar clase</option>@foreach(($catalog['mail_classes'] ?? []) as $class)<option value="{{ trim($class->MAIL_CLASS_CD) }}" @selected(old('mail_class') === trim($class->MAIL_CLASS_CD))>{{ $class->MAIL_CLASS_NM }}</option>@endforeach</select></div>
                    <div class="col-md-4 form-group"><label for="inbound-condition">Condición física del paquete</label><select id="inbound-condition" name="condition_cd" class="form-control" required><option value="">Revisar y seleccionar</option>@foreach(($catalog['item_conditions'] ?? []) as $condition)<option value="{{ trim($condition->ITEM_CONDITION_CD) }}" @selected((string) old('condition_cd') === trim($condition->ITEM_CONDITION_CD))>{{ $condition->ITEM_CONDITION_NM }}</option>@endforeach</select></div>
                    <div class="col-md-4 form-group"><label for="inbound-origin">País de origen</label><select id="inbound-origin" name="origin_country" class="form-control" required><option value="">Seleccionar país</option>@foreach(($catalog['countries'] ?? []) as $country)<option value="{{ trim($country->COUNTRY_CD) }}" @selected(old('origin_country') === trim($country->COUNTRY_CD))>{{ $country->COUNTRY_NM }}</option>@endforeach</select></div>
                    <div class="col-md-8 form-group"><label for="inbound-receptacle">Envase abierto que contiene el paquete</label><select id="inbound-receptacle" name="receptacle_pid" class="form-control" required><option value="">Seleccionar envase</option>@foreach(($catalog['open_inbound_receptacles'] ?? []) as $receptacle)<option value="{{ $receptacle->RECPTCL_PID }}" @selected((string) old('receptacle_pid', $preferredReceptaclePid) === (string) $receptacle->RECPTCL_PID)>{{ trim($receptacle->RECPTCL_FID) }} · {{ $receptacle->RECPTCL_MAILITMS_NO !== null ? $receptacle->RECPTCL_MAILITMS_NO.' declarados' : 'cantidad no declarada' }}</option>@endforeach</select></div>
                </div>
                @foreach(['sender' => 'Remitente', 'recipient' => 'Destinatario'] as $party => $label)
                    <fieldset class="border rounded p-3 mb-3"><legend class="w-auto px-2 h6">{{ $label }}</legend><div class="row">
                        <div class="col-md-4 form-group"><label for="{{ $party }}-name">Nombre</label><input id="{{ $party }}-name" name="{{ $party }}[name]" class="form-control" maxlength="64" value="{{ old($party.'.name') }}" required></div>
                        <div class="col-md-4 form-group"><label for="{{ $party }}-address">Dirección</label><input id="{{ $party }}-address" name="{{ $party }}[address]" class="form-control" maxlength="105" value="{{ old($party.'.address') }}" required></div>
                        <div class="col-md-4 form-group"><label for="{{ $party }}-city">Ciudad</label><input id="{{ $party }}-city" name="{{ $party }}[city]" class="form-control" maxlength="32" value="{{ old($party.'.city') }}" required></div>
                        <div class="col-md-4 form-group"><label for="{{ $party }}-country">País</label><select id="{{ $party }}-country" name="{{ $party }}[country]" class="form-control" required><option value="">Seleccionar país</option>@foreach(($catalog['countries'] ?? []) as $country)<option value="{{ trim($country->COUNTRY_CD) }}" @selected(old($party.'.country') === trim($country->COUNTRY_CD))>{{ $country->COUNTRY_NM }}</option>@endforeach</select></div>
                        <div class="col-md-4 form-group"><label for="{{ $party }}-postcode">Código postal</label><input id="{{ $party }}-postcode" name="{{ $party }}[postcode]" class="form-control" maxlength="20" value="{{ old($party.'.postcode') }}"></div>
                        <div class="col-md-4 form-group"><label for="{{ $party }}-phone">Teléfono</label><input id="{{ $party }}-phone" name="{{ $party }}[phone]" class="form-control" maxlength="64" value="{{ old($party.'.phone') }}"></div>
                    </div></fieldset>
                @endforeach
                <button class="btn btn-primary" type="submit">Crear paquete y registrar recepción</button>
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
        <aside class="ips-panel ips-tray" aria-label="Bandeja de trabajo">
            <div class="ips-panel-header"><h2><i class="fas fa-clipboard-list mr-2"></i><span id="ips-tray-title">{{ $courierOnly ? 'Mi reparto' : ($deliveryOnly ? 'Procesar entregas' : (($workflowAction ?? null) ? $operationLabel.' · por registrar' : 'Tu bandeja')) }}</span></h2><span id="ips-tray-count">{{ count($selection) }}/50 en bandeja</span></div>
            <div class="ips-tray-body">
                @if(!$deliveryOnly && !($workflowAction ?? null))
                    <p>{{ ($workflowAction ?? null) ? 'Agrega envíos habilitados para este paso. La bandeja se conserva mientras escaneas o consultas otros códigos.' : 'Agrega los resultados que quieras trabajar. La lista se conserva mientras buscas otros paquetes.' }}</p>
                @endif
                @if($deliveryOnly)
                    @if($courierOnly)<p>Registra la salida al iniciar el recorrido. Al finalizar, confirma la entrega o registra el intento fallido.</p>@endif
                    @php($deliveryActionGroups = $courierOnly
                        ? ['Reparto' => [['action' => 'EDG', 'label' => 'Registrar salida a reparto'], ['action' => 'EMH', 'label' => 'Registrar intento fallido']], 'Entrega final' => [['action' => 'EMI', 'label' => 'Confirmar entrega al destinatario']]]
                        : ['En oficina' => [['action' => 'HANDOVER_AGENT', 'event' => 32, 'label' => 'Entregar al cartero'], ['action' => 'HANDOVER_AGENT', 'event' => 36, 'label' => 'Asignar cartero para reintento'], ['action' => 'HANDOVER_AGENT', 'event' => 38, 'label' => 'Entregar al cartero'], ['action' => 'EDH', 'label' => 'Dejar disponible para retiro'], ['action' => 'CUSTOMS_PRESENT', 'label' => 'Enviar a Aduana']], 'Reparto' => [['action' => 'EDG', 'label' => 'Registrar salida a reparto'], ['action' => 'EMH', 'label' => 'Registrar intento fallido']], 'Entrega final' => [['action' => 'EMI', 'label' => 'Confirmar entrega al destinatario']]])
                    <section class="ips-delivery-actions" aria-label="Acciones disponibles según la etapa">
                        <h3 class="ips-action-heading">Elige el siguiente paso</h3>
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
                @if(($workflowAction ?? '') === 'EMR')
                    <div id="ips-batch-physical-fields" class="ips-batch-fields mt-3" hidden>
                        <label class="ips-receipt-confirm-label"><input id="ips-batch-physical-confirm" type="checkbox" class="mr-1"><span id="ips-batch-physical-label">Confirmo la recepción física del lote.</span></label>
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
                        <article class="ips-tray-item" data-code="{{ $code }}" data-event="{{ (int) ($entry['package']['operational_event_cd'] ?? $entry['package']['event_cd'] ?? 0) }}" data-actions="{{ implode(',', $entry['package']['allowed_actions'] ?? []) }}" data-status="{{ $entry['status'] }}" data-payload-action="{{ $entry['payload']['event'] ?? '' }}" data-customs-return="{{ in_array((int) ($entry['package']['operational_event_cd'] ?? $entry['package']['event_cd'] ?? 0), [31, 34], true) && (int) ($entry['package']['state_cd'] ?? -1) === 1 ? '1' : '0' }}">
                            <div class="d-flex align-items-center"><input class="ips-choice mr-2" type="checkbox" aria-label="Seleccionar {{ $code }}" @if($deliveryOnly) hidden @endif><strong>{{ $code }}</strong>
                                @unless(in_array($entry['status'],['processing','uncertain'],true))<form class="ml-auto ips-selection-remove" method="POST" action="{{ route($selectionRemoveRouteName, ($workflowAction ?? null) ? ['section' => $section, 'codigo' => $code] : $code) }}">@csrf @method('DELETE')<button type="submit" class="btn btn-sm text-muted" aria-label="Quitar {{ $code }}"><i class="fas fa-times"></i></button></form>@endunless
                            </div>
                            <small class="ips-item-stage">Etapa actual · {{ $entry['package']['stage']['label'] }}</small>
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
                <button id="ips-refresh" class="btn btn-outline-primary btn-block" hidden onclick="location.reload()">Actualizar resultados</button>
            </div>
        </aside>
        @endunless
    </div>
    @endif
</div>
<dialog id="ips-confirm"><form method="dialog"><h3>Confirmar movimiento</h3><p id="ips-confirm-description"></p><p id="ips-confirm-codes"></p><p>Se validará cada paquete nuevamente. Cada movimiento tendrá su propio resultado.</p><div class="d-flex justify-content-end"><button value="cancel" class="btn btn-light mr-2">Volver</button><button value="confirm" class="btn btn-primary">Confirmar y registrar</button></div></form></dialog>
@endsection
@section('css')
<style>
.ips-heading{display:flex;justify-content:space-between;gap:20px;padding:10px 5px 20px;align-items:center}.ips-heading h1{font-weight:800;color:#183352;margin:3px 0}.ips-heading p,.ips-footnote{color:#697b90;margin:0;font-size:.9rem}.ips-eyebrow{font-size:.68rem;color:#47698e;letter-spacing:.14em;font-weight:700}.ips-identity{display:flex;gap:12px;align-items:center;padding:12px 18px;background:white;border:1px solid #dce6f1;border-radius:12px;color:#254e80}.ips-identity small{display:block;color:#718096}
.ips-operation-nav{background:#fff;border:1px solid #dce6f1;border-radius:12px;padding:12px 14px;margin:0 0 14px;box-shadow:0 4px 16px #17314d08}.ips-operation-groups,.ips-operation-tabs,.ips-warehouse-nav{display:flex;gap:7px;align-items:center;overflow-x:auto}.ips-operation-groups{padding-bottom:9px;border-bottom:1px solid #edf1f6}.ips-operation-groups a,.ips-operation-tabs a,.ips-warehouse-nav a{display:inline-flex;align-items:center;min-height:36px;padding:7px 12px;border-radius:8px;color:#47617d;font-size:.8rem;text-decoration:none;white-space:nowrap}.ips-operation-groups a{font-weight:700}.ips-operation-groups a.active,.ips-operation-tabs a.active,.ips-warehouse-nav a.active{background:#eaf1fc;color:#214c84;font-weight:700}.ips-operation-tabs{padding-top:8px}.ips-operation-description{font-size:.76rem;color:#718096;margin:8px 3px 0}.ips-warehouse-nav{background:#fff;border:1px solid #dce6f1;border-radius:12px;padding:8px;margin:0 0 14px;box-shadow:0 4px 16px #17314d08}
.ips-flow{background:#fff;border:1px solid #dce6f1;border-radius:14px;padding:18px 20px;margin:0 0 18px;box-shadow:0 5px 20px #17314d08}.ips-flow-heading{display:flex;justify-content:space-between;gap:16px;align-items:center}.ips-flow-heading h2{font-size:1rem;font-weight:700;color:#234f8b;margin:0 0 3px}.ips-flow-heading p,.ips-flow-heading>span{font-size:.78rem;color:#697b90;margin:0}.ips-flow-heading>span{white-space:nowrap;font-weight:700}.ips-flow-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));list-style:none;padding:0;margin:18px 0;gap:8px}.ips-flow-steps li{min-width:0}.ips-flow-steps a{display:flex;gap:9px;align-items:center;height:100%;padding:10px;border:1px solid #e1e8f0;border-radius:10px;color:#64758a;font-size:.78rem;text-decoration:none}.ips-flow-steps li.is-current a{background:#edf4ff;border-color:#7fa9de;color:#1e4d87;font-weight:700}.ips-flow-number{display:grid;place-items:center;flex:0 0 28px;height:28px;border-radius:50%;background:#edf1f6;font-weight:700}.ips-flow-steps li.is-current .ips-flow-number{background:#234f8b;color:#fff}.ips-flow-steps small{display:block;font-size:.68rem;color:#7a8ba0;margin-top:2px}.ips-flow-rules{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;border-top:1px solid #edf1f6;padding-top:12px}.ips-flow-rules p{font-size:.76rem;color:#60748a;margin:0}.ips-flow-rules strong{color:#294c72}.ips-receipt-confirm-label{display:flex;gap:4px;align-items:flex-start;font-size:.78rem;color:#284e75;cursor:pointer}.ips-receipt-confirm-label input{margin-top:3px}
.ips-workspace{padding:24px 5px 24px}
.ips-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:20px;align-items:start}.ips-main{min-width:0}.ips-panel{background:white;border:1px solid #e0e7ef;border-radius:14px;box-shadow:0 5px 20px #17314d08;overflow:hidden}.ips-panel-header{display:flex;justify-content:space-between;align-items:center;padding:18px 20px;background:#234f8b;color:white;gap:12px}.ips-panel-header h2{font-size:1.05rem;font-weight:700;margin:0}.ips-panel-header>span{font-size:.78rem;white-space:nowrap}.ips-search{padding:20px;display:flex;gap:10px}.ips-search input{flex:1;min-width:100px}.ips-tabs{display:flex;gap:5px;padding:0 20px 15px;overflow:auto}.ips-tabs a{padding:8px 12px;border-radius:8px;color:#54687e;font-size:.8rem;white-space:nowrap}.ips-tabs a.is-active{background:#eaf1fc;color:#214c84;font-weight:700}.ips-table{margin:0}.ips-table th{background:#f0f4f9;font-size:.75rem;color:#315279;border-top:1px solid #e3eaf2;white-space:nowrap}.ips-table td{font-size:.8rem;padding:15px 12px;vertical-align:middle;border-color:#edf1f6}.ips-table small{display:block;color:#76869a;margin-top:5px;min-width:85px}.ips-code{color:#234f8b;white-space:nowrap}.ips-table .badge{white-space:normal;text-align:left;font-size:.74rem;line-height:1.5}.ips-pagination{display:flex;justify-content:space-between;align-items:center;padding:18px 20px;gap:10px;flex-wrap:wrap;font-size:.8rem;color:#5b6d82}.ips-pagination .pagination{margin:0}.ips-footnote{padding:14px 2px;font-size:.78rem}
.ips-setup-state{max-width:760px;margin:36px auto}.ips-setup-state .ips-tray-body>p{font-size:.9rem;color:#596e84}
.ips-tray{position:sticky;top:75px}.ips-tray-body{padding:18px}.ips-tray-body>p{font-size:.82rem;color:#697b90}.ips-tray-body label{font-size:.82rem;color:#324c69}.ips-tray-items{max-height:480px;overflow:auto}.ips-tray-item{padding:12px 0;border-bottom:1px solid #e8eef4;font-size:.8rem}.ips-tray-item small{display:block;color:#738297;margin:5px 0}.ips-tray-item .ips-item-stage{font-weight:700;color:#435d79}.ips-tray-item.is-ineligible{opacity:.65}.ips-help{font-size:.77rem;color:#63778e;margin:10px 0}.ips-action-heading{font-size:.83rem;font-weight:700;color:#324c69;margin:14px 0 8px}.ips-delivery-action-group{border-top:1px solid #edf1f6;padding:8px 0}.ips-delivery-action-group[hidden],.ips-delivery-action[hidden]{display:none!important}.ips-delivery-action-group h4{font-size:.67rem;text-transform:uppercase;letter-spacing:.06em;color:#718096;margin:0 0 6px}.ips-delivery-action-options{display:grid;gap:5px}.ips-delivery-action{display:flex;justify-content:space-between;align-items:center;gap:8px;width:100%;padding:8px 10px;border:1px solid #dbe4ef;border-radius:8px;background:#fff;color:#315279;text-align:left;font-size:.76rem;line-height:1.25;cursor:pointer}.ips-delivery-action:hover{background:#f5f8fc;border-color:#9db5d2}.ips-delivery-action:focus-visible{outline:3px solid #9dc2f1;outline-offset:1px}.ips-delivery-action.is-active{background:#eaf1fc;border-color:#6d96c5;color:#1f4d83;font-weight:700}.ips-delivery-action small{flex:0 0 auto;color:#718096;font-size:.66rem;font-weight:500;white-space:nowrap;margin:0}.ips-delivery-action.is-active small{color:#315b88}.ips-empty{text-align:center;padding:35px 20px!important;color:#7e8da0}.ips-empty>i{font-size:1.8rem;opacity:.5;margin-bottom:10px}.ips-empty p{margin:8px 0}.ips-tray-item[data-status=succeeded] .ips-item-message{color:#18794e}.ips-tray-item[data-status=rejected] .ips-item-message{color:#b33c38}
#ips-confirm{border:0;border-radius:15px;max-width:520px;width:90%;padding:28px;color:#263e59;box-shadow:0 20px 70px #10233e55}#ips-confirm::backdrop{background:#10233e88}#ips-confirm h3{font-size:1.3rem;font-weight:700}#ips-confirm p{font-size:.9rem}#ips-confirm-codes{max-height:160px;overflow:auto;background:#f0f4f9;padding:10px;border-radius:8px}
.ips-field-label{display:block;font-size:.74rem;font-weight:700;color:#385574;margin-bottom:4px}
.ips-batch-fields .ips-help{display:block;margin:6px 0 0}
.ips-layout--readonly{grid-template-columns:minmax(0,1fr)}
#ips-action[hidden]{display:none!important}
.ips-tray-item small[hidden]{display:none!important}
@media(max-width:1100px){.ips-layout{grid-template-columns:minmax(0,1fr)}.ips-tray{position:static}.ips-tray-items{max-height:300px}}@media(max-width:600px){.ips-heading{display:block}.ips-identity{margin-top:12px}.ips-search{flex-wrap:wrap}.ips-journey{gap:12px}.ips-operation-groups a,.ips-operation-tabs a,.ips-warehouse-nav a{font-size:.75rem;padding:7px 9px}}
</style>
@endsection
@section('js')
<script src="{{ asset('js/ips-listing.js') }}?v={{ filemtime(public_path('js/ips-listing.js')) }}" defer></script>
@unless($readOnly)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const action = document.getElementById('ips-action'), process = document.getElementById('ips-process'), dialog = document.getElementById('ips-confirm');
    const clearCompleted = document.getElementById('ips-clear-completed');
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
        compatibleBatch(readyRows).forEach(row => { row.querySelector('.ips-choice').checked = true; });
    }
    function canJoinSelectedWorkflowBatch(row) {
        const selectedRows = rows.filter(candidate => candidate.querySelector('.ips-choice').checked && isReadyForWorkflow(candidate));
        return !selectedRows.length || compatibleBatch([...selectedRows, row]).includes(row);
    }
    let running = false;
    let lastPhysicalSelection = null;
    const customsNotice = document.createElement('p');
    customsNotice.className = 'alert alert-warning';
    customsNotice.hidden = true;
    document.getElementById('ips-confirm-description').after(customsNotice);
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
        const needsPhysicalReceipt = workflowAction === 'EMR';
        let missingPhysicalReceipt = false;
        if (batchPhysicalFields && batchPhysicalConfirm && batchPhysicalLabel) {
            const selectionKey = selectedRows.map(row => row.dataset.code).sort().join('|');
            if (selectionKey !== lastPhysicalSelection) {
                batchPhysicalConfirm.checked = needsPhysicalReceipt && selectedRows.length > 0
                    && selectedRows.every(row => row.dataset.scanned === '1');
                lastPhysicalSelection = selectionKey;
            }
            batchPhysicalFields.hidden = !needsPhysicalReceipt || selectedRows.length === 0;
            batchPhysicalConfirm.disabled = running || !needsPhysicalReceipt || selectedRows.length === 0;
            batchPhysicalConfirm.required = needsPhysicalReceipt && selectedRows.length > 0;
            batchPhysicalLabel.textContent = 'Confirmo que estos paquetes están físicamente en el envase recibido y abierto.';
            selectedRows.forEach(row => {
                const perItemConfirm = row.querySelector('.ips-physical-receipt-confirm');
                perItemConfirm.checked = batchPhysicalConfirm.checked;
            });
            missingPhysicalReceipt = needsPhysicalReceipt && selectedRows.length > 0 && !batchPhysicalConfirm.checked;
        }
        return {missingTarget, missingPhysicalReceipt};
    }
    function update() {
        const workspace = document.querySelector('.ips-workspace');
        const deliveryOnly = workspace?.dataset.deliveryOnly === '1';
        const workflowAction = workspace?.dataset.workflowAction || '';
        const workflowTitles = {EMR: 'Registrar paquetes de la saca', EDN: 'Clasificar paquetes por destino', EIB: 'Agrupar en saca nacional', EMG: 'Recibir paquetes en destino'};
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
        process.disabled = running || count === 0 || batchState.missingTarget || batchState.missingPhysicalReceipt || Boolean(deliveryAgentSelect && action.value === 'HANDOVER_AGENT' && !deliveryAgentSelect.value) || Boolean(customsDetailsMissing);
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
            const batchLabels = {EMR: 'Registrar paquetes', EDN: 'Registrar despacho', EIB: 'Asociar a saca', EMG: 'Registrar recepción en IPS', HANDOVER_AGENT: 'Asignar cartero', EDG: 'Registrar salida a reparto', EMH: 'Registrar intento fallido'};
            process.textContent = count
                ? (action.value === 'EMR' && batchState.missingPhysicalReceipt
                    ? 'Confirma llegada física · ' + count + (count === 1 ? ' paquete' : ' paquetes')
                    : (batchLabels[action.value] || 'Registrar movimiento') + ' · ' + count + (count === 1 ? ' paquete' : ' paquetes'))
                : 'Selecciona paquetes compatibles';
        }
    }
    action.addEventListener('change', () => {
        if (action.value !== 'HANDOVER_AGENT') handoverEventFilter = null;
        update();
    });
    batchTarget?.addEventListener('change', update);
    batchPhysicalConfirm?.addEventListener('change', update);
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
    rows.forEach(row => row.querySelector('.ips-choice').addEventListener('change', update));
    window.addEventListener('ips:selection-added', event => {
        const data = event.detail || {}, packageData = data.package || {}, tray = document.querySelector('.ips-tray-items');
        if (!tray || !data.code || rows.some(row => row.dataset.code === data.code)) return;
        const article = document.createElement('article');
        article.className = 'ips-tray-item'; article.dataset.code = data.code; article.dataset.event = Number(packageData.operational_event_cd ?? packageData.event_cd ?? 0);
        article.dataset.actions = (packageData.allowed_actions || []).join(','); article.dataset.status = 'ready'; article.dataset.payloadAction = ''; article.dataset.scanned = data.scanned ? '1' : '0';
        article.dataset.customsReturn = [31, 34].includes(Number(packageData.operational_event_cd ?? packageData.event_cd)) && Number(packageData.state_cd) === 1 ? '1' : '0';
        const deliveryModeField = courierOnly
            ? '<div class="ips-delivery-fields mt-2" hidden><label class="ips-field-label">Modalidad efectiva de entrega</label><input type="hidden" class="ips-delivery-mode" value="DOMICILIARIA"><span class="d-block">Cartero · domiciliaria</span></div>'
            : '<div class="ips-delivery-fields mt-2" hidden><label class="ips-field-label">Modalidad efectiva de entrega</label><select class="form-control form-control-sm ips-delivery-mode"><option value="">Seleccionar modalidad</option><option value="NO_DOMICILIARIA">Ventanilla · no domiciliaria</option><option value="DOMICILIARIA">Cartero · domiciliaria</option></select></div>';
        article.innerHTML = '<div class="d-flex align-items-center"><input class="ips-choice mr-2" type="checkbox" aria-label="Seleccionar '+data.code+'"><strong>'+data.code+'</strong><form class="ml-auto ips-selection-remove" method="POST" action="'+(data.remove_url || '')+'"><input type="hidden" name="_token" value="'+document.querySelector('meta[name="csrf-token"]')?.content+'"><input type="hidden" name="_method" value="DELETE"><button type="submit" class="btn btn-sm text-muted" aria-label="Quitar '+data.code+'"><i class="fas fa-times"></i></button></form></div>'
            + '<small class="ips-item-stage"></small><div class="ips-physical-receipt mt-2" hidden><label class="ips-receipt-confirm-label"><input class="ips-physical-receipt-confirm mr-1" type="checkbox"> Confirmo que el envío llegó físicamente a esta oficina.</label></div><small class="ips-item-message" role="status" hidden></small>'
            + '<input class="form-control form-control-sm ips-signatory mt-2" maxlength="64" placeholder="Persona que recibió (opcional)" aria-label="Receptor real de '+data.code+'" hidden>'
            + '<div class="ips-delivery-fields mt-2" hidden><label class="ips-field-label">Modalidad efectiva de entrega</label><select class="form-control form-control-sm ips-delivery-mode"><option value="">Seleccionar modalidad</option><option value="NO_DOMICILIARIA">Ventanilla · no domiciliaria</option><option value="DOMICILIARIA">Cartero · domiciliaria</option></select></div>'
            + '<div class="ips-attempt-fields mt-2" hidden><label class="ips-field-label">Motivo del intento fallido</label><select class="form-control form-control-sm ips-nondelivery-reason"><option value="">Seleccionar motivo</option></select><label class="ips-field-label mt-2">Medida aplicada</label><select class="form-control form-control-sm ips-nondelivery-measure"><option value="">Seleccionar medida</option></select></div>'
            + '<div class="ips-route-fields mt-2" hidden><label class="ips-field-label">Siguiente oficina de la ruta</label><select class="form-control form-control-sm ips-next-office" required><option value="">Seleccionar oficina</option></select></div>'
            + '<div class="ips-sack-fields mt-2" hidden><label class="ips-field-label">Saca nacional abierta</label><select class="form-control form-control-sm ips-domestic-receptacle" required><option value="">Seleccionar saca</option></select></div>'
            + '<div class="ips-exchange-receptacle-fields mt-2" hidden><label class="ips-field-label">Envase internacional recibido y abierto</label><select class="form-control form-control-sm ips-exchange-receptacle" required><option value="">Seleccionar envase</option></select></div>'
            + '<div class="ips-condition-fields mt-2" hidden><label class="ips-field-label">Condicion fisica del paquete</label><select class="form-control form-control-sm ips-package-condition"><option value="">Revisar y seleccionar</option></select></div>';
        if (courierOnly) article.querySelector('.ips-delivery-fields').outerHTML = deliveryModeField;
        article.querySelector('.ips-item-stage').textContent = 'Etapa actual · ' + ((packageData.stage && packageData.stage.label) || 'Paquete IPS');
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
        article.querySelector('.ips-choice').addEventListener('change', update);
        if (fixedWorkflow) {
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
        if (batchTarget && !batchTargetFields.hidden && !batchTarget.value) {
            batchTarget.reportValidity();
            return;
        }
        if (batchPhysicalConfirm && !batchPhysicalFields.hidden && !batchPhysicalConfirm.checked) {
            batchPhysicalConfirm.reportValidity();
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
        const customsRows = selected.filter(row => row.dataset.customsReturn === '1');
        const targetDescription = batchTarget && !batchTargetFields.hidden
            ? ' Destino común: ' + batchTarget.options[batchTarget.selectedIndex]?.textContent.trim() + '.'
            : '';
        document.getElementById('ips-confirm-description').textContent = action.value === 'EMI' && customsRows.length
            ? 'Se registrará la devolución de Aduana y la entrega final para los envíos indicados.'
            : (action.value === 'CUSTOMS_PRESENT'
                ? 'Se registrará el envío a Aduana para los paquetes seleccionados.'
                : (deliveryActionLabels[action.value] || action.options[action.selectedIndex]?.textContent || 'Se registrará el movimiento seleccionado en IPS.'));
        if (targetDescription) document.getElementById('ips-confirm-description').textContent += targetDescription;
        customsNotice.hidden = action.value !== 'EMI' || customsRows.length === 0;
        customsNotice.textContent = customsNotice.hidden ? '' : 'Para ' + customsRows.map(row => row.dataset.code).join(', ') + ': confirma que Aduana devolvió físicamente estos envíos a la oficina y que ahora se entregan a sus destinatarios. SITRA registrará primero la devolución de Aduana y después la entrega.';
        document.getElementById('ips-confirm-codes').textContent = selected.map(row=>row.dataset.code).join(', ');
        if (fixedWorkflow) {
            executeBatch(selected, action.value);
        } else {
            dialog.returnValue = '';
            dialog.showModal();
        }
    });
    async function executeBatch(selected, chosenAction) {
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
                if (chosenAction === 'EMG') operation.physical_receipt_confirmed = true;
                if (chosenAction === 'EMI') operation.delivery_mode = row.querySelector('.ips-delivery-mode').value;
                if (chosenAction === 'EDN') operation.next_office_cd = row.querySelector('.ips-next-office').value;
                if (chosenAction === 'EIB') operation.domestic_receptacle_pid = row.querySelector('.ips-domestic-receptacle').value;
                if (chosenAction === 'EMR') { operation.exchange_receptacle_pid = row.querySelector('.ips-exchange-receptacle').value; operation.condition_cd = row.querySelector('.ips-package-condition').value; }
                if (row.dataset.customsReturn === '1') operation.customs_return_confirmed = true;
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
                const response = await fetch(document.querySelector('.ips-workspace')?.dataset.operateUrl || @json(route('ips.operate')), {
                    method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},
                    body:JSON.stringify(operation)
                });
                const result = await response.json();
                const validationOnly = response.status === 422;
                const editableRejection = ['needs_reason','needs_physical_confirmation'].includes(result.status) || validationOnly;
                row.dataset.status = editableRejection ? 'ready' : (result.status || 'rejected');
                row.dataset.payloadAction = editableRejection ? '' : chosenAction;
                row.querySelector('.ips-item-message').textContent = result.message || 'Revise la respuesta antes de continuar.';
                row.querySelector('.ips-item-message').hidden = false;
                if (!editableRejection) row.querySelectorAll('input:not(.ips-choice), select').forEach(field => field.disabled = true);
                if (result.status === 'succeeded') successes++;
            } catch(e) {
                row.dataset.status = 'uncertain'; row.dataset.payloadAction = chosenAction;
                row.querySelector('.ips-item-message').hidden = false;
                row.querySelector('.ips-item-message').textContent = 'Sin confirmación. Actualiza y reintenta la misma acción.';
            }
            row.querySelector('.ips-choice').checked = false;
        }
        running = false; action.disabled = false;
        if (fixedWorkflow && !rows.some(row => row.querySelector('.ips-choice').checked)) selectNextWorkflowBatch();
        update();
        progress.textContent = chosenAction === 'EMG'
            ? successes + ' recepciones registradas en IPS de ' + selected.length + '. Revisa el resultado de cada paquete.'
            : successes + ' confirmados de ' + selected.length + '. Revisa el resultado de cada paquete.';
        document.getElementById('ips-refresh').hidden = false;
    }
    dialog.addEventListener('close', () => {
        if (dialog.returnValue !== 'confirm') return;
        executeBatch(rows.filter(row => row.querySelector('.ips-choice').checked), action.value);
    });
    if (fixedWorkflow) selectNextWorkflowBatch();
    update();
});
</script>
@endunless
@endsection
