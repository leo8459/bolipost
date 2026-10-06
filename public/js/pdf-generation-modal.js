(function () {
    const modal = document.getElementById('globalPdfGenerationModal');
    if (!modal) return;

    const title = document.getElementById('globalPdfGenerationTitle');
    const message = document.getElementById('globalPdfGenerationMessage');
    const closeButton = document.getElementById('globalPdfGenerationClose');
    let hideTimer = null;
    let concealTimer = null;

    const normalize = (value) => String(value || '').replace(/\s+/g, ' ').trim();

    const actionUrlFor = (element, form = null) => {
        if (!element) return form?.action || '';
        return element.dataset?.pdfUrl
            || element.dataset?.exportUrl
            || element.formAction
            || element.getAttribute('formaction')
            || element.href
            || form?.action
            || '';
    };

    const controlTextFor = (element, form = null) => normalize([
        element?.dataset?.pdfLabel,
        element?.getAttribute?.('aria-label'),
        element?.getAttribute?.('title'),
        element?.innerText,
        element?.value,
        element?.getAttribute?.('wire:click'),
        form?.dataset?.pdfLabel,
        form?.id,
    ].filter(Boolean).join(' '));

    const looksLikePdfAction = (url, hint = '') => {
        const normalizedUrl = String(url || '').toLowerCase();
        const normalizedHint = String(hint || '').toLowerCase();
        return normalizedUrl.includes('pdf')
            || /factura-con-firma/.test(normalizedUrl)
            || /data-pdf-generation|\.pdf\b|\bpdf\b/.test(normalizedHint)
            || (/\/ticket(?:\/|$|[?#])/.test(normalizedUrl) && /ticket|pdf/i.test(normalizedHint))
            || /reimprimir\s+r[oó]tulo/.test(normalizedHint)
            || /(?:generar|generando|descargar|imprimir|exportar).{0,35}cn\s*-?\s*33/.test(normalizedHint);
    };

    const documentNameFor = (url, hint = '') => {
        const source = `${String(url || '')} ${String(hint || '')}`.toLowerCase();
        if (/reimprimir-cn33|cn\s*-?\s*33|cn33/.test(source)) return 'CN-33';
        if (/generacion-cn/.test(source)) return 'documento de generación CN';
        if (/marbete/.test(source)) return 'marbetes';
        if (/flujo-paqueteria/.test(source)) return 'reporte de flujo de paquetería';
        if (/rendimiento-servicios/.test(source)) return 'reporte de rendimiento comercial';
        if (/flujo-cajero/.test(source)) return 'reporte de flujo de caja';
        if (/hacer-envio-desde-casa|preregistro/.test(source)) return 'ticket de preenvío';
        if (/paquetes-ems.*solicitudes.*ticket|clientes.*solicitudes.*ticket/.test(source)) return 'ticket de solicitud EMS';
        if (/mis-ventas.*ticket/.test(source)) return 'ticket de venta';
        if (/fuel-log/.test(source)) return 'bitácora de combustible';
        if (/vehicle-log|vehicle-assignments|vehiculo/.test(source)) return 'reporte vehicular';
        if (/bitacora/.test(source)) return 'reporte de bitácoras';
        if (/entrega/.test(source)) return 'reporte de entregas';
        if (/dashboard/.test(source)) return 'reporte del dashboard';
        if (/factura|conciliacion/.test(source)) return 'factura';
        if (/paquetes-ems|boleta-ems|guia-ems|vge/.test(source)) return 'guía o boleta EMS';
        if (/paquetes-contrato|guia-verificacion|vgc/.test(source)) return 'guía de contrato';
        if (/paquetes-certificados/.test(source)) return 'reporte de certificados';
        if (/alertas-empresa/.test(source)) return 'documento adjunto de la alerta';
        if (/empresa/.test(source)) return 'reporte de empresas';
        if (/performance|rendimiento/.test(source)) return 'reporte de rendimiento';
        if (/financiera|flujo-cajero/.test(source)) return 'reporte financiero';
        if (/despacho|expedicion/.test(source)) return 'documento del despacho';
        if (/tarifario|tarifa/.test(source)) return 'tarifario';
        if (/maintenance-incentive/.test(source)) return 'reporte de incentivos de mantenimiento';
        if (/maintenance-document/.test(source)) return 'reporte de documentos de mantenimiento';
        if (/maintenance-appointment/.test(source)) return 'reporte de mantenimientos aprobados';
        if (/workshop/.test(source)) return 'reporte de talleres';
        if (/malencaminado/.test(source)) return 'reporte de envíos mal encaminados';
        if (/mis-ventas|ventas-sucursal/.test(source)) return 'reporte de ventas';
        if (/usuarios|users/.test(source)) return 'reporte de usuarios';

        const readableHint = normalize(hint)
            .replace(/\b(generar|generando|descargar|imprimir|exportar|reporte|pdf)\b/gi, '')
            .replace(/[\s|:]+/g, ' ')
            .trim();

        return readableHint ? `documento ${readableHint}` : 'reporte PDF';
    };

    function hideModal() {
        window.clearTimeout(hideTimer);
        window.clearTimeout(concealTimer);
        modal.classList.remove('is-visible');
        concealTimer = window.setTimeout(() => modal.classList.add('is-hidden'), 220);
    }

    const showModal = (url, hint = '') => {
        const sourceHint = normalize(hint);
        const isOpening = /\b(ver|abrir|visualizar)\s+(el\s+)?pdf\b/i.test(sourceHint);
        const documentName = documentNameFor(url, sourceHint);
        title.textContent = `${isOpening ? 'Abriendo' : 'Generando'} ${documentName}`;
        message.textContent = isOpening
            ? `Preparando ${documentName} para mostrarlo.`
            : `Estamos preparando el PDF de ${documentName}. La descarga o vista se abrirá en cuanto termine.`;

        window.clearTimeout(hideTimer);
        window.clearTimeout(concealTimer);
        modal.classList.remove('is-hidden');
        window.requestAnimationFrame(() => modal.classList.add('is-visible'));
        hideTimer = window.setTimeout(hideModal, 12000);
    };

    closeButton?.addEventListener('click', hideModal);
    window.showPdfGenerationModal = showModal;

    document.addEventListener('click', function (event) {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const control = event.target.closest?.('a[href], button, input[type="submit"], input[type="button"], [data-pdf-url], [data-export-url], [data-pdf-generation]');
        if (!control || control.id === 'dashboardPdfDownloadBtn' || control.hasAttribute('data-pdf-modal-ignore')) return;

        const hint = controlTextFor(control, control.form || null);
        const url = actionUrlFor(control, control.form || null);
        if (looksLikePdfAction(url, hint)) showModal(url, hint);
    });

    document.addEventListener('submit', function (event) {
        const form = event.target;
        const submitter = event.submitter || null;
        const url = actionUrlFor(submitter, form);
        const hint = controlTextFor(submitter, form);
        if (looksLikePdfAction(url, hint)) showModal(url, hint);
    });

    const nativeWindowOpen = window.open;
    window.open = function (url, ...args) {
        const hint = normalize(args.join(' '));
        if (looksLikePdfAction(url, hint)) showModal(url, hint);
        return nativeWindowOpen.call(window, url, ...args);
    };

    const nativeFetch = window.fetch;
    window.fetch = function (...args) {
        const input = args[0];
        const requestUrl = typeof input === 'string' || input instanceof URL ? String(input) : (input?.url || '');
        const requestHeaders = args[1]?.headers || (typeof Request !== 'undefined' && input instanceof Request ? input.headers : null);
        const acceptHeader = requestHeaders?.get?.('Accept') || requestHeaders?.Accept || requestHeaders?.accept || '';
        const isDashboardOwnExport = requestUrl.includes('/dashboard/export/pdf');
        const expectsPdf = looksLikePdfAction(requestUrl, acceptHeader);

        if (expectsPdf && !isDashboardOwnExport && !modal.classList.contains('is-visible')) {
            showModal(requestUrl, acceptHeader);
        }

        return nativeFetch.apply(this, args).then((response) => {
            const contentType = response.headers.get('Content-Type') || '';
            if (contentType.toLowerCase().includes('application/pdf') && !isDashboardOwnExport) {
                const resolvedUrl = requestUrl || response.url;
                const documentName = documentNameFor(resolvedUrl, acceptHeader);
                if (!modal.classList.contains('is-visible')) showModal(resolvedUrl, acceptHeader || 'PDF');
                title.textContent = `PDF listo: ${documentName}`;
                message.textContent = 'La descarga o visualización comenzará ahora.';
                window.clearTimeout(hideTimer);
                hideTimer = window.setTimeout(hideModal, 1200);
            } else if (expectsPdf && !response.ok && !isDashboardOwnExport) {
                title.textContent = 'No se pudo generar el PDF';
                message.textContent = 'Ocurrió un problema al preparar el documento. Intenta de nuevo.';
                window.clearTimeout(hideTimer);
                hideTimer = window.setTimeout(hideModal, 4000);
            }
            return response;
        });
    };

    const nativePrint = window.print;
    window.print = function (...args) {
        const pageName = normalize(document.querySelector('h1')?.innerText || document.title || 'documento');
        showModal(window.location.href, `imprimir PDF ${pageName}`);
        return nativePrint.apply(window, args);
    };

    window.addEventListener('afterprint', hideModal);
})();
