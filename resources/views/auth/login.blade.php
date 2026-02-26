<x-guest-layout cardMaxWidth="620px" cardClasses="p-0">
    <div>
        <section class="px-6 py-7 sm:px-8 sm:py-8">
            <div class="rounded-xl border border-[#e8edf7] bg-[#f9fbff] p-4 mb-5">
          
                <h2 class="mt-2 text-2xl font-bold text-[#123c76]">Iniciar sesion</h2>
                <p class="mt-1 text-sm text-[#123c76]/75">Ingresa con tu cuenta.</p>
            </div>

            <x-auth-session-status class="mb-4 rounded-lg border border-[#eab312]/40 bg-[#fff7dd] px-3 py-2" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <x-input-label for="email" :value="__('Correo electronico')" />
                    <x-text-input id="email" class="block mt-1 w-full h-11 rounded-lg bg-white" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" :value="__('Contrasena')" />
                    <div class="relative mt-1">
                        <x-text-input id="password" class="block w-full h-11 rounded-lg bg-white pr-12"
                                        type="password"
                                        name="password"
                                        required autocomplete="current-password" />
                        <button
                            type="button"
                            id="toggle-password"
                            class="absolute z-10 inline-flex h-8 w-8 items-center justify-center text-[#123c76]/70 hover:text-[#123c76] focus:outline-none"
                            style="right: 0.75rem; top: 50%; transform: translateY(-50%);"
                            aria-label="Mostrar contrasena"
                            aria-pressed="false"
                        >
                            <svg id="eye-open" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.27 2.943 9.543 7-1.273 4.057-5.065 7-9.543 7-4.477 0-8.268-2.943-9.542-7z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg id="eye-closed" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58a2 2 0 102.83 2.83" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A10.94 10.94 0 0112 5c4.48 0 8.27 2.94 9.54 7a10.52 10.52 0 01-4.05 5.26M6.1 6.1A10.52 10.52 0 002.46 12c1.27 4.06 5.06 7 9.54 7 1.76 0 3.42-.45 4.88-1.24" />
                            </svg>
                        </button>
                    </div>

                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between gap-4 pt-1">
                    <label for="remember_me" class="inline-flex items-center">
                        <input id="remember_me" type="checkbox" class="rounded border-[#dcb15f] text-[#c79706] shadow-sm focus:ring-[#fecb34]/35" name="remember">
                        <span class="ms-2 text-sm text-[#123c76]/80">{{ __('Recordarme') }}</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="text-sm font-medium text-[#9f7a08] hover:text-[#7c5d02] rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#fecb34]" href="{{ route('password.request') }}">
                            {{ __('Olvidaste tu contrasena?') }}
                        </a>
                    @endif
                </div>

                <x-primary-button class="w-full h-11 justify-center rounded-lg text-sm shadow-[0_8px_16px_rgba(234,179,18,0.35)]">
                    {{ __('Ingresar al sistema') }}
                </x-primary-button>

                <p class="pt-1 text-[11px] text-center text-[#123c76]/65">
                    Uso exclusivo de personal autorizado.
                </p>
            </form>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.getElementById('toggle-password');
            const eyeOpen = document.getElementById('eye-open');
            const eyeClosed = document.getElementById('eye-closed');

            if (!passwordInput || !toggleButton || !eyeOpen || !eyeClosed) return;

            toggleButton.addEventListener('click', function () {
                const isHidden = passwordInput.type === 'password';
                passwordInput.type = isHidden ? 'text' : 'password';
                toggleButton.setAttribute('aria-pressed', String(isHidden));
                toggleButton.setAttribute('aria-label', isHidden ? 'Ocultar contrasena' : 'Mostrar contrasena');
                eyeOpen.classList.toggle('hidden', isHidden);
                eyeClosed.classList.toggle('hidden', !isHidden);
            });
        });
    </script>
</x-guest-layout>
