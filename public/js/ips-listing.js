(() => {
    'use strict';
    const listing = document.getElementById('ips-listing');
    const status = document.getElementById('ips-listing-status');
    if (!listing || !status) return;
    let activeRequest;

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

    listing.addEventListener('submit', async event => {
        const addForm = event.target.closest('.ips-selection-add');
        if (addForm) {
            event.preventDefault();
            const button = addForm.querySelector('button');
            button.disabled = true;
            button.textContent = 'Guardando…';
            try {
                const response = await fetch(addForm.action, {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''},
                    body: new FormData(addForm),
                    credentials: 'same-origin',
                });
                const result = await response.json();
                if (!response.ok || !result.code) throw new Error(result.message || 'No se pudo guardar el paquete.');
                const row = addForm.closest('tr');
                row.querySelector('td:last-child').innerHTML = '<span class="text-success"><i class="fas fa-check"></i> En bandeja</span>';
                const trayCount = document.querySelector('.ips-tray .ips-panel-header > span');
                if (trayCount) trayCount.textContent = result.count + '/50';
                window.dispatchEvent(new CustomEvent('ips:selection-added', {detail: result}));
                status.hidden = false;
                status.textContent = 'Paquete guardado en la bandeja. El listado no se recargó.';
                setTimeout(() => { status.hidden = true; }, 3000);
            } catch (error) {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-plus"></i> Agregar';
                status.hidden = false;
                status.textContent = error.message;
            }
            return;
        }
        const form = event.target.closest('.ips-search');
        if (!form) return;
        event.preventDefault();
        const url = new URL(form.action, location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        const loaded = await load(url);
        // A complete UPU identifier (two letters + nine digits + two letters) is safe to add on Enter.
        const code = String(new FormData(form).get('q') || '').trim().toUpperCase();
        if (loaded && /^[A-Z]{2}\d{9}[A-Z]{2}$/.test(code)) {
            const addForm = listing.querySelector('.ips-selection-add');
            if (addForm) addForm.requestSubmit();
        }
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
        cell.innerHTML = '<form class="ips-selection-add" method="POST" action="/ips/seleccionados"><input type="hidden" name="_token" value="'+token+'"><input type="hidden" name="codigo" value="'+code+'"><button class="btn btn-sm btn-outline-primary"><i class="fas fa-plus"></i> Agregar</button></form>';
    });
    window.addEventListener('popstate', () => load(new URL(location.href), false));
})();
