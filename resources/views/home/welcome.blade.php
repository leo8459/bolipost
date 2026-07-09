@extends('adminlte::page')

@section('title', 'Bienvenida')

@section('content_header')
    @php
        $welcomeUser = auth()->user();
        $isEmpresaUser = $welcomeUser && method_exists($welcomeUser, 'hasRole') && $welcomeUser->hasRole('empresa');
    @endphp
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
        <div>
            <h1 class="mb-1">Bienvenido, {{ auth()->user()?->name }}</h1>
            <small class="text-muted">Resumen rapido de alertas y accesos principales.</small>
        </div>
        @if($isEmpresaUser)
        <div class="mt-3 mt-lg-0 d-flex flex-wrap">
            <a href="{{ route('paquetes-contrato.index') }}" class="btn btn-primary mb-2">
                Solicitar correspondencia
            </a>
        </div>
        @endif
    </div>
@stop

@section('content')
    <div class="welcome-hub">
        @unless($isEmpresaUser)
        @include('dashboard.partials.alerts')
        @endunless

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center">
                    <div class="col-lg-7 mb-4 mb-lg-0">
                        <span class="welcome-badge">Inicio</span>
                        <h2 class="welcome-title mt-3">Tu jornada empieza aqui</h2>
                        <p class="welcome-copy mb-0">
                            Revisa primero las alertas operativas. Luego puedes continuar al dashboard completo
                            o entrar directamente a los modulos que mas uses en tu flujo diario.
                        </p>
                    </div>
                    <div class="col-lg-5">
                        <div class="welcome-video-copy">
                            <h3 class="welcome-video-title mb-2">Video guia del modulo</h3>
                            <p class="welcome-video-text mb-3">
                                Conoce de forma visual el flujo de registro y gestion dentro de la plataforma.
                                Este material te ayudara a comprender los pasos principales y agilizar el uso del sistema.
                            </p>
                        </div>
                        <div class="welcome-media">
                            <video class="welcome-video" autoplay muted loop playsinline controls preload="metadata"
                                poster="{{ asset('images/MONITO.png') }}">
                                <source src="{{ asset('videos/empresa-presentacion.mp4') }}" type="video/mp4">
                                Tu navegador no soporta la reproduccion de video.
                            </video>
                        </div>
                        <div class="welcome-manual mb-3">
                            <a href="{{ asset('manuales/manual-de-usuario.pdf') }}" class="btn btn-outline-primary btn-block"
                                download="manual-de-usuario.pdf" target="_blank" rel="noopener">
                                Manual de Usuario
                            </a>
                        </div>
                        @unless($isEmpresaUser)
                        <div class="welcome-actions">
                            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-block mb-2">Abrir dashboard</a>
                            <a href="{{ route('carteros.distribucion') }}" class="btn btn-outline-secondary btn-block mb-2">Ir a distribucion</a>
                            <a href="{{ route('bitacoras.create') }}" class="btn btn-outline-secondary btn-block">Registrar bitacora</a>
                        </div>
                        @endunless
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        .welcome-hub .card {
            border-radius: 20px;
        }

        .welcome-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            background: #eef4ff;
            color: #20539A;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            font-size: 0.78rem;
        }

        .welcome-title {
            color: #163b6d;
            font-weight: 800;
            font-size: clamp(1.7rem, 2.4vw, 2.4rem);
        }

        .welcome-copy {
            color: #52627a;
            font-size: 1rem;
            max-width: 58ch;
        }

        .welcome-actions .btn {
            min-height: 46px;
            border-radius: 12px;
            font-weight: 700;
        }

        .welcome-manual .btn {
            min-height: 46px;
            border-radius: 12px;
            font-weight: 700;
        }

        .welcome-media {
            display: flex;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .welcome-video-copy {
            max-width: 420px;
            margin: 0 auto 0.9rem;
        }

        .welcome-video-title {
            color: #163b6d;
            font-size: 1.05rem;
            font-weight: 800;
        }

        .welcome-video-text {
            color: #5a6c86;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .welcome-video {
            width: 100%;
            max-width: 420px;
            border-radius: 18px;
            background: #0f2748;
            box-shadow: 0 16px 38px rgba(18, 47, 89, 0.18);
        }

        @media (max-width: 991.98px) {
            .welcome-video {
                max-width: 100%;
            }
        }
    </style>
@stop

@section('js')
    @include('dashboard.partials.pickup-alert-sound')
@stop
