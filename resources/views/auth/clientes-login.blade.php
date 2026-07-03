<x-guest-layout cardMaxWidth="1120px" cardClasses="!p-0 overflow-hidden" :showLogo="false">
    <style>
        .cliente-login-shell {
            display: flex;
            min-height: 660px;
            background: #fff;
            border-radius: 22px;
        }

        .cliente-login-side {
            width: 290px;
            min-width: 290px;
            background: #ffffff;
            padding: 46px 34px 34px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            border-right: 1px solid rgba(32, 83, 154, 0.06);
        }

        .cliente-login-brand {
            margin: 0 auto 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .cliente-login-brand-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 0;
            background: transparent;
            border: 0;
            box-shadow: none;
        }

        .cliente-login-brand img {
            width: 220px;
            height: auto;
            object-fit: contain;
        }

        .cliente-login-title {
            margin: 18px 0 0;
            text-align: center;
            font-size: 2rem;
            font-weight: 600;
            color: #173f75;
            letter-spacing: -0.03em;
        }

        .cliente-login-subtitle {
            margin: 14px 0 0;
            text-align: center;
            font-size: 0.92rem;
            line-height: 1.75;
            color: #6b7280;
        }

        .cliente-login-status {
            margin-top: 22px;
        }

        .cliente-login-field {
            margin-top: 0;
        }

        .cliente-login-actions {
            margin-top: 34px;
            display: grid;
            gap: 12px;
        }

        .cliente-login-actions form {
            margin: 0;
            width: 100%;
        }

        .cliente-login-primary,
        .cliente-login-secondary,
        .cliente-login-hero-cta {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .cliente-google-icon {
            width: 20px;
            height: 20px;
            flex: 0 0 20px;
        }

        .cliente-login-primary,
        .cliente-login-secondary {
            width: 100%;
            min-height: 58px;
            border-radius: 16px;
            border: 1px solid #dadce0;
            background: #fff;
            color: #3c4043;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.04);
            padding: 0 20px;
            cursor: pointer;
        }

        .cliente-login-primary:hover,
        .cliente-login-secondary:hover {
            background: #fbfdff;
            border-color: #c7d2e3;
            box-shadow: 0 14px 28px rgba(32, 83, 154, 0.08);
            color: #1f2937;
            text-decoration: none;
        }

        .cliente-google-text {
            font-size: 0.98rem;
            line-height: 1.35;
            text-align: left;
            white-space: nowrap;
        }

        .cliente-login-help {
            margin-top: 26px;
            font-size: 12px;
            line-height: 1.7;
            text-align: center;
            color: #6b7280;
            max-width: 280px;
            margin-left: auto;
            margin-right: auto;
        }

        .cliente-login-footer {
            margin-top: auto;
            padding-top: 30px;
            font-size: 10px;
            line-height: 1.7;
            text-align: center;
            color: #94a3b8;
        }

        .cliente-login-visual {
            position: relative;
            flex: 1 1 auto;
            overflow: hidden;
            background-color: #d8ecff;
            background-image: url('{{ asset('imagecliente.png') }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        @media (max-width: 991.98px) {
            .cliente-login-shell {
                flex-direction: column;
                min-height: auto;
            }

            .cliente-login-side {
                width: 100%;
                min-width: 0;
            }

            .cliente-login-visual {
                min-height: 420px;
            }
        }

        @media (max-width: 420px) {
            .cliente-login-side {
                padding-left: 24px;
                padding-right: 24px;
            }

            .cliente-login-primary,
            .cliente-login-secondary {
                min-height: 56px;
                padding-left: 16px;
                padding-right: 16px;
            }

            .cliente-google-text {
                white-space: normal;
            }
        }
    </style>

    <section class="cliente-login-shell">
        <div class="cliente-login-side">
            <div class="cliente-login-brand">
                <div class="cliente-login-brand-badge">
                    <img src="{{ asset('images/AGBClogo1.png') }}" alt="Correos de Bolivia">
                </div>
            </div>

            <h1 class="cliente-login-title">Inicia sesión</h1>
            <p class="cliente-login-subtitle">Accede al portal de clientes con tu cuenta autenticada por Google.</p>

            <div class="cliente-login-status">
                @unless (session('session_expired'))
                    <x-auth-session-status class="rounded-xl border border-[#FECC36]/40 bg-[#fff7dd] px-4 py-3 text-sm text-left" :status="session('status')" />
                @endunless

                @if ($errors->has('google'))
                    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $errors->first('google') }}
                    </div>
                @endif
            </div>

            <div class="cliente-login-actions">
                <form method="POST" action="{{ route('clientes.login.store') }}">
                    @csrf
                    <button type="submit" class="cliente-login-primary">
                        <svg class="cliente-google-icon" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#EA4335" d="M12 10.2v3.9h5.4c-.2 1.3-1.5 3.9-5.4 3.9-3.2 0-5.9-2.7-5.9-6s2.7-6 5.9-6c1.8 0 3 .8 3.7 1.5l2.5-2.4C16.7 3.7 14.6 2.8 12 2.8 6.9 2.8 2.8 6.9 2.8 12S6.9 21.2 12 21.2c6.3 0 8.8-4.4 8.8-6.7 0-.5 0-.8-.1-1.2H12Z"/>
                            <path fill="#4285F4" d="M3.8 7 7 9.3C7.9 7.5 9.8 6.2 12 6.2c1.8 0 3 .8 3.7 1.5l2.5-2.4C16.7 3.7 14.6 2.8 12 2.8 8.4 2.8 5.3 4.8 3.8 7Z"/>
                            <path fill="#FBBC05" d="M12 21.2c2.5 0 4.6-.8 6.1-2.2l-2.8-2.3c-.8.6-1.8 1-3.3 1-3.8 0-5.2-2.5-5.4-3.8L3.5 16c1.5 3 4.6 5.2 8.5 5.2Z"/>
                            <path fill="#34A853" d="M6.6 13.9c-.1-.4-.2-.9-.2-1.4s.1-1 .2-1.4L3.5 8.9C3 9.9 2.8 10.9 2.8 12s.3 2.1.7 3.1l3.1-1.2Z"/>
                        </svg>
                        <span class="cliente-google-text">Iniciar con Google</span>
                    </button>
                </form>
                <a href="{{ route('clientes.register') }}" class="cliente-login-secondary">
                    <svg class="cliente-google-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#EA4335" d="M12 10.2v3.9h5.4c-.2 1.3-1.5 3.9-5.4 3.9-3.2 0-5.9-2.7-5.9-6s2.7-6 5.9-6c1.8 0 3 .8 3.7 1.5l2.5-2.4C16.7 3.7 14.6 2.8 12 2.8 6.9 2.8 2.8 6.9 2.8 12S6.9 21.2 12 21.2c6.3 0 8.8-4.4 8.8-6.7 0-.5 0-.8-.1-1.2H12Z"/>
                        <path fill="#4285F4" d="M3.8 7 7 9.3C7.9 7.5 9.8 6.2 12 6.2c1.8 0 3 .8 3.7 1.5l2.5-2.4C16.7 3.7 14.6 2.8 12 2.8 8.4 2.8 5.3 4.8 3.8 7Z"/>
                        <path fill="#FBBC05" d="M12 21.2c2.5 0 4.6-.8 6.1-2.2l-2.8-2.3c-.8.6-1.8 1-3.3 1-3.8 0-5.2-2.5-5.4-3.8L3.5 16c1.5 3 4.6 5.2 8.5 5.2Z"/>
                        <path fill="#34A853" d="M6.6 13.9c-.1-.4-.2-.9-.2-1.4s.1-1 .2-1.4L3.5 8.9C3 9.9 2.8 10.9 2.8 12s.3 2.1.7 3.1l3.1-1.2Z"/>
                    </svg>
                    <span class="cliente-google-text">Registrarme con Google</span>
                </a>
            </div>

            <div class="cliente-login-help">
                Google valida el correo autenticado, por eso este acceso usa una cuenta real.
            </div>

            <div class="cliente-login-footer">
                Correos de Bolivia · TrackingBO
            </div>
        </div>

        <div class="cliente-login-visual">
            <span class="sr-only">Imagen de bienvenida de clientes</span>
        </div>
    </section>

    <x-session-expired-modal :show="session('session_expired')" :redirect-url="route('clientes.login')" />
</x-guest-layout>
