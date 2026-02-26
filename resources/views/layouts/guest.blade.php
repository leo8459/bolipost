@props([
    'cardMaxWidth' => '460px',
    'cardClasses' => '',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-[#041e42] bg-[radial-gradient(circle_at_top,rgba(254,203,52,0.30),transparent_48%),linear-gradient(180deg,#f8fbff_0%,#eef3fb_100%)]">
        <div class="min-h-screen flex flex-col justify-center items-center px-4 py-8">
            <div class="mb-5">
                <a href="/">
                    <img src="{{ asset('images/AGBClogo.png') }}" alt="TrackingBo" class="w-48 h-auto object-contain drop-shadow-sm">
                </a>
            </div>

            <div
                class="relative w-full px-6 py-5 bg-white/95 border border-[#1a549a]/20 shadow-[0_18px_40px_rgba(15,39,74,0.18)] overflow-hidden rounded-2xl backdrop-blur {{ $cardClasses }}"
                style="max-width: {{ $cardMaxWidth }};"
            >
                <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-[linear-gradient(90deg,#eab312_0%,#fecb34_50%,#eab312_100%)]"></div>
                {{ $slot }}
            </div>
           
        </div>
    </body>
</html>
