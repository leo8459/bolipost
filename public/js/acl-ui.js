(function () {
    'use strict';

    const endpoint = '/acl/livewire-actions';
    const componentPermissions = new Map();
    const fetchingAliases = new Set();

    let scanTimer = null;
    let isRunning = false;
    let rerunRequested = false;

    const scheduleScan = function () {
        if (scanTimer) {
            clearTimeout(scanTimer);
        }

        scanTimer = setTimeout(function () {
            void runScan();
        }, 120);
    };

    const runScan = async function () {
        if (isRunning) {
            rerunRequested = true;
            return;
        }

        isRunning = true;

        do {
            rerunRequested = false;

            const usage = collectActionUsage();
            const aliases = Array.from(usage.keys());
            const unknownAliases = aliases.filter(function (alias) {
                return !componentPermissions.has(alias);
            });

            if (unknownAliases.length > 0) {
                await fetchPermissions(unknownAliases);
            }

            applyPermissions(usage);
        } while (rerunRequested);

        isRunning = false;
    };

    const collectActionUsage = function () {
        const usageByAlias = new Map();
        const roots = document.querySelectorAll('[wire\\:id][wire\\:snapshot]');

        roots.forEach(function (root) {
            const alias = extractComponentAlias(root);
            if (!alias) {
                return;
            }

            const candidates = [root].concat(Array.from(root.querySelectorAll('*')));

            candidates.forEach(function (element) {
                if (element.hasAttribute('data-acl-ignore')) {
                    return;
                }

                Array.from(element.attributes || []).forEach(function (attribute) {
                    const attrName = attribute.name.toLowerCase();
                    if (!attrName.startsWith('wire:click') && !attrName.startsWith('wire:submit')) {
                        return;
                    }

                    const methodName = extractMethodName(attribute.value);
                    if (!methodName) {
                        return;
                    }

                    if (!usageByAlias.has(alias)) {
                        usageByAlias.set(alias, []);
                    }

                    usageByAlias.get(alias).push({
                        element: element,
                        method: methodName,
                    });
                });
            });
        });

        return usageByAlias;
    };

    const extractComponentAlias = function (root) {
        const snapshot = root.getAttribute('wire:snapshot');
        if (!snapshot) {
            return null;
        }

        try {
            const parsed = JSON.parse(snapshot);
            const alias = parsed && parsed.memo && typeof parsed.memo.name === 'string'
                ? parsed.memo.name.trim()
                : '';

            return alias !== '' ? alias : null;
        } catch (error) {
            return null;
        }
    };

    const extractMethodName = function (expression) {
        if (typeof expression !== 'string') {
            return null;
        }

        const firstChunk = expression.split(';')[0].trim();

        if (firstChunk === '' || firstChunk.startsWith('$')) {
            return null;
        }

        const directMatch = firstChunk.match(/^([A-Za-z_][A-Za-z0-9_]*)$/);
        if (directMatch) {
            return directMatch[1];
        }

        const callMatch = firstChunk.match(/^([A-Za-z_][A-Za-z0-9_]*)\s*\(/);
        if (callMatch) {
            return callMatch[1];
        }

        return null;
    };

    const fetchPermissions = async function (aliases) {
        const aliasesToFetch = aliases.filter(function (alias) {
            return !fetchingAliases.has(alias);
        });

        if (aliasesToFetch.length === 0) {
            return;
        }

        aliasesToFetch.forEach(function (alias) {
            fetchingAliases.add(alias);
        });

        const query = encodeURIComponent(aliasesToFetch.join(','));
        const requestUrl = endpoint + '?components=' + query;

        try {
            const response = await fetch(requestUrl, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const enabled = payload && payload.enabled === true;
            const components = payload && typeof payload.components === 'object'
                ? payload.components
                : {};

            aliasesToFetch.forEach(function (alias) {
                if (!enabled) {
                    componentPermissions.set(alias, {});
                    return;
                }

                const permissionMap = components && typeof components[alias] === 'object'
                    ? components[alias]
                    : {};

                componentPermissions.set(alias, permissionMap);
            });
        } catch (error) {
            // Ignore network/parsing errors to avoid blocking the UI.
        } finally {
            aliasesToFetch.forEach(function (alias) {
                fetchingAliases.delete(alias);
            });
        }
    };

    const applyPermissions = function (usageByAlias) {
        usageByAlias.forEach(function (entries, alias) {
            const permissionMap = componentPermissions.get(alias);
            if (!permissionMap || typeof permissionMap !== 'object') {
                return;
            }

            entries.forEach(function (entry) {
                if (!Object.prototype.hasOwnProperty.call(permissionMap, entry.method)) {
                    return;
                }

                if (permissionMap[entry.method] !== false) {
                    return;
                }

                hideElement(entry.element);
            });
        });
    };

    const hideElement = function (element) {
        if (!element || element.getAttribute('data-acl-hidden') === '1') {
            return;
        }

        element.setAttribute('data-acl-hidden', '1');
        element.style.setProperty('display', 'none', 'important');

        if ('disabled' in element) {
            element.disabled = true;
        }
    };

    document.addEventListener('DOMContentLoaded', scheduleScan);
    document.addEventListener('livewire:init', scheduleScan);
    document.addEventListener('livewire:initialized', scheduleScan);
    document.addEventListener('livewire:navigated', scheduleScan);

    const observer = new MutationObserver(function () {
        scheduleScan();
    });

    const startObserver = function () {
        if (!document.body) {
            return;
        }

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startObserver);
    } else {
        startObserver();
    }
})();

