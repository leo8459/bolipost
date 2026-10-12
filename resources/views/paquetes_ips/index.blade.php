@extends('adminlte::page')
@section('title', 'IPS')
@section('content')
<div class="ips-workspace" data-delivery-only="{{ $deliveryOnly ? '1' : '0' }}" data-workflow-action="{{ $workflowAction ?? '' }}" data-add-label="{{ ($workflowAction ?? null) ? 'Agregar al paso' : (($deliveryOnly ?? false) ? 'Agregar a entrega' : 'Agregar') }}" data-selection-add-url="{{ $selectionAddUrl }}" data-operate-url="{{ $operateUrl }}">
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
    @if($internationalWorkflow ?? false)
        <section class="ips-flow" aria-label="Recorrido nacional del envío">
            <div class="ips-flow-heading">
                <div><h2>Recorrido nacional</h2><p>El avance depende de los eventos confirmados en SITRA para cada paquete.</p></div>
                <span>Paso {{ $activeWorkflowStep }} de {{ count($internationalFlowSteps) }} · {{ $workflowPlace }}</span>
            </div>
            <ol class="ips-flow-steps">
                @foreach($internationalFlowSteps as $flowStep)
                    <li class="{{ $flowStep['section'] === $section ? 'is-current' : '' }}">
                        <a href="{{ route('internacional.section', ['section' => $flowStep['section']]) }}" @if($flowStep['section'] === $section) aria-current="step" @endif>
                            <span class="ips-flow-number">{{ $loop->iteration }}</span>
                            <span>{{ $flowStep['label'] }}<small>{{ $flowStep['place'] }}</small></span>
                        </a>
                    </li>
                @endforeach
            </ol>
            <div class="ips-flow-rules">
                <p><strong>Para registrar este paso:</strong> {{ $internationalWorkflow['requires'] }}</p>
                <p><strong>Este paso registra:</strong> {{ $internationalWorkflow['records'] }}</p>
                <p><strong>Después:</strong> {{ $internationalWorkflow['next'] }}</p>
            </div>
        </section>
        @if(in_array($workflowAction, ['EMR', 'EDN', 'EIB'], true))
            <div class="alert alert-info" role="note">Los pasos de recepción, despacho y saca se realizan en la oficina de cambio que recibe el envío. La oficina asignada debe coincidir con la etapa que SITRA habilite; la recepción posterior se registra en la oficina de destino.</div>
        @endif
    @endif
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<p id="ips-listing-status" class="ips-help" role="status" aria-live="polite" hidden></p>
    <div class="ips-layout {{ $readOnly ? 'ips-layout--readonly' : '' }}">
        <main class="ips-main" id="ips-listing">
            @include('paquetes_ips.partials.listing')
        </main>
        @unless($readOnly)
        <aside class="ips-panel ips-tray" aria-label="Bandeja de trabajo">
            <div class="ips-panel-header"><h2><i class="fas fa-clipboard-list mr-2"></i>{{ $deliveryOnly ? 'Entregas por registrar' : (($workflowAction ?? null) ? $operationLabel.' · por registrar' : 'Tu bandeja') }}</h2><span>{{ count($selection) }}/50</span></div>
            <div class="ips-tray-body">
                <p>{{ $deliveryOnly ? 'Agrega solo paquetes habilitados para dar de baja. La lista se conserva mientras buscas otros códigos.' : (($workflowAction ?? null) ? 'Agrega envíos habilitados para este paso. La bandeja se conserva mientras escaneas o consultas otros códigos.' : 'Agrega los resultados que quieras trabajar. La lista se conserva mientras buscas otros paquetes.') }}</p>
                <label for="ips-action">{{ ($workflowAction ?? null) ? 'Paso del recorrido' : ($deliveryOnly ? 'Movimiento' : 'Movimiento a registrar') }}</label>
                <select id="ips-action" class="form-control">@foreach($actions as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select>
                <p id="ips-action-help" class="ips-help"></p>
                <div class="ips-tray-items">
                    @forelse($selection as $code=>$entry)
                        <article class="ips-tray-item" data-code="{{ $code }}" data-actions="{{ implode(',', $entry['package']['allowed_actions'] ?? []) }}" data-status="{{ $entry['status'] }}" data-payload-action="{{ $entry['payload']['event'] ?? '' }}" data-customs-return="{{ in_array((int) ($entry['package']['operational_event_cd'] ?? $entry['package']['event_cd'] ?? 0), [31, 34], true) && (int) ($entry['package']['state_cd'] ?? -1) === 1 ? '1' : '0' }}">
                            <div class="d-flex align-items-center"><input class="ips-choice mr-2" type="checkbox" aria-label="Seleccionar {{ $code }}"><strong>{{ $code }}</strong>
                                @unless(in_array($entry['status'],['processing','uncertain'],true))<form class="ml-auto ips-selection-remove" method="POST" action="{{ route($selectionRemoveRouteName, ($workflowAction ?? null) ? ['section' => $section, 'codigo' => $code] : $code) }}">@csrf @method('DELETE')<button type="submit" class="btn btn-sm text-muted" aria-label="Quitar {{ $code }}"><i class="fas fa-times"></i></button></form>@endunless
                            </div>
                            <small>{{ $entry['package']['stage']['label'] }}</small>
                            <div class="ips-physical-receipt mt-2" @if(!in_array($workflowAction ?? '', ['EMR', 'EMG'], true)) hidden @endif>
                                <label class="ips-receipt-confirm-label"><input class="ips-physical-receipt-confirm mr-1" type="checkbox" @checked(!empty($entry['payload']['physical_receipt_confirmed'])) @disabled(!empty($entry['payload']))> Confirmo que el envío llegó físicamente a esta oficina.</label>
                            </div>
                            <div class="ips-route-fields mt-2" hidden><label class="ips-field-label">Siguiente oficina de la ruta</label><select class="form-control form-control-sm ips-next-office" @disabled(!empty($entry['payload']))><option value="">Seleccionar oficina</option>@foreach(($entry['package']['next_offices'] ?? []) as $office)<option value="{{ data_get($office, 'OWN_OFFICE_CD') }}" @selected((string) ($entry['payload']['next_office_cd'] ?? '') === (string) data_get($office, 'OWN_OFFICE_CD'))>{{ data_get($office, 'OFFICE_NM') }}{{ data_get($office, 'OFFICE_FCD') ? ' · '.data_get($office, 'OFFICE_FCD') : '' }}</option>@endforeach</select></div>
                            <div class="ips-sack-fields mt-2" hidden><label class="ips-field-label">Saca nacional abierta</label><select class="form-control form-control-sm ips-domestic-receptacle" @disabled(!empty($entry['payload']))><option value="">Seleccionar saca</option>@foreach(($entry['package']['domestic_receptacles'] ?? []) as $receptacle)<option value="{{ data_get($receptacle, 'DOM_REC_PID') }}" @selected((string) ($entry['payload']['domestic_receptacle_pid'] ?? '') === (string) data_get($receptacle, 'DOM_REC_PID'))>{{ data_get($receptacle, 'DOM_REC_FID') }} · destino {{ data_get($receptacle, 'DEST_OWN_OFFICE_CD') }} · {{ data_get($receptacle, 'MAILITEMCNT', 0) }} envíos</option>@endforeach</select></div>
                            <small class="ips-item-message" role="status">{{ $entry['message'] ?: (empty($entry['package']['allowed_actions']) ? 'Sin acción habilitada para esta oficina.' : 'Elige un movimiento compatible.') }}</small>
                            <label class="ips-field-label mt-2">Fecha y hora efectiva · La Paz</label>
                            <input class="form-control form-control-sm ips-effective-time" type="datetime-local" step="60" value="{{ !empty($entry['payload']['occurred_at']) ? \Illuminate\Support\Carbon::parse($entry['payload']['occurred_at'])->timezone('America/La_Paz')->format('Y-m-d\\TH:i') : now('America/La_Paz')->format('Y-m-d\\TH:i') }}" aria-label="Fecha y hora efectiva en La Paz para {{ $code }}" @disabled(!empty($entry['payload']))>
                            <input class="form-control form-control-sm ips-time-reason mt-2" maxlength="255" value="{{ $entry['payload']['time_reason'] ?? '' }}" placeholder="Motivo si se registra con más de 2 minutos de retraso" aria-label="Motivo del registro tardío para {{ $code }}" @disabled(!empty($entry['payload']))>
                            <input class="form-control form-control-sm ips-signatory mt-2" maxlength="64" value="{{ $entry['payload']['signatory'] ?? '' }}" placeholder="Persona que recibió (opcional)" aria-label="Receptor real de {{ $code }}" hidden @disabled(!empty($entry['payload']))>
                            <div class="ips-delivery-fields mt-2" hidden><label class="ips-field-label">Modalidad efectiva de entrega</label><select class="form-control form-control-sm ips-delivery-mode" @disabled(!empty($entry['payload']))><option value="">Seleccionar modalidad</option><option value="NO_DOMICILIARIA" @selected(($entry['payload']['delivery_mode'] ?? '') === 'NO_DOMICILIARIA')>Ventanilla · no domiciliaria</option><option value="DOMICILIARIA" @selected(($entry['payload']['delivery_mode'] ?? '') === 'DOMICILIARIA')>Cartero · domiciliaria</option></select></div>
                            <div class="ips-attempt-fields mt-2" hidden><label class="ips-field-label">Motivo del intento fallido</label><select class="form-control form-control-sm ips-nondelivery-reason" @disabled(!empty($entry['payload']))><option value="">Seleccionar motivo</option>@foreach(($catalog['non_delivery_reasons'] ?? []) as $reason)<option value="{{ data_get($reason, 'NON_DELIVERY_REASON_CD') }}" @selected((string) ($entry['payload']['non_delivery_reason'] ?? '') === (string) data_get($reason, 'NON_DELIVERY_REASON_CD'))>{{ data_get($reason, 'NON_DELIVERY_REASON_NM') }}</option>@endforeach</select><label class="ips-field-label mt-2">Medida aplicada</label><select class="form-control form-control-sm ips-nondelivery-measure" @disabled(!empty($entry['payload']))><option value="">Seleccionar medida</option>@foreach(($catalog['non_delivery_measures'] ?? []) as $measure)<option value="{{ data_get($measure, 'NON_DELIVERY_MEASURE_CD') }}" @selected((string) ($entry['payload']['non_delivery_measure'] ?? '') === (string) data_get($measure, 'NON_DELIVERY_MEASURE_CD'))>{{ data_get($measure, 'NON_DELIVERY_MEASURE_NM') }}</option>@endforeach</select></div>
                        </article>
                    @empty<div class="ips-empty"><i class="fas fa-inbox"></i><p>Tu bandeja está vacía.<br>Busca un paquete y pulsa Agregar.</p></div>@endforelse
                </div>
                <button id="ips-process" class="btn btn-primary btn-block mt-3" disabled>Revisar seleccionados</button>
                <p id="ips-progress" role="status" aria-live="polite" class="ips-help"></p>
                <button id="ips-refresh" class="btn btn-outline-primary btn-block" hidden onclick="location.reload()">Actualizar resultados</button>
            </div>
        </aside>
        @endunless
    </div>
</div>
<dialog id="ips-confirm"><form method="dialog"><h3>Confirmar movimiento</h3><p id="ips-confirm-description"></p><p id="ips-confirm-codes"></p><p>Se validará cada paquete nuevamente. Cada movimiento tendrá su propio resultado.</p><div class="d-flex justify-content-end"><button value="cancel" class="btn btn-light mr-2">Volver</button><button value="confirm" class="btn btn-primary">Confirmar y registrar</button></div></form></dialog>
@endsection
@section('css')
<style>
.ips-heading{display:flex;justify-content:space-between;gap:20px;padding:10px 5px 20px;align-items:center}.ips-heading h1{font-weight:800;color:#183352;margin:3px 0}.ips-heading p,.ips-footnote{color:#697b90;margin:0;font-size:.9rem}.ips-eyebrow{font-size:.68rem;color:#47698e;letter-spacing:.14em;font-weight:700}.ips-identity{display:flex;gap:12px;align-items:center;padding:12px 18px;background:white;border:1px solid #dce6f1;border-radius:12px;color:#254e80}.ips-identity small{display:block;color:#718096}
.ips-flow{background:#fff;border:1px solid #dce6f1;border-radius:14px;padding:18px 20px;margin:0 0 18px;box-shadow:0 5px 20px #17314d08}.ips-flow-heading{display:flex;justify-content:space-between;gap:16px;align-items:center}.ips-flow-heading h2{font-size:1rem;font-weight:700;color:#234f8b;margin:0 0 3px}.ips-flow-heading p,.ips-flow-heading>span{font-size:.78rem;color:#697b90;margin:0}.ips-flow-heading>span{white-space:nowrap;font-weight:700}.ips-flow-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));list-style:none;padding:0;margin:18px 0;gap:8px}.ips-flow-steps li{min-width:0}.ips-flow-steps a{display:flex;gap:9px;align-items:center;height:100%;padding:10px;border:1px solid #e1e8f0;border-radius:10px;color:#64758a;font-size:.78rem;text-decoration:none}.ips-flow-steps li.is-current a{background:#edf4ff;border-color:#7fa9de;color:#1e4d87;font-weight:700}.ips-flow-number{display:grid;place-items:center;flex:0 0 28px;height:28px;border-radius:50%;background:#edf1f6;font-weight:700}.ips-flow-steps li.is-current .ips-flow-number{background:#234f8b;color:#fff}.ips-flow-steps small{display:block;font-size:.68rem;color:#7a8ba0;margin-top:2px}.ips-flow-rules{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;border-top:1px solid #edf1f6;padding-top:12px}.ips-flow-rules p{font-size:.76rem;color:#60748a;margin:0}.ips-flow-rules strong{color:#294c72}.ips-receipt-confirm-label{display:flex;gap:4px;align-items:flex-start;font-size:.78rem;color:#284e75;cursor:pointer}.ips-receipt-confirm-label input{margin-top:3px}
.ips-workspace{padding:24px 5px 24px}
.ips-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:20px;align-items:start}.ips-main{min-width:0}.ips-panel{background:white;border:1px solid #e0e7ef;border-radius:14px;box-shadow:0 5px 20px #17314d08;overflow:hidden}.ips-panel-header{display:flex;justify-content:space-between;align-items:center;padding:18px 20px;background:#234f8b;color:white;gap:12px}.ips-panel-header h2{font-size:1.05rem;font-weight:700;margin:0}.ips-panel-header>span{font-size:.78rem;white-space:nowrap}.ips-search{padding:20px;display:flex;gap:10px}.ips-search input{flex:1;min-width:100px}.ips-tabs{display:flex;gap:5px;padding:0 20px 15px;overflow:auto}.ips-tabs a{padding:8px 12px;border-radius:8px;color:#54687e;font-size:.8rem;white-space:nowrap}.ips-tabs a.is-active{background:#eaf1fc;color:#214c84;font-weight:700}.ips-table{margin:0}.ips-table th{background:#f0f4f9;font-size:.75rem;color:#315279;border-top:1px solid #e3eaf2;white-space:nowrap}.ips-table td{font-size:.8rem;padding:15px 12px;vertical-align:middle;border-color:#edf1f6}.ips-table small{display:block;color:#76869a;margin-top:5px;min-width:85px}.ips-code{color:#234f8b;white-space:nowrap}.ips-table .badge{white-space:normal;text-align:left;font-size:.74rem;line-height:1.5}.ips-pagination{display:flex;justify-content:space-between;align-items:center;padding:18px 20px;gap:10px;flex-wrap:wrap;font-size:.8rem;color:#5b6d82}.ips-pagination .pagination{margin:0}.ips-footnote{padding:14px 2px;font-size:.78rem}
.ips-tray{position:sticky;top:75px}.ips-tray-body{padding:18px}.ips-tray-body>p{font-size:.82rem;color:#697b90}.ips-tray-body label{font-size:.82rem;color:#324c69}.ips-tray-items{max-height:480px;overflow:auto}.ips-tray-item{padding:12px 0;border-bottom:1px solid #e8eef4;font-size:.8rem}.ips-tray-item small{display:block;color:#738297;margin:5px 0}.ips-tray-item.is-ineligible{opacity:.65}.ips-help{font-size:.77rem;color:#63778e;margin:10px 0}.ips-empty{text-align:center;padding:35px 20px!important;color:#7e8da0}.ips-empty>i{font-size:1.8rem;opacity:.5;margin-bottom:10px}.ips-empty p{margin:8px 0}.ips-tray-item[data-status=succeeded] .ips-item-message{color:#18794e}.ips-tray-item[data-status=rejected] .ips-item-message{color:#b33c38}
#ips-confirm{border:0;border-radius:15px;max-width:520px;width:90%;padding:28px;color:#263e59;box-shadow:0 20px 70px #10233e55}#ips-confirm::backdrop{background:#10233e88}#ips-confirm h3{font-size:1.3rem;font-weight:700}#ips-confirm p{font-size:.9rem}#ips-confirm-codes{max-height:160px;overflow:auto;background:#f0f4f9;padding:10px;border-radius:8px}
.ips-field-label{display:block;font-size:.74rem;font-weight:700;color:#385574;margin-bottom:4px}
.ips-layout--readonly{grid-template-columns:minmax(0,1fr)}
@media(max-width:1100px){.ips-layout{grid-template-columns:minmax(0,1fr)}.ips-tray{position:static}.ips-tray-items{max-height:300px}}@media(max-width:760px){.ips-flow-steps{grid-template-columns:1fr 1fr}.ips-flow-rules{grid-template-columns:1fr}.ips-flow-heading{align-items:flex-start}}@media(max-width:600px){.ips-heading{display:block}.ips-identity{margin-top:12px}.ips-search{flex-wrap:wrap}.ips-journey{gap:12px}}
</style>
@endsection
@section('js')
<script src="{{ asset('js/ips-listing.js') }}?v={{ filemtime(public_path('js/ips-listing.js')) }}" defer></script>
@unless($readOnly)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const action = document.getElementById('ips-action'), process = document.getElementById('ips-process'), dialog = document.getElementById('ips-confirm');
    let rows = [...document.querySelectorAll('.ips-tray-item')], progress = document.getElementById('ips-progress');
    const descriptions = {
        EMR: 'Confirma la recepción física del envío en esta oficina de cambio.',
        EDN: 'Selecciona la siguiente oficina habilitada en la ruta del envío.',
        EIB: 'Selecciona una saca nacional abierta que corresponda a la ruta y clase postal.',
        EMG: 'Confirma solo paquetes que ya recibiste físicamente en tu oficina.',
        EDH: 'Confirma que estos paquetes están disponibles para retiro en tu punto de atención.',
        EDG: 'Confirma que estos paquetes están saliendo efectivamente a reparto.',
        EMH: 'Registra un intento fallido solo después de acudir al domicilio. Indica el motivo y la medida aplicada.',
        EMI: 'Confirma que la entrega física al destinatario ya se realizó. Si el envío sigue en Aduana, confirma además su devolución física; SITRA registrará ambos eventos en orden.'
    };
    let running = false;
    const customsNotice = document.createElement('p');
    customsNotice.className = 'alert alert-warning';
    customsNotice.hidden = true;
    document.getElementById('ips-confirm-description').after(customsNotice);
    function update() {
        const workspace = document.querySelector('.ips-workspace');
        const deliveryOnly = workspace?.dataset.deliveryOnly === '1';
        const workflowAction = workspace?.dataset.workflowAction || '';
        const candidateRows = rows.filter(row => ['ready','processing','uncertain'].includes(row.dataset.status));
        const candidateActions = [...new Set(candidateRows.flatMap(row => row.dataset.actions.split(',').filter(Boolean)))].filter(value => !deliveryOnly || value === 'EMI');
        const automaticAction = workflowAction || (deliveryOnly ? 'EMI' : (candidateActions.length === 1 ? candidateActions[0] : null));
        if (automaticAction && action.value !== automaticAction && [...action.options].some(option => option.value === automaticAction)) action.value = automaticAction;
        action.disabled = running || deliveryOnly || Boolean(workflowAction) || Boolean(automaticAction);
        document.getElementById('ips-action-help').textContent = descriptions[action.value];
        if (automaticAction && !deliveryOnly && !workflowAction) document.getElementById('ips-action-help').textContent += ' Movimiento propuesto automáticamente por la etapa IPS.';
        rows.forEach(row => {
            const allowed = row.dataset.actions.split(',').includes(action.value);
            const retry = ['processing','uncertain'].includes(row.dataset.status) && row.dataset.payloadAction === action.value;
            const enabled = retry || (row.dataset.status === 'ready' && allowed);
            const check = row.querySelector('.ips-choice');
            check.disabled = running || !enabled;
            if (!enabled) check.checked = false;
            // Cuando la bandeja contiene un solo paquete, la selección ya es
            // inequívoca: queda marcada automáticamente para evitar un clic
            // redundante. El operador todavía puede desmarcarla.
            if (rows.length === 1 && enabled && !row.dataset.autoSelected && !running) {
                check.checked = true;
                row.dataset.autoSelected = '1';
            }
            row.classList.toggle('is-ineligible', !enabled);
            row.querySelector('.ips-signatory').hidden = action.value !== 'EMI';
            row.querySelector('.ips-delivery-fields').hidden = action.value !== 'EMI';
            row.querySelector('.ips-attempt-fields').hidden = action.value !== 'EMH';
            const receiptFields = row.querySelector('.ips-physical-receipt');
            const receiptConfirm = row.querySelector('.ips-physical-receipt-confirm');
            const requiresPhysicalReceipt = ['EMR','EMG'].includes(action.value);
            receiptFields.hidden = !requiresPhysicalReceipt;
            receiptConfirm.required = requiresPhysicalReceipt;
            receiptConfirm.disabled = running || !enabled || Boolean(row.dataset.payloadAction);
            row.querySelector('.ips-route-fields').hidden = action.value !== 'EDN';
            row.querySelector('.ips-sack-fields').hidden = action.value !== 'EIB';
            row.querySelector('.ips-next-office').required = action.value === 'EDN';
            row.querySelector('.ips-domestic-receptacle').required = action.value === 'EIB';
        });
        const count = rows.filter(row => row.querySelector('.ips-choice').checked).length;
        process.disabled = running || count === 0;
        process.textContent = count ? 'Revisar ' + count + ' seleccionados' : 'Selecciona paquetes compatibles';
    }
    action.addEventListener('change', update);
    rows.forEach(row => row.querySelector('.ips-choice').addEventListener('change', update));
    window.addEventListener('ips:selection-added', event => {
        const data = event.detail || {}, packageData = data.package || {}, tray = document.querySelector('.ips-tray-items');
        if (!tray || !data.code || rows.some(row => row.dataset.code === data.code)) return;
        const article = document.createElement('article');
        article.className = 'ips-tray-item'; article.dataset.code = data.code;
        article.dataset.actions = (packageData.allowed_actions || []).join(','); article.dataset.status = 'ready'; article.dataset.payloadAction = '';
        article.dataset.customsReturn = [31, 34].includes(Number(packageData.operational_event_cd ?? packageData.event_cd)) && Number(packageData.state_cd) === 1 ? '1' : '0';
        article.innerHTML = '<div class="d-flex align-items-center"><input class="ips-choice mr-2" type="checkbox" aria-label="Seleccionar '+data.code+'"><strong>'+data.code+'</strong><form class="ml-auto ips-selection-remove" method="POST" action="'+(data.remove_url || '')+'"><input type="hidden" name="_token" value="'+document.querySelector('meta[name="csrf-token"]')?.content+'"><input type="hidden" name="_method" value="DELETE"><button type="submit" class="btn btn-sm text-muted" aria-label="Quitar '+data.code+'"><i class="fas fa-times"></i></button></form></div>'
            + '<small>'+((packageData.stage && packageData.stage.label) || 'Paquete IPS')+'</small><div class="ips-physical-receipt mt-2" hidden><label class="ips-receipt-confirm-label"><input class="ips-physical-receipt-confirm mr-1" type="checkbox"> Confirmo que el envío llegó físicamente a esta oficina.</label></div><small class="ips-item-message" role="status">Elige un movimiento compatible.</small>'
            + '<label class="ips-field-label mt-2">Fecha y hora efectiva · La Paz</label><input class="form-control form-control-sm ips-effective-time" type="datetime-local" step="60" value="'+new Date(Date.now()-4*60*60*1000).toISOString().slice(0,16)+'" aria-label="Fecha y hora efectiva en La Paz para '+data.code+'">'
            + '<input class="form-control form-control-sm ips-time-reason mt-2" maxlength="255" placeholder="Motivo si se registra con más de 2 minutos de retraso" aria-label="Motivo del registro tardío para '+data.code+'">'
            + '<input class="form-control form-control-sm ips-signatory mt-2" maxlength="64" placeholder="Persona que recibió (opcional)" aria-label="Receptor real de '+data.code+'" hidden>'
            + '<div class="ips-delivery-fields mt-2" hidden><label class="ips-field-label">Modalidad efectiva de entrega</label><select class="form-control form-control-sm ips-delivery-mode"><option value="">Seleccionar modalidad</option><option value="NO_DOMICILIARIA">Ventanilla · no domiciliaria</option><option value="DOMICILIARIA">Cartero · domiciliaria</option></select></div>'
            + '<div class="ips-attempt-fields mt-2" hidden><label class="ips-field-label">Motivo del intento fallido</label><select class="form-control form-control-sm ips-nondelivery-reason"><option value="">Seleccionar motivo</option></select><label class="ips-field-label mt-2">Medida aplicada</label><select class="form-control form-control-sm ips-nondelivery-measure"><option value="">Seleccionar medida</option></select></div>'
            + '<div class="ips-route-fields mt-2" hidden><label class="ips-field-label">Siguiente oficina de la ruta</label><select class="form-control form-control-sm ips-next-office" required><option value="">Seleccionar oficina</option></select></div>'
            + '<div class="ips-sack-fields mt-2" hidden><label class="ips-field-label">Saca nacional abierta</label><select class="form-control form-control-sm ips-domestic-receptacle" required><option value="">Seleccionar saca</option></select></div>';
        const reasonCatalog = @json($catalog['non_delivery_reasons'] ?? []);
        const measureCatalog = @json($catalog['non_delivery_measures'] ?? []);
        const appendOptions = (select, items, idKey, labelKey) => items.forEach(item => {
            const option = document.createElement('option'); option.value = item[idKey]; option.textContent = item[labelKey]; select.appendChild(option);
        });
        appendOptions(article.querySelector('.ips-nondelivery-reason'), reasonCatalog, 'NON_DELIVERY_REASON_CD', 'NON_DELIVERY_REASON_NM');
        appendOptions(article.querySelector('.ips-nondelivery-measure'), measureCatalog, 'NON_DELIVERY_MEASURE_CD', 'NON_DELIVERY_MEASURE_NM');
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
        tray.querySelector('.ips-empty')?.remove(); tray.appendChild(article); rows.push(article);
        article.querySelector('.ips-choice').addEventListener('change', update); update();
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
            const trayCount = document.querySelector('.ips-tray .ips-panel-header > span');
            if (trayCount) trayCount.textContent = result.count + '/50';
            window.dispatchEvent(new CustomEvent('ips:selection-removed', {detail: result}));
            update();
        } catch (error) { button.disabled = false; progress.textContent = error.message; }
    });
    process.addEventListener('click', () => {
        const selected = rows.filter(row => row.querySelector('.ips-choice').checked);
        for (const row of selected) {
            const requiredField = action.value === 'EDN' ? row.querySelector('.ips-next-office') : (action.value === 'EIB' ? row.querySelector('.ips-domestic-receptacle') : null);
            if (requiredField && !requiredField.value) {
                requiredField.reportValidity();
                return;
            }
            if (['EMR','EMG'].includes(action.value) && !row.querySelector('.ips-physical-receipt-confirm').checked) {
                row.querySelector('.ips-physical-receipt-confirm').reportValidity();
                progress.textContent = 'Confirma la recepción física de cada envío antes de continuar.';
                return;
            }
        }
        const customsRows = selected.filter(row => row.dataset.customsReturn === '1');
        document.getElementById('ips-confirm-description').textContent = action.value === 'EMI' && customsRows.length
            ? 'Se registrará la devolución de Aduana y la entrega final para los envíos indicados.'
            : descriptions[action.value];
        customsNotice.hidden = action.value !== 'EMI' || customsRows.length === 0;
        customsNotice.textContent = customsNotice.hidden ? '' : 'Para ' + customsRows.map(row => row.dataset.code).join(', ') + ': confirma que Aduana devolvi\u00F3 f\u00EDsicamente estos env\u00EDos a la oficina y que ahora se entregan a sus destinatarios. SITRA registrar\u00E1 primero la devoluci\u00F3n de Aduana y despu\u00E9s la entrega.';
        document.getElementById('ips-confirm-codes').textContent = selected.map(row=>row.dataset.code).join(', ');
        dialog.returnValue = '';
        dialog.showModal();
    });
    dialog.addEventListener('close', async () => {
        if (dialog.returnValue !== 'confirm' || running) return;
        const selected = rows.filter(row => row.querySelector('.ips-choice').checked), chosenAction = action.value;
        running = true; action.disabled = true; update();
        let successes = 0;
        for (let i = 0; i < selected.length; i++) {
            const row = selected[i];
            progress.textContent = 'Procesando ' + (i+1) + ' de ' + selected.length + '…';
            try {
                const operation = {codigo:row.dataset.code,action:chosenAction,confirmed:true,signatory:row.querySelector('.ips-signatory').value || null,occurred_at_local:row.querySelector('.ips-effective-time').value,time_reason:row.querySelector('.ips-time-reason').value || null};
                if (['EMR','EMG'].includes(chosenAction)) operation.physical_receipt_confirmed = row.querySelector('.ips-physical-receipt-confirm').checked;
                if (chosenAction === 'EMI') operation.delivery_mode = row.querySelector('.ips-delivery-mode').value;
                if (chosenAction === 'EDN') operation.next_office_cd = row.querySelector('.ips-next-office').value;
                if (chosenAction === 'EIB') operation.domestic_receptacle_pid = row.querySelector('.ips-domestic-receptacle').value;
                if (chosenAction === 'EMH') {
                    operation.non_delivery_reason = row.querySelector('.ips-nondelivery-reason').value;
                    operation.non_delivery_measure = row.querySelector('.ips-nondelivery-measure').value;
                }
                if (row.dataset.customsReturn === '1') operation.customs_return_confirmed = true;
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
                if (!editableRejection) row.querySelectorAll('input:not(.ips-choice), select').forEach(field => field.disabled = true);
                if (result.status === 'succeeded') successes++;
            } catch(e) {
                row.dataset.status = 'uncertain'; row.dataset.payloadAction = chosenAction;
                row.querySelector('.ips-item-message').textContent = 'Sin confirmación. Actualiza y reintenta la misma acción.';
            }
            row.querySelector('.ips-choice').checked = false;
        }
        running = false; action.disabled = false; update();
        progress.textContent = successes + ' confirmados de ' + selected.length + '. Revisa el resultado de cada paquete.';
        document.getElementById('ips-refresh').hidden = false;
    });
    update();
});
</script>
@endunless
@endsection
