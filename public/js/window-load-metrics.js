(function () {
    'use strict';

    const script = document.currentScript;
    if (!script || !window.fetch || !window.performance) {
        return;
    }

    const endpoint = script.dataset.endpoint;
    const token = script.dataset.csrf;
    if (!endpoint || !token) {
        return;
    }

    const normalize = function (value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    };

    window.addEventListener('load', function () {
        window.setTimeout(function () {
            const navigation = performance.getEntriesByType('navigation')[0];
            if (!navigation || navigation.loadEventEnd <= 0) {
                return;
            }

            const heading = document.querySelector('.content-header h1, main h1, h1');
            const windowName = normalize(heading && heading.textContent) || normalize(document.title);
            const routeName = normalize(script.dataset.route)
                || ('path:' + window.location.pathname.replace(/[^A-Za-z0-9_./:-]/g, '').slice(0, 180));
            const appTiming = Array.from(navigation.serverTiming || []).find(function (entry) {
                return entry.name === 'app' && Number.isFinite(entry.duration);
            });
            const payload = {
                route_name: routeName.slice(0, 190),
                window_name: windowName.slice(0, 180) || routeName.slice(0, 180),
                load_time_ms: Math.min(180000, Math.max(0, Math.round(navigation.loadEventEnd - navigation.startTime))),
                server_time_ms: appTiming
                    ? Math.min(180000, Math.max(0, Math.round(appTiming.duration)))
                    : null,
            };

            window.fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            }).catch(function () {
                // La métrica es informativa y no debe afectar la navegación.
            });
        }, 0);
    }, { once: true });
}());
