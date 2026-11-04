<x-guest-layout :showLogo="false" :fullScreen="true" title="SIOP">
    <main class="siop-login-shell">
        <section class="siop-login-panel" aria-labelledby="login-title">
            <div class="siop-login-content">
                <a class="siop-brand" href="{{ url('/') }}" aria-label="Inicio SIOP">
                    <img src="{{ asset('images/correos-de-bolivia-logo.png') }}" alt="Correos de Bolivia">
                </a>
                <div class="siop-access-label"><span></span> SIOP · Acceso institucional</div>

                <header class="siop-login-heading">
                    <h1 id="login-title">Iniciar sesión</h1>
                    <p>Ingrese sus credenciales para acceder al sistema.</p>
                </header>

                @unless (session('session_expired'))
                    <x-auth-session-status class="siop-session-status" :status="session('status')" />
                @endunless

                <form id="loginForm" class="siop-login-form" method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="siop-field">
                        <label for="alias">Nombre de usuario</label>
                        <div class="siop-input-wrap">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" /></svg>
                            <input id="alias" name="alias" type="text" value="{{ old('alias') }}" placeholder="Ej.: jperez" required autofocus autocomplete="username" @error('alias') aria-invalid="true" @enderror>
                        </div>
                        <x-input-error :messages="$errors->get('alias')" class="siop-field-error" />
                    </div>

                    <div class="siop-field">
                        <label for="password">Contraseña</label>
                        <div class="siop-input-wrap">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2" /><path d="M8 10V7a4 4 0 1 1 8 0v3M12 14v3" /></svg>
                            <input id="password" name="password" type="password" placeholder="Ingrese su contraseña" required autocomplete="current-password" @error('password') aria-invalid="true" @enderror>
                            <button type="button" id="toggle-password" class="siop-password-toggle" aria-label="Mostrar contraseña" aria-pressed="false">
                                <svg id="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
                                <svg id="eye-closed" class="hidden" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8" /><path d="M9.9 5.1A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a11.4 11.4 0 0 1-4.1 5.2M6.1 6.1A11.5 11.5 0 0 0 2 12s3.5 7 10 7a10.8 10.8 0 0 0 4.9-1.2" /></svg>
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="siop-field-error" />
                    </div>

                    <label for="remember_me" class="siop-remember">
                        <input id="remember_me" type="checkbox" name="remember">
                        <span>Recordarme</span>
                    </label>

                    <button type="submit" class="siop-submit-button">
                        <span>Iniciar sesión</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7" /></svg>
                    </button>

                    <p class="siop-authorized-note">Uso exclusivo de personal autorizado.</p>
                </form>

                <section class="siop-forms" aria-labelledby="siop-forms-title">
                    <div class="siop-forms-heading">
                        <span class="siop-forms-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6M8 13h8M8 17h8" /></svg>
                        </span>
                        <div>
                            <h2 id="siop-forms-title">Formularios de solicitud</h2>
                            <p>Descargue el formulario que necesita.</p>
                        </div>
                    </div>

                    <a class="siop-download-link" href="{{ route('login.formularios.creacion') }}">
                        <span class="siop-download-copy">
                            <strong>Solicitud de creación de usuario</strong>
                            <small>Documento Word editable (.docx)</small>
                        </span>
                        <svg class="siop-download-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M5 17v3h14v-3" /></svg>
                    </a>

                    <a class="siop-download-link" href="{{ route('login.formularios.cambios') }}">
                        <span class="siop-download-copy">
                            <strong>Solicitud de baja o edición de usuario</strong>
                            <small>Documento Word editable (.docx)</small>
                        </span>
                        <svg class="siop-download-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M5 17v3h14v-3" /></svg>
                    </a>

                    <a class="siop-download-link" href="{{ route('login.formularios.cambios-sistema') }}">
                        <span class="siop-download-copy">
                            <strong>Solicitud de cambios del sistema</strong>
                            <small>Documento Word editable (.docx)</small>
                        </span>
                        <svg class="siop-download-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M5 17v3h14v-3" /></svg>
                    </a>
                </section>

                <p class="siop-login-footer">Sistema Integral Operativo Postal · SIOP</p>
            </div>
        </section>

        <section class="siop-hero-panel" aria-label="Sistema Integral Operativo Postal">
            <div class="siop-hero-art" aria-hidden="true"></div>
            <div class="siop-hero-content">
                <p class="siop-hero-kicker">CORREOS DE BOLIVIA <span></span> SIOP</p>
                <h2>SIOP</h2>
                <h3>Sistema Integral Operativo Postal</h3>
                <div class="siop-hero-rule"></div>
                <p class="siop-hero-description">Administre las operaciones postales desde una plataforma integrada.</p>
                <div class="siop-feature-grid">
                    <div class="siop-feature">
                        <span><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m4 7 8 6 8-6" /></svg></span>
                        <small>Admisión y<br>recepción</small>
                    </div>
                    <div class="siop-feature">
                        <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5Z" /><path d="m3 8 9 5 9-5M3 8v9l9 5 9-5V8M12 13v9" /></svg></span>
                        <small>Despachos</small>
                    </div>
                    <div class="siop-feature">
                        <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.5" /><path d="M3 5 1 7l2 2" /></svg></span>
                        <small>Seguimiento</small>
                    </div>
                    <div class="siop-feature">
                        <span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3" /><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a8 8 0 0 1-1.8 1l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.8-1l-1.7.7-1.4-2.4 1.4-1.1a7 7 0 0 1 0-2l-1.4-1.1 1.4-2.4 1.7.7a8 8 0 0 1 1.8-1l.3-1.8h2.8l.3 1.8a8 8 0 0 1 1.8 1l1.7-.7 1.4 2.4-1.4 1.1a7 7 0 0 1 0 2Z" /></svg></span>
                        <small>Gestión<br>operativa</small>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <div id="loginLoaderOverlay" class="siop-loader-overlay hidden" aria-hidden="true">
        <div class="siop-loader-content">
            <span class="siop-loader-status" aria-hidden="true"></span>
            <p>Ingresando</p>
            <small>El cartero va en camino</small>
        </div>
        <div class="siop-rider-road" aria-hidden="true"></div>
        <div class="siop-rider-track" aria-hidden="true">
            <img src="{{ asset('images/cartero/cartero-conduciendo.png') }}" alt="">
        </div>
    </div>

    <style>
        .siop-login-shell{display:flex;min-height:100vh;background:#fff}
        .siop-login-panel{position:relative;z-index:1;display:flex;flex:0 0 37%;justify-content:center;align-items:center;min-width:430px;max-width:590px;padding:34px clamp(28px,4vw,64px);background:#fff;box-shadow:12px 0 40px rgba(9,44,94,.08)}
        .siop-login-content{width:100%;max-width:430px}
        .siop-brand{display:flex;justify-content:center;margin:0 auto 14px;width:min(230px,70%)}
        .siop-brand img{display:block;width:100%;height:auto;object-fit:contain}
        .siop-access-label{display:flex;align-items:center;justify-content:center;gap:8px;margin:0 auto 19px;color:#547093;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
        .siop-access-label span{width:8px;height:8px;border-radius:50%;background:#f4b900;box-shadow:0 0 0 4px #fff5d5}
        .siop-login-heading{margin-bottom:22px;text-align:center}
        .siop-login-heading h1{margin:0;color:#0a3c82;font-size:clamp(30px,3vw,39px);font-weight:800;letter-spacing:-.04em;line-height:1.12}
        .siop-login-heading p{margin:8px 0 0;color:#64748b;font-size:15px;line-height:1.5}
        .siop-session-status{margin:0 0 16px;border:1px solid rgba(254,204,54,.5);border-radius:10px;background:#fff8df;padding:10px 12px;color:#42536c;font-size:13px}
        .siop-login-form{display:grid;gap:14px}
        .siop-field{display:grid;gap:6px}
        .siop-field>label{color:#173f7a;font-size:14px;font-weight:700}
        .siop-input-wrap{position:relative;display:flex;align-items:center;min-height:52px;border:1px solid #cdd8e7;border-radius:11px;background:#fff;transition:border-color .18s,box-shadow .18s}
        .siop-input-wrap:focus-within{border-color:#79a4ec;box-shadow:0 0 0 3px rgba(42,104,193,.12)}
        .siop-input-wrap>svg{width:19px;height:19px;margin-left:15px;flex:0 0 auto;fill:none;stroke:#73839c;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        .siop-input-wrap input{width:100%;height:50px;min-width:0;border:0!important;outline:0!important;box-shadow:none!important;background:transparent!important;padding:0 14px!important;color:#163d76;font-size:15px}
        .siop-input-wrap input::placeholder{color:#9aa8bd;opacity:1}
        .siop-password-toggle{display:flex;width:42px;height:42px;flex:0 0 auto;align-items:center;justify-content:center;border:0;background:transparent;color:#74839a;cursor:pointer}
        .siop-password-toggle svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        .siop-field-error{color:#bd2b32;font-size:12px}
        .siop-field-error ul{margin:0;padding-left:16px}
        .siop-remember{display:flex;align-items:center;gap:9px;width:max-content;margin:0;color:#6d7f99;font-size:14px;cursor:pointer}
        .siop-remember input{width:18px;height:18px;margin:0;border:1px solid #a9bad0;border-radius:4px;color:#f2b900;accent-color:#f2b900}
        .siop-submit-button{display:flex;min-height:52px;align-items:center;justify-content:space-between;border:0;border-radius:11px;background:linear-gradient(105deg,#ffc400,#ffb600);padding:0 20px 0 24px;color:#073f8f;font-size:17px;font-weight:800;box-shadow:0 9px 18px rgba(236,177,0,.25);transition:transform .18s,box-shadow .18s;cursor:pointer}
        .siop-submit-button:hover{transform:translateY(-1px);box-shadow:0 12px 22px rgba(236,177,0,.3)}
        .siop-submit-button:disabled{opacity:.65;cursor:wait;transform:none}
        .siop-submit-button svg{width:23px;height:23px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:2}
        .siop-authorized-note{margin:-3px 0 0;text-align:center;color:#8b98aa;font-size:11px}
        .siop-forms{display:grid;gap:8px;margin-top:22px;border-top:1px solid #e8edf4;padding-top:16px}
        .siop-forms-heading{display:flex;align-items:center;gap:10px;margin-bottom:2px}
        .siop-forms-icon{display:flex;width:36px;height:36px;flex:0 0 auto;align-items:center;justify-content:center;border-radius:10px;background:#fff3ce;color:#0c4a92}
        .siop-forms-icon svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.7}
        .siop-forms-heading h2{margin:0;color:#143e79;font-size:14px;font-weight:800}
        .siop-forms-heading p{margin:2px 0 0;color:#8794a6;font-size:11px}
        .siop-download-link{display:flex;min-height:49px;align-items:center;justify-content:space-between;gap:12px;border:1px solid #e1e8f1;border-radius:10px;background:#fff;padding:8px 12px;text-decoration:none;transition:border-color .18s,background .18s,transform .18s}
        .siop-download-link:hover{transform:translateY(-1px);border-color:#f0c441;background:#fffdf6}
        .siop-download-copy{display:grid;gap:2px}
        .siop-download-copy strong{color:#214b84;font-size:12px;font-weight:750;line-height:1.25}
        .siop-download-copy small{color:#8694a8;font-size:10px}
        .siop-download-arrow{width:20px;height:20px;flex:0 0 auto;fill:none;stroke:#b78600;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        .siop-login-footer{margin:16px 0 0;text-align:center;color:#a0a9b6;font-size:10px}
        .siop-hero-panel{position:relative;display:flex;flex:1;align-items:center;overflow:hidden;min-width:0;background:linear-gradient(130deg,#fff2bd,#ffd36b)}
        .siop-hero-art{position:absolute;inset:0;background-image:linear-gradient(90deg,rgba(255,245,211,.7) 0%,rgba(255,243,202,.2) 42%,rgba(255,222,131,.03) 100%),url('{{ asset('images/siop-login-hero.png') }}');background-size:cover;background-position:66% center}
        .siop-hero-content{position:relative;width:min(670px,72%);margin-left:clamp(24px,5vw,86px);border:1px solid rgba(255,255,255,.8);border-radius:30px;background:rgba(255,255,255,.9);padding:clamp(32px,4.1vw,65px);box-shadow:0 24px 70px rgba(59,72,92,.12);backdrop-filter:blur(10px)}
        .siop-hero-kicker{display:flex;align-items:center;gap:10px;margin:0 0 11px;color:#758197;font-size:11px;font-weight:800;letter-spacing:.16em}
        .siop-hero-kicker span{width:24px;height:2px;background:#ffc000}
        .siop-hero-content h2{margin:0;color:#0a408e;font-size:clamp(60px,7.3vw,100px);font-weight:900;letter-spacing:-.065em;line-height:.92}
        .siop-hero-content h3{margin:8px 0 0;color:#0d3f84;font-size:clamp(20px,2.25vw,30px);font-weight:800;letter-spacing:-.035em;line-height:1.15}
        .siop-hero-rule{width:64px;height:5px;margin:20px 0 19px;border-radius:5px;background:#ffc000}
        .siop-hero-description{max-width:460px;margin:0;color:#52627d;font-size:clamp(16px,1.6vw,21px);line-height:1.55}
        .siop-feature-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:26px}
        .siop-feature{display:grid;justify-items:center;gap:9px;text-align:center}
        .siop-feature>span{display:flex;width:66px;height:66px;align-items:center;justify-content:center;border-radius:20px;background:#fff4d2;color:#0b4c9b}
        .siop-feature svg{width:31px;height:31px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.7}
        .siop-feature small{color:#19477f;font-size:13px;line-height:1.35}
        .siop-loader-overlay{position:fixed;inset:0;z-index:9999;overflow:hidden;background:rgba(255,255,255,.72);backdrop-filter:blur(3px)}
        .siop-loader-overlay:not(.hidden){display:block}
        .siop-loader-content{position:absolute;z-index:2;top:37%;left:50%;display:flex;min-width:220px;flex-direction:column;align-items:center;transform:translate(-50%,-50%);color:#174985}
        .siop-loader-status{width:22px;height:22px;border:3px solid #dce7f4;border-top-color:#1452a0;border-radius:50%;animation:siop-rotation .8s linear infinite}
        .siop-loader-content p{margin:13px 0 5px;font-size:20px;font-weight:800;letter-spacing:.16em;text-transform:uppercase}
        .siop-loader-content small{color:#526783;font-size:17px;font-weight:600}
        .siop-rider-road{position:absolute;right:0;bottom:15%;left:0;height:3px;background:repeating-linear-gradient(90deg,rgba(12,65,135,.2) 0 32px,transparent 32px 56px)}
        .siop-rider-track{position:absolute;right:0;bottom:calc(15% + 3px);left:0;height:clamp(150px,27vh,290px);overflow:hidden}
        .siop-rider-track img{position:absolute;bottom:0;left:0;width:clamp(210px,27vw,370px);max-height:100%;height:auto;object-fit:contain;filter:drop-shadow(0 9px 12px rgba(17,48,86,.16));animation:siop-rider-ride 3.8s linear infinite;will-change:transform}
        @keyframes siop-rotation{to{transform:rotate(360deg)}}
        @keyframes siop-rider-ride{from{transform:translateX(-100%)}to{transform:translateX(100vw)}}
        @media(max-width:600px){.siop-loader-content{top:30%}.siop-loader-content p{font-size:18px}.siop-loader-content small{font-size:15px}.siop-rider-track{height:180px;bottom:13%}.siop-rider-track img{width:240px}.siop-rider-road{bottom:13%}}
        @media(prefers-reduced-motion:reduce){.siop-loader-status{animation-duration:2s}.siop-rider-track img{animation-duration:8s}}
        @media(max-width:1100px){.siop-login-panel{flex-basis:43%;min-width:390px;padding-inline:28px}.siop-hero-content{width:76%;margin-left:22px;padding:30px}.siop-feature-grid{gap:6px}.siop-feature>span{width:56px;height:56px;border-radius:17px}}
        @media(max-width:850px){.siop-login-shell{flex-direction:column}.siop-login-panel{flex:auto;width:100%;min-width:0;max-width:none;padding:30px 22px 27px}.siop-login-content{max-width:460px}.siop-brand{width:min(205px,60%);margin-bottom:10px}.siop-access-label{margin-bottom:13px}.siop-login-heading{margin-bottom:17px}.siop-login-form{gap:12px}.siop-forms{margin-top:17px}.siop-hero-panel{min-height:440px;flex:auto;padding:34px 16px;background:#ffe49a}.siop-hero-art{background-position:72% center}.siop-hero-content{width:min(600px,100%);margin:0 auto;padding:32px}.siop-hero-content h2{font-size:66px}.siop-hero-content h3{font-size:23px}.siop-hero-description{font-size:16px}}
        @media(max-width:480px){.siop-login-panel{padding:26px 18px 24px}.siop-login-heading h1{font-size:32px}.siop-input-wrap{min-height:50px}.siop-input-wrap input{height:48px}.siop-submit-button{min-height:50px}.siop-hero-panel{min-height:400px;padding:20px 12px}.siop-hero-content{padding:25px 20px;border-radius:24px}.siop-hero-content h2{font-size:56px}.siop-hero-content h3{font-size:20px}.siop-hero-rule{margin:15px 0}.siop-feature-grid{gap:5px;margin-top:20px}.siop-feature>span{width:48px;height:48px;border-radius:15px}.siop-feature svg{width:25px;height:25px}.siop-feature small{font-size:10px}.siop-hero-kicker{font-size:9px}}
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const loginForm = document.getElementById('loginForm');
            const passwordInput = document.getElementById('password');
            const toggleButton = document.getElementById('toggle-password');
            const eyeOpen = document.getElementById('eye-open');
            const eyeClosed = document.getElementById('eye-closed');
            const loaderOverlay = document.getElementById('loginLoaderOverlay');
            const submitButton = loginForm ? loginForm.querySelector('button[type="submit"]') : null;

            if (loginForm) {
                loginForm.addEventListener('submit', function () {
                    sessionStorage.setItem('dashboardSoundAfterLogin', '1');
                    if (loaderOverlay) {
                        loaderOverlay.classList.remove('hidden');
                        loaderOverlay.setAttribute('aria-hidden', 'false');
                    }
                    if (submitButton) submitButton.setAttribute('disabled', 'disabled');
                });
            }

            if (!passwordInput || !toggleButton || !eyeOpen || !eyeClosed) return;
            toggleButton.addEventListener('click', function () {
                const isHidden = passwordInput.type === 'password';
                passwordInput.type = isHidden ? 'text' : 'password';
                toggleButton.setAttribute('aria-pressed', String(isHidden));
                toggleButton.setAttribute('aria-label', isHidden ? 'Ocultar contraseña' : 'Mostrar contraseña');
                eyeOpen.classList.toggle('hidden', isHidden);
                eyeClosed.classList.toggle('hidden', !isHidden);
            });
        });
    </script>

    <x-session-expired-modal :show="session('session_expired')" :redirect-url="route('login')" />
</x-guest-layout>
