@once
    <link rel="stylesheet" href="{{ asset('css/system-responsive.css') }}?v={{ filemtime(public_path('css/system-responsive.css')) }}">
    <script defer src="{{ asset('js/system-responsive.js') }}?v={{ filemtime(public_path('js/system-responsive.js')) }}"></script>
@endonce

