@extends('layouts.cliente-adminlte')

@section('title', 'Dashboard Cliente')

@section('css')
    <style>
        .client-dashboard {
            --client-navy: #172c4d;
            --client-blue: #205a91;
            --client-gold: #ffca28;
        }

        .client-dashboard .card {
            border-radius: 16px;
        }

        .delivery-welcome {
            position: relative;
            overflow: hidden;
            border: 0;
            color: #fff;
            background: linear-gradient(115deg, var(--client-navy), var(--client-blue));
            box-shadow: 0 12px 30px rgba(23, 44, 77, .16);
            animation: client-dashboard-in .45s ease both;
        }

        .delivery-welcome::after {
            position: absolute;
            top: -95px;
            right: 16%;
            width: 230px;
            height: 230px;
            border: 1px solid rgba(255, 255, 255, .14);
            border-radius: 50%;
            box-shadow: 0 0 0 25px rgba(255, 255, 255, .035), 0 0 0 52px rgba(255, 255, 255, .025);
            content: "";
            pointer-events: none;
        }

        .delivery-welcome .card-body {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 26px 30px;
        }

        .delivery-welcome__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            color: #ffe28b;
            font-size: .75rem;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .delivery-welcome h2 {
            margin: 0;
            color: #fff;
            font-size: clamp(1.35rem, 2.4vw, 1.85rem);
            font-weight: 800;
        }

        .delivery-welcome p {
            max-width: 630px;
            margin: 7px 0 0;
            color: rgba(255, 255, 255, .78);
        }

        .delivery-welcome__status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 13px;
            padding: 6px 10px;
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 999px;
            color: #fff;
            background: rgba(255, 255, 255, .1);
            font-size: .75rem;
            font-weight: 700;
        }

        .delivery-welcome__status::before {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #5ee09a;
            box-shadow: 0 0 0 4px rgba(94, 224, 154, .13);
            content: "";
        }

        .delivery-welcome__status--paused::before {
            background: #ffc45c;
            box-shadow: 0 0 0 4px rgba(255, 196, 92, .13);
        }

        .delivery-welcome__actions {
            position: relative;
            display: flex;
            flex: 0 0 auto;
            flex-wrap: wrap;
            gap: 9px;
        }

        .delivery-welcome__actions .btn {
            min-height: 42px;
            padding: 10px 15px;
            border-radius: 10px;
            font-weight: 700;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .delivery-welcome__actions .btn:hover,
        .delivery-welcome__actions .btn:focus-visible {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, .18);
        }

        .delivery-welcome__actions .btn-warning {
            border-color: var(--client-gold);
            color: #172c4d;
            background: var(--client-gold);
        }

        .client-profile-card {
            overflow: hidden;
            border: 1px solid #e3eaf3;
            box-shadow: 0 9px 26px rgba(25, 48, 78, .07);
            animation: client-dashboard-in .55s ease both;
        }

        .client-profile-card .card-body {
            padding: 25px;
        }

        .client-profile__heading {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 22px;
        }

        .client-profile__avatar {
            flex: 0 0 76px;
            width: 76px;
            height: 76px;
            padding: 3px;
            border: 2px solid #ffca28;
            border-radius: 50%;
            background: #fff8df;
            object-fit: cover;
            box-shadow: 0 5px 14px rgba(23, 44, 77, .12);
        }

        .client-profile__name {
            margin: 0 0 4px;
            color: var(--client-navy);
            font-size: 1.35rem;
            font-weight: 800;
        }

        .client-profile__email {
            color: #63738a;
            overflow-wrap: anywhere;
        }

        .client-profile__section-title {
            margin: 0 0 12px;
            color: var(--client-navy);
            font-size: .9rem;
            font-weight: 800;
        }

        .client-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(205px, 1fr));
            gap: 12px;
        }

        .client-info-card {
            min-width: 0;
            padding: 15px 16px;
            border: 1px solid #e8edf4;
            border-radius: 12px;
            background: #f9fbfe;
            transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
        }

        .client-info-card:hover {
            transform: translateY(-3px);
            border-color: #c7d9ef;
            background: #fff;
            box-shadow: 0 8px 18px rgba(25, 48, 78, .08);
        }

        .client-info-card__label {
            display: block;
            margin-bottom: 5px;
            color: #75839a;
            font-size: .74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .client-info-card__value {
            display: block;
            color: #253954;
            font-size: .92rem;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .client-info-card__hint {
            display: block;
            margin-top: 4px;
            color: #78869a;
            font-size: .78rem;
        }

        @keyframes client-dashboard-in {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 767.98px) {
            .delivery-welcome .card-body {
                align-items: flex-start;
                flex-direction: column;
                padding: 22px;
            }

            .delivery-welcome__actions {
                width: 100%;
            }

            .delivery-welcome__actions .btn {
                flex: 1 1 145px;
            }

            .client-profile-card .card-body {
                padding: 20px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .client-dashboard *,
            .client-dashboard *::before,
            .client-dashboard *::after {
                animation-duration: .01ms !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
@endsection

@section('content_header')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div>
            <h1 class="m-0 text-dark">Panel de Delivery Express</h1>
            <small class="text-muted">Gestiona tus solicitudes y revisa los datos de tu cuenta.</small>
        </div>
        <form method="POST" action="{{ route('clientes.logout') }}" class="mt-3 mt-md-0">
            @csrf
            <button type="submit" class="btn btn-outline-primary">
                Cerrar sesión
            </button>
        </form>
    </div>
@endsection

@section('content')
    @php
        $canCreateSolicitud = auth('cliente')->user()?->can('feature.clientes.dashboard.create') ?? false;
        $canHistorySolicitud = auth('cliente')->user()?->can('feature.clientes.dashboard.history') ?? false;
        $deliveryExpressEnabled = \App\Support\ServiceAvailability::isEnabled('solicitud');
    @endphp

    <div class="client-dashboard">
    <div class="row">
        <div class="col-12">
            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-warning" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <section class="card delivery-welcome" aria-labelledby="delivery-welcome-title">
                <div class="card-body">
                    <div class="delivery-welcome__copy">
                        <div class="delivery-welcome__eyebrow"><i class="fas fa-shipping-fast" aria-hidden="true"></i> Delivery Express</div>
                        <h2 id="delivery-welcome-title">Tus envíos, en un solo lugar</h2>
                        <p>
                            @if ($deliveryExpressEnabled)
                                Crea una solicitud y consulta el avance de tus envíos desde tu historial.
                            @else
                                La creación de solicitudes está pausada temporalmente. Tu historial sigue disponible.
                            @endif
                        </p>
                        <span class="delivery-welcome__status {{ $deliveryExpressEnabled ? '' : 'delivery-welcome__status--paused' }}">
                            {{ $deliveryExpressEnabled ? 'Servicio habilitado' : 'Creación de solicitudes pausada' }}
                        </span>
                    </div>
                    <div class="delivery-welcome__actions">
                        @if ($canCreateSolicitud && $deliveryExpressEnabled)
                            <a href="{{ route('clientes.solicitudes.create') }}" class="btn btn-warning">
                                <i class="fas fa-plus mr-1" aria-hidden="true"></i> Crear envío
                            </a>
                        @endif
                        @if ($canHistorySolicitud)
                            <a href="{{ route('clientes.solicitudes.history') }}" class="btn btn-outline-light">
                                <i class="fas fa-list-alt mr-1" aria-hidden="true"></i> Ver mis envíos
                            </a>
                        @endif
                    </div>
                </div>
            </section>
        </div>
        <div class="col-12">
            <section class="card client-profile-card" aria-labelledby="client-profile-title">
                <div class="card-body">
                    <div class="client-profile__heading">
                        <img
                            src="{{ $cliente->avatar ?: 'https://picsum.photos/120/120' }}"
                            alt="Avatar del cliente"
                            class="client-profile__avatar"
                        >
                        <div>
                            <h2 class="client-profile__name" id="client-profile-title">{{ $cliente->name }}</h2>
                            <div class="client-profile__email"><i class="fas fa-envelope mr-1" aria-hidden="true"></i>{{ $cliente->email ?: 'Correo no registrado' }}</div>
                        </div>
                    </div>

                    <h3 class="client-profile__section-title">Datos de tu cuenta</h3>
                    <div class="client-info-grid">
                        <article class="client-info-card">
                            <span class="client-info-card__label"><i class="far fa-id-card mr-1" aria-hidden="true"></i> Documento</span>
                            <strong class="client-info-card__value">{{ $cliente->tipoDocumentoIdentidadLabel() }}</strong>
                            <span class="client-info-card__hint">N.º {{ $cliente->numero_carnet ?: 'No registrado' }}{{ $cliente->complemento ? ' · ' . $cliente->complemento : '' }}</span>
                        </article>
                        <article class="client-info-card">
                            <span class="client-info-card__label"><i class="fas fa-building mr-1" aria-hidden="true"></i> Razón social</span>
                            <strong class="client-info-card__value">{{ $cliente->razon_social ?: 'No registrada' }}</strong>
                        </article>
                        <article class="client-info-card">
                            <span class="client-info-card__label"><i class="fas fa-phone mr-1" aria-hidden="true"></i> Teléfono</span>
                            <strong class="client-info-card__value">{{ $cliente->telefono ?: 'No registrado' }}</strong>
                        </article>
                        <article class="client-info-card">
                            <span class="client-info-card__label"><i class="fas fa-map-marker-alt mr-1" aria-hidden="true"></i> Dirección</span>
                            <strong class="client-info-card__value">{{ $cliente->direccion ?: 'No registrada' }}</strong>
                        </article>
                    </div>
                </div>
            </section>
        </div>
    </div>
    </div>
@endsection
