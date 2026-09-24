@extends('adminlte::page')
@section('title', 'IPS')
@section('content')
<div class="ips-workspace">
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<p id="ips-listing-status" class="ips-help" role="status" aria-live="polite" hidden></p>
    <div class="ips-layout">
        <main class="ips-main" id="ips-listing">
            @include('paquetes_ips.partials.listing')
        </main>
        <aside class="ips-panel ips-tray" aria-label="Bandeja de trabajo">
            <div class="ips-panel-header"><h2><i class="fas fa-clipboard-list mr-2"></i>Tu bandeja</h2><span>{{ count($selection) }}/50</span></div>
            <div class="ips-tray-body">
                <p>Agrega los resultados que quieras trabajar. La lista se conserva mientras buscas otros paquetes.</p>
                <label for="ips-action">Movimiento a registrar</label>
                <select id="ips-action" class="form-control">@foreach($actions as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select>
                <p id="ips-action-help" class="ips-help"></p>
                <div class="ips-tray-items">
                    @forelse($selection as $code=>$entry)
                        <article class="ips-tray-item" data-code="{{ $code }}" data-actions="{{ implode(',', $entry['package']['allowed_actions'] ?? []) }}" data-status="{{ $entry['status'] }}" data-payload-action="{{ $entry['payload']['event'] ?? '' }}">
                            <div class="d-flex align-items-center"><input class="ips-choice mr-2" type="checkbox" aria-label="Seleccionar {{ $code }}"><strong>{{ $code }}</strong>
                                @unless(in_array($entry['status'],['processing','uncertain'],true))<form class="ml-auto ips-selection-remove" method="POST" action="{{ route('ips.selection.remove',$code) }}">@csrf @method('DELETE')<button type="submit" class="btn btn-sm text-muted" aria-label="Quitar {{ $code }}"><i class="fas fa-times"></i></button></form>@endunless
                            </div>
                            <small>{{ $entry['package']['stage']['label'] }}</small>
                            <small class="ips-item-message" role="status">{{ $entry['message'] ?: (empty($entry['package']['allowed_actions']) ? 'Sin acción habilitada para esta oficina.' : 'Elige un movimiento compatible.') }}</small>
                            <input class="form-control form-control-sm ips-signatory mt-2" maxlength="64" placeholder="Persona que recibió (opcional)" aria-label="Receptor real de {{ $code }}" hidden>
                        </article>
                    @empty<div class="ips-empty"><i class="fas fa-inbox"></i><p>Tu bandeja está vacía.<br>Busca un paquete y pulsa Agregar.</p></div>@endforelse
                </div>
                <button id="ips-process" class="btn btn-primary btn-block mt-3" disabled>Revisar seleccionados</button>
                <p id="ips-progress" role="status" aria-live="polite" class="ips-help"></p>
                <button id="ips-refresh" class="btn btn-outline-primary btn-block" hidden onclick="location.reload()">Actualizar resultados</button>
            </div>
        </aside>
    </div>
</div>
<dialog id="ips-confirm"><form method="dialog"><h3>Confirmar movimiento</h3><p id="ips-confirm-description"></p><p id="ips-confirm-codes"></p><p>Se validará cada paquete nuevamente. Cada movimiento tendrá su propio resultado.</p><div class="d-flex justify-content-end"><button value="cancel" class="btn btn-light mr-2">Volver</button><button value="confirm" class="btn btn-primary">Confirmar y registrar</button></div></form></dialog>
@endsection
@section('css')
<style>
.ips-heading{display:flex;justify-content:space-between;gap:20px;padding:10px 5px 20px;align-items:center}.ips-heading h1{font-weight:800;color:#183352;margin:3px 0}.ips-heading p,.ips-footnote{color:#697b90;margin:0;font-size:.9rem}.ips-eyebrow{font-size:.68rem;color:#47698e;letter-spacing:.14em;font-weight:700}.ips-identity{display:flex;gap:12px;align-items:center;padding:12px 18px;background:white;border:1px solid #dce6f1;border-radius:12px;color:#254e80}.ips-identity small{display:block;color:#718096}
.ips-workspace{padding:24px 5px 24px}
.ips-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:20px;align-items:start}.ips-main{min-width:0}.ips-panel{background:white;border:1px solid #e0e7ef;border-radius:14px;box-shadow:0 5px 20px #17314d08;overflow:hidden}.ips-panel-header{display:flex;justify-content:space-between;align-items:center;padding:18px 20px;background:#234f8b;color:white;gap:12px}.ips-panel-header h2{font-size:1.05rem;font-weight:700;margin:0}.ips-panel-header>span{font-size:.78rem;white-space:nowrap}.ips-search{padding:20px;display:flex;gap:10px}.ips-search input{flex:1;min-width:100px}.ips-tabs{display:flex;gap:5px;padding:0 20px 15px;overflow:auto}.ips-tabs a{padding:8px 12px;border-radius:8px;color:#54687e;font-size:.8rem;white-space:nowrap}.ips-tabs a.is-active{background:#eaf1fc;color:#214c84;font-weight:700}.ips-table{margin:0}.ips-table th{background:#f0f4f9;font-size:.75rem;color:#315279;border-top:1px solid #e3eaf2;white-space:nowrap}.ips-table td{font-size:.8rem;padding:15px 12px;vertical-align:middle;border-color:#edf1f6}.ips-table small{display:block;color:#76869a;margin-top:5px;min-width:85px}.ips-code{color:#234f8b;white-space:nowrap}.ips-table .badge{white-space:normal;text-align:left;font-size:.74rem;line-height:1.5}.ips-pagination{display:flex;justify-content:space-between;align-items:center;padding:18px 20px;gap:10px;flex-wrap:wrap;font-size:.8rem;color:#5b6d82}.ips-pagination .pagination{margin:0}.ips-footnote{padding:14px 2px;font-size:.78rem}
.ips-tray{position:sticky;top:75px}.ips-tray-body{padding:18px}.ips-tray-body>p{font-size:.82rem;color:#697b90}.ips-tray-body label{font-size:.82rem;color:#324c69}.ips-tray-items{max-height:480px;overflow:auto}.ips-tray-item{padding:12px 0;border-bottom:1px solid #e8eef4;font-size:.8rem}.ips-tray-item small{display:block;color:#738297;margin:5px 0}.ips-tray-item.is-ineligible{opacity:.65}.ips-help{font-size:.77rem;color:#63778e;margin:10px 0}.ips-empty{text-align:center;padding:35px 20px!important;color:#7e8da0}.ips-empty>i{font-size:1.8rem;opacity:.5;margin-bottom:10px}.ips-empty p{margin:8px 0}.ips-tray-item[data-status=succeeded] .ips-item-message{color:#18794e}.ips-tray-item[data-status=rejected] .ips-item-message{color:#b33c38}
#ips-confirm{border:0;border-radius:15px;max-width:520px;width:90%;padding:28px;color:#263e59;box-shadow:0 20px 70px #10233e55}#ips-confirm::backdrop{background:#10233e88}#ips-confirm h3{font-size:1.3rem;font-weight:700}#ips-confirm p{font-size:.9rem}#ips-confirm-codes{max-height:160px;overflow:auto;background:#f0f4f9;padding:10px;border-radius:8px}
@media(max-width:1100px){.ips-layout{grid-template-columns:minmax(0,1fr)}.ips-tray{position:static}.ips-tray-items{max-height:300px}}@media(max-width:600px){.ips-heading{display:block}.ips-identity{margin-top:12px}.ips-search{flex-wrap:wrap}.ips-journey{gap:12px}}
</style>
@endsection
@section('js')
<script src="{{ asset('js/ips-listing.js') }}?v={{ filemtime(public_path('js/ips-listing.js')) }}" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const action = document.getElementById('ips-action'), process = document.getElementById('ips-process'), dialog = document.getElementById('ips-confirm');
    let rows = [...document.querySelectorAll('.ips-tray-item')], progress = document.getElementById('ips-progress');
    const descriptions = {
        EMG: 'Confirma solo paquetes que ya recibiste físicamente en tu oficina.',
        EDH: 'Confirma que estos paquetes están disponibles para retiro en tu punto de atención.',
        EDG: 'Confirma que estos paquetes están saliendo efectivamente a reparto.',
        EMI: 'Confirma solo paquetes ya entregados a sus receptores. El receptor opcional es quien recibió, no el empleado.'
    };
    let running = false;
    function update() {
        const candidateRows = rows.filter(row => ['ready','processing','uncertain'].includes(row.dataset.status));
        const candidateActions = [...new Set(candidateRows.flatMap(row => row.dataset.actions.split(',').filter(Boolean)))];
        const automaticAction = candidateActions.length === 1 ? candidateActions[0] : null;
        if (automaticAction && action.value !== automaticAction) action.value = automaticAction;
        action.disabled = running || Boolean(automaticAction);
        document.getElementById('ips-action-help').textContent = descriptions[action.value];
        if (automaticAction) document.getElementById('ips-action-help').textContent += ' Movimiento propuesto automáticamente por la etapa IPS.';
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
        article.innerHTML = '<div class="d-flex align-items-center"><input class="ips-choice mr-2" type="checkbox" aria-label="Seleccionar '+data.code+'"><strong>'+data.code+'</strong><form class="ml-auto ips-selection-remove" method="POST" action="'+(data.remove_url || '')+'"><input type="hidden" name="_token" value="'+document.querySelector('meta[name="csrf-token"]')?.content+'"><input type="hidden" name="_method" value="DELETE"><button type="submit" class="btn btn-sm text-muted" aria-label="Quitar '+data.code+'"><i class="fas fa-times"></i></button></form></div>'
            + '<small>'+((packageData.stage && packageData.stage.label) || 'Paquete IPS')+'</small><small class="ips-item-message" role="status">Elige un movimiento compatible.</small>'
            + '<input class="form-control form-control-sm ips-signatory mt-2" maxlength="64" placeholder="Persona que recibió (opcional)" aria-label="Receptor real de '+data.code+'" hidden>';
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
        document.getElementById('ips-confirm-description').textContent = descriptions[action.value];
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
                const response = await fetch(@json(route('ips.operate')), {
                    method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},
                    body:JSON.stringify({codigo:row.dataset.code,action:chosenAction,confirmed:true,signatory:row.querySelector('.ips-signatory').value || null})
                });
                const result = await response.json();
                row.dataset.status = result.status || 'rejected';
                row.dataset.payloadAction = chosenAction;
                row.querySelector('.ips-item-message').textContent = result.message || 'Revise la respuesta antes de continuar.';
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
@endsection
