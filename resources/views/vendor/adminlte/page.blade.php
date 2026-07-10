@extends('adminlte::master')

@inject('layoutHelper', 'JeroenNoten\LaravelAdminLte\Helpers\LayoutHelper')
@inject('preloaderHelper', 'JeroenNoten\LaravelAdminLte\Helpers\PreloaderHelper')

@section('adminlte_css')
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-H41CHNHCL0"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }

        gtag('js', new Date());
        gtag('config', 'G-H41CHNHCL0');
    </script>
    <style>
        body,
        .wrapper,
        .content-wrapper,
        .main-sidebar,
        .main-header,
        .content-header,
        .content,
        .card,
        .table,
        .btn,
        .form-control,
        .nav-sidebar,
        .info-box {
            font-family: Verdana, Geneva, sans-serif !important;
        }

        .contract-alert-toast {
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .contract-alert-toast.is-hiding {
            opacity: 0;
            transform: translateX(24px);
            pointer-events: none;
        }
    </style>
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

        @auth
            @if(!empty($empresaContractAlerts) && collect($empresaContractAlerts)->isNotEmpty())
                <div class="position-fixed" style="top: 72px; right: 18px; z-index: 1055; width: min(460px, calc(100vw - 24px));">
                    @foreach(collect($empresaContractAlerts)->take(5) as $contractAlert)
                        <div class="alert alert-warning shadow-sm border mb-2 contract-alert-toast" data-contract-alert>
                            <div class="font-weight-bold">Alerta de contrato</div>
                            <div>{{ $contractAlert['message'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endauth

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

        (function () {
            const alerts = document.querySelectorAll('[data-contract-alert]');
            if (!alerts.length) {
                return;
            }

            window.setTimeout(function () {
                alerts.forEach(function (alertBox) {
                    alertBox.classList.add('is-hiding');

                    window.setTimeout(function () {
                        alertBox.remove();
                    }, 600);
                });
            }, 10000);
        })();
    </script>
    @endauth
@stop
