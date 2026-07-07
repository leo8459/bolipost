@extends('adminlte::page')

@section('title', 'Bienvenida')

@section('content_header')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
        <div>
            <h1 class="mb-1">Bienvenido, {{ auth()->user()?->name }}</h1>
            <small class="text-muted">Resumen rapido de alertas y accesos principales.</small>
        </div>
        <div class="mt-3 mt-lg-0 d-flex flex-wrap">
            <a href="{{ route('dashboard') }}" class="btn btn-primary mr-2 mb-2">
                Ir al dashboard
            </a>
            <a href="{{ route('preregistros.public.create') }}" target="_blank" rel="noopener" class="btn btn-outline-primary mb-2">
                Hacer envio desde casa
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="welcome-hub">
        @include('dashboard.partials.alerts')

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center">
                    <div class="col-lg-8 mb-4 mb-lg-0">
                        <span class="welcome-badge">Inicio</span>
                        <h2 class="welcome-title mt-3">Tu jornada empieza aqui</h2>
                        <p class="welcome-copy mb-0">
                            Revisa primero las alertas operativas. Luego puedes continuar al dashboard completo
                            o entrar directamente a los modulos que mas uses en tu flujo diario.
                        </p>
                    </div>
                    <div class="col-lg-4">
                        <div class="welcome-actions">
                            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-block mb-2">Abrir dashboard</a>
                            <a href="{{ route('carteros.distribucion') }}" class="btn btn-outline-secondary btn-block mb-2">Ir a distribucion</a>
                            <a href="{{ route('bitacoras.create') }}" class="btn btn-outline-secondary btn-block">Registrar bitacora</a>
                        </div>
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
    </style>
@stop

@section('js')
    @include('dashboard.partials.pickup-alert-sound')
@stop
