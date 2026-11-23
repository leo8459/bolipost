(() => {
    'use strict';
    const listing = document.getElementById('ips-listing');
    const status = document.getElementById('ips-listing-status');
    if (!listing || !status) return;
    let activeRequest;
    const workspace = document.querySelector('.ips-workspace');
    const fixedWorkflow = workspace?.dataset.workflowAction || '';
    const scanEnabled = workspace?.dataset.scanEnabled === '1';
    let deliveryScanTyping = {input: null, startedAt: 0, lastAt: 0, characters: 0};
    let searchButtonClicked = false;
    const setScanPlaceholder = () => {
        if (fixedWorkflow === 'EMG') {
            listing.querySelector('#ips-code')?.setAttribute('placeholder', 'Escanea un c\u00f3digo para recibir');
            return;
        }
        if (fixedWorkflow) listing.querySelector('#ips-code')?.setAttribute('placeholder', 'Escanea un c\u00f3digo para a\u00f1adir al lote');
    };
    setScanPlaceholder();

    function showAlreadyReceived(code) {
        const dialog = document.getElementById('ips-already-received');
        if (!dialog) return;
        document.getElementById('ips-already-received-code').textContent = code;
        if (!dialog.open) dialog.showModal();
    }

    function searchUrl(form) {
        const url = new URL(form.action, location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        return url;
    }

    async function load(url, push = true) {
        if (url.origin !== location.origin || url.pathname !== location.pathname) {
            location.assign(url.href);
            return;
        }
        if (activeRequest) activeRequest.abort();
        const request = new AbortController();
        activeRequest = request;
        listing.setAttribute('aria-busy', 'true');
        listing.querySelectorAll('.ips-selection-add button').forEach(button => { button.disabled = true; });
        status.hidden = false;
        const recipientQuery = url.searchParams.get('recipient_address_q')?.trim() || '';
        const codeLookup = /^[A-Z]{2}\d/i.test(recipientQuery);
        status.textContent = recipientQuery
            ? (codeLookup ? 'Buscando código en IPS…' : 'Buscando en SIOP por destinatario o dirección…')
            : 'Actualizando paquetes…';
        try {
            const response = await fetch(url.href, {
                signal: request.signal,
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                cache: 'no-store',
            });
            if (!response.headers.get('content-type')?.includes('application/json')) {
                throw new Error('No se pudo cargar el listado. Si tu sesión venció, recarga la página para iniciar sesión.');
            }
            const result = await response.json();
            if (!response.ok || typeof result.html !== 'string') {
                throw new Error(result.message || 'No se pudo consultar IPS.');
            }
            if (request !== activeRequest) return;
            const previousSearchInput = listing.querySelector('#ips-code');
            const restoreSearchFocus = document.activeElement === previousSearchInput;
            const selectionStart = restoreSearchFocus ? previousSearchInput.selectionStart : null;
            const selectionEnd = restoreSearchFocus ? previousSearchInput.selectionEnd : null;
            const selectionDirection = restoreSearchFocus ? previousSearchInput.selectionDirection : null;
            // Server-rendered, escaped Blade markup from this authenticated same-origin route.
            listing.classList.remove('is-stale');
            listing.innerHTML = result.html;
            setScanPlaceholder();
            if (restoreSearchFocus) {
                const nextSearchInput = listing.querySelector('#ips-code');
                nextSearchInput?.focus({preventScroll: true});
                if (nextSearchInput && selectionStart !== null && selectionEnd !== null) {
                    const max = nextSearchInput.value.length;
                    nextSearchInput.setSelectionRange(
                        Math.min(selectionStart, max),
                        Math.min(selectionEnd, max),
                        selectionDirection || 'none'
                    );
                }
            }
            if (push) history.pushState(null, '', url.href);
            status.hidden = true;
            return true;
        } catch (error) {
            if (request !== activeRequest || error.name === 'AbortError') return;
            listing.classList.add('is-stale');
            listing.querySelectorAll('.ips-selection-add button').forEach(button => { button.disabled = true; });
            status.hidden = false;
            status.textContent = recipientQuery && !codeLookup
                ? 'No se pudo buscar en SIOP. Se mantienen resultados anteriores, que pueden no corresponder a esta búsqueda. Vuelve a intentar antes de agregar paquetes.'
                : 'No se pudo consultar IPS. Se mantienen resultados anteriores, que pueden no corresponder a esta búsqueda. Vuelve a intentar antes de agregar paquetes.';
            return false;
        } finally {
            if (request === activeRequest) listing.removeAttribute('aria-busy');
        }
    }

    const scanQueue = [];
    let processingScans = false;

    function resetDeliveryScanTyping() {
        deliveryScanTyping = {input: null, startedAt: 0, lastAt: 0, characters: 0};
    }

    listing.addEventListener('input', event => {
        const input = event.target.closest('#ips-code[name="recipient_address_q"]');
        if (!input || workspace?.dataset.deliveryOnly !== '1' || fixedWorkflow) return;

        const insertedText = event.inputType === 'insertText' ? String(event.data || '') : '';
        if (!insertedText || !/^[A-Z0-9]+$/i.test(insertedText)) {
            resetDeliveryScanTyping();
            return;
        }

        const now = performance.now();
        if (deliveryScanTyping.input !== input || !deliveryScanTyping.lastAt || now - deliveryScanTyping.lastAt > 250) {
            deliveryScanTyping = {input, startedAt: now, lastAt: now, characters: insertedText.length};
            return;
        }

        deliveryScanTyping.lastAt = now;
        deliveryScanTyping.characters += insertedText.length;
    });

    listing.addEventListener('click', event => {
        if (!event.target.closest('.ips-search-submit')) return;
        searchButtonClicked = true;
        window.setTimeout(() => { searchButtonClicked = false; }, 0);
    });

    function isRapidDeliveryScannerInput(input, code) {
        const typing = deliveryScanTyping;
        if (typing.input !== input || typing.characters < code.length - 1 || !typing.lastAt) return false;
        const duration = typing.lastAt - typing.startedAt;
        const timeSinceLastCharacter = performance.now() - typing.lastAt;
        return duration <= 900
            && duration / Math.max(typing.characters, 1) <= 45
            && timeSinceLastCharacter <= 300;
    }

    async function addSelectionForm(addForm, scanned = false) {
        const button = addForm.querySelector('button');
        button.disabled = true;
        button.textContent = 'Guardando...';
        try {
            const response = await fetch(addForm.action, {
                method: 'POST',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''},
                body: new FormData(addForm),
                credentials: 'same-origin',
            });
            const result = await response.json().catch(() => ({}));
            if (result.error_code === 'already_received') showAlreadyReceived(result.code);
            if (!response.ok || !result.code) throw new Error(result.message || 'No se pudo guardar el paquete.');
            const row = addForm.closest('tr');
            row.querySelector('td:last-child').innerHTML = '<span class="text-success"><i class="fas fa-check"></i> En bandeja</span>';
            const trayCount = document.getElementById('ips-tray-count');
            if (trayCount) trayCount.textContent = result.count + '/50 paquetes';
            window.dispatchEvent(new CustomEvent('ips:selection-added', {detail: {...result, scanned}}));
            if (scanQueue.length) processScanQueue();
            status.hidden = false;
            status.textContent = fixedWorkflow === 'EMG'
                ? 'Revisa los datos UPU y confirma la llegada f\u00edsica en el modal de recepci\u00f3n.'
                : result.route_mismatch_receipt
                ? 'Paquete con ruta a otra oficina agregado para revisar la recepción física. Confirma la diferencia de ruta en la bandeja antes de procesar.'
                : (scanned ? 'Paquete agregado al lote. Escanea el siguiente.' : 'Paquete guardado en la bandeja.');
            setTimeout(() => { status.hidden = true; }, 3000);
            return true;
        } catch (error) {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-plus"></i> ' + (document.querySelector('.ips-workspace')?.dataset.addLabel || 'Agregar');
            status.hidden = false;
            status.textContent = error.message;
            return false;
        }
    }

    const queueOfficeScope = workspace?.dataset.combinedOffices
        ? 'group-' + workspace.dataset.combinedOffices
        : (new URL(location.href).searchParams.get('office_cd') || listing.querySelector('[name="office_cd"]')?.value || 'default');
    const inboundQueueStorageKey = 'ips-inbound-next-scans:' + location.pathname + ':office:' + queueOfficeScope;

    async function processScanQueue() {
        if (processingScans) return;
        processingScans = true;
        const workspace = document.querySelector('.ips-workspace');
        while (scanQueue.length) {
            const code = scanQueue.shift();
            const alreadyInTray = [...document.querySelectorAll('.ips-tray-item')]
                .find(row => row.dataset.code === code);
            if (alreadyInTray) {
                if (alreadyInTray.dataset.status === 'succeeded') showAlreadyReceived(code);
                else {
                    const checkbox = alreadyInTray.querySelector('.ips-choice');
                    if (checkbox && !checkbox.disabled) {
                        checkbox.checked = true;
                        checkbox.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                    if (fixedWorkflow === 'EMG' || workspace?.dataset.smartReception === '1') window.dispatchEvent(new CustomEvent('ips:receipt-requested', {detail: {code}}));
                }
                status.hidden = false;
                status.textContent = 'Ese paquete ya está en la bandeja.';
                continue;
            }
            const body = new FormData();
            body.append('codigo', code);
            if (workspace?.dataset.deliveryOnly === '1' && !fixedWorkflow) body.append('workflow', 'delivery');
            try {
                const response = await fetch(workspace?.dataset.selectionAddUrl || '/ips/seleccionados', {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''},
                    body,
                    credentials: 'same-origin',
                });
                const result = await response.json().catch(() => ({}));
                if (result.error_code === 'already_received') showAlreadyReceived(result.code || code);
                if (!response.ok || !result.code) {
                    if (response.status === 404 && (fixedWorkflow === 'EMR' || (fixedWorkflow === 'EMG' && /^L[A-Z][0-9]{9}[A-Z]{2}$/.test(code)))) {
                        try {
                            if (scanQueue.length) sessionStorage.setItem(inboundQueueStorageKey, JSON.stringify({codes: scanQueue, savedAt: Date.now()}));
                            else sessionStorage.removeItem(inboundQueueStorageKey);
                        } catch (_) {}
                        const searchForm = listing.querySelector('.ips-search');
                        if (searchForm) {
                            const url = new URL(searchForm.action, location.href);
                            const params = new URLSearchParams(new FormData(searchForm));
                            params.set('q', code);
                            url.search = params.toString();
                            processingScans = false;
                            location.assign(url.href);
                            return;
                        }
                    }
                    throw new Error(result.message || 'IPS no habilita este paquete para el paso actual.');
                }
                const codeCell = [...listing.querySelectorAll('.ips-code')]
                    .find(node => node.textContent.trim().toUpperCase() === code);
                const actionCell = codeCell?.closest('tr')?.querySelector('td:last-child');
                if (actionCell) actionCell.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> En bandeja</span>';
                const trayCount = document.getElementById('ips-tray-count');
                if (trayCount) trayCount.textContent = result.count + '/50 paquetes';
                window.dispatchEvent(new CustomEvent('ips:selection-added', {detail: {...result, scanned: true}}));
                status.hidden = false;
                status.textContent = workspace?.dataset.smartReception === '1'
                    ? 'IPS determinó el siguiente paso. Confirma la llegada física y el resultado real de Aduana cuando corresponda.'
                    : fixedWorkflow === 'EMG'
                    ? 'Revisa los datos UPU y confirma la llegada f\u00edsica en el modal de recepci\u00f3n.'
                    : result.package?.needs_receipt_office
                    ? 'Paquete seleccionado y oficina asignada automáticamente. Confirma su llegada física en el modal de recepción.'
                    : result.route_mismatch_receipt
                    ? 'Paquete con ruta a otra oficina agregado para revisar la recepción física. Confirma la diferencia de ruta en la bandeja antes de procesar.'
                    : 'Paquete agregado al lote. Escanea el siguiente.';
            } catch (error) {
                status.hidden = false;
                status.textContent = error.message;
            }
        }
        processingScans = false;
        if (!document.querySelector('dialog[open]')) listing.querySelector('#ips-code')?.focus();
    }
    listing.addEventListener('submit', event => {
        const addForm = event.target.closest('.ips-selection-add');
        if (addForm) {
            event.preventDefault();
            if (listing.classList.contains('is-stale')) {
                status.hidden = false;
                status.textContent = 'Actualiza la búsqueda antes de agregar paquetes; la lista visible puede estar desactualizada.';
                return;
            }
            addSelectionForm(addForm);
            return;
        }
        const form = event.target.closest('.ips-search');
        if (!form) return;
        event.preventDefault();
        const formData = new FormData(form);
        const code = String(formData.get('q') || formData.get('recipient_address_q') || '').trim().toUpperCase();
        const isWorkflowScan = scanEnabled && fixedWorkflow && /^[A-Z0-9-]{8,35}$/.test(code);
        const searchInput = form.querySelector('#ips-code');
        const isDeliveryScannerScan = scanEnabled
            && workspace?.dataset.deliveryOnly === '1'
            && !fixedWorkflow
            && !searchButtonClicked
            && /^[A-Z]{2}\d{9}[A-Z]{2}$/.test(code)
            && isRapidDeliveryScannerInput(searchInput, code);
        if (isWorkflowScan || isDeliveryScannerScan) {
            scanQueue.push(code);
            if (searchInput) { searchInput.value = ''; searchInput.focus(); }
            resetDeliveryScanTyping();
            status.hidden = false;
            status.textContent = scanQueue.length > 1
                ? 'Escaneos en cola: ' + scanQueue.length + '.'
                : isDeliveryScannerScan ? 'Código escaneado; agregando a la bandeja…' : 'Consultando el paquete en IPS...';
            processScanQueue();
            return;
        }
        load(searchUrl(form)).then(loaded => {
            if (!loaded) return;
            const nextInput = listing.querySelector('#ips-code');
            nextInput?.focus({preventScroll: true});
            if (nextInput) nextInput.setSelectionRange(nextInput.value.length, nextInput.value.length);
        });
    });
    listing.addEventListener('paste', event => {
        const input = event.target.closest('#ips-code[data-search-on-paste]');
        if (!input?.form) return;
        const code = event.clipboardData?.getData('text/plain').trim().toUpperCase();
        if (!/^[A-Z0-9-]{1,35}$/.test(code ?? '')) return;

        if (workspace?.dataset.deliveryOnly === '1' && !fixedWorkflow) {
            if (!/^[A-Z]{2}\d/.test(code)) return;
            event.preventDefault();
            input.value = code;
            load(searchUrl(input.form)).then(loaded => {
                if (!loaded) return;
                const nextInput = listing.querySelector('#ips-code');
                nextInput?.focus({preventScroll: true});
                if (nextInput) nextInput.setSelectionRange(nextInput.value.length, nextInput.value.length);
            });
            return;
        }

        event.preventDefault();
        input.value = code;
        load(searchUrl(input.form));
    });
    listing.addEventListener('click', event => {
        const receivedButton = event.target.closest('.ips-already-received');
        if (receivedButton) {
            showAlreadyReceived(receivedButton.dataset.code);
            return;
        }
        const link = event.target.closest('.ips-tabs a, .ips-pagination a, .ips-search a');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        load(new URL(link.href, location.href));
    });
    window.addEventListener('ips:selection-removed', event => {
        const code = event.detail?.code;
        if (!code) return;
        const codeCell = [...listing.querySelectorAll('.ips-code')].find(node => node.textContent.trim() === code);
        const cell = codeCell?.closest('tr')?.querySelector('td:last-child');
        if (cell && event.detail.received) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-outline-success ips-already-received';
            button.dataset.code = code;
            button.textContent = 'Ya recibido';
            cell.replaceChildren(button);
            return;
        }
        if (!cell || cell.querySelector('.ips-selection-add')) return;
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const workspace = document.querySelector('.ips-workspace');
        const deliveryOnly = workspace?.dataset.deliveryOnly === '1';
        const addUrl = workspace?.dataset.selectionAddUrl || '/ips/seleccionados';
        const workflowField = deliveryOnly ? '<input type="hidden" name="workflow" value="delivery">' : '';
        const addLabel = workspace?.dataset.addLabel || 'Agregar';
        cell.innerHTML = '<form class="ips-selection-add" method="POST" action="'+addUrl+'"><input type="hidden" name="_token" value="'+token+'"><input type="hidden" name="codigo" value="'+code+'">'+workflowField+'<button class="btn btn-sm btn-outline-primary"><i class="fas fa-plus"></i> '+addLabel+'</button></form>';
    });
    window.addEventListener('popstate', () => load(new URL(location.href), false));
    const selectInitialReception = () => {
        const input = listing.querySelector('#ips-code');
        const code = String(input?.value || '').trim().toUpperCase();
        if (scanEnabled && fixedWorkflow === 'EMG' && /^[A-Z0-9-]{8,35}$/.test(code)
            && !document.getElementById('ips-inbound-create')) {
            scanQueue.push(code);
            if (input) input.value = '';
            processScanQueue();
        }
    };
    if (document.readyState === 'complete') selectInitialReception();
    else window.addEventListener('DOMContentLoaded', selectInitialReception, {once: true});
    try {
        const queued = JSON.parse(sessionStorage.getItem(inboundQueueStorageKey) || 'null');
        const created = [...document.querySelectorAll('.alert-success')]
            .some(node => node.textContent.includes('Paquete creado'));
        const queueIsFresh = queued && Date.now() - Number(queued.savedAt || 0) < 3600000;
        if (created && queueIsFresh && Array.isArray(queued.codes)) {
            sessionStorage.removeItem(inboundQueueStorageKey);
            scanQueue.push(...queued.codes.filter(code => typeof code === 'string'));
            const input = listing.querySelector('#ips-code');
            if (input) input.value = '';
            processScanQueue();
        } else if (!queueIsFresh) {
            sessionStorage.removeItem(inboundQueueStorageKey);
        }
    } catch (_) {}
})();
