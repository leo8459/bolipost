(() => {
    'use strict';
    const listing = document.getElementById('ips-listing');
    const status = document.getElementById('ips-listing-status');
    if (!listing || !status) return;
    let activeRequest;
    const workspace = document.querySelector('.ips-workspace');
    const fixedWorkflow = Boolean(workspace?.dataset.workflowAction);
    const scanEnabled = workspace?.dataset.scanEnabled === '1';
    const setScanPlaceholder = () => {
        if (fixedWorkflow) listing.querySelector('#ips-code')?.setAttribute('placeholder', 'Escanea un c\u00f3digo para a\u00f1adir al lote');
    };
    setScanPlaceholder();

    async function load(url, push = true) {
        if (url.origin !== location.origin || url.pathname !== location.pathname) {
            location.assign(url.href);
            return;
        }
        if (activeRequest) activeRequest.abort();
        const request = new AbortController();
        activeRequest = request;
        listing.setAttribute('aria-busy', 'true');
        status.hidden = true;
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
            // Server-rendered, escaped Blade markup from this authenticated same-origin route.
            listing.innerHTML = result.html;
            setScanPlaceholder();
            if (push) history.pushState(null, '', url.href);
            status.hidden = true;
            return true;
        } catch (error) {
            if (request !== activeRequest || error.name === 'AbortError') return;
            status.hidden = false;
            status.textContent = error.message + ' Se mantienen los resultados anteriores; puedes volver a intentar.';
            return false;
        } finally {
            if (request === activeRequest) listing.removeAttribute('aria-busy');
        }
    }

    const scanQueue = [];
    let processingScans = false;

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
            if (!response.ok || !result.code) throw new Error(result.message || 'No se pudo guardar el paquete.');
            const row = addForm.closest('tr');
            row.querySelector('td:last-child').innerHTML = '<span class="text-success"><i class="fas fa-check"></i> En bandeja</span>';
            const trayCount = document.getElementById('ips-tray-count');
            if (trayCount) trayCount.textContent = result.count + '/50 paquetes';
            window.dispatchEvent(new CustomEvent('ips:selection-added', {detail: {...result, scanned}}));
            status.hidden = false;
            status.textContent = scanned ? 'Paquete agregado al lote. Escanea el siguiente.' : 'Paquete guardado en la bandeja.';
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

    const inboundQueueStorageKey = 'ips-inbound-next-scans:' + location.pathname;

    async function processScanQueue() {
        if (processingScans) return;
        processingScans = true;
        const workspace = document.querySelector('.ips-workspace');
        while (scanQueue.length) {
            const code = scanQueue.shift();
            const alreadyInTray = [...document.querySelectorAll('.ips-tray-item')]
                .some(row => row.dataset.code === code);
            if (alreadyInTray) {
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
                if (!response.ok || !result.code) {
                    if (fixedWorkflow === 'EMR' && response.status === 404) {
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
                status.textContent = 'Paquete agregado al lote. Escanea el siguiente.';
            } catch (error) {
                status.hidden = false;
                status.textContent = error.message;
            }
        }
        processingScans = false;
        listing.querySelector('#ips-code')?.focus();
    }
    listing.addEventListener('submit', event => {
        const addForm = event.target.closest('.ips-selection-add');
        if (addForm) {
            event.preventDefault();
            addSelectionForm(addForm);
            return;
        }
        const form = event.target.closest('.ips-search');
        if (!form) return;
        event.preventDefault();
        const code = String(new FormData(form).get('q') || '').trim().toUpperCase();
        const isWorkflowScan = scanEnabled && fixedWorkflow && /^[A-Z0-9-]{8,35}$/.test(code);
        if (isWorkflowScan) {
            scanQueue.push(code);
            const input = form.querySelector('#ips-code');
            if (input) { input.value = ''; input.focus(); }
            status.hidden = false;
            status.textContent = scanQueue.length > 1
                ? 'Escaneos en cola: ' + scanQueue.length + '.'
                : 'Consultando el paquete en IPS...';
            processScanQueue();
            return;
        }
        const url = new URL(form.action, location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        load(url).then(loaded => {
            const legacyBarcode = scanEnabled && /^[A-Z]{2}\d{9}[A-Z]{2}$/.test(code);
            if (!loaded || !legacyBarcode) return;
            const exactRow = [...listing.querySelectorAll('.ips-code')]
                .find(node => node.textContent.trim().toUpperCase() === code)?.closest('tr');
            const addForm = exactRow?.querySelector('.ips-selection-add');
            if (addForm) addSelectionForm(addForm);
        });
    });
    listing.addEventListener('click', event => {
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
