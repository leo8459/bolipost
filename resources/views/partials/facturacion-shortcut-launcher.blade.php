@once('facturacion-shortcut-launcher')
@if (!request()->routeIs('users.empresas', 'users.empresas.*') && auth('web')->check() && auth('web')->user()->can('feature.dashboard.facturacion'))
    @php
        // Preserve feedback, automatic downloads and QR dialogs after a billing redirect.
        $hasFacturacionFeedback = session()->hasAny([
            'facturacion_feedback', 'facturacion_download_pdf', 'facturacion_qr_data',
            'facturacion_qr_force_open', 'facturacion_scanner_open',
        ]) || old('scan_code') !== null;
    @endphp
    @if ($hasFacturacionFeedback)
        @include('partials.facturacion-shortcut')
    @else
        <link rel="stylesheet" href="{{ asset('css/facturacion-shortcut-launcher.css') }}?v={{ filemtime(public_path('css/facturacion-shortcut-launcher.css')) }}">
        <div id="facturacionShortcutLauncher" data-endpoint="{{ route('facturacion.shortcut', absolute: false) }}">
            <button type="button" class="global-facturacion-fab" id="openFacturacionShortcut"
                aria-controls="facturacionShortcutModal" aria-expanded="false" aria-label="Abrir accesos de Facturacion">
                <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i>
                <span>Facturacion</span>
            </button>
            <div id="facturacionShortcutLoadStatus" role="status" aria-live="polite" hidden></div>
        </div>
        <script defer src="{{ asset('js/facturacion-shortcut-loader.js') }}?v={{ filemtime(public_path('js/facturacion-shortcut-loader.js')) }}"></script>
    @endif
@endif
@endonce
