<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monitor QR</title>
    <style>
        :root {
            --bg: #edf3fc;
            --panel: rgba(255, 255, 255, 0.92);
            --line: rgba(24, 58, 116, 0.1);
            --text: #17315c;
            --muted: #6782aa;
            --accent: #ffcb32;
            --accent-strong: #f4b700;
            --success: #22a25f;
            --warning: #e68426;
            --shadow: 0 24px 60px rgba(23, 49, 92, 0.12);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(255, 203, 50, 0.16), transparent 18%),
                radial-gradient(circle at bottom right, rgba(66, 133, 244, 0.10), transparent 22%),
                linear-gradient(180deg, #f8fbff, var(--bg));
        }
        .screen {
            min-height: 100vh;
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: 18px;
            padding: 18px;
        }
        .topbar, .footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 24px;
            border-radius: 24px;
            background: var(--panel);
            border: 1px solid rgba(255, 255, 255, 0.75);
            box-shadow: var(--shadow);
            backdrop-filter: blur(16px);
        }
        .brand h1 {
            margin: 0;
            font-size: 32px;
            line-height: 1;
            font-weight: 900;
        }
        .brand p, .footer, .status {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
        }
        .status {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }
        .status__dot {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            background: var(--success);
            box-shadow: 0 0 0 8px rgba(34, 162, 95, 0.12);
        }
        .content {
            min-height: 0;
        }
        .state {
            height: 100%;
            border-radius: 32px;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,251,255,.95));
            border: 1px solid rgba(255, 255, 255, 0.75);
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
        }
        .ads, .qr, .terminal {
            min-height: 100%;
            padding: 36px;
        }
        .ads {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ads__panel {
            width: 100%;
            min-height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ads__badge, .eyebrow {
            display: inline-flex;
            width: fit-content;
            align-items: center;
            gap: 10px;
            padding: 11px 16px;
            border-radius: 999px;
            background: #f5f8ff;
            border: 1px solid rgba(205, 220, 245, 0.95);
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
        }
        .ads__badge::before, .eyebrow::before {
            content: "";
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: var(--accent);
        }
        .qr__title, .terminal__title {
            margin: 0;
            font-size: 64px;
            line-height: .96;
            letter-spacing: -.05em;
            font-weight: 900;
        }
        .qr__text, .terminal__text {
            margin: 0;
            max-width: 560px;
            font-size: 21px;
            line-height: 1.5;
            color: var(--muted);
        }
        .ads__visual {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ads__qr-shell {
            width: min(62vmin, 560px);
            aspect-ratio: 1 / 1;
            position: relative;
            padding: 28px;
            border-radius: 40px;
            background:
                radial-gradient(circle at top, rgba(255,203,50,.18), transparent 36%),
                linear-gradient(180deg, #ffffff, #f5f9ff);
            border: 1px solid rgba(214, 226, 244, 0.9);
            box-shadow: 0 24px 50px rgba(23, 49, 92, 0.10);
        }
        .ads__qr-shell::before,
        .ads__qr-shell::after,
        .ads__qr-frame::before,
        .ads__qr-frame::after {
            content: "";
            position: absolute;
            width: 60px;
            height: 60px;
            border: 5px solid #ffcf3f;
            filter: drop-shadow(0 0 12px rgba(255, 203, 50, 0.45));
        }
        .ads__qr-shell::before {
            top: 16px;
            left: 16px;
            border-right: 0;
            border-bottom: 0;
            border-radius: 26px 0 0 0;
        }
        .ads__qr-shell::after {
            top: 16px;
            right: 16px;
            border-left: 0;
            border-bottom: 0;
            border-radius: 0 26px 0 0;
        }
        .ads__qr-frame {
            position: relative;
            width: 100%;
            height: 100%;
            border-radius: 32px;
            border: 2px dashed rgba(118, 154, 255, 0.6);
            background:
                linear-gradient(180deg, rgba(255,255,255,.95), rgba(243,248,255,.95));
            display: grid;
            place-items: center;
            text-align: center;
            padding: 28px;
        }
        .ads__qr-frame::before {
            bottom: -14px;
            left: -14px;
            border-right: 0;
            border-top: 0;
            border-radius: 0 0 0 26px;
        }
        .ads__qr-frame::after {
            bottom: -14px;
            right: -14px;
            border-left: 0;
            border-top: 0;
            border-radius: 0 0 26px 0;
        }
        .ads__qr-icon {
            width: 140px;
            height: 140px;
            display: grid;
            place-items: center;
            color: #c0cce3;
        }
        .ads__qr-icon svg {
            width: 104px;
            height: 104px;
        }
        .ads__qr-caption {
            margin: 10px 0 0;
            font-size: 18px;
            font-weight: 700;
            color: #2450b4;
        }
        .qr {
            display: grid;
            grid-template-columns: minmax(360px, 520px) minmax(320px, 560px);
            gap: 42px;
            align-items: center;
            justify-content: center;
        }
        .qr__visual {
            display: grid;
            place-items: center;
            min-height: 520px;
            padding: 32px;
            border-radius: 40px;
            background:
                radial-gradient(circle at top, rgba(255,203,50,.22), transparent 28%),
                linear-gradient(180deg, #ffffff, #f3f8ff);
            border: 1px solid var(--line);
            box-shadow: 0 24px 48px rgba(23,49,92,.09);
        }
        .qr__visual img {
            display: block;
            width: 100%;
            max-width: 420px;
            height: auto;
            padding: 18px;
            border-radius: 30px;
            background: #fff;
            box-shadow: 0 24px 44px rgba(23,49,92,.14);
        }
        .qr__empty {
            color: var(--muted);
            font-size: 18px;
            text-align: center;
        }
        .qr__meta {
            display: grid;
            gap: 18px;
            align-content: center;
            padding: 28px 8px;
        }
        .qr__grid, .terminal__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(180px, 1fr));
            gap: 16px;
        }
        .qr__box, .terminal__box {
            border-radius: 24px;
            border: 1px solid rgba(214,226,244,.9);
            background: rgba(247,250,255,.95);
            padding: 18px 20px;
        }
        .qr__box small, .terminal__box small {
            display: block;
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .qr__box strong, .terminal__box strong {
            font-size: 30px;
            line-height: 1.2;
        }
        .qr__box--wide {
            grid-column: 1 / -1;
        }
        .terminal {
            display: grid;
            place-items: center;
            text-align: center;
            align-content: center;
            gap: 22px;
            padding-block: 56px;
        }
        .terminal__icon {
            width: 148px;
            height: 148px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            font-size: 62px;
            font-weight: 900;
            color: #fff;
            box-shadow: 0 24px 54px rgba(23,49,92,.18);
        }
        .terminal__icon--success { background: linear-gradient(180deg, #31c96f, #1f9d55); }
        .terminal__icon--warning { background: linear-gradient(180deg, #ffb04e, #e67e22); }
        .terminal__hero {
            width: min(100%, 780px);
            display: grid;
            justify-items: center;
            gap: 18px;
            padding: 40px 30px;
            border-radius: 34px;
            background:
                radial-gradient(circle at top, rgba(255,203,50,.14), transparent 34%),
                linear-gradient(180deg, rgba(255,255,255,.98), rgba(244,248,255,.97));
            border: 1px solid rgba(214,226,244,.95);
            box-shadow: 0 24px 52px rgba(23,49,92,.10);
        }
        .terminal__headline {
            display: grid;
            gap: 12px;
            justify-items: center;
        }
        .terminal__grid {
            width: min(100%, 780px);
            grid-template-columns: repeat(3, minmax(180px, 1fr));
            gap: 18px;
        }
        .terminal__box {
            min-height: 128px;
            display: grid;
            align-content: center;
            gap: 6px;
            background: linear-gradient(180deg, rgba(255,255,255,.97), rgba(243,248,255,.98));
            box-shadow: inset 0 1px 0 rgba(255,255,255,.92);
        }
        .simulator {
            position: fixed;
            right: 18px;
            bottom: 96px;
            z-index: 20;
            width: min(320px, calc(100vw - 36px));
            padding: 16px;
            border-radius: 22px;
            background: rgba(255,255,255,.94);
            border: 1px solid rgba(214,226,244,.95);
            box-shadow: 0 18px 40px rgba(23,49,92,.16);
            backdrop-filter: blur(14px);
        }
        .simulator__title {
            margin: 0 0 10px;
            font-size: 15px;
            font-weight: 800;
            color: var(--text);
        }
        .simulator__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .simulator__button {
            min-height: 42px;
            border: 1px solid rgba(201,214,238,.95);
            border-radius: 14px;
            background: linear-gradient(180deg, #ffffff, #f4f8ff);
            color: #214b91;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 10px 22px rgba(23,49,92,.06);
        }
        .simulator__button:hover,
        .simulator__button:focus {
            outline: none;
            border-color: rgba(147,174,225,.95);
        }
        .flow {
            min-height: 100%;
            display: grid;
            align-content: start;
            justify-items: center;
            gap: 24px;
            padding: 32px 28px;
        }
        .flow__hero {
            width: min(100%, 860px);
            display: grid;
            justify-items: center;
            gap: 20px;
            padding: 30px;
            border-radius: 36px;
            background:
                radial-gradient(circle at top, rgba(255,203,50,.16), transparent 32%),
                linear-gradient(180deg, rgba(255,255,255,.98), rgba(244,248,255,.97));
            border: 1px solid rgba(214,226,244,.95);
            box-shadow: 0 24px 52px rgba(23,49,92,.10);
        }
        .flow__visual {
            width: min(100%, 460px);
            min-height: 400px;
            display: grid;
            place-items: center;
            padding: 24px;
            border-radius: 40px;
            background:
                radial-gradient(circle at top, rgba(255,203,50,.18), transparent 34%),
                linear-gradient(180deg, #ffffff, #f3f8ff);
            border: 1px solid rgba(214, 226, 244, 0.9);
            box-shadow: 0 24px 48px rgba(23,49,92,.09);
        }
        .flow__visual--compact {
            width: auto;
            min-height: auto;
            padding: 0;
        }
        .flow__visual img {
            display: block;
            width: 100%;
            max-width: 380px;
            height: auto;
            padding: 18px;
            border-radius: 28px;
            background: #fff;
            box-shadow: 0 24px 44px rgba(23,49,92,.14);
        }
        .flow__headline {
            display: grid;
            justify-items: center;
            gap: 10px;
            text-align: center;
        }
        .flow__title {
            margin: 0;
            font-size: 54px;
            line-height: .96;
            letter-spacing: -.05em;
            font-weight: 900;
        }
        .flow__text {
            margin: 0;
            max-width: 620px;
            font-size: 21px;
            line-height: 1.5;
            color: var(--muted);
        }
        .flow__grid {
            width: min(100%, 860px);
            display: grid;
            grid-template-columns: repeat(3, minmax(180px, 1fr));
            gap: 16px;
        }
        .flow__card {
            min-height: 104px;
            display: grid;
            align-content: center;
            gap: 8px;
            padding: 18px 20px;
            text-align: center;
            border-radius: 24px;
            border: 1px solid rgba(214,226,244,.9);
            background: linear-gradient(180deg, rgba(255,255,255,.97), rgba(243,248,255,.98));
            box-shadow: inset 0 1px 0 rgba(255,255,255,.92);
        }
        .flow__card small {
            display: block;
            color: var(--muted);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .flow__card strong {
            font-size: 30px;
            line-height: 1.2;
        }
        .flow__card--wide {
            grid-column: 1 / -1;
        }
        @media (orientation: landscape) and (min-width: 1100px) {
            .ads__qr-shell {
                width: min(42vw, 58vh, 520px);
            }
        }
        @media (orientation: portrait) {
            .screen {
                grid-template-rows: auto 1fr auto;
            }
            .ads, .qr, .terminal {
                padding: 24px;
            }
            .ads__qr-shell {
                width: min(74vw, 46vh, 560px);
            }
            .qr {
                grid-template-columns: 1fr;
                justify-items: center;
                text-align: center;
            }
            .qr__meta {
                justify-items: center;
                text-align: center;
            }
            .qr__visual {
                width: min(100%, 78vw);
                max-width: 560px;
                min-height: auto;
                aspect-ratio: 1 / 1;
            }
            .qr__grid {
                width: 100%;
            }
            .terminal__grid {
                grid-template-columns: 1fr;
            }
            .terminal__hero,
            .terminal__grid {
                width: 100%;
            }
            .flow__hero,
            .flow__grid {
                width: 100%;
            }
        }
        @media (max-width: 1240px) {
            .ads__panel, .qr, .qr__grid, .terminal__grid {
                grid-template-columns: 1fr;
            }
            .qr__title, .terminal__title {
                font-size: 46px;
            }
            .qr__meta {
                justify-items: center;
                text-align: center;
            }
            .qr__box--wide {
                grid-column: auto;
            }
            .flow__grid {
                grid-template-columns: 1fr;
            }
            .flow__card--wide {
                grid-column: auto;
            }
        }
        @media (max-width: 720px) {
            body { overflow: auto; }
            .screen { padding: 14px; }
            .topbar, .footer {
                padding: 16px 18px;
                flex-direction: column;
                align-items: flex-start;
            }
            .ads, .qr, .terminal {
                padding: 20px;
            }
            .qr__title, .terminal__title {
                font-size: 34px;
            }
            .qr__text, .terminal__text {
                font-size: 18px;
            }
            .ads__qr-shell {
                width: min(86vw, 48vh, 460px);
                min-width: 0;
            }
            .qr__visual {
                width: min(100%, 86vw);
                min-width: 0;
                min-height: auto;
                aspect-ratio: 1 / 1;
            }
            .qr__grid {
                grid-template-columns: 1fr;
            }
            .qr__box--wide {
                grid-column: auto;
            }
            .terminal__grid {
                grid-template-columns: 1fr;
            }
            .terminal__hero {
                padding: 28px 20px;
                border-radius: 28px;
            }
            .terminal__icon {
                width: 124px;
                height: 124px;
                font-size: 54px;
            }
            .terminal__box {
                min-height: 110px;
            }
            .flow {
                padding: 20px 18px;
                gap: 22px;
            }
            .flow__hero {
                padding: 22px 18px;
                border-radius: 28px;
            }
            .flow__visual {
                width: min(100%, 82vw);
                min-height: auto;
                aspect-ratio: 1 / 1;
                padding: 20px;
            }
            .flow__visual--compact {
                width: auto;
                min-height: auto;
            }
            .flow__title {
                font-size: 34px;
            }
            .flow__text {
                font-size: 18px;
            }
            .flow__grid {
                grid-template-columns: 1fr;
            }
            .flow__card--wide {
                grid-column: auto;
            }
            .simulator {
                right: 14px;
                bottom: 88px;
                width: min(280px, calc(100vw - 28px));
            }
        }
    </style>
</head>
<body>
    <div class="screen">
        <div class="topbar">
            <div class="brand">
                <h1>Correos de Bolivia</h1>
                <p>Pantalla de cobro QR</p>
            </div>
            <div class="status">
                <span class="status__dot"></span>
                <span id="monitorStatusText">Monitor listo</span>
            </div>
        </div>

        <div class="content">
            <div class="state" id="monitorStateRoot"></div>
        </div>

        <div class="footer">
            <div>Monitor: {{ $monitorKey }}</div>
            <div id="updatedAtText">Esperando eventos</div>
        </div>
    </div>
    @if (app()->environment('local'))
        <div class="simulator" id="monitorSimulator">
            <p class="simulator__title">Simulador</p>
            <div class="simulator__grid">
                <button type="button" class="simulator__button" data-sim-state="ads">Esperando QR</button>
                <button type="button" class="simulator__button" data-sim-state="qr">QR activo</button>
                <button type="button" class="simulator__button" data-sim-state="paid">Pagado</button>
                <button type="button" class="simulator__button" data-sim-state="reset">Limpiar</button>
            </div>
        </div>
    @endif

    <script>
        (() => {
            const monitorKey = @json($monitorKey);
            const stateRoot = document.getElementById('monitorStateRoot');
            const statusText = document.getElementById('monitorStatusText');
            const updatedAtText = document.getElementById('updatedAtText');
            const simulator = document.getElementById('monitorSimulator');
            const storageKey = 'facturacion-monitor-state:' + monitorKey;
            const broadcastName = 'facturacion-qr-monitor';
            let resetTimer = null;
            let channel = null;

            const escapeHtml = (value) => String(value || '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const formatAmount = (amount) => {
                const numeric = Number(amount || 0);
                return Number.isFinite(numeric) && numeric > 0 ? 'Bs ' + numeric.toFixed(2) : 'Bs 0.00';
            };

            const formatDate = (value) => {
                if (!value) {
                    return 'Esperando eventos';
                }
                const parsed = new Date(value);
                return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleString('es-BO');
            };

            const normalizeImageSrc = (value) => {
                const raw = String(value || '').trim();
                if (raw === '') {
                    return '';
                }
                if (raw.startsWith('data:image') || /^https?:\/\//i.test(raw)) {
                    return raw;
                }
                return 'data:image/png;base64,' + raw;
            };

            const resolveMonitorStatusLabel = (value, fallback = 'Esperando QR') => {
                const status = String(value || '').trim().toLowerCase();
                if (status === '') {
                    return fallback;
                }

                if (['paid', 'pagado', 'approved', 'confirmed', 'completed', 'success'].includes(status)) {
                    return 'Pagado';
                }

                if (['cancelado', 'cancelled', 'rejected', 'failed', 'expired'].includes(status)) {
                    return 'No pagado';
                }

                if (['pending', 'pendiente', 'holding', 'waiting'].includes(status)) {
                    return 'Esperando pago';
                }

                return fallback;
            };

            const resolveMonitorStatusCaption = (value, fallback = 'Esperando QR...') => {
                const label = resolveMonitorStatusLabel(value, fallback.replace(/\.\.\.$/, ''));
                if (label === 'Pagado') {
                    return 'Pagado';
                }
                if (label === 'No pagado') {
                    return 'No pagado';
                }
                if (label === 'Esperando pago') {
                    return 'Esperando pago...';
                }
                if (label === 'Esperando QR') {
                    return 'Esperando QR...';
                }

                return fallback;
            };

            const baseAdsState = () => ({
                mode: 'ads',
                title: 'Pagos QR',
                message: 'Escanea o acerca tu codigo QR para realizar el pago.',
                payment_status: '',
                updated_at: new Date().toISOString(),
            });

            const sampleQrSvg = () => {
                const svg = `
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240">
                        <rect width="240" height="240" fill="#ffffff"/>
                        <rect x="16" y="16" width="56" height="56" fill="#111111"/>
                        <rect x="28" y="28" width="32" height="32" fill="#ffffff"/>
                        <rect x="36" y="36" width="16" height="16" fill="#111111"/>
                        <rect x="168" y="16" width="56" height="56" fill="#111111"/>
                        <rect x="180" y="28" width="32" height="32" fill="#ffffff"/>
                        <rect x="188" y="36" width="16" height="16" fill="#111111"/>
                        <rect x="16" y="168" width="56" height="56" fill="#111111"/>
                        <rect x="28" y="180" width="32" height="32" fill="#ffffff"/>
                        <rect x="36" y="188" width="16" height="16" fill="#111111"/>
                        <rect x="96" y="24" width="16" height="16" fill="#111111"/>
                        <rect x="120" y="24" width="16" height="16" fill="#111111"/>
                        <rect x="96" y="48" width="16" height="16" fill="#111111"/>
                        <rect x="136" y="48" width="16" height="16" fill="#111111"/>
                        <rect x="88" y="88" width="16" height="16" fill="#111111"/>
                        <rect x="112" y="88" width="16" height="16" fill="#111111"/>
                        <rect x="136" y="88" width="16" height="16" fill="#111111"/>
                        <rect x="160" y="88" width="16" height="16" fill="#111111"/>
                        <rect x="88" y="112" width="16" height="16" fill="#111111"/>
                        <rect x="136" y="112" width="16" height="16" fill="#111111"/>
                        <rect x="184" y="112" width="16" height="16" fill="#111111"/>
                        <rect x="88" y="136" width="16" height="16" fill="#111111"/>
                        <rect x="112" y="136" width="16" height="16" fill="#111111"/>
                        <rect x="160" y="136" width="16" height="16" fill="#111111"/>
                        <rect x="184" y="136" width="16" height="16" fill="#111111"/>
                        <rect x="96" y="160" width="16" height="16" fill="#111111"/>
                        <rect x="120" y="160" width="16" height="16" fill="#111111"/>
                        <rect x="144" y="160" width="16" height="16" fill="#111111"/>
                        <rect x="168" y="160" width="16" height="16" fill="#111111"/>
                        <rect x="96" y="184" width="16" height="16" fill="#111111"/>
                        <rect x="144" y="184" width="16" height="16" fill="#111111"/>
                        <rect x="192" y="184" width="16" height="16" fill="#111111"/>
                    </svg>
                `;
                return 'data:image/svg+xml;base64,' + btoa(svg);
            };

            const renderAds = (state = {}) => {
                statusText.textContent = resolveMonitorStatusLabel(state.payment_status, 'Esperando QR');
                const caption = resolveMonitorStatusCaption(state.payment_status, 'Esperando QR...');
                stateRoot.innerHTML = `
                    <div class="flow">
                        <div class="flow__hero">
                            <div class="flow__visual">
                                <div class="ads__qr-shell">
                                    <div class="ads__qr-frame">
                                        <div class="ads__qr-icon" aria-hidden="true">
                                            <svg viewBox="0 0 64 64" fill="none">
                                                <rect x="10" y="10" width="16" height="16" rx="2.5" stroke="currentColor" stroke-width="4"/>
                                                <rect x="38" y="10" width="16" height="16" rx="2.5" stroke="currentColor" stroke-width="4"/>
                                                <rect x="10" y="38" width="16" height="16" rx="2.5" stroke="currentColor" stroke-width="4"/>
                                                <path d="M38 40h6v6" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M50 40v14" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                                                <path d="M38 54h14" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                                                <path d="M44 32v10" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flow__headline">
                                <span class="eyebrow">Cobro QR</span>
                                <h2 class="flow__title">${escapeHtml(state.title || 'Esperando QR')}</h2>
                                <p class="flow__text">${escapeHtml(state.message || 'Cuando la venta prepare el cobro, el codigo QR aparecera automaticamente en esta pantalla.')}</p>
                                <p class="ads__qr-caption">${escapeHtml(caption)}</p>
                            </div>
                        </div>
                    </div>
                `;
            };

            const renderQr = (state = {}) => {
                statusText.textContent = resolveMonitorStatusLabel(state.payment_status, 'Esperando pago');
                const imageSrc = normalizeImageSrc(state.image_data || '');
                stateRoot.innerHTML = `
                    <div class="flow">
                        <div class="flow__hero">
                            <div class="flow__visual">
                                ${imageSrc !== '' ? `<img src="${escapeHtml(imageSrc)}" alt="QR de cobro">` : '<div class="qr__empty">QR no disponible</div>'}
                            </div>
                            <div class="flow__headline">
                                <span class="eyebrow">Cobro QR activo</span>
                                <h2 class="flow__title">${escapeHtml(state.title || 'Escanea para pagar')}</h2>
                                <p class="flow__text">${escapeHtml(state.message || 'Escanea este codigo con tu app bancaria para completar el pago.')}</p>
                            </div>
                        </div>
                        <div class="flow__grid">
                            <div class="flow__card flow__card--wide">
                                <small>Monto</small>
                                <strong>${escapeHtml(formatAmount(state.amount))}</strong>
                            </div>
                            <div class="flow__card">
                                <small>Orden</small>
                                <strong>${escapeHtml(state.internal_code || '-')}</strong>
                            </div>
                            <div class="flow__card">
                                <small>Referencia QR</small>
                                <strong>${escapeHtml(state.transaction_id || '-')}</strong>
                            </div>
                        </div>
                    </div>
                `;
            };

            const renderTerminal = (state = {}) => {
                const success = ['paid', 'pagado', 'confirmed', 'success', 'approved', 'completed'].includes(String(state.terminal_status || state.payment_status || '').toLowerCase());
                statusText.textContent = resolveMonitorStatusLabel(
                    state.terminal_status || state.payment_status,
                    success ? 'Pagado' : 'Actualizando estado'
                );
                stateRoot.innerHTML = `
                    <div class="flow">
                        <div class="flow__hero">
                            <div class="terminal__icon ${success ? 'terminal__icon--success' : 'terminal__icon--warning'}">
                                ${success ? '&#10003;' : '!'}
                            </div>
                            <div class="flow__headline">
                                <span class="eyebrow">Estado actualizado</span>
                                <h2 class="flow__title">${escapeHtml(state.title || 'Operacion finalizada')}</h2>
                                <p class="flow__text">${escapeHtml(state.message || 'La pantalla volvera automaticamente al modo de espera.')}</p>
                            </div>
                        </div>
                        <div class="flow__grid">
                            <div class="flow__card">
                                <small>Orden</small>
                                <strong>${escapeHtml(state.internal_code || '-')}</strong>
                            </div>
                            <div class="flow__card">
                                <small>Referencia QR</small>
                                <strong>${escapeHtml(state.transaction_id || '-')}</strong>
                            </div>
                            <div class="flow__card">
                                <small>Monto</small>
                                <strong>${escapeHtml(formatAmount(state.amount))}</strong>
                            </div>
                        </div>
                    </div>
                `;
            };

            const renderState = (state = {}) => {
                updatedAtText.textContent = 'Actualizado: ' + formatDate(state.updated_at);

                if (resetTimer) {
                    clearTimeout(resetTimer);
                    resetTimer = null;
                }

                if (state.mode === 'qr') {
                    renderQr(state);
                } else if (state.mode === 'terminal') {
                    renderTerminal(state);
                    const resetMs = Number(state.auto_reset_ms || 8000);
                    if (resetMs > 0) {
                        resetTimer = window.setTimeout(() => {
                            const adsState = baseAdsState();
                            persistState(adsState);
                            renderAds(adsState);
                        }, resetMs);
                    }
                } else {
                    renderAds(state);
                }
            };

            const persistState = (state) => {
                try {
                    window.localStorage.setItem(storageKey, JSON.stringify(state));
                } catch (error) {
                    // Ignora errores de storage.
                }
            };

            const readPersistedState = () => {
                try {
                    const raw = window.localStorage.getItem(storageKey);
                    if (!raw) {
                        return baseAdsState();
                    }
                    const parsed = JSON.parse(raw);
                    if (!parsed || typeof parsed !== 'object') {
                        return baseAdsState();
                    }
                    if (parsed.mode === 'terminal') {
                        const updatedAt = new Date(parsed.updated_at || Date.now()).getTime();
                        const autoResetMs = Number(parsed.auto_reset_ms || 8000);
                        if (Number.isFinite(updatedAt) && Number.isFinite(autoResetMs) && Date.now() - updatedAt >= autoResetMs) {
                            return baseAdsState();
                        }
                    }
                    return parsed;
                } catch (error) {
                    return baseAdsState();
                }
            };

            const handleIncomingState = (payload) => {
                if (!payload || typeof payload !== 'object') {
                    return;
                }
                if (String(payload.monitor_key || '') !== monitorKey) {
                    return;
                }
                const state = payload.state && typeof payload.state === 'object'
                    ? payload.state
                    : baseAdsState();
                persistState(state);
                renderState(state);
            };

            const bootstrapBroadcast = () => {
                if (!('BroadcastChannel' in window)) {
                    return;
                }
                channel = new BroadcastChannel(broadcastName);
                channel.addEventListener('message', (event) => {
                    handleIncomingState(event.data);
                });
            };

            window.addEventListener('storage', (event) => {
                if (event.key !== storageKey || !event.newValue) {
                    return;
                }
                try {
                    renderState(JSON.parse(event.newValue));
                } catch (error) {
                    renderState(baseAdsState());
                }
            });

            if (simulator instanceof HTMLElement) {
                simulator.addEventListener('click', (event) => {
                    const target = event.target;
                    if (!(target instanceof HTMLButtonElement)) {
                        return;
                    }

                    const kind = String(target.dataset.simState || '').trim();
                    const now = new Date().toISOString();
                    let state = baseAdsState();

                    if (kind === 'pending') {
                        state = {
                            mode: 'qr',
                            title: 'Escanea para pagar',
                            message: 'Escanea este codigo con tu app bancaria para completar el pago.',
                            image_data: sampleQrSvg(),
                            transaction_id: 'SIM-QR-001',
                            internal_code: 'ORD-SIM-001',
                            payment_status: 'HOLDING',
                            amount: 15.50,
                            updated_at: now,
                        };
                    } else if (kind === 'qr') {
                        state = {
                            mode: 'qr',
                            title: 'QR activo',
                            message: 'El QR esta visible y listo para escanear.',
                            image_data: sampleQrSvg(),
                            transaction_id: 'SIM-QR-002',
                            internal_code: 'ORD-SIM-002',
                            payment_status: 'PENDING',
                            amount: 22.00,
                            updated_at: now,
                        };
                    } else if (kind === 'paid') {
                        state = {
                            mode: 'terminal',
                            title: 'Pago completado',
                            message: 'La transaccion fue aprobada correctamente.',
                            transaction_id: 'SIM-QR-003',
                            internal_code: 'ORD-SIM-003',
                            payment_status: 'PAID',
                            terminal_status: 'PAID',
                            amount: 48.90,
                            auto_reset_ms: 9000,
                            updated_at: now,
                        };
                    } else if (kind === 'failed') {
                        state = {
                            mode: 'terminal',
                            title: 'Pago no completado',
                            message: 'La operacion no fue confirmada.',
                            transaction_id: 'SIM-QR-004',
                            internal_code: 'ORD-SIM-004',
                            payment_status: 'FAILED',
                            terminal_status: 'FAILED',
                            amount: 48.90,
                            auto_reset_ms: 9000,
                            updated_at: now,
                        };
                    } else if (kind === 'reset') {
                        state = baseAdsState();
                    } else if (kind === 'ads') {
                        state = baseAdsState();
                    }

                    persistState(state);
                    renderState(state);
                });
            }

            bootstrapBroadcast();
            renderState(readPersistedState());
        })();
    </script>
</body>
</html>
