@once
    <link rel="stylesheet" href="{{ asset('css/system-responsive.css') }}?v={{ filemtime(public_path('css/system-responsive.css')) }}">
    <script defer src="{{ asset('js/system-responsive.js') }}?v={{ filemtime(public_path('js/system-responsive.js')) }}"></script>
    @if(auth('web')->check() || auth('cliente')->check())
        <script defer src="{{ asset('js/window-load-metrics.js') }}?v={{ filemtime(public_path('js/window-load-metrics.js')) }}"
            data-endpoint="{{ route('window-load-metrics.store', absolute: false) }}"
            data-route="{{ request()->route()?->getName() }}"
            data-csrf="{{ csrf_token() }}"></script>
    @endif
@endonce

