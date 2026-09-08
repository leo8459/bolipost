(function (window, document) {
    'use strict';

    if (window.__fixedWeightInputInitialized) {
        return;
    }
    window.__fixedWeightInputInitialized = true;

    const programmaticUpdates = new WeakSet();

    function parseWeight(value) {
        const raw = String(value ?? '').trim().replace(/\s+/g, '');
        if (raw === '') {
            return null;
        }

        const normalized = raw.includes(',')
            ? raw.replace(/\./g, '').replace(',', '.')
            : raw;
        const numeric = Number(normalized);

        return Number.isFinite(numeric) ? numeric : null;
    }

    function validate(input, numeric) {
        const minimum = Number(input.dataset.weightMin || 0.001);
        const maximum = Number(input.dataset.weightMax || 150);

        if (numeric === null) {
            input.setCustomValidity('Ingrese el peso del paquete.');
            return;
        }

        input.setCustomValidity(
            numeric < minimum || numeric > maximum
                ? 'El peso debe estar entre 0,001 y 150,000 kg.'
                : ''
        );
    }

    function syncInput(input) {
        programmaticUpdates.add(input);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        programmaticUpdates.delete(input);
    }

    function formatWeight(input) {
        const numeric = parseWeight(input.value);
        validate(input, numeric);

        if (numeric !== null) {
            input.value = numeric.toFixed(3).replace('.', ',');
            syncInput(input);
        }
    }

    document.addEventListener('focusin', function (event) {
        if (event.target.matches('[data-fixed-weight-input]')) {
            event.target.select();
        }
    });

    document.addEventListener('input', function (event) {
        const input = event.target;
        if (!input.matches('[data-fixed-weight-input]') || programmaticUpdates.has(input)) {
            return;
        }

        const numeric = parseWeight(input.value);
        const maximum = Number(input.dataset.weightMax || 150);

        if (numeric !== null && numeric > maximum) {
            input.value = '';
            input.setCustomValidity('El peso maximo permitido es 150,000 kg. Vuelva a ingresar el peso.');
            syncInput(input);
            input.reportValidity();
            return;
        }

        validate(input, numeric);
    });

    document.addEventListener('focusout', function (event) {
        const input = event.target;
        if (!input.matches('[data-fixed-weight-input]')) {
            return;
        }

        formatWeight(input);
    });
}(window, document));
