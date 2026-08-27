@extends('adminlte::master')

@inject('layoutHelper', 'JeroenNoten\LaravelAdminLte\Helpers\LayoutHelper')
@inject('preloaderHelper', 'JeroenNoten\LaravelAdminLte\Helpers\PreloaderHelper')

@section('adminlte_css')
    @include('partials.system-responsive-assets')
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
            position: relative;
            padding-right: 2.75rem;
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .contract-alert-toast .contract-alert-close {
            position: absolute;
            top: 0.35rem;
            right: 0.65rem;
            padding: 0;
            border: 0;
            background: transparent;
            color: #212529;
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
            opacity: 0.65;
            cursor: pointer;
        }

        .contract-alert-toast .contract-alert-close:hover,
        .contract-alert-toast .contract-alert-close:focus {
            opacity: 1;
        }

        .contract-alert-toast.is-hiding {
            opacity: 0;
            transform: translateX(24px);
            pointer-events: none;
        }

        .impersonation-navbar-item {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            min-height: 38px;
            margin-right: 0.45rem;
            padding: 0.2rem 0.3rem 0.2rem 0.7rem;
            border: 1px solid rgba(0, 0, 0, 0.2);
            border-radius: 0.35rem;
            background: rgba(255, 255, 255, 0.28);
            color: #172b4d;
            font-size: 0.78rem;
            line-height: 1.1;
            white-space: nowrap;
        }

        .impersonation-navbar-item form {
            margin: 0;
        }

        .impersonation-navbar-item .btn {
            padding: 0.3rem 0.55rem;
            font-size: 0.74rem;
            font-weight: 700;
        }

        @media (max-width: 991.98px) {
            .impersonation-navbar-user {
                display: none;
            }
        }

        @media (max-width: 575.98px) {
            .impersonation-navbar-label {
                display: none;
            }

            .impersonation-navbar-item {
                padding-left: 0.3rem;
            }
        }
    </style>
    @stack('css')
    @yield('css')
@stop

@section('classes_body', $layoutHelper->makeBodyClasses())

@section('body_data', $layoutHelper->makeBodyData())

@section('content_top_nav_right')
    @auth
        @if(session()->has('impersonator_id'))
            <li class="nav-item impersonation-navbar-item" title="Estas usando el perfil de {{ auth()->user()?->name }}">
                <span class="impersonation-navbar-label">
                    <i class="fas fa-user-secret mr-1"></i>
                    Vista como <strong class="impersonation-navbar-user">{{ auth()->user()?->name }}</strong>
                </span>
                <form method="POST" action="{{ route('users.impersonate.destroy') }}">
                    @csrf
                    <button type="submit" class="btn btn-dark btn-sm text-nowrap" title="Volver a tu cuenta de administrador">
                        <i class="fas fa-undo-alt mr-1"></i> Administrador
                    </button>
                </form>
            </li>
        @endif
    @endauth
@stop

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
                            <button type="button" class="contract-alert-close" data-contract-alert-close aria-label="Cerrar alerta" title="Cerrar alerta">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <div class="font-weight-bold">Alerta de contrato</div>
                            <div>{{ $contractAlert['message'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            @include('alertas_empresa.partials.recipient-modal')
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

            const closeAlert = function (alertBox) {
                if (!alertBox || alertBox.classList.contains('is-hiding')) {
                    return;
                }

                alertBox.classList.add('is-hiding');

                window.setTimeout(function () {
                    alertBox.remove();
                }, 600);
            };

            alerts.forEach(function (alertBox) {
                const closeButton = alertBox.querySelector('[data-contract-alert-close]');

                if (closeButton) {
                    closeButton.addEventListener('click', function () {
                        closeAlert(alertBox);
                    });
                }
            });

            window.setTimeout(function () {
                alerts.forEach(function (alertBox) {
                    closeAlert(alertBox);
                });
            }, 10000);
        })();
    </script>
    @endauth
@stop
