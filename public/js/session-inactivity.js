(function () {
    'use strict';

    const script = document.currentScript;
    if (!script || window.bolipostInactivityStarted) return;
    window.bolipostInactivityStarted = true;

    const limit = 2 * 60 * 60 * 1000;
    const key = 'bolipost:last-activity:' + script.dataset.sessionKey;
    const logoutKey = key + ':logout';
    let lastActivity = Date.now();
    let lastWrite = 0;
    let timer;
    let leaving = false;

    // Storage may be unavailable in privacy modes; the current tab still expires.
    function read(name) {
        try { return window.localStorage.getItem(name); } catch (_) { return null; }
    }
    function write(name, value) {
        try { window.localStorage.setItem(name, String(value)); } catch (_) { /* local timer */ }
    }
    function logout() {
        if (leaving) return;
        leaving = true;
        window.clearTimeout(timer);
        write(logoutKey, '1');
        window.location.replace(script.dataset.logoutUrl);
    }
    function check() {
        if (leaving) return false;
        const shared = Number(read(key));
        if (shared > 0 && shared <= Date.now()) lastActivity = Math.max(lastActivity, shared);
        const remaining = limit - (Date.now() - lastActivity);
        if (read(logoutKey) || remaining <= 0) {
            logout();
            return false;
        }
        window.clearTimeout(timer);
        timer = window.setTimeout(check, remaining);
        return true;
    }
    function activity(event) {
        // An input after sleep must not revive an already expired session.
        if (!event.isTrusted || !check()) return;
        lastActivity = Date.now();
        if (lastActivity - lastWrite >= 1000) {
            write(key, lastActivity);
            lastWrite = lastActivity;
        }
        check();
    }

    const saved = Number(read(key));
    if (saved > 0 && saved <= Date.now()) lastActivity = saved;
    else write(key, lastActivity);

    ['pointermove', 'pointerdown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(name => {
        window.addEventListener(name, activity, { passive: true, capture: true });
    });
    window.addEventListener('storage', event => {
        if (event.key === key || event.key === logoutKey) check();
    });
    window.addEventListener('pageshow', check);
    window.addEventListener('focus', check);
    document.addEventListener('visibilitychange', check);
    // Preserve the final throttled interaction when navigating between pages.
    window.addEventListener('pagehide', () => {
        if (check()) write(key, lastActivity);
    });
    check();
})();
