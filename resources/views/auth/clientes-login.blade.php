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
            background: linear-gradient(180deg, #d8ecff 0%, #c6e4ff 48%, #b3dbff 100%);
        }

        .cliente-login-visual svg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
        }

        .cliente-login-hero {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: min(100%, 400px);
            background: rgba(255, 255, 255, 0.94);
            padding: 18px;
            border-radius: 10px;
            box-shadow: 0 24px 48px rgba(32, 83, 154, 0.14);
            backdrop-filter: blur(3px);
        }

        .cliente-login-hero-image {
            display: block;
            width: 100%;
            height: auto;
            border-radius: 4px;
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
                <x-auth-session-status class="rounded-xl border border-[#FECC36]/40 bg-[#fff7dd] px-4 py-3 text-sm text-left" :status="session('status')" />

                @if ($errors->has('google'))
                    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $errors->first('google') }}
                    </div>
                @endif
            </div>

            <div class="cliente-login-actions">
                <a href="{{ route('auth.google.redirect') }}" class="cliente-login-primary">
                    <svg class="cliente-google-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#EA4335" d="M12 10.2v3.9h5.4c-.2 1.3-1.5 3.9-5.4 3.9-3.2 0-5.9-2.7-5.9-6s2.7-6 5.9-6c1.8 0 3 .8 3.7 1.5l2.5-2.4C16.7 3.7 14.6 2.8 12 2.8 6.9 2.8 2.8 6.9 2.8 12S6.9 21.2 12 21.2c6.3 0 8.8-4.4 8.8-6.7 0-.5 0-.8-.1-1.2H12Z"/>
                        <path fill="#4285F4" d="M3.8 7 7 9.3C7.9 7.5 9.8 6.2 12 6.2c1.8 0 3 .8 3.7 1.5l2.5-2.4C16.7 3.7 14.6 2.8 12 2.8 8.4 2.8 5.3 4.8 3.8 7Z"/>
                        <path fill="#FBBC05" d="M12 21.2c2.5 0 4.6-.8 6.1-2.2l-2.8-2.3c-.8.6-1.8 1-3.3 1-3.8 0-5.2-2.5-5.4-3.8L3.5 16c1.5 3 4.6 5.2 8.5 5.2Z"/>
                        <path fill="#34A853" d="M6.6 13.9c-.1-.4-.2-.9-.2-1.4s.1-1 .2-1.4L3.5 8.9C3 9.9 2.8 10.9 2.8 12s.3 2.1.7 3.1l3.1-1.2Z"/>
                    </svg>
                    <span class="cliente-google-text">Iniciar con Google</span>
                </a>
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
            <svg viewBox="0 0 860 640" preserveAspectRatio="none" aria-hidden="true">
                <path d="M0 160 C90 145 170 185 255 165 C355 142 440 104 550 126 C670 150 760 120 860 136 L860 640 L0 640 Z" fill="#eaf6ff"/>
                <path d="M0 235 C85 220 165 250 250 228 C360 198 430 182 555 194 C666 206 756 180 860 196 L860 640 L0 640 Z" fill="#d7edff"/>
                <path d="M0 320 C90 300 160 340 260 320 C352 302 445 256 568 270 C684 284 766 258 860 280 L860 640 L0 640 Z" fill="#bde0fb"/>
                <path d="M0 640 L0 548 C110 520 190 566 284 548 C374 530 446 492 550 506 C655 520 740 500 860 522 L860 640 Z" fill="#9fcdf2"/>
                <path d="M526 640 C540 600 566 567 592 532 C622 494 657 462 716 426" stroke="#335f8e" stroke-width="8" stroke-linecap="round" fill="none"/>
                <path d="M596 640 C608 607 629 578 649 548 C669 519 689 495 722 468" stroke="#ffd24d" stroke-width="10" stroke-linecap="round" fill="none"/>
                <path d="M236 420 L266 342 L295 420" stroke="#2a74b0" stroke-width="5" fill="#99d0f4"/>
                <path d="M272 420 L308 314 L344 420" stroke="#2a74b0" stroke-width="5" fill="#86c0ea"/>
                <path d="M318 420 L360 288 L402 420" stroke="#2a74b0" stroke-width="5" fill="#74b2e2"/>
                <path d="M182 420 L210 360 L238 420" stroke="#2a74b0" stroke-width="5" fill="#a7d8f8"/>
                <path d="M24 214 C88 214 114 186 176 188" stroke="#54a6da" stroke-width="4" stroke-linecap="round" fill="none"/>
                <path d="M706 210 C758 210 788 190 840 194" stroke="#54a6da" stroke-width="4" stroke-linecap="round" fill="none"/>
            </svg>

            <div class="cliente-login-hero">
                <img src="{{ asset('bienvenida.png') }}" alt="Bienvenida clientes" class="cliente-login-hero-image">
            </div>
        </div>
    </section>
</x-guest-layout>
