(function (window) {
    'use strict';

    const formatters = new Map();

    function formatter(decimals) {
        const precision = Math.max(0, Number.parseInt(decimals, 10) || 0);

        if (!formatters.has(precision)) {
            formatters.set(precision, new Intl.NumberFormat('es-BO', {
                minimumFractionDigits: precision,
                maximumFractionDigits: precision,
            }));
        }

        return formatters.get(precision);
    }

    window.BolivianNumber = Object.freeze({
        format(value, decimals) {
            const numeric = Number(value);

            return Number.isFinite(numeric) ? formatter(decimals).format(numeric) : '';
        },
    });
}(window));
