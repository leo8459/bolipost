(() => {
    'use strict';

    // These are browser hints and UI restrictions, not a security boundary.
    // Extensions and DevTools controlled by the user can bypass them.
    const root = document.documentElement;
    root.setAttribute('translate', 'no');
    root.classList.add('notranslate');

    if (window.bolipostBrowserRestrictionsLoaded) return;
    window.bolipostBrowserRestrictionsLoaded = true;

    window.addEventListener('contextmenu', event => {
        event.preventDefault();
    }, { capture: true });

    window.addEventListener('keydown', event => {
        const key = event.key.toLowerCase();
        const windowsDevTools = event.ctrlKey && event.shiftKey && ['i', 'j', 'c'].includes(key);
        const macDevTools = event.metaKey && event.altKey && ['i', 'j', 'c'].includes(key);

        if (key === 'f12' || windowsDevTools || macDevTools) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, { capture: true });
})();
