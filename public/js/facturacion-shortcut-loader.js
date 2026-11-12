(function () {
    'use strict';

    const root = document.getElementById('facturacionShortcutLauncher');
    if (!root) return;

    const button = root.querySelector('#openFacturacionShortcut');
    const status = root.querySelector('#facturacionShortcutLoadStatus');
    let loading = null;
    let loaded = false;

    const load = function () {
        if (loaded) return Promise.resolve();
        if (loading) return loading;

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        status.textContent = 'Cargando facturacion...';
        status.hidden = false;

        loading = (async function () {
            const response = await fetch(root.dataset.endpoint, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                credentials: 'same-origin',
            });
            if (!response.ok || response.redirected) throw new Error('No se pudo cargar facturacion.');

            const source = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (!source.getElementById('facturacionShortcutModal')) throw new Error('Respuesta de facturacion no disponible.');

            // The authenticated, same-origin fragment includes its existing initializer.
            const scripts = Array.from(source.querySelectorAll('script'));
            scripts.forEach(function (script) { script.remove(); });
            const fragment = document.createDocumentFragment();
            Array.from(source.head.childNodes).concat(Array.from(source.body.childNodes)).forEach(function (node) {
                fragment.appendChild(document.importNode(node, true));
            });
            root.replaceChildren(fragment);
            scripts.forEach(function (sourceScript) {
                const script = document.createElement('script');
                script.textContent = sourceScript.textContent;
                root.appendChild(script);
            });
            loaded = true;
        })().catch(function (error) {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            status.textContent = 'No se pudo cargar facturacion. Pulsa el boton para reintentar.';
            status.hidden = false;
            throw error;
        }).finally(function () { loading = null; });

        return loading;
    };

    button.addEventListener('click', function () {
        load().then(function () {
            root.querySelector('#openFacturacionShortcut').click();
        }).catch(function () {});
    });

    // Items added from Livewire will be fetched when the user opens billing.
    window.syncFacturacionShortcutSections = async function () {};

    // Continue tracking an emission that was already in progress when navigating.
    try {
        if (window.sessionStorage.getItem('facturacion-pending-emit')) {
            load().catch(function () {});
        }
    } catch (error) {
        // Billing remains available when browser storage is disabled.
    }
}());
