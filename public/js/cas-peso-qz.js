(() => {
    if (window.__casPesoQzInit) {
        return;
    }
    window.__casPesoQzInit = true;

    const fullCfg = window.CAS_PESAJE_CONFIG ?? {};
    const cfg = fullCfg.serial ?? {};
    const endpoints = {
        certificate: fullCfg.endpoints?.certificate ?? '/qz/certificate',
        sign: fullCfg.endpoints?.sign ?? '/qz/sign',
    };

    const state = {
        booted: false,
        port: null,
        connecting: false,
        pollTimer: null,
        serialCallbacksBound: false,
        serialBuffer: '',
        serialBufferUpdatedAt: 0,
    };

    const serial = {
        baudRate: Number(cfg.baudRate ?? 9600),
        dataBits: Number(cfg.dataBits ?? 7),
        stopBits: Number(cfg.stopBits ?? 1),
        parity: cfg.parity ?? 'EVEN',
        flowControl: cfg.flowControl ?? 'NONE',
        encoding: cfg.encoding ?? 'UTF-8',
        rxStart: cfg.rxStart ?? '',
        rxEnd: cfg.rxEnd ?? '\r',
        rxUntilNewline: Boolean(cfg.rxUntilNewline ?? false),
        rxWidth: cfg.rxWidth ?? null,
        rxRaw: Boolean(cfg.rxRaw ?? false),
        startCommand: decodeEscaped(cfg.startCommand ?? 'W'),
        stopCommand: decodeEscaped(cfg.stopCommand ?? ''),
        portRegex: cfg.portRegex ?? '^COM\\d+$',
        pollCommands: normalizePollCommands(cfg.pollCommands ?? []),
        pollGapMs: Number(cfg.pollGapMs ?? 120),
        pollEveryMs: Number(cfg.pollEveryMs ?? 900),
    };

    waitForPesoFieldsAndBoot();

    function waitForPesoFieldsAndBoot() {
        if (hasPesoInputs()) {
            boot();
            return;
        }

        const observer = new MutationObserver(() => {
            if (!hasPesoInputs()) {
                return;
            }

            observer.disconnect();
            boot();
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
        });
    }

    function hasPesoInputs() {
        return Boolean(document.querySelector('[data-cas-peso-input]'));
    }

    function boot() {
        if (state.booted) {
            return;
        }

        state.booted = true;

        document.addEventListener('click', async (event) => {
            const reconnectBtn = event.target.closest('[data-cas-reconnect]');
            if (!reconnectBtn) {
                return;
            }

            reconnectBtn.disabled = true;
            try {
                await reconnect();
            } finally {
                reconnectBtn.disabled = false;
            }
        });

        connectAndRead().catch(() => {
            // El estado visual ya queda reflejado en los pills
        });
    }

    async function reconnect() {
        stopPolling();
        await closeCurrentPort();
        await connectAndRead();
    }

    async function connectAndRead() {
        if (!hasPesoInputs() || state.connecting) {
            return;
        }

        state.connecting = true;

        try {
            markQzPending('Esperando libreria QZ...');
            await waitForQz();
            ensureQzApi();
            configureSecuritySigned();
            bindWebSocketClose();
            bindSerialCallbacks();

            await ensureSocket();
            const port = await openPortAuto();
            state.port = port;

            await sendStartCommand(port);

            markQzOk('QZ conectado');
            markPortOk(`Leyendo ${port}`);
            startPolling();
        } catch (error) {
            markQzError(errorMessage(error, 'Error de conexion'));
            markPortError('Sin puerto');
            stopPolling();
            await closeCurrentPort();
            throw error;
        } finally {
            state.connecting = false;
        }
    }

    async function waitForQz() {
        const timeoutAt = Date.now() + 10000;

        while (Date.now() < timeoutAt) {
            if (window.qz) {
                return;
            }
            await sleep(200);
        }

        throw new Error('QZ Tray JS no esta disponible');
    }

    function ensureQzApi() {
        if (!window.qz) {
            throw new Error('QZ no esta cargado');
        }

        if (!qz.security || !qz.websocket || !qz.serial) {
            throw new Error('La API de QZ Tray esta incompleta');
        }
    }

    function configureSecuritySigned() {
        qz.security.setCertificatePromise((resolve, reject) => {
            fetch(endpoints.certificate, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { Accept: 'text/plain' },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`Certificado no disponible (${response.status})`);
                    }
                    return response.text();
                })
                .then((text) => {
                    const certificate = text.trim();

                    if (!certificate.startsWith('-----BEGIN CERTIFICATE-----')) {
                        throw new Error('Certificado invalido. Revisa el endpoint /qz/certificate');
                    }

                    return certificate;
                })
                .then(resolve)
                .catch((error) => reject(errorMessage(error, 'No se pudo obtener certificado')));
        });

        qz.security.setSignatureAlgorithm('SHA512');

        qz.security.setSignaturePromise((toSign) => {
            const signPayload = Array.isArray(toSign) ? toSign[0] : toSign;

            return (resolve, reject) => {
                fetch(endpoints.sign, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'text/plain',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ request: signPayload }),
                })
                    .then(async (response) => {
                        const text = await response.text();

                        if (!response.ok) {
                            throw new Error(text || `Firma no disponible (${response.status})`);
                        }

                        const signature = text.trim();

                        if (!isLikelyBase64(signature)) {
                            throw new Error('Firma invalida. Revisa sesion/autenticacion y endpoint /qz/sign');
                        }

                        return signature;
                    })
                    .then(resolve)
                    .catch((error) => reject(errorMessage(error, 'No se pudo firmar solicitud')));
            };
        });
    }

    function bindWebSocketClose() {
        if (typeof qz.websocket.setClosedCallbacks === 'function') {
            qz.websocket.setClosedCallbacks(() => {
                stopPolling();
                state.port = null;
                markQzPending('QZ desconectado');
                markPortError('Sin puerto');
            });
        }
    }

    function bindSerialCallbacks() {
        if (state.serialCallbacksBound) {
            return;
        }

        qz.serial.setSerialCallbacks((event) => {
            if (isSerialErrorEvent(event)) {
                markPortPending(`Balanza conectada (${state.port ?? 'COM'})`);
                return;
            }

            const output = toOutput(event);
            if (!output) {
                return;
            }

            const chunks = splitIncomingChunks(output);

            for (const chunk of chunks) {
                const frame = sanitize(chunk);
                if (!frame) {
                    continue;
                }

                setFrameText(frame);

                const parsed = parseCasWeight(frame);
                if (!parsed) {
                    continue;
                }

                setPesoValue(parsed.kg);

                if (parsed.stable) {
                    markPortOk(`Leyendo ${state.port} (estable)`);
                } else {
                    markPortPending(`Leyendo ${state.port} (inestable)`);
                }
            }
        });

        state.serialCallbacksBound = true;
    }

    async function ensureSocket() {
        if (qz.websocket.isActive()) {
            return;
        }

        await qz.websocket.connect({
            retries: 0,
            delay: 0,
        });
    }

    async function openPortAuto() {
        const ports = await qz.serial.findPorts();

        if (!Array.isArray(ports) || ports.length === 0) {
            throw new Error('No hay puertos seriales detectados');
        }

        const ordered = orderPorts(ports);

        const openOptions = {
            baudRate: serial.baudRate,
            dataBits: serial.dataBits,
            stopBits: serial.stopBits,
            parity: serial.parity,
            flowControl: serial.flowControl,
            encoding: serial.encoding,
        };

        const rxOptions = buildRxOptions();
        if (rxOptions !== null) {
            openOptions.rx = rxOptions;
        }

        for (const port of ordered) {
            try {
                await closeCurrentPort();
                await qz.serial.openPort(port, openOptions);
                localStorage.setItem('cas:last_port', port);
                return port;
            } catch (_error) {
                // prueba con el siguiente puerto
            }
        }

        throw new Error('No se pudo abrir un puerto para la CAS PR II');
    }

    function orderPorts(ports) {
        const remembered = localStorage.getItem('cas:last_port');
        const regex = safeRegex(serial.portRegex, /^COM\d+$/i);
        const all = [...new Set(ports)];
        const preferred = all.filter((port) => regex.test(port));
        const rest = all.filter((port) => !regex.test(port));
        const ordered = [...preferred, ...rest];

        if (remembered && ordered.includes(remembered)) {
            return [remembered, ...ordered.filter((port) => port !== remembered)];
        }

        return ordered;
    }

    function startPolling() {
        stopPolling();

        const commands = serial.pollCommands;
        if (commands.length === 0) {
            return;
        }

        state.pollTimer = setInterval(async () => {
            if (!state.port) {
                return;
            }

            try {
                await sendPollCommands(state.port, commands);
            } catch (_error) {
                stopPolling();
                markPortError('Error de lectura');
            }
        }, serial.pollEveryMs);
    }

    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }

        state.serialBuffer = '';
    }

    async function closeCurrentPort() {
        if (!state.port) {
            return;
        }

        const port = state.port;

        try {
            await sendStopCommand(port);
        } catch (_error) {
            // ignore
        }

        try {
            await qz.serial.closePort(port);
        } catch (_error) {
            // ignore
        } finally {
            state.port = null;
            state.serialBuffer = '';
            state.serialBufferUpdatedAt = 0;
        }
    }

    function parseCasWeight(frame) {
        if (!frame) {
            return null;
        }

        const normalized = frame.toLowerCase();
        if (
            normalized === 'k' ||
            normalized === 'g' ||
            normalized === 'kg' ||
            normalized === 'lb' ||
            normalized === '.'
        ) {
            return null;
        }

        const compactCas = frame.match(/^0(\d{4})$/);
        if (compactCas) {
            const kgCompact = Number.parseInt(compactCas[1], 10) / 100;
            if (!Number.isNaN(kgCompact)) {
                return { kg: kgCompact, stable: true };
            }
        }

        const stable = /\bST\b/i.test(frame) || !/\bUS\b/i.test(frame);
        const match = frame.match(/([-+]?\d{1,6}(?:[.,]\d{1,3})?)\s*(kg|g|lb)?/i);

        if (!match) {
            return null;
        }

        const raw = match[1].replace(',', '.');
        const value = Number.parseFloat(raw);

        if (Number.isNaN(value)) {
            return null;
        }

        const unit = (match[2] || 'kg').toLowerCase();
        let kg = value;

        if (unit === 'g') {
            kg = value / 1000;
        } else if (unit === 'lb') {
            kg = value * 0.45359237;
        }

        if (kg < 0) {
            kg = 0;
        }

        return { kg, stable };
    }

    function sanitize(text) {
        return String(text)
            .replace(/[\x00-\x1F\x7F]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .slice(0, 120);
    }

    function toOutput(event) {
        if (typeof event === 'string') {
            return event;
        }

        if (typeof event?.data === 'string') {
            return event.data;
        }

        if (typeof event?.output === 'string') {
            return event.output;
        }

        if (Array.isArray(event?.data)) {
            return decodeByteArray(event.data);
        }

        if (Array.isArray(event?.output)) {
            return decodeByteArray(event.output);
        }

        return '';
    }

    function splitIncomingChunks(text) {
        const source = String(text ?? '');
        if (source === '') {
            return [];
        }

        const now = Date.now();

        if (state.serialBuffer && now - state.serialBufferUpdatedAt > 300) {
            state.serialBuffer = '';
        }

        state.serialBufferUpdatedAt = now;
        state.serialBuffer += source;

        const chunks = [];
        let current = '';

        for (const ch of state.serialBuffer) {
            if (ch === '\r' || ch === '\n' || ch.charCodeAt(0) === 0x04) {
                if (current !== '') {
                    chunks.push(current);
                    current = '';
                }
                continue;
            }

            current += ch;
        }

        state.serialBuffer = current;

        if (state.serialBuffer.length > 80) {
            chunks.push(state.serialBuffer);
            state.serialBuffer = '';
        }

        return chunks;
    }

    function decodeByteArray(values) {
        if (!Array.isArray(values)) {
            return '';
        }

        const allNumeric = values.every((v) => Number.isFinite(Number(v)));
        if (!allNumeric) {
            return values.join(' ');
        }

        return values
            .map((v) => String.fromCharCode(Number(v) & 0xff))
            .join('');
    }

    function normalizePollCommands(rawCommands) {
        if (Array.isArray(rawCommands)) {
            return rawCommands
                .map((cmd) => decodeEscaped(cmd))
                .filter((cmd) => cmd !== '');
        }

        const single = decodeEscaped(rawCommands);
        if (!single) {
            return [];
        }

        return [single];
    }

    function buildRxOptions() {
        if (serial.rxRaw) {
            return {};
        }

        const start = decodeEscaped(serial.rxStart ?? '');
        const end = decodeEscaped(serial.rxEnd ?? '');
        const untilNewline = Boolean(serial.rxUntilNewline);
        const widthNumber = Number.parseInt(String(serial.rxWidth), 10);
        const hasWidth = Number.isFinite(widthNumber) && widthNumber > 0;

        const rx = {};

        if (start) {
            rx.start = start;
        }

        if (end) {
            rx.end = end;
        }

        if (hasWidth) {
            rx.width = widthNumber;
        }

        if (untilNewline) {
            rx.untilNewline = true;
        }

        return Object.keys(rx).length > 0 ? rx : null;
    }

    async function sendPollCommands(port, commands) {
        for (let i = 0; i < commands.length; i += 1) {
            await qz.serial.sendData(port, commands[i]);

            if (i < commands.length - 1) {
                await sleep(serial.pollGapMs);
            }
        }
    }

    async function sendStartCommand(port) {
        if (!serial.startCommand) {
            return;
        }

        await qz.serial.sendData(port, serial.startCommand);
    }

    async function sendStopCommand(port) {
        if (!serial.stopCommand) {
            return;
        }

        await qz.serial.sendData(port, serial.stopCommand);
    }

    function decodeEscaped(text) {
        return String(text)
            .replace(/\\x([0-9A-Fa-f]{2})/g, (_, hex) => String.fromCharCode(Number.parseInt(hex, 16)))
            .replace(/\\r/g, '\r')
            .replace(/\\n/g, '\n')
            .replace(/\\t/g, '\t');
    }

    function safeRegex(pattern, fallback) {
        try {
            return new RegExp(pattern, 'i');
        } catch (_error) {
            return fallback;
        }
    }

    function isSerialErrorEvent(event) {
        if (!event || typeof event !== 'object') {
            return false;
        }

        if (String(event.type ?? '').toUpperCase() === 'ERROR') {
            return true;
        }

        if (event.exception instanceof Error) {
            return true;
        }

        if (typeof event.exception === 'string' && event.exception.trim() !== '') {
            return true;
        }

        return false;
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    }

    function isLikelyBase64(value) {
        if (!value || value.length % 4 !== 0) {
            return false;
        }

        return /^[A-Za-z0-9+/=]+$/.test(value);
    }

    function errorMessage(error, fallback) {
        if (typeof error === 'string' && error.trim() !== '') {
            return error;
        }

        if (typeof error?.message === 'string' && error.message.trim() !== '') {
            return error.message;
        }

        return fallback;
    }

    function setText(selector, value) {
        document.querySelectorAll(selector).forEach((el) => {
            el.textContent = value;
        });
    }

    function setPill(selector, klass, value) {
        document.querySelectorAll(selector).forEach((el) => {
            el.className = `status-pill ${klass}`;
            el.textContent = value;
        });
    }

    function setFrameText(value) {
        setText('[data-cas-frame-text]', value);
    }

    function setPesoValue(kg) {
        const value = Number(kg).toFixed(3);

        document.querySelectorAll('[data-cas-peso-input]').forEach((input) => {
            if (!input || input.value === value) {
                return;
            }

            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    function markQzOk(message) {
        setText('[data-cas-qz-text]', message);
        setPill('[data-cas-qz-pill]', 'status-ok', 'CONECTADO');
    }

    function markQzPending(message) {
        setText('[data-cas-qz-text]', message);
        setPill('[data-cas-qz-pill]', 'status-warn', 'PENDIENTE');
    }

    function markQzError(message) {
        setText('[data-cas-qz-text]', message);
        setPill('[data-cas-qz-pill]', 'status-bad', 'ERROR');
    }

    function markPortOk(message) {
        setText('[data-cas-port-text]', message);
        setPill('[data-cas-port-pill]', 'status-ok', 'LEYENDO');
    }

    function markPortPending(message) {
        setText('[data-cas-port-text]', message);
        setPill('[data-cas-port-pill]', 'status-warn', 'PENDIENTE');
    }

    function markPortError(message) {
        setText('[data-cas-port-text]', message);
        setPill('[data-cas-port-pill]', 'status-bad', 'DESCONECTADO');
    }

    function sleep(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }
})();
