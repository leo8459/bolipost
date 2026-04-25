@extends('adminlte::master')

@inject('layoutHelper', 'JeroenNoten\LaravelAdminLte\Helpers\LayoutHelper')
@inject('preloaderHelper', 'JeroenNoten\LaravelAdminLte\Helpers\PreloaderHelper')

@section('adminlte_css')
    @stack('css')
    @yield('css')
@stop

@section('classes_body', $layoutHelper->makeBodyClasses())

@section('body_data', $layoutHelper->makeBodyData())

@section('body')
    <div class="wrapper">

        @if($preloaderHelper->isPreloaderEnabled())
            @include('adminlte::partials.common.preloader')
        @endif

        @if($layoutHelper->isLayoutTopnavEnabled())
            @include('adminlte::partials.navbar.navbar-layout-topnav')
        @else
            @include('adminlte::partials.navbar.navbar')
        @endif

        @if(!$layoutHelper->isLayoutTopnavEnabled())
            @include('adminlte::partials.sidebar.left-sidebar')
        @endif

        @empty($iFrameEnabled)
            @include('adminlte::partials.cwrapper.cwrapper-default')
        @else
            @include('adminlte::partials.cwrapper.cwrapper-iframe')
        @endempty

        @include('partials.facturacion-shortcut')

        @hasSection('footer')
            @include('adminlte::partials.footer.footer')
        @endif

        @if($layoutHelper->isRightSidebarEnabled())
            @include('adminlte::partials.sidebar.right-sidebar')
        @endif

    </div>
@stop

@section('adminlte_js')
    @stack('js')
    @yield('js')
    @auth
    <script>
        (function () {
            const inactivityLimitMs = {{ (int) config('session.lifetime', 60) * 60 * 1000 }};
            const logoutUrl = @json(route('logout.get', absolute: false));
            let inactivityTimer = null;

            const triggerAutoLogout = function () {
                window.location.href = logoutUrl + '?motivo=inactividad';
            };

            const resetInactivityTimer = function () {
                if (inactivityTimer) {
                    window.clearTimeout(inactivityTimer);
                }
                inactivityTimer = window.setTimeout(triggerAutoLogout, inactivityLimitMs);
            };

            [
                'mousemove',
                'mousedown',
                'keydown',
                'scroll',
                'touchstart',
                'click'
            ].forEach(function (eventName) {
                window.addEventListener(eventName, resetInactivityTimer, { passive: true });
            });

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    resetInactivityTimer();
                }
            });

            resetInactivityTimer();
        })();
    </script>
    @endauth
@stop
