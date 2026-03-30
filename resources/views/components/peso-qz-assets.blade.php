@once
    @php
        $pesoQzJsPath = public_path('js/cas-peso-qz.js');
        $pesoQzJsVersion = is_file($pesoQzJsPath) ? filemtime($pesoQzJsPath) : time();
    @endphp

    <style>
        input[data-cas-peso-input]{
            font-weight:700;
            font-variant-numeric: tabular-nums;
        }
        .peso-cas-toggle{
            color:#1f3d7a;
            font-size:12px;
            font-weight:700;
            text-decoration:none;
            outline:none;
        }
        .peso-cas-toggle:hover{
            color:#16305f;
            text-decoration:none;
        }
        .peso-cas-toggle:focus{
            box-shadow:none;
            text-decoration:none;
        }
        .peso-cas-toggle::before{
            content:"+ ";
            font-weight:800;
        }
        .peso-cas-toggle[aria-expanded="true"]::before{
            content:"- ";
        }
        button[data-cas-clear].btn,
        button[data-cas-reconnect].btn{
            border:1px solid #c3d0e5;
            background:#fff;
            color:#1e3a8a;
            font-weight:700;
            border-radius:10px;
            transition:all .2s ease;
        }
        button[data-cas-clear].btn{
            padding-left:14px;
            padding-right:14px;
            white-space:nowrap;
        }
        button[data-cas-clear].btn:hover{
            border-color:#91a8d4;
            background:#f8fbff;
            color:#163a77;
        }
        button[data-cas-reconnect].btn:hover{
            border-color:#91a8d4;
            background:#f8fbff;
            color:#163a77;
        }
        button[data-cas-clear].btn:focus,
        button[data-cas-reconnect].btn:focus{
            box-shadow:0 0 0 .18rem rgba(37,99,235,.18);
        }
        .peso-cas-panel{
            margin-top:10px;
            padding:10px 12px 9px;
            border:1px solid #dbe5f4;
            border-radius:12px;
            background:linear-gradient(145deg,#f8fbff 0%,#f2f7ff 100%);
        }
        .peso-cas-status-line{
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
        }
        .peso-cas-frame{
            margin-top:7px;
            padding-top:7px;
            border-top:1px dashed #d5dfef;
            font-size:12px;
            color:#425269;
            word-break:break-word;
        }
        .status-pill{
            border-radius:999px;
            display:inline-block;
            font-size:10px;
            font-weight:800;
            letter-spacing:.05em;
            padding:4px 10px;
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
            noDataTimeoutMs: 8000,
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.4/qz-tray.js" defer></script>
    <script src="{{ asset('js/cas-peso-qz.js') }}?v={{ $pesoQzJsVersion }}" defer></script>
@endonce
