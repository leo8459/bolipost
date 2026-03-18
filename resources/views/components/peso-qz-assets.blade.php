@once
    <style>
        .peso-cas-panel{
            margin-top:10px;
            padding:10px 12px;
            border:1px solid #e5e7eb;
            border-radius:10px;
            background:#f8fafc;
        }
        .peso-cas-status-line{
            display:flex;
            align-items:center;
            gap:8px;
            flex-wrap:wrap;
        }
        .peso-cas-frame{
            margin-top:6px;
            font-size:12px;
            color:#475569;
            word-break:break-word;
        }
        .status-pill{
            border-radius:999px;
            display:inline-block;
            font-size:11px;
            font-weight:800;
            letter-spacing:.03em;
            padding:3px 10px;
        }
        .status-ok{
            color:#166534;
            background:rgba(22,101,52,.12);
        }
        .status-warn{
            color:#b45309;
            background:rgba(180,83,9,.14);
        }
        .status-bad{
            color:#b91c1c;
            background:rgba(185,28,28,.12);
        }
    </style>

    <script>
        window.CAS_PESAJE_CONFIG = window.CAS_PESAJE_CONFIG ?? {};
        window.CAS_PESAJE_CONFIG.endpoints = window.CAS_PESAJE_CONFIG.endpoints ?? {
            certificate: '{{ route("qz.certificate", absolute: false) }}',
            sign: '{{ route("qz.sign", absolute: false) }}',
        };
        window.CAS_PESAJE_CONFIG.serial = window.CAS_PESAJE_CONFIG.serial ?? {
            baudRate: 9600,
            dataBits: 7,
            stopBits: 1,
            parity: 'EVEN',
            flowControl: 'NONE',
            encoding: 'UTF-8',
            rxStart: '',
            rxEnd: '\\r',
            rxWidth: null,
            rxRaw: false,
            portRegex: '^COM\\d+$',
            startCommand: 'W',
            stopCommand: '',
            pollCommands: [],
            pollGapMs: 120,
            pollEveryMs: 900,
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.4/qz-tray.js" defer></script>
    <script src="{{ asset('js/cas-peso-qz.js') }}" defer></script>
@endonce
